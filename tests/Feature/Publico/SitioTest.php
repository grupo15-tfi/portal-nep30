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

    // ---------- #32: «Mi panel» cuando hay sesión iniciada ----------

    public function test_un_invitado_ve_ingresar_y_no_mi_panel(): void
    {
        $this->get('/')
            ->assertSee('Ingresar')
            ->assertSee(route('login'), false)
            ->assertDontSee('Mi panel');
    }

    public static function roles(): array
    {
        return [
            'administrador' => ['administrador', 'admin.inicio'],
            'profesor'      => ['profesor', 'profesor.inicio'],
            'alumno'        => ['alumno', 'alumno.inicio'],
            'auditor'       => ['auditor', 'auditor.inicio'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('roles')]
    public function test_con_sesion_iniciada_ve_mi_panel_con_el_enlace_de_su_rol(string $rol, string $ruta): void
    {
        $usuario = User::role($rol)->firstOrFail();

        // En el menú de escritorio y en el del celular: el enlace aparece dos veces
        $respuesta = $this->actingAs($usuario)->get('/')
            ->assertSee('Mi panel')
            ->assertDontSee(route('login'), false);

        $this->assertSame(2, substr_count($respuesta->getContent(), 'href="'.route($ruta).'"'));
    }

    public function test_mi_panel_tambien_aparece_en_las_otras_paginas_publicas(): void
    {
        $alumno = User::role('alumno')->firstOrFail();

        foreach (['historia', 'contacto', 'privacidad', 'noticias.index'] as $ruta) {
            $this->actingAs($alumno)->get(route($ruta))
                ->assertOk()
                ->assertSee('Mi panel')
                ->assertSee(route('alumno.inicio'), false);
        }
    }

    public function test_un_usuario_sin_rol_no_ve_mi_panel(): void
    {
        $sinRol = User::factory()->create();

        $this->actingAs($sinRol)->get('/')
            ->assertOk()
            ->assertDontSee('Mi panel');
    }
    // ---------- #60: Inicio rediseñado, sin el lema ----------

    public function test_la_portada_no_tiene_el_lema_y_lleva_a_la_oferta_y_a_las_sedes(): void
    {
        $this->get('/')->assertOk()
            ->assertDontSee('Tú puedes lograrlo')
            ->assertDontSee('TE AYUDAMOS')
            ->assertSee('Formación profesional y terminalidad primaria en Formosa')
            ->assertSee('Ver oferta educativa')
            ->assertSee('Encontrar mi sede más cercana')
            ->assertSee('Preinscribite en línea');
    }

    public function test_el_inicio_muestra_solo_cursos_vigentes_con_sus_cupos(): void
    {
        // Datos de prueba: Peluquería sin cupo (lista de espera) e Informática con cupos; Alfabetización es terminalidad
        $this->get('/')->assertOk()
            ->assertSee('¿Qué querés estudiar?')
            ->assertSee('Peluquería')
            ->assertSee('Sin cupo: Lista de espera')
            ->assertSee('Operador de Informática para la Administración y Gestión')
            ->assertSee('cupos disponibles')
            ->assertSee('Terminalidad primaria para adultos');

        // Un curso inactivo deja de aparecer
        \App\Models\Curso::where('plan_id', \App\Models\PlanEstudio::where('nombre', 'Peluquería')->value('id'))->update(['estado' => 'inactivo']);
        $this->get('/')->assertDontSee('Peluquería');
    }

    public function test_el_inicio_muestra_hasta_tres_noticias_publicadas_y_un_mensaje_si_no_hay(): void
    {
        $this->noticia(['titulo' => 'Noticia reciente uno']);
        $this->noticia(['titulo' => 'Noticia reciente dos']);
        $this->noticia(['titulo' => 'Noticia en borrador', 'slug' => 'noticia-en-borrador', 'estado' => 'borrador', 'publicada_en' => null]);

        $this->get('/')->assertOk()
            ->assertSee('Últimas noticias')
            ->assertSee('Abrió la preinscripción 2026')
            ->assertSee('Noticia reciente uno')
            ->assertDontSee('Noticia en borrador');

        Noticia::query()->update(['estado' => 'borrador', 'publicada_en' => null]);
        $this->get('/')->assertOk()->assertSee('Todavía no hay noticias publicadas.');
    }
}
