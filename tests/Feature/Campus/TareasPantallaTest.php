<?php

namespace Tests\Feature\Campus;

use App\Actions\Tareas\EntregarTarea;
use App\Actions\Tareas\GuardarTarea;
use App\Livewire\Alumno\Tareas as TareasAlumno;
use App\Livewire\Profesor\Tareas as TareasProfesor;
use App\Models\CursoProfesor;
use App\Models\Entrega;
use App\Models\Inscripcion;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HU12 (#120): pantallas de tareas. El profesor publica, ve quién entregó y devuelve;
 * el alumno ve sus tareas y entrega. Datos de DatosDePruebaSeeder: la profesora tiene
 * Corte y peinado en el curso de Peluquería, con Alumna Uno y Alumno Dos.
 */
class TareasPantallaTest extends TestCase
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

    private function corteYPeinado(): CursoProfesor
    {
        return CursoProfesor::where('profesor_id', $this->usuario('profesor@nep30.test')->id)
            ->whereHas('materia', fn ($q) => $q->where('nombre', 'like', '%Corte y peinado%'))->firstOrFail();
    }

    /** Publica una tarea como la profesora (fecha relativa a hoy, en días) */
    private function tarea(string $titulo = 'TP 1: corte en capas', int $dias = 7): Tarea
    {
        $this->actingAs($this->usuario('profesor@nep30.test'));

        return GuardarTarea::ejecutar([
            'curso_profesor_id' => $this->corteYPeinado()->id, 'titulo' => $titulo, 'consigna' => 'Subí fotos del paso a paso.',
            'fecha_entrega' => now()->addDays($dias)->format('Y-m-d H:i'), 'archivo' => null,
        ]);
    }

    private function entregar(Tarea $tarea, string $email): Entrega
    {
        $insc = Inscripcion::whereHas('alumno', fn ($q) => $q->where('email', $email))->where('curso_id', $tarea->cursoProfesor->curso_id)->firstOrFail();

        return EntregarTarea::ejecutar($tarea, $insc, UploadedFile::fake()->create('entrega.pdf', 200, 'application/pdf'));
    }

    private function rutasDeDescarga(): void
    {
        Route::middleware(['web', 'auth'])->get('/campus/tareas/{tarea}/archivo', fn () => 'ok')->name('tareas.archivo');
        Route::middleware(['web', 'auth'])->get('/campus/entregas/{entrega}/archivo', fn () => 'ok')->name('entregas.archivo');
        Route::getRoutes()->refreshNameLookups();
    }

    public function test_cada_rol_entra_solo_a_su_pantalla_y_tiene_el_menu(): void
    {
        $this->get(route('profesor.tareas'))->assertRedirect(route('login'));

        $this->actingAs($this->usuario('profesor@nep30.test'))->get(route('profesor.tareas'))
            ->assertOk()->assertSee('href="'.route('profesor.tareas').'"', false);
        $this->actingAs($this->usuario('alumno@nep30.test'))->get(route('alumno.tareas'))
            ->assertOk()->assertSee('href="'.route('alumno.tareas').'"', false);

        $this->actingAs($this->usuario('alumno@nep30.test'))->get(route('profesor.tareas'))->assertForbidden();
        $this->actingAs($this->usuario('profesor@nep30.test'))->get(route('alumno.tareas'))->assertForbidden();
        $this->actingAs($this->usuario('admin@nep30.test'))->get(route('profesor.tareas'))->assertForbidden();
    }

    public function test_el_profesor_publica_una_tarea_con_archivo(): void
    {
        Livewire::actingAs($this->usuario('profesor@nep30.test'))->test(TareasProfesor::class)
            ->assertSee('Trabajo práctico 1: tipos de corte') // la del seeder
            ->call('nueva')
            ->assertSet('ventanaTarea', true)
            ->assertSet('curso_profesor_id', $this->corteYPeinado()->id)
            ->set('titulo', 'TP 1: corte en capas')
            ->set('consigna', 'Subí fotos del paso a paso.')
            ->set('fecha_entrega', now()->addWeek()->format('Y-m-d\TH:i'))
            ->set('archivo', UploadedFile::fake()->create('consigna.pdf', 120, 'application/pdf'))
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('ventanaTarea', false)
            ->assertSee('Se publicó la tarea «TP 1: corte en capas».')
            ->assertSeeHtmlInOrder(['TP 1: corte en capas', '0 / 2']);

        $tarea = Tarea::where('titulo', 'TP 1: corte en capas')->firstOrFail();
        Storage::disk('local')->assertExists($tarea->archivo_ruta);
    }

    public function test_los_errores_quedan_dentro_de_la_ventana(): void
    {
        $antes = Tarea::count();

        Livewire::actingAs($this->usuario('profesor@nep30.test'))->test(TareasProfesor::class)
            ->call('nueva')
            ->set('titulo', '')
            ->call('guardar')
            ->assertHasErrors(['titulo'])
            ->assertSet('ventanaTarea', true)
            ->assertSee('aria-invalid', false);

        $this->assertSame($antes, Tarea::count());
    }

    public function test_editar_una_tarea_y_no_abrir_tareas_ajenas(): void
    {
        $tarea = $this->tarea();

        Livewire::actingAs($this->usuario('profesor@nep30.test'))->test(TareasProfesor::class)
            ->call('editar', $tarea->id)
            ->assertSet('titulo', 'TP 1: corte en capas')
            ->assertSee('Editar tarea')
            ->set('titulo', 'TP 1: corte en capas (corregido)')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSee('Se guardaron los cambios de «TP 1: corte en capas (corregido)».');

        $otroProfe = User::factory()->conRol('profesor')->create();
        Livewire::actingAs($otroProfe)->test(TareasProfesor::class)->call('editar', $tarea->id)->assertStatus(404);
    }

    public function test_el_profesor_ve_quien_entrego_y_quien_no(): void
    {
        $tarea = $this->tarea();
        $this->entregar($tarea, 'alumno@nep30.test');

        Livewire::actingAs($this->usuario('profesor@nep30.test'))->test(TareasProfesor::class)
            ->assertSee('1 / 2')
            ->call('verEntregas', $tarea->id)
            ->assertSee('Entregas · TP 1: corte en capas')
            ->assertSeeHtmlInOrder(['Prueba, Alumna Uno', 'Entregó', 'Prueba, Alumno Dos', 'No entregó'])
            ->assertDontSee('Fuera de término');
    }

    public function test_una_entrega_vencida_aparece_fuera_de_termino_en_rojo(): void
    {
        $tarea = $this->tarea('TP vencido', -1);
        $this->entregar($tarea, 'alumno@nep30.test');

        Livewire::actingAs($this->usuario('profesor@nep30.test'))->test(TareasProfesor::class)
            ->call('verEntregas', $tarea->id)
            ->assertSeeHtmlInOrder(['Prueba, Alumna Uno', 'Entregó', 'bg-error-claro', 'Fuera de término']);
    }

    public function test_devolver_una_entrega_y_el_alumno_la_ve(): void
    {
        $tarea = $this->tarea();
        $entrega = $this->entregar($tarea, 'alumno@nep30.test');

        Livewire::actingAs($this->usuario('profesor@nep30.test'))->test(TareasProfesor::class)
            ->call('verEntregas', $tarea->id)
            ->call('devolver', $entrega->id)
            ->assertSee('Devolver la entrega')
            ->set('devolucion', '   ')
            ->call('guardarDevolucion')
            ->assertHasErrors(['devolucion'])
            ->set('devolucion', 'Muy buen trabajo, cuidá las puntas.')
            ->call('guardarDevolucion')
            ->assertHasNoErrors()
            ->assertSee('Se guardó la devolución para Prueba, Alumna Uno.')
            ->assertSee('Devuelta');

        Livewire::actingAs($this->usuario('alumno@nep30.test'))->test(TareasAlumno::class)
            ->assertSee('Devolución del profesor')
            ->assertSee('Muy buen trabajo, cuidá las puntas.');
    }

    public function test_el_alumno_ve_sus_tareas_y_entrega(): void
    {
        $tarea = $this->tarea();

        Livewire::actingAs($this->usuario('alumno@nep30.test'))->test(TareasAlumno::class)
            ->assertSeeHtmlInOrder(['TP 1: corte en capas', 'Pendiente', 'Subí fotos del paso a paso.'])
            ->call('entregar', $tarea->id)
            ->assertSee('Entregar la tarea')
            ->assertSee('PDF, DOCX, JPG, PNG o ZIP, hasta 10 MB.')
            ->set('archivo', UploadedFile::fake()->create('mi-tp.pdf', 300, 'application/pdf'))
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSee('Se entregó «TP 1: corte en capas».')
            ->assertSee('Entregada')
            ->assertSee('Reemplazar');

        $this->assertSame(1, Entrega::where('tarea_id', $tarea->id)->count());
    }

    public function test_tipo_no_permitido_el_error_queda_en_la_ventana(): void
    {
        $tarea = $this->tarea();

        Livewire::actingAs($this->usuario('alumno@nep30.test'))->test(TareasAlumno::class)
            ->call('entregar', $tarea->id)
            ->set('archivo', UploadedFile::fake()->create('virus.exe', 10))
            ->call('guardar')
            ->assertHasErrors(['archivo'])
            ->assertSet('tareaId', $tarea->id);

        $this->assertSame(0, Entrega::count());
    }

    public function test_despues_del_vencimiento_no_aparece_reemplazar(): void
    {
        $tarea = $this->tarea('TP corto', 1);
        $this->entregar($tarea, 'alumno@nep30.test');
        $this->travel(2)->days();

        Livewire::actingAs($this->usuario('alumno@nep30.test'))->test(TareasAlumno::class)
            ->assertSee('Entregada')
            ->assertDontSee('Reemplazar');

        // Quien no entregó la ve vencida y todavía puede entregar (queda fuera de término)
        Livewire::actingAs($this->usuario('alumno2@nep30.test'))->test(TareasAlumno::class)
            ->assertSee('Vencida')
            ->call('entregar', $tarea->id)
            ->assertSee('fuera de término');
    }

    public function test_el_alumno_no_entrega_tareas_de_otro_curso(): void
    {
        $tarea = $this->tarea();
        $ajeno = User::factory()->conRol('alumno')->create();

        Livewire::actingAs($ajeno)->test(TareasAlumno::class)
            ->assertSee('Todavía no hay tareas publicadas')
            ->call('entregar', $tarea->id)
            ->assertStatus(404);
    }

    public function test_las_descargas_aparecen_cuando_existen_sus_rutas(): void
    {
        $this->actingAs($this->usuario('profesor@nep30.test'));
        $tarea = GuardarTarea::ejecutar([
            'curso_profesor_id' => $this->corteYPeinado()->id, 'titulo' => 'TP con archivo', 'consigna' => null,
            'fecha_entrega' => now()->addWeek()->format('Y-m-d H:i'),
            'archivo' => UploadedFile::fake()->create('c.pdf', 50, 'application/pdf'),
        ]);
        $this->entregar($tarea, 'alumno@nep30.test');

        // Sin las rutas de #119 los botones no aparecen (no hay enlaces rotos)
        Livewire::actingAs($this->usuario('profesor@nep30.test'))->test(TareasProfesor::class)
            ->call('verEntregas', $tarea->id)->assertDontSee('Descargar');
        Livewire::actingAs($this->usuario('alumno@nep30.test'))->test(TareasAlumno::class)
            ->assertDontSee('Descargar consigna')->assertDontSee('Ver mi entrega');

        $this->rutasDeDescarga();

        Livewire::actingAs($this->usuario('profesor@nep30.test'))->test(TareasProfesor::class)
            ->call('verEntregas', $tarea->id)->assertSee('Descargar');
        Livewire::actingAs($this->usuario('alumno@nep30.test'))->test(TareasAlumno::class)
            ->assertSee('Descargar consigna')->assertSee('Ver mi entrega');
    }
}
