<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Cursos\Index as Cursos;
use App\Livewire\Admin\Planes\Index as Planes;
use App\Livewire\Admin\Sedes\Index as Sedes;
use App\Models\Curso;
use App\Models\PeriodoLectivo;
use App\Models\PlanEstudio;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * #73: los formularios de Sedes, Planes (y Materias) y Cursos se abren en la
 * ventana emergente, como Profesores. Sin la ventana abierta no hay formulario debajo de la tabla.
 */
class VentanasFormulariosTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function admin(): User
    {
        return User::factory()->conRol('administrador')->create();
    }

    // ---------------- Componente compartido ----------------

    public function test_la_ventana_es_un_dialogo_accesible_con_titulo_asociado(): void
    {
        Livewire::actingAs($this->admin())->test(Sedes::class)
            ->call('nueva')
            ->assertSeeHtml('role="dialog"')
            ->assertSeeHtml('aria-modal="true"')
            ->assertSeeHtml('aria-labelledby="titulo-sede"')
            ->assertSeeHtml('id="titulo-sede"')
            ->assertSeeHtml('wire:keydown.escape.window="limpiar"');
    }

    // ---------------- Sedes ----------------

    public function test_sedes_sin_ventana_no_muestra_el_formulario_debajo_de_la_tabla(): void
    {
        Livewire::actingAs($this->admin())->test(Sedes::class)
            ->assertSet('ventanaAbierta', false)
            ->assertSee('+ Nueva sede')
            ->assertDontSeeHtml('role="dialog"')
            ->assertDontSeeHtml('wire:submit="guardar"');
    }

    public function test_sedes_nueva_y_editar_abren_la_ventana(): void
    {
        $sede = Sede::first();

        Livewire::actingAs($this->admin())->test(Sedes::class)
            ->call('nueva')
            ->assertSet('ventanaAbierta', true)
            ->assertSee('Nueva sede')
            ->call('limpiar')
            ->assertSet('ventanaAbierta', false)
            ->call('editar', $sede->id)
            ->assertSet('ventanaAbierta', true)
            ->assertSee('Editar sede')
            ->assertSet('nombre', $sede->nombre);
    }

    public function test_sedes_guardar_bien_cierra_la_ventana_y_avisa_en_la_lista(): void
    {
        Livewire::actingAs($this->admin())->test(Sedes::class)
            ->call('nueva')
            ->set('nombre', 'Anexo Barrio Ventana')
            ->set('tipo', 'anexo')
            ->set('localidad', 'Formosa')
            ->set('latitud', '-26.1849000')
            ->set('longitud', '-58.1731000')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('ventanaAbierta', false)
            ->assertSee('Sede creada.');
    }

    public function test_sedes_con_errores_la_ventana_sigue_abierta_y_los_muestra(): void
    {
        Livewire::actingAs($this->admin())->test(Sedes::class)
            ->call('nueva')
            ->call('guardar')
            ->assertHasErrors(['nombre', 'localidad', 'latitud', 'longitud'])
            ->assertSet('ventanaAbierta', true)
            ->assertSeeHtml('role="dialog"')
            ->assertSeeHtml('role="alert"');
    }

    // ---------------- Planes ----------------

    public function test_planes_nuevo_y_editar_abren_la_ventana_y_guardar_la_cierra(): void
    {
        $plan = PlanEstudio::first();

        Livewire::actingAs($this->admin())->test(Planes::class)
            ->assertDontSeeHtml('wire:submit="guardarPlan"')
            ->call('editarPlan', $plan->id)
            ->assertSet('ventanaPlan', true)
            ->assertSee('Editar plan')
            ->call('limpiarPlan')
            ->assertSet('ventanaPlan', false)
            ->call('nuevoPlan')
            ->assertSet('ventanaPlan', true)
            ->assertSee('Nuevo plan')
            ->set('nombre', 'Plan de prueba de Herrería')
            ->call('guardarPlan')
            ->assertHasNoErrors()
            ->assertSet('ventanaPlan', false)
            ->assertSee('Plan creado.');
    }

    public function test_planes_con_errores_la_ventana_sigue_abierta(): void
    {
        Livewire::actingAs($this->admin())->test(Planes::class)
            ->call('nuevoPlan')
            ->set('edadMinima', '5')
            ->call('guardarPlan')
            ->assertHasErrors(['nombre', 'edadMinima'])
            ->assertSet('ventanaPlan', true);
    }

    public function test_materias_se_abren_en_la_ventana_y_cerrar_la_cierra(): void
    {
        $plan = PlanEstudio::has('materias')->first();

        Livewire::actingAs($this->admin())->test(Planes::class)
            ->assertDontSeeHtml('wire:submit="guardarMateria"')
            ->call('verMaterias', $plan->id)
            ->assertSeeHtml('aria-labelledby="titulo-materias"')
            ->assertSee('Materias de «' . $plan->nombre . '»')
            ->set('materiaNombre', 'Módulo de prueba en la ventana')
            ->call('guardarMateria')
            ->assertHasNoErrors()
            ->assertSet('planSeleccionado', $plan->id)   // sigue abierta para cargar la siguiente
            ->assertSee('Materia agregada.')
            ->call('cerrarMaterias')
            ->assertSet('planSeleccionado', null)
            ->assertDontSeeHtml('aria-labelledby="titulo-materias"');
    }

    // ---------------- Cursos ----------------

    public function test_cursos_nuevo_y_editar_abren_la_ventana_y_guardar_la_cierra(): void
    {
        $curso = Curso::first();

        Livewire::actingAs($this->admin())->test(Cursos::class)
            ->assertDontSeeHtml('wire:submit="guardar"')
            ->call('editar', $curso->id)
            ->assertSet('ventanaAbierta', true)
            ->assertSee('Editar curso')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('ventanaAbierta', false)
            ->assertSee('Curso actualizado.')
            ->call('nuevo')
            ->assertSet('ventanaAbierta', true)
            ->assertSet('cursoId', null)
            ->assertSee('Nuevo curso');
    }

    public function test_cursos_con_errores_la_ventana_sigue_abierta(): void
    {
        Livewire::actingAs($this->admin())->test(Cursos::class)
            ->call('nuevo')
            ->set('periodoId', (string) PeriodoLectivo::first()->id)
            ->call('guardar')
            ->assertHasErrors(['planId', 'sedeId', 'horaInicio', 'horaFin', 'cupo'])
            ->assertSet('ventanaAbierta', true)
            ->assertSeeHtml('role="dialog"');
    }
}
