<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * #154: base de estilos comunes del sitio público y del campus (botones, tarjeta, chips y títulos).
 * Las clases viven en app.css; acá se controla que estén las reglas centrales y lo que pide #153:
 * todo lo que se aprieta mide 44 px o más, y los colores son los de la paleta (contraste AA ya verificado).
 */
class EstilosBaseTest extends TestCase
{
    private function css(): string
    {
        return file_get_contents(resource_path('css/app.css'));
    }

    private function regla(string $selector): string
    {
        $css = $this->css();
        // La regla que empieza en su propia línea con ese selector (no la que lo comparte con otros)
        $this->assertSame(1, preg_match('/^\s*'.preg_quote($selector, '/').' \{/m', $css, $m, PREG_OFFSET_CAPTURE), "Falta la regla {$selector}");
        $inicio = $m[0][1];

        return substr($css, $inicio, strpos($css, '}', $inicio) - $inicio);
    }

    public function test_las_clases_viven_en_la_capa_de_componentes(): void
    {
        // Así cualquier clase de Tailwind puesta en el elemento les gana (igual que en la capa base)
        $css = $this->css();
        $capa = strpos($css, '@layer components {');
        $this->assertNotFalse($capa);
        $this->assertGreaterThan($capa, strpos($css, '.boton-principal { @apply'));
        $this->assertGreaterThan($capa, strpos($css, '.titulo-pagina { @apply'));
    }

    public function test_los_cuatro_botones_miden_44_px_y_no_se_subrayan(): void
    {
        $comun = $this->regla('.boton-principal, .boton-secundario, .boton-volver, .boton-peligro');

        $this->assertStringContainsString('min-h-11', $comun);  // 2,75rem = 44 px
        $this->assertStringContainsString('no-underline', $comun);
        $this->assertStringContainsString('border-0', $comun);
        $this->assertStringContainsString('min-h-11', $this->regla('.boton-volver'));
    }

    public function test_cada_boton_usa_los_colores_de_la_paleta(): void
    {
        $this->assertStringContainsString('bg-primario text-white', $this->regla('.boton-principal'));
        $this->assertStringContainsString('bg-primario-claro text-primario', $this->regla('.boton-secundario'));
        $this->assertStringContainsString('text-error', $this->regla('.boton-peligro'));
        $this->assertStringContainsString('border-borde bg-white', $this->regla('.boton-volver'));
    }

    public function test_la_flecha_de_volver_no_se_lee_en_voz_alta(): void
    {
        $this->assertStringContainsString("content: '←' / '';", $this->css());
    }

    public function test_tarjeta_sin_borde_fino_con_sombra_y_esquinas_de_16_px(): void
    {
        $tarjeta = $this->regla('.tarjeta');

        $this->assertStringContainsString('rounded-2xl bg-white', $tarjeta);
        $this->assertStringContainsString('box-shadow:', $tarjeta);
        $this->assertStringNotContainsString('border', $tarjeta);
    }

    public function test_chips_de_estado_con_texto_oscuro_sobre_fondo_claro(): void
    {
        $this->assertStringContainsString('bg-exito-claro text-exito', $this->regla('.chip-exito'));
        $this->assertStringContainsString('bg-amber-100 text-amber-700', $this->regla('.chip-aviso'));
        $this->assertStringContainsString('bg-error-claro text-error', $this->regla('.chip-error'));
        $this->assertStringContainsString('bg-primario-claro text-primario', $this->regla('.chip-primario'));
    }

    public function test_titulos_con_etiqueta_titulo_y_subtitulo(): void
    {
        $this->assertStringContainsString('uppercase', $this->regla('.etiqueta'));
        $this->assertStringContainsString('font-titulos', $this->regla('.titulo-pagina'));
        $this->assertStringContainsString('text-texto-suave', $this->regla('.subtitulo'));
    }

    public function test_los_tonos_nuevos_estan_en_el_tema(): void
    {
        $css = $this->css();
        $this->assertStringContainsString('--color-fondo-suave: #F5F8FD;', $css);
        $this->assertStringContainsString('--color-primario-suave: #D3E2F7;', $css);
    }
}
