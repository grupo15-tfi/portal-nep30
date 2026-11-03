<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Alumnos\Index;
use App\Models\Inscripcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HU09 (#88): pantalla de alumnos inscriptos y baja con motivo.
 * La baja la hace DarDeBaja (#86); la pantalla solo la llama y muestra sus errores.
 * Datos de prueba: «Prueba, Alumna Uno» (DNI 30000001) y «Prueba, Alumno Dos» (DNI 30000002), activos en el curso 1.
 */
class AlumnosPantallaTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function admin(): User
    {
        return User::factory()->conRol('administrador')->create();
    }

    private function inscripcionDe(string $dni): Inscripcion
    {
        return Inscripcion::whereHas('alumno', fn ($q) => $q->where('dni', $dni))->firstOrFail();
    }

    public function test_el_administrador_ve_la_pantalla_y_el_menu(): void
    {
        $this->actingAs($this->admin())->get(route('admin.alumnos'))
            ->assertOk()
            ->assertSee('Alumnos')
            ->assertSee('href="'.route('admin.alumnos').'"', false)
            ->assertSee('30000001');
    }

    public function test_profesor_y_alumno_reciben_403(): void
    {
        foreach (['profesor', 'alumno'] as $rol) {
            $this->actingAs(User::factory()->conRol($rol)->create())->get(route('admin.alumnos'))->assertForbidden();
        }
    }

    public function test_lista_las_inscripciones_con_el_estado_en_color(): void
    {
        Livewire::actingAs($this->admin())->test(Index::class)
            ->assertSee('Prueba, Alumna Uno')
            ->assertSee('Prueba, Alumno Dos')
            ->assertSeeHtml('estado-activa bg-exito-claro text-exito');
    }

    public function test_busca_por_dni_o_por_apellido(): void
    {
        Livewire::actingAs($this->admin())->test(Index::class)
            ->set('buscar', '30000002')
            ->assertSee('30000002')
            ->assertDontSee('30000001')
            ->set('buscar', 'Prueba')
            ->assertSee('30000001')
            ->assertSee('30000002')
            ->set('buscar', 'Inexistente')
            ->assertSee('No hay alumnos con esos filtros');
    }

    public function test_filtra_por_curso_y_por_estado(): void
    {
        $cursoId = $this->inscripcionDe('30000001')->curso_id;
        $otroCurso = \App\Models\Curso::where('id', '!=', $cursoId)->value('id');

        Livewire::actingAs($this->admin())->test(Index::class)
            ->set('cursoId', $cursoId)
            ->assertSee('30000001')
            ->set('cursoId', $otroCurso)
            ->assertDontSee('30000001')
            ->set('cursoId', null)
            ->set('estado', 'baja')
            ->assertDontSee('30000001')
            ->assertSee('No hay alumnos con esos filtros');
    }

    public function test_dar_de_baja_con_motivo_cierra_la_ventana_y_muestra_el_estado(): void
    {
        $inscripcion = $this->inscripcionDe('30000001');

        Livewire::actingAs($this->admin())->test(Index::class)
            ->call('abrirBaja', $inscripcion->id)
            ->assertSeeHtml('aria-labelledby="titulo-baja"')
            ->assertSee('Prueba, Alumna Uno')
            ->set('motivo', 'Se mudó a otra ciudad.')
            ->call('darDeBaja')
            ->assertHasNoErrors()
            ->assertSet('bajaId', null)
            ->assertDontSeeHtml('aria-labelledby="titulo-baja"')
            ->assertSee('Se dio de baja a Prueba, Alumna Uno')
            ->assertSee('ya no puede ingresar al Campus')
            ->assertSeeHtml('estado-baja bg-error-claro text-error')
            ->assertSee('Se mudó a otra ciudad.');

        $inscripcion->refresh();
        $this->assertSame('baja', $inscripcion->estado);
        $this->assertSame('Se mudó a otra ciudad.', $inscripcion->motivo_baja);
        $this->assertFalse($inscripcion->alumno->fresh()->activo);
    }

    public function test_sin_motivo_el_error_queda_dentro_de_la_ventana(): void
    {
        $inscripcion = $this->inscripcionDe('30000002');

        Livewire::actingAs($this->admin())->test(Index::class)
            ->call('abrirBaja', $inscripcion->id)
            ->set('motivo', '   ')
            ->call('darDeBaja')
            ->assertHasErrors(['motivo'])
            ->assertSet('bajaId', $inscripcion->id)
            ->assertSeeHtml('aria-labelledby="titulo-baja"')
            ->assertSeeHtml('aria-invalid="true"')
            ->assertSee('Escribí el motivo de la baja.');

        $this->assertSame('activa', $inscripcion->fresh()->estado);
    }

    public function test_cancelar_cierra_la_ventana_sin_dar_de_baja(): void
    {
        $inscripcion = $this->inscripcionDe('30000002');

        Livewire::actingAs($this->admin())->test(Index::class)
            ->call('abrirBaja', $inscripcion->id)
            ->set('motivo', 'Lo pensé mejor')
            ->call('cerrar')
            ->assertSet('bajaId', null)
            ->assertSet('motivo', '')
            ->assertDontSeeHtml('aria-labelledby="titulo-baja"');

        $this->assertSame('activa', $inscripcion->fresh()->estado);
    }

    public function test_una_inscripcion_dada_de_baja_no_se_puede_volver_a_dar_de_baja(): void
    {
        $inscripcion = $this->inscripcionDe('30000001');
        \App\Actions\Alumnos\DarDeBaja::ejecutar($inscripcion, 'Primera baja');

        Livewire::actingAs($this->admin())->test(Index::class)
            ->assertDontSeeHtml('wire:click="abrirBaja('.$inscripcion->id.')"')   // sin botón «Dar de baja»
            ->call('abrirBaja', $inscripcion->id)                                   // y aunque se fuerce, no abre la ventana
            ->assertStatus(404);

        $this->assertSame('Primera baja', $inscripcion->fresh()->motivo_baja);
    }
}
