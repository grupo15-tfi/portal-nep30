<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sugerencia de José (revisión de #55): el menú público marca la sección actual,
 * en el escritorio y en el celular, con aria-current="page" para los lectores de pantalla.
 */
class MenuActivoTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    /** Devuelve los textos de los enlaces del menú marcados como actuales */
    private function marcados(string $url): array
    {
        $html = $this->get($url)->assertOk()->getContent();
        preg_match_all('/<a[^>]*aria-current="page"[^>]*>([^<]+)<\/a>/', $html, $m);

        return array_map('trim', $m[1]);
    }

    public function test_la_oferta_el_detalle_de_un_curso_y_el_calendario_marcan_oferta_educativa(): void
    {
        // Una vez en el menú de escritorio y otra en el del celular
        $this->assertSame(['Oferta educativa', 'Oferta educativa'], $this->marcados(route('oferta')));
        $this->assertSame(['Oferta educativa', 'Oferta educativa'], $this->marcados(route('calendario')));

        $curso = DB::table('v_oferta_publica')->value('curso_id');
        $this->assertNotNull($curso, 'Los datos de prueba deberían tener al menos un curso en la oferta');
        $this->assertSame(['Oferta educativa', 'Oferta educativa'], $this->marcados(route('oferta.curso', $curso)));
    }

    public function test_cada_seccion_marca_solo_su_enlace(): void
    {
        $this->assertSame(['Inicio', 'Inicio'], $this->marcados(route('inicio')));
        $this->assertSame(['Historia', 'Historia'], $this->marcados(route('historia')));
        $this->assertSame(['Noticias', 'Noticias'], $this->marcados(route('noticias.index')));
        $this->assertSame(['Contacto', 'Contacto'], $this->marcados(route('contacto')));
    }

    public function test_una_pagina_fuera_del_menu_no_marca_ningun_enlace(): void
    {
        $this->assertSame([], $this->marcados(route('privacidad')));
    }
}
