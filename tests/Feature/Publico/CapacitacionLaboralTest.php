<?php

namespace Tests\Feature\Publico;

use App\Livewire\Admin\Planes\Index;
use App\Models\Curso;
use App\Models\PlanEstudio;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * #95: tipo de plan «Capacitación laboral» (pedido del PO en la revisión del Sprint 2, 30/10).
 * Tejido Artesanal y Belleza Integral pasan de formación profesional a capacitación laboral.
 */
class CapacitacionLaboralTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_tejido_artesanal_y_belleza_integral_son_capacitacion_laboral(): void
    {
        $this->assertSame(
            ['Belleza Integral', 'Tejido Artesanal'],
            PlanEstudio::where('tipo', 'capacitacion_laboral')->orderBy('nombre')->pluck('nombre')->all(),
        );
    }

    public function test_la_base_acepta_el_tipo_nuevo_y_rechaza_otros(): void
    {
        $plan = PlanEstudio::create(['nombre' => 'Plan de prueba de Herrería', 'tipo' => 'capacitacion_laboral']);
        $this->assertSame('capacitacion_laboral', $plan->fresh()->tipo);

        $this->expectException(QueryException::class);   // chk_plan_tipo
        DB::table('planes_estudio')->where('id', $plan->id)->update(['tipo' => 'universitario']);
    }

    public function test_el_administrador_crea_un_plan_de_capacitacion_laboral(): void
    {
        Livewire::actingAs(User::factory()->conRol('administrador')->create())->test(Index::class)
            ->set('nombre', 'Plan de prueba de Marroquinería')
            ->set('tipo', 'capacitacion_laboral')
            ->set('edadMinima', '18')
            ->set('notaAprobacion', '6')
            ->set('asistenciaMinima', '80')
            ->call('guardarPlan')
            ->assertHasNoErrors()
            ->assertSee('Capacitación laboral');   // la columna «Tipo» del listado

        $this->assertDatabaseHas('planes_estudio', ['nombre' => 'Plan de prueba de Marroquinería', 'tipo' => 'capacitacion_laboral']);
    }

    public function test_la_oferta_publica_muestra_el_tipo_nuevo(): void
    {
        // Un curso de Tejido Artesanal en la misma sede y período que Peluquería (datos de prueba)
        $modelo = Curso::where('plan_id', PlanEstudio::where('nombre', 'Peluquería')->value('id'))->firstOrFail();
        DB::table('cursos')->insert([
            'plan_id' => PlanEstudio::where('nombre', 'Tejido Artesanal')->value('id'),
            'sede_id' => $modelo->sede_id, 'periodo_id' => $modelo->periodo_id,
            'turno' => 'noche', 'cupo' => 15, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->get(route('oferta'))
            ->assertOk()
            ->assertSee('Tejido Artesanal')
            ->assertSee('Capacitación laboral');
    }
}