<?php

namespace Tests\Feature\Publico;

use Database\Seeders\SedesRealesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * #97: sedes reales relevadas el 31/10/2026 (Benitez y Arce), pedido del PO en la revisión del Sprint 2.
 * El seeder no corre con DatabaseSeeder: acá se llama a mano, igual que en la VPS.
 */
class SedesRealesSeederTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function nombres(): array
    {
        return array_column(SedesRealesSeeder::SEDES, 'nombre');
    }

    public function test_carga_las_seis_sedes_reales_activas(): void
    {
        $this->seed(SedesRealesSeeder::class);

        $this->assertSame(6, DB::table('sedes')->whereIn('nombre', $this->nombres())->where('activo', true)->count());
        $this->assertDatabaseHas('sedes', ['nombre' => 'Sede central – Barrio Luján', 'tipo' => 'central']);
    }

    public function test_correrlo_dos_veces_no_duplica_las_sedes(): void
    {
        $this->seed(SedesRealesSeeder::class);
        $this->seed(SedesRealesSeeder::class);

        $this->assertSame(6, DB::table('sedes')->whereIn('nombre', $this->nombres())->count());
    }

    public function test_todas_las_coordenadas_estan_en_formosa_y_no_estan_invertidas(): void
    {
        foreach (SedesRealesSeeder::SEDES as $sede) {
            // Latitud ≈ -26 y longitud ≈ -58. Si alguien las invierte, esta prueba falla.
            $this->assertTrue($sede['latitud'] > -26.3 && $sede['latitud'] < -26.0, "Latitud fuera de Formosa: {$sede['nombre']}");
            $this->assertTrue($sede['longitud'] > -58.3 && $sede['longitud'] < -58.0, "Longitud fuera de Formosa: {$sede['nombre']}");
        }
    }

    public function test_las_sedes_reales_aparecen_en_el_mapa_y_en_las_localidades(): void
    {
        $this->seed(SedesRealesSeeder::class);

        $this->getJson(route('sedes.datos'))
            ->assertOk()
            ->assertJsonFragment(['nombre' => 'Anexo San Isidro']);

        $this->getJson(route('sedes.localidades'))
            ->assertOk()
            ->assertJsonFragment(['localidad' => 'Formosa']);
    }
}