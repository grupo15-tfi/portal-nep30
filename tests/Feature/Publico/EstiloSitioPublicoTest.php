<?php

namespace Tests\Feature\Publico;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * #155: el sitio público con la base de estilos (#154).
 * Las acciones son botones de verdad, los «volver» tienen su botón, la oferta se filtra con chips
 * (enlaces GET, sin «Filtrar») y una noticia sin imagen muestra el sello en vez de un recuadro vacío.
 */
class EstiloSitioPublicoTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function curso(): int
    {
        return (int) DB::table('v_oferta_publica')->value('curso_id');
    }

    public function test_ya_no_quedan_volver_con_comillas_ni_flechas_de_texto(): void
    {
        foreach ([route('inicio'), route('oferta'), route('oferta.curso', $this->curso()), route('calendario'),
                  route('noticias.index'), route('login'), route('password.request')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('« ', $html, "Quedó un «« Volver» de texto en {$url}");
            $this->assertStringNotContainsString(' →</a>', $html, "Quedó una flecha de texto en {$url}");
        }
    }

    public function test_la_tarjeta_de_curso_tiene_botones_y_el_cupo_como_chip(): void
    {
        $html = $this->get(route('oferta'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/class="boton boton-principal w-full">\s*(Preinscribirme|Anotarme en la lista de espera)/', $html);
        $this->assertStringContainsString('class="boton boton-secundario w-full">Ver detalle', $html);
        $this->assertMatchesRegularExpression('/class="chip chip-punto chip-(exito|aviso)"/', $html);
    }

    public function test_la_oferta_se_filtra_con_chips_sin_formulario(): void
    {
        $sede = DB::table('v_oferta_publica')->value('sede_id');

        $todas = $this->get(route('oferta'))->assertOk()
            ->assertDontSee('>Filtrar<', false)
            ->assertDontSee('<select id="sede"', false)
            ->assertSee('aria-label="Filtrar por sede"', false)
            ->getContent();
        $this->assertMatchesRegularExpression('/aria-current="true"[^>]*>Todas las sedes</', $todas);

        $filtrada = $this->get(route('oferta', ['sede' => $sede]))->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/aria-current="true"[^>]*>Todas las sedes</', $filtrada);
        $this->assertSame(1, substr_count($filtrada, 'aria-current="true"'));
    }

    public function test_una_noticia_sin_imagen_muestra_el_sello(): void
    {
        DB::table('noticias')->update(['imagen_ruta' => null]);

        $this->get(route('inicio'))->assertOk()
            ->assertSee('class="size-18 opacity-40"', false);
    }

    public function test_el_inicio_usa_titulos_y_tarjetas_de_la_base(): void
    {
        $this->get(route('inicio'))->assertOk()
            ->assertSee('class="titulo-pagina"', false)
            ->assertSee('<p class="etiqueta">Oferta educativa', false)
            ->assertSee('class="tarjeta flex flex-col gap-2 p-5 md:p-6"', false);
    }

    public function test_el_login_tiene_el_sello_y_volver_como_boton(): void
    {
        $this->get(route('login'))->assertOk()
            ->assertSee('class="boton boton-volver">Volver al sitio', false)
            ->assertSee('alt="Sello del N.E.P. y F.P. N.º 30"', false)
            ->assertSee('class="tarjeta w-full max-w-sm rounded-3xl', false);
    }
}
