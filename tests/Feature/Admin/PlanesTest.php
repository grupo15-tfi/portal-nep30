<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Planes\Index;
use App\Models\PlanEstudio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HU07 (#9): ABM de planes de estudio y materias (RF15).
 */
class PlanesTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function admin(): User
    {
        return User::factory()->conRol('administrador')->create();
    }

    private function formularioPlan(array $cambios = [])
    {
        $datos = array_merge([
            'nombre'           => 'Plan de prueba de Carpintería',
            'tipo'             => 'formacion_profesional',
            'edadMinima'       => '18',
            'notaAprobacion'   => '6',
            'asistenciaMinima' => '80',
        ], $cambios);

        $componente = Livewire::actingAs($this->admin())->test(Index::class);
        foreach ($datos as $campo => $valor) {
            $componente->set($campo, $valor);
        }

        return $componente->call('guardarPlan');
    }

    public function test_el_administrador_ve_la_pantalla_de_planes(): void
    {
        $this->actingAs($this->admin())->get('/admin/planes')->assertOk();
    }

    public function test_profesor_recibe_403(): void
    {
        $profesor = User::factory()->conRol('profesor')->create();
        $this->actingAs($profesor)->get('/admin/planes')->assertForbidden();
    }

    public function test_alta_de_plan_valida(): void
    {
        $this->formularioPlan()->assertHasNoErrors();

        $this->assertDatabaseHas('planes_estudio', ['nombre' => 'Plan de prueba de Carpintería', 'activo' => true]);
    }

    public function test_nombre_de_plan_repetido_da_error(): void
    {
        $this->formularioPlan(['nombre' => 'Peluquería'])->assertHasErrors(['nombre' => 'unique']);
    }

    public function test_reglas_del_plan_fuera_de_rango_dan_error(): void
    {
        $this->formularioPlan(['edadMinima' => '13'])->assertHasErrors('edadMinima');
        $this->formularioPlan(['notaAprobacion' => '11'])->assertHasErrors('notaAprobacion');
        $this->formularioPlan(['asistenciaMinima' => '101'])->assertHasErrors('asistenciaMinima');
        $this->formularioPlan(['cantidadCiclos' => '7'])->assertHasErrors('cantidadCiclos');
        $this->formularioPlan(['tipo' => 'universitario'])->assertHasErrors('tipo');
    }

    public function test_agregar_materia_a_un_plan(): void
    {
        $plan = PlanEstudio::where('nombre', 'Peluquería')->firstOrFail();

        Livewire::actingAs($this->admin())->test(Index::class)
            ->call('verMaterias', $plan->id)
            ->set('materiaNombre', 'Módulo 3: Peinados de fiesta')
            ->set('materiaCarga', '48')
            ->call('guardarMateria')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('materias', ['plan_id' => $plan->id, 'nombre' => 'Módulo 3: Peinados de fiesta', 'carga_horaria' => 48]);
    }

    public function test_materia_repetida_en_el_mismo_plan_da_error(): void
    {
        $plan = PlanEstudio::where('nombre', 'Peluquería')->firstOrFail();

        Livewire::actingAs($this->admin())->test(Index::class)
            ->call('verMaterias', $plan->id)
            ->set('materiaNombre', 'Módulo 1: Corte y peinado') // ya existe en los datos de prueba
            ->call('guardarMateria')
            ->assertHasErrors(['materiaNombre' => 'unique']);
    }

    public function test_desactivar_plan_saca_sus_cursos_de_la_oferta_publica(): void
    {
        $plan = PlanEstudio::where('nombre', 'Peluquería')->firstOrFail();
        $this->assertGreaterThan(0, DB::table('v_oferta_publica')->where('plan', $plan->nombre)->count());

        Livewire::actingAs($this->admin())->test(Index::class)->call('cambiarEstadoPlan', $plan->id);

        $this->assertFalse($plan->fresh()->activo);
        $this->assertSame(0, DB::table('v_oferta_publica')->where('plan', $plan->nombre)->count());
    }
}