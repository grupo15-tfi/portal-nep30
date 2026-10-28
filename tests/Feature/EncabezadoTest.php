<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #59: encabezado público blanco con el sello, menú del celular y pie azul oscuro
 * (rediseño aprobado por Aguayo el 26/10). El campus no cambia.
 */
class EncabezadoTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    /** Devuelve el HTML entre <$etiqueta ...> y </$etiqueta> */
    private function parte(string $html, string $etiqueta): string
    {
        $desde = strpos($html, "<{$etiqueta}");

        return substr($html, $desde, strpos($html, "</{$etiqueta}>") - $desde);
    }

    public function test_el_encabezado_publico_es_blanco_con_el_sello_y_el_nombre_completo(): void
    {
        $encabezado = $this->parte($this->get(route('inicio'))->assertOk()->getContent(), 'header');

        $this->assertStringContainsString('class="bg-white text-texto border-b border-borde"', $encabezado);
        $this->assertStringContainsString(asset('img/sello.png'), $encabezado);
        $this->assertStringContainsString('Núcleo de Educación Permanente<br>y Formación Profesional N.º 30', $encabezado);
        $this->assertStringContainsString('«Hna. María Delgado Valero»', $encabezado);
        // «Ingresar» como botón secundario (borde azul)
        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('login'), '/').'" class="[^"]*border-2 border-primario[^"]*">Ingresar<\/a>/', $encabezado);
    }

    public function test_el_menu_del_celular_se_abre_con_un_boton_y_tiene_todas_las_secciones(): void
    {
        $encabezado = $this->parte($this->get(route('oferta'))->assertOk()->getContent(), 'header');
        $celular = $this->parte($encabezado, 'details');

        // <details>/<summary>: se abre y se cierra con Enter o la barra espaciadora, sin JavaScript
        $this->assertStringContainsString('<details id="menu-celular" class="lg:hidden', $celular);
        $this->assertMatchesRegularExpression('/<summary[^>]*>Menú<\/summary>/', $celular);
        foreach (['Inicio', 'Historia', 'Oferta educativa', 'Sedes', 'Noticias', 'Contacto', 'Ingresar'] as $item) {
            $this->assertStringContainsString(">{$item}</a>", $celular, "Falta «{$item}» en el menú del celular");
        }
        // La sección actual se distingue también por el borde, no solo por el color
        $this->assertMatchesRegularExpression('/aria-current="page"\s+class="[^"]*border-l-4[^"]*">Oferta educativa</', $celular);
    }

    public function test_el_pie_es_azul_oscuro(): void
    {
        $this->assertStringContainsString('<footer class="bg-navy-900 text-white">', $this->get(route('inicio'))->getContent());
    }

    public function test_el_campus_no_cambia(): void
    {
        $html = $this->actingAs(User::role('administrador')->firstOrFail())->get(route('admin.inicio'))->assertOk()->getContent();

        $this->assertStringContainsString('<header class="bg-primario text-white">', $html);
        $this->assertStringNotContainsString('id="menu-celular"', $html);
    }
}
