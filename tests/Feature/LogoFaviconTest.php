<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #56: logo del proyecto (sello aprobado por Aguayo el 24/10) y favicon en el sitio y el campus.
 */
class LogoFaviconTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const ALT = 'alt="Sello del N.E.P. y F.P. N.º 30"';

    private function assertTieneFavicon(string $html): void
    {
        $this->assertStringContainsString('<link rel="icon" href="'.asset('favicon.ico').'" sizes="any">', $html);
        $this->assertStringContainsString('<link rel="icon" href="'.asset('favicon.svg').'" type="image/svg+xml">', $html);
        $this->assertStringContainsString('<link rel="apple-touch-icon" href="'.asset('apple-touch-icon.png').'">', $html);
    }

    public function test_los_archivos_del_sello_y_del_favicon_existen_y_son_livianos(): void
    {
        foreach (['favicon.ico', 'favicon.svg', 'apple-touch-icon.png', 'img/sello-blanco.png', 'img/sello.png'] as $archivo) {
            $this->assertFileExists(public_path($archivo));
        }
        $this->assertLessThan(30 * 1024, filesize(public_path('img/sello-blanco.png')));
        $this->assertLessThan(30 * 1024, filesize(public_path('img/sello.png')));
    }

    public function test_el_sitio_publico_tiene_favicon_y_el_sello_en_el_encabezado_y_el_pie(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertTieneFavicon($html);
        // Una vez en el encabezado y otra en el pie
        $this->assertSame(2, substr_count($html, self::ALT));

        $encabezado = substr($html, strpos($html, '<header'), strpos($html, '</header>') - strpos($html, '<header'));
        $pie = substr($html, strpos($html, '<footer'), strpos($html, '</footer>') - strpos($html, '<footer'));
        // #59: el encabezado es blanco y lleva el sello a color; el pie sigue azul con el sello blanco
        $this->assertStringContainsString(asset('img/sello.png'), $encabezado);
        $this->assertStringContainsString(asset('img/sello-blanco.png'), $pie);
    }

    public function test_las_pantallas_de_acceso_tienen_favicon(): void
    {
        $this->assertTieneFavicon($this->get(route('login'))->assertOk()->getContent());
        $this->assertTieneFavicon($this->get(route('password.request'))->assertOk()->getContent());
        $this->assertTieneFavicon($this->get(route('password.reset', 'token-de-prueba'))->assertOk()->getContent());
    }

    public function test_los_cuatro_paneles_tienen_favicon_y_el_sello_en_la_barra(): void
    {
        foreach (['administrador' => 'admin.inicio', 'profesor' => 'profesor.inicio', 'alumno' => 'alumno.inicio', 'auditor' => 'auditor.inicio'] as $rol => $ruta) {
            $html = $this->actingAs(User::role($rol)->firstOrFail())->get(route($ruta))->assertOk()->getContent();

            $this->assertTieneFavicon($html);
            $barra = substr($html, strpos($html, '<header'), strpos($html, '</header>') - strpos($html, '<header'));
            $this->assertStringContainsString(self::ALT, $barra, "Falta el sello en la barra del panel {$rol}");
        }
    }
}
