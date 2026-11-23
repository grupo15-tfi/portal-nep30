<?php

namespace Tests\Feature\Panel;

use App\Actions\Tareas\EntregarTarea;
use App\Models\Inscripcion;
use App\Models\Tarea;
use App\Models\User;
use App\Queries\InicioDelPanel;
use App\Queries\TareasDelAlumno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * #156: el campus con la base de estilos (#154) y un Inicio con tarjetas según el rol.
 * Criterios: cada número del Inicio coincide con su pantalla y cada rol ve solo sus datos.
 */
class EstiloCampusTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function usuario(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    /** Valor de la tarjeta con ese título, tal como lo arma la consulta del Inicio */
    private function valor(User $usuario, string $titulo): int|string
    {
        return collect(InicioDelPanel::para($usuario)['tarjetas'])->firstWhere('titulo', $titulo)['valor'];
    }

    public function test_el_inicio_del_administrador_coincide_con_sus_pantallas(): void
    {
        $admin = $this->usuario('admin@nep30.test');

        $this->assertSame(DB::table('preinscripciones')->where('estado', 'pendiente')->count(), $this->valor($admin, 'Preinscripciones pendientes'));
        $this->assertSame(DB::table('inscripciones')->where('estado', 'activa')->distinct()->count('alumno_id'), $this->valor($admin, 'Alumnos activos'));

        $this->actingAs($admin)->get(route('admin.inicio'))->assertOk()
            ->assertDontSee('Elegí una opción del menú')
            ->assertSee('Hola, '.$admin->nombre)
            ->assertSeeInOrder(['Preinscripciones pendientes', 'Alumnos activos', 'Cursos con lista de espera'])
            ->assertSee('href="'.route('admin.preinscripciones').'"', false);
    }

    public function test_el_profesor_ve_solo_sus_entregas_sin_devolver(): void
    {
        Storage::fake('local');
        $profesora = $this->usuario('profesor@nep30.test');
        $otro = User::factory()->conRol('profesor')->create();
        $this->assertSame(0, $this->valor($profesora, 'Entregas sin devolver'));

        // La alumna entrega la tarea de la profesora: le aparece a ella, no al otro profesor
        $tarea = Tarea::firstOrFail();
        $tarea->update(['fecha_entrega' => now()->addWeek()]);
        $insc = Inscripcion::whereHas('alumno', fn ($q) => $q->where('email', 'alumno@nep30.test'))->where('curso_id', $tarea->cursoProfesor->curso_id)->firstOrFail();
        EntregarTarea::ejecutar($tarea, $insc, UploadedFile::fake()->create('tp.pdf', 50, 'application/pdf'));

        $this->assertSame(1, $this->valor($profesora, 'Entregas sin devolver'));
        $this->assertSame(0, $this->valor($otro, 'Entregas sin devolver'));
        $this->assertSame(0, $this->valor($otro, 'Mis materias'));

        $this->actingAs($profesora)->get(route('profesor.inicio'))->assertOk()
            ->assertSeeInOrder(['Mis materias', 'Entregas sin devolver', 'Espera tu devolución']);
    }

    public function test_el_alumno_ve_sus_tareas_pendientes_y_las_proximas(): void
    {
        $alumna = $this->usuario('alumno@nep30.test');
        $tarea = Tarea::firstOrFail();
        $tarea->update(['fecha_entrega' => now()->addDays(3)->setTime(18, 0)]);

        $pendientes = TareasDelAlumno::de($alumna)->filter(fn ($t) => $t['entrega'] === null && ! $t['vencida'])->count();
        $this->assertSame($pendientes, $this->valor($alumna, 'Tareas pendientes'));
        $this->assertGreaterThan(0, $pendientes);

        $this->actingAs($alumna)->get(route('alumno.inicio'))->assertOk()
            ->assertSee('Próximas entregas')
            ->assertSee($tarea->titulo)
            ->assertSee('Vence el '.$tarea->fecha_entrega->format('d/m').' a las 18:00');

        // Un alumno sin inscripciones no ve tareas de otros ni un promedio inventado
        $nuevo = User::factory()->conRol('alumno')->create();
        $this->assertSame(0, $this->valor($nuevo, 'Tareas pendientes'));
        $this->assertSame('—', $this->valor($nuevo, 'Promedio'));
        $this->actingAs($nuevo)->get(route('alumno.inicio'))->assertOk()->assertDontSee($tarea->titulo);
    }

    public function test_el_auditor_ve_el_resumen_y_los_movimientos_de_hoy(): void
    {
        $auditor = $this->usuario('auditor@nep30.test');

        $this->assertSame(DB::table('sedes')->where('activo', true)->count(), $this->valor($auditor, 'Sedes activas'));
        $this->actingAs($auditor)->get(route('auditor.inicio'))->assertOk()
            ->assertSee('href="'.route('auditor.sedes').'"', false)
            ->assertSee('href="'.route('auditor.auditoria').'"', false);
    }

    public function test_el_menu_del_administrador_tiene_grupos_e_iconos(): void
    {
        $html = $this->actingAs($this->usuario('admin@nep30.test'))->get(route('admin.inicio'))->assertOk()
            ->assertSeeInOrder(['Gestión académica', 'Sedes', 'Sitio web', 'Noticias', 'Control', 'Auditoría'])
            ->getContent();

        // Cada opción del menú lateral lleva su ícono (decorativo, no se lee)
        $this->assertMatchesRegularExpression('/aria-current="page"[^>]*>\s*<svg aria-hidden="true"/', $html);
    }

    public function test_arriba_el_rol_es_un_chip_y_mi_perfil_un_boton(): void
    {
        $this->actingAs($this->usuario('profesor@nep30.test'))->get(route('profesor.inicio'))->assertOk()
            ->assertSee('text-xs font-bold">Profesor</span>', false)
            ->assertSee('px-4 font-bold text-white no-underline hover:bg-white/10 hover:text-white">Mi perfil</a>', false);
    }

    public function test_notas_y_asistencia_y_volver_son_botones(): void
    {
        $profesora = $this->usuario('profesor@nep30.test');
        $this->actingAs($profesora)->get(route('profesor.materias'))->assertOk()
            ->assertSee('class="boton boton-secundario px-4 text-sm">Notas', false)
            ->assertSee('class="boton boton-secundario px-4 text-sm">Asistencia', false);

        $asignacion = DB::table('curso_profesores')->where('profesor_id', $profesora->id)->value('id');
        $this->actingAs($profesora)->get(route('profesor.notas', $asignacion))->assertOk()
            ->assertSee('class="boton boton-volver">Mis materias', false);
    }

    public function test_alumnos_con_filtros_en_una_fila_y_dar_de_baja_discreto(): void
    {
        $this->actingAs($this->usuario('admin@nep30.test'))->get(route('admin.alumnos'))->assertOk()
            ->assertSee('md:grid-cols-[minmax(0,1.2fr)_minmax(0,1.5fr)_minmax(0,0.8fr)]', false)
            ->assertSee('class="boton boton-peligro whitespace-nowrap px-3 text-sm"', false);
    }
}
