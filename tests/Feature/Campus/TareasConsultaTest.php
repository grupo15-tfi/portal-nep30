<?php

namespace Tests\Feature\Campus;

use App\Actions\Tareas\EntregarTarea;
use App\Models\CursoProfesor;
use App\Models\Entrega;
use App\Models\Inscripcion;
use App\Models\Materia;
use App\Models\Tarea;
use App\Models\User;
use App\Queries\EntregasDeLaTarea;
use App\Queries\TareasDelAlumno;
use App\Queries\TareasDelProfesor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * HU12 (#119): consultas de tareas y descargas de archivos.
 * Datos de DatosDePruebaSeeder:
 *   - Profesora con «Corte y peinado» y «Coloración» en Peluquería, y «Ofimática» en Informática.
 *   - Prueba Alumna Uno y Prueba Alumno Dos, inscriptos (activos) solo en Peluquería.
 *   - La tarea «Trabajo práctico 1: tipos de corte» (Corte y peinado), fecha límite 01/12/2026 23:59.
 */
class TareasConsultaTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function usuario(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function asignacion(string $materia): CursoProfesor
    {
        return CursoProfesor::where('materia_id', Materia::where('nombre', $materia)->value('id'))->firstOrFail();
    }

    private function inscripcion(string $email): Inscripcion
    {
        return Inscripcion::where('alumno_id', $this->usuario($email)->id)->firstOrFail();
    }

    private function tarea(string $titulo, string $materia = 'Módulo 1: Corte y peinado', string $limite = '+7 days', bool $conArchivo = false): Tarea
    {
        $tarea = Tarea::create([
            'curso_profesor_id' => $this->asignacion($materia)->id,
            'titulo'            => $titulo,
            'fecha_entrega'     => now()->modify($limite),
        ]);

        if ($conArchivo) {
            Storage::disk('local')->put("tareas/{$tarea->id}/consigna.pdf", 'consigna');
            $tarea->update(['archivo_ruta' => "tareas/{$tarea->id}/consigna.pdf"]);
        }

        return $tarea;
    }

    private function entregar(Tarea $tarea, string $email): Entrega
    {
        return EntregarTarea::ejecutar($tarea, $this->inscripcion($email), UploadedFile::fake()->create('tp.pdf', 20, 'application/pdf'));
    }

    // ── TareasDelAlumno ────────────────────────────────────

    public function test_el_alumno_ve_solo_las_tareas_de_su_curso_ordenadas_por_fecha(): void
    {
        $this->tarea('Antes', limite: '+1 day');
        $this->tarea('Vencida', limite: '-1 day');
        $this->tarea('De Informática', materia: 'Módulo 1: Ofimática');

        $filas = TareasDelAlumno::de($this->usuario('alumno@nep30.test'));

        $this->assertSame(['Vencida', 'Antes', 'Trabajo práctico 1: tipos de corte'], $filas->pluck('titulo')->all());
        $this->assertTrue($filas[0]['vencida']);
        $this->assertFalse($filas[1]['vencida']);
        $this->assertSame('Módulo 1: Corte y peinado', $filas[1]['materia']);
        $this->assertStringContainsString(' · ', $filas[1]['curso']);
        $this->assertNull($filas[1]['entrega']);
        $this->assertFalse($filas[1]['tiene_archivo']);
    }

    public function test_la_fila_trae_la_entrega_y_la_devolucion_del_alumno(): void
    {
        $tarea = $this->tarea('Con entrega', conArchivo: true);
        $entrega = $this->entregar($tarea, 'alumno@nep30.test');
        $entrega->update(['devolucion' => 'Muy bien', 'devuelta_en' => now()]);
        $this->entregar($tarea, 'alumno2@nep30.test');

        $fila = TareasDelAlumno::de($this->usuario('alumno@nep30.test'))->firstWhere('titulo', 'Con entrega');

        $this->assertTrue($fila['tiene_archivo']);
        $this->assertSame($entrega->id, $fila['entrega']['id']);
        $this->assertFalse($fila['entrega']['fuera_de_termino']);
        $this->assertSame('Muy bien', $fila['entrega']['devolucion']);
        $this->assertNotNull($fila['entrega']['devuelta_en']);
    }

    public function test_un_alumno_de_baja_no_ve_tareas(): void
    {
        $this->inscripcion('alumno@nep30.test')->update(['estado' => 'baja', 'fecha_baja' => now()->toDateString()]);

        $this->assertCount(0, TareasDelAlumno::de($this->usuario('alumno@nep30.test')));
    }

    // ── EntregasDeLaTarea ──────────────────────────────────

    public function test_quien_entrego_y_quien_no_ordenados_por_apellido_y_nombre(): void
    {
        $tarea = $this->tarea('TP', limite: '+1 hour');
        $this->travel(2)->hours();
        $entrega = $this->entregar($tarea, 'alumno2@nep30.test');

        $filas = EntregasDeLaTarea::de($tarea);

        $this->assertSame(['Alumna Uno', 'Alumno Dos'], $filas->pluck('nombre')->all());
        $this->assertNull($filas[0]['entrega_id']);
        $this->assertFalse($filas[0]['fuera_de_termino']);
        $this->assertSame($entrega->id, $filas[1]['entrega_id']);
        $this->assertTrue($filas[1]['fuera_de_termino']);
        $this->assertFalse($filas[1]['devuelta']);
    }

    public function test_un_alumno_de_baja_no_aparece_en_las_entregas(): void
    {
        $tarea = $this->tarea('TP');
        $this->inscripcion('alumno2@nep30.test')->update(['estado' => 'baja', 'fecha_baja' => now()->toDateString()]);

        $this->assertSame(['Alumna Uno'], EntregasDeLaTarea::de($tarea)->pluck('nombre')->all());
    }

    // ── TareasDelProfesor ──────────────────────────────────

    public function test_el_profesor_ve_sus_tareas_con_entregadas_y_alumnos(): void
    {
        $tarea = $this->tarea('TP nuevo', limite: '+2 days');
        $this->tarea('De Informática', materia: 'Módulo 1: Ofimática', limite: '+3 days');
        $this->entregar($tarea, 'alumno@nep30.test');

        $filas = TareasDelProfesor::de($this->usuario('profesor@nep30.test'));

        $this->assertSame(['TP nuevo', 'De Informática', 'Trabajo práctico 1: tipos de corte'], $filas->pluck('titulo')->all());
        $this->assertSame([1, 2], [$filas[0]['entregadas'], $filas[0]['alumnos']]);
        $this->assertSame([0, 0], [$filas[1]['entregadas'], $filas[1]['alumnos']]);
        $this->assertSame($tarea->curso_profesor_id, $filas[0]['curso_profesor_id']);
    }

    public function test_otro_profesor_no_ve_tareas_ajenas(): void
    {
        $this->assertCount(0, TareasDelProfesor::de(User::factory()->conRol('profesor')->create()));
    }

    // ── Descargas ──────────────────────────────────────────

    public function test_la_consigna_la_bajan_el_profesor_y_los_alumnos_del_curso(): void
    {
        $tarea = $this->tarea('TP 1: corte', conArchivo: true);
        $url = route('tareas.archivo', $tarea);

        $this->get($url)->assertRedirect(route('login'));

        $this->actingAs($this->usuario('profesor@nep30.test'))->get($url)->assertOk()
            ->assertDownload('TP 1 corte - consigna.pdf');
        $this->actingAs($this->usuario('alumno@nep30.test'))->get($url)->assertOk();

        $this->actingAs($this->usuario('admin@nep30.test'))->get($url)->assertForbidden();
        $this->actingAs(User::factory()->conRol('profesor')->create())->get($url)->assertForbidden();
    }

    public function test_un_alumno_de_otro_curso_o_de_baja_no_baja_la_consigna(): void
    {
        $info = $this->tarea('De Informática', materia: 'Módulo 1: Ofimática', conArchivo: true);
        $this->actingAs($this->usuario('alumno@nep30.test'))->get(route('tareas.archivo', $info))->assertForbidden();

        $pelu = $this->tarea('De Peluquería', conArchivo: true);
        $this->inscripcion('alumno@nep30.test')->update(['estado' => 'baja', 'fecha_baja' => now()->toDateString()]);
        $this->get(route('tareas.archivo', $pelu))->assertForbidden();
    }

    public function test_la_entrega_la_bajan_el_profesor_y_su_dueno_nada_mas(): void
    {
        $tarea = $this->tarea('TP 1');
        $entrega = $this->entregar($tarea, 'alumno@nep30.test');
        $url = route('entregas.archivo', $entrega);

        $this->actingAs($this->usuario('profesor@nep30.test'))->get($url)->assertOk()
            ->assertDownload('TP 1 - Prueba Alumna Uno.pdf');
        $this->actingAs($this->usuario('alumno@nep30.test'))->get($url)->assertOk();

        $this->actingAs($this->usuario('alumno2@nep30.test'))->get($url)->assertForbidden();
        $this->actingAs($this->usuario('auditor@nep30.test'))->get($url)->assertForbidden();
    }

    public function test_sin_archivo_da_404_y_un_ajeno_igual_recibe_403(): void
    {
        $tarea = $this->tarea('Sin consigna');
        $url = route('tareas.archivo', $tarea);

        $this->actingAs($this->usuario('alumno@nep30.test'))->get($url)->assertNotFound();
        $this->actingAs(User::factory()->conRol('profesor')->create())->get($url)->assertForbidden();
    }
}