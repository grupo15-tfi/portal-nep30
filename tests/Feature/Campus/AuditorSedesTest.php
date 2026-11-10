<?php

namespace Tests\Feature\Campus;

use App\Livewire\Auditor\Sedes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HU18 (#130): «Resumen por sede» del Auditor. Los números salen de ResumenPorSede::todas() (#129),
 * que lee v_resumen_sedes; acá se comparan contra esa misma vista con los datos de prueba.
 */
class AuditorSedesTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function auditor(): User
    {
        return User::where('email', 'auditor@nep30.test')->firstOrFail();
    }

    public function test_muestra_cada_sede_con_sus_numeros(): void
    {
        $vista = DB::table('v_resumen_sedes')->get();
        $this->assertNotEmpty($vista);

        $pantalla = Livewire::actingAs($this->auditor())->test(Sedes::class);

        foreach ($vista as $s) {
            $pantalla->assertSeeHtml('wire:key="sede-'.$s->sede_id.'"')->assertSee($s->sede);
        }
        $pantalla->assertSee(['Cursos activos', 'Alumnos activos', 'Preinscripciones pendientes', 'En lista de espera']);
    }

    public function test_los_totales_suman_todas_las_sedes(): void
    {
        $vista = DB::table('v_resumen_sedes')->get();

        $totales = Livewire::actingAs($this->auditor())->test(Sedes::class)->viewData('totales');

        $this->assertSame((int) $vista->sum('cursos_activos'), $totales['cursos_activos']);
        $this->assertSame((int) $vista->sum('alumnos_activos'), $totales['alumnos_activos']);
        $this->assertSame((int) $vista->sum('preinscripciones_pendientes'), $totales['preinscripciones_pendientes']);
        $this->assertSame((int) $vista->sum('en_lista_espera'), $totales['en_lista_espera']);
        $this->assertGreaterThan(0, $totales['alumnos_activos']);
    }

    public function test_las_sedes_inactivas_quedan_marcadas(): void
    {
        $sede = DB::table('sedes')->orderBy('id')->first();
        DB::table('sedes')->where('id', $sede->id)->update(['activo' => false]);

        Livewire::actingAs($this->auditor())->test(Sedes::class)
            ->assertSeeInOrder([$sede->nombre, 'Inactiva']);
    }

    public function test_es_solo_lectura_sin_botones_ni_enlaces_de_edicion(): void
    {
        $html = Livewire::actingAs($this->auditor())->test(Sedes::class)->html();

        foreach (['<button', '<a ', '<form', '<input', 'wire:click', 'wire:submit', 'wire:model'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $html, "No debería haber «{$prohibido}» en una vista de solo lectura");
        }
    }

    public function test_la_pagina_del_auditor_usa_el_panel(): void
    {
        // La ruta real la registra #129 (Benitez); acá se simula igual para probar la página
        Route::middleware(['web', 'auth', 'role:auditor'])->get('/auditor/sedes-prueba', fn () => view('auditor.sedes'));

        // El administrador se crea antes de iniciar sesión como auditor: la base no deja escribir al Auditor
        $admin = User::factory()->conRol('administrador')->create();

        $this->get('/auditor/sedes-prueba')->assertRedirect(route('login'));
        $this->actingAs($this->auditor())->get('/auditor/sedes-prueba')
            ->assertOk()->assertSee('Resumen por sede')->assertSee('Total');
        $this->actingAs($admin)->get('/auditor/sedes-prueba')->assertForbidden();
    }
}
