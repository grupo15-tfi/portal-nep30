<?php

namespace Tests\Feature\Publico;

use App\Models\Noticia;
use App\Models\Seccion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HU01 (#11): sitio público institucional y noticias (RF01, RF02, RF08).
 * Ojo: con $seed = true ya existe UNA noticia publicada del DatosDePruebaSeeder
 * («Abrió la preinscripción 2026», publicada ahora). Las pruebas cuentan con ella: no se borra.
 */
class SitioTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function noticia(array $datos = []): Noticia
    {
        static $n = 0;
        $n++;

        return Noticia::create(array_merge([
            'titulo'       => "Noticia de prueba {$n}",
            'slug'         => "noticia-de-prueba-{$n}",  // solo minúsculas, números y guiones (CHECK de la base)
            'cuerpo'       => 'Texto de la noticia.',
            'estado'       => 'publicada',
            'publicada_en' => now()->subDays($n),
            'autor_id'     => User::role('administrador')->value('id'),
        ], $datos));
    }

    public function test_las_paginas_publicas_responden_sin_iniciar_sesion(): void
    {
        foreach (['/', '/historia', '/contacto', '/privacidad', '/noticias', '/noticias/abrio-la-preinscripcion-2026'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->assertGuest();
    }

    public function test_los_textos_salen_de_la_base(): void
    {
        Seccion::where('clave', 'historia')->update(['contenido' => 'Texto de historia cargado en la base']);

        $this->get('/historia')->assertSee('Texto de historia cargado en la base');
    }

    public function test_un_borrador_no_aparece_y_su_detalle_da_404(): void
    {
        $this->noticia(['titulo' => 'Borrador secreto', 'slug' => 'borrador-secreto', 'estado' => 'borrador', 'publicada_en' => null]);

        $this->get('/noticias')->assertDontSee('Borrador secreto');
        $this->get('/noticias/borrador-secreto')->assertNotFound();
    }

    public function test_una_noticia_con_fecha_futura_todavia_no_se_ve(): void
    {
        $this->noticia(['titulo' => 'Noticia programada', 'slug' => 'noticia-programada', 'publicada_en' => now()->addDay()]);

        $this->get('/noticias')->assertDontSee('Noticia programada');
        $this->get('/noticias/noticia-programada')->assertNotFound();
    }

    public function test_noticias_de_a_10_y_de_la_mas_reciente_a_la_mas_antigua(): void
    {
        foreach (range(1, 15) as $i) {
            $this->noticia(['titulo' => "Nota numero {$i}", 'slug' => "nota-numero-{$i}", 'publicada_en' => now()->subDays($i)]);
        }

        $pagina1 = $this->get('/noticias')->assertOk();
        $this->assertCount(10, $pagina1->viewData('noticias')->items());

        // Nota 1 es la más nueva de las creadas; la del seeder (publicada ahora) sale antes que todas
        $pagina1->assertSeeInOrder(['Abrió la preinscripción 2026', 'Nota numero 1', 'Nota numero 2', 'Nota numero 3']);

        $this->get('/noticias?page=2')->assertOk()->assertSee('Nota numero 15');
    }

    public function test_el_pie_tiene_el_enlace_a_la_politica_de_privacidad(): void
    {
        $this->get('/')->assertSee(route('privacidad'), false);
    }

    public function test_el_menu_tiene_ingresar_y_la_pagina_esta_en_espanol(): void
    {
        $this->get('/')
            ->assertSee('<html lang="es">', false)
            ->assertSee(route('login'), false);
    }
}
