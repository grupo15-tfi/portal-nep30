<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Preinscripciones\Index;
use App\Models\Curso;
use App\Models\PlanEstudio;
use App\Models\Preinscripcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HU09 (#87): pantalla de preinscripciones y lista de espera.
 * Datos de DatosDePruebaSeeder: Peluquería completo con Aspirante Uno (30000003) y Dos (30000004)
 * en espera; Aspirante Tres (30000005) pendiente en Informática.
 */
class PreinscripcionesPantallaTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function admin(): User
    {
        return User::factory()->conRol('administrador')->create();
    }

    private function cursoPeluqueria(): Curso
    {
        return Curso::where('plan_id', PlanEstudio::where('nombre', 'Peluquería')->value('id'))->firstOrFail();
    }

    public function test_solo_el_administrador_entra_y_el_menu_muestra_preinscripciones(): void
    {
        $this->actingAs($this->admin())->get('/admin/preinscripciones')->assertOk()->assertSee('Preinscripciones');
        $this->actingAs($this->admin())->get('/admin/inicio')->assertSee(route('admin.preinscripciones'), false);

        foreach (['profesor', 'alumno', 'auditor'] as $rol) {
            $this->actingAs(User::factory()->conRol($rol)->create())->get('/admin/preinscripciones')->assertForbidden();
        }
        $this->get('/admin/preinscripciones')->assertRedirect();
    }

    public function test_arranca_con_las_pendientes_y_filtra_por_estado(): void
    {
        Livewire::actingAs($this->admin())->test(Index::class)
            ->assertSee('30000005')->assertDontSee('30000003')
            ->set('estado', 'en_espera')->assertSee('30000003')->assertSee('30000004')->assertDontSee('30000005')
            ->set('estado', '')->assertSee('30000003')->assertSee('30000005');
    }

    public function test_busca_por_dni_o_apellido_y_filtra_por_curso(): void
    {
        Livewire::actingAs($this->admin())->test(Index::class)
            ->set('estado', '')
            ->set('buscar', '30000004')->assertSee('30000004')->assertDontSee('30000003')
            ->set('buscar', '')
            ->set('cursoId', $this->cursoPeluqueria()->id)->assertSee('30000003')->assertDontSee('30000005');
    }

    public function test_el_listado_no_muestra_domicilio_ni_correo_y_el_detalle_si(): void
    {
        $p = Preinscripcion::where('dni', '30000005')->firstOrFail();

        Livewire::actingAs($this->admin())->test(Index::class)
            ->assertDontSee('aspirante3@nep30.test')
            ->call('ver', $p->id)
            ->assertSee('aspirante3@nep30.test')
            ->assertSee('3704 111111')
            ->call('cerrar')
            ->assertDontSee('aspirante3@nep30.test');
    }

    public function test_lista_de_espera_por_orden_de_llegada_con_telefono(): void
    {
        Livewire::actingAs($this->admin())->test(Index::class)
            ->set('cursoId', $this->cursoPeluqueria()->id)
            ->assertSee('Lista de espera')
            ->assertSeeInOrder(['Aspirante Uno', 'Aspirante Dos'])
            ->assertSee('3704 111111')
            ->assertSee('Lugares disponibles');
    }
}