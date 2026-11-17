<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #112: los botones responden al apretarlos y avisan mientras guardan.
 * El efecto visual vive en app.css (una regla para todos); acá se controla que esté y que cada
 * formulario tenga su texto de espera y no permita el doble envío.
 */
class BotonesRespuestaTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function css(): string
    {
        return file_get_contents(resource_path('css/app.css'));
    }

    public function test_una_sola_regla_central_para_apretar_con_transicion_corta(): void
    {
        $css = $this->css();

        $this->assertStringContainsString('button, summary, .boton {', $css);
        $this->assertMatchesRegularExpression('/transition:[^;]*150ms/', $css);
        $this->assertStringContainsString('button:not(:disabled):active, summary:active, .boton:active { scale: 0.97; }', $css);
        $this->assertStringContainsString('button:disabled { @apply cursor-not-allowed opacity-60; }', $css);
    }

    public function test_mientras_procesa_no_se_puede_volver_a_apretar(): void
    {
        $this->assertStringContainsString(
            "button[data-loading], form[data-loading] button[type='submit'] { cursor: wait; pointer-events: none; }",
            $this->css()
        );
    }

    public function test_con_reducir_movimiento_no_se_mueve_nada(): void
    {
        $css = $this->css();
        $reducido = substr($css, strpos($css, '@media (prefers-reduced-motion: reduce)'));

        $this->assertStringContainsString('transition: none;', $reducido);
        $this->assertStringContainsString('scale: none;', $reducido);
        $this->assertStringContainsString('animation: none;', $reducido);
    }

    public function test_los_enlaces_con_forma_de_boton_tienen_la_clase_comun(): void
    {
        $this->get(route('inicio'))->assertOk()
            ->assertSee('class="boton inline-flex', false)
            ->assertSee('class="tarjeta-enlace', false);

        $this->get(route('login'))->assertOk();
        $this->get(route('oferta.curso', 1))->assertOk()->assertSee('class="boton mt-2', false);
    }

    public function test_login_olvide_y_restablecer_avisan_mientras_procesan(): void
    {
        $this->get(route('login'))
            ->assertSeeInOrder(['wire:loading.remove wire:target="ingresar"', 'Ingresar', 'wire:loading wire:target="ingresar"', 'Ingresando…'], false);

        $this->get(route('password.request'))->assertSee('Enviando…');
        $this->get(route('password.reset', 'token-de-prueba'))->assertSee('Guardando…');
    }

    public function test_los_formularios_del_panel_dicen_guardando_y_se_deshabilitan(): void
    {
        $admin = User::where('email', 'admin@nep30.test')->firstOrFail();

        // La ventana de Sedes se abre con «Nueva sede»: el botón de guardar lleva el texto de espera
        \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\Admin\Sedes\Index::class)
            ->call('nueva')
            ->assertSeeHtml('wire:loading.attr="disabled" wire:target="guardar"')
            ->assertSee('Guardando…');

        $this->actingAs($admin)->get(route('perfil'))->assertOk()->assertSee('Guardando…');
    }
}
