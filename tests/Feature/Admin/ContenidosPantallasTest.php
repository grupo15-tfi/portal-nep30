<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Calendario\Index as Calendario;
use App\Livewire\Admin\Contenidos\Index as Contenidos;
use App\Livewire\Admin\Noticias\Index as Noticias;
use App\Models\Evento;
use App\Models\Noticia;
use App\Models\Seccion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HU10 (#75): pantallas del administrador para noticias, contenidos y calendario,
 * y la imagen y el video de YouTube en el sitio público. Las pantallas no validan ni
 * guardan por su cuenta: llaman a las acciones de #74.
 */
class ContenidosPantallasTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const VIDEO = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

    private const EMBED = 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->conRol('administrador')->create();
    }

    // ---------------- Acceso y menú ----------------

    public function test_el_administrador_ve_las_tres_pantallas_y_el_menu(): void
    {
        foreach (['admin.noticias' => 'Noticias', 'admin.contenidos' => 'Contenidos del sitio', 'admin.calendario' => 'Calendario académico'] as $ruta => $titulo) {
            $this->actingAs($this->admin)->get(route($ruta))->assertOk()->assertSee($titulo);
        }

        $html = $this->actingAs($this->admin)->get(route('admin.noticias'))->getContent();
        foreach (['admin.noticias', 'admin.contenidos', 'admin.calendario'] as $ruta) {
            $this->assertStringContainsString('href="'.route($ruta).'"', $html);
        }
    }

    public function test_un_profesor_recibe_403(): void
    {
        $profesor = User::factory()->conRol('profesor')->create();

        foreach (['admin.noticias', 'admin.contenidos', 'admin.calendario'] as $ruta) {
            $this->actingAs($profesor)->get(route($ruta))->assertForbidden();
        }
    }

    // ---------------- Noticias ----------------

    public function test_nueva_noticia_publicada_cierra_la_ventana_y_se_ve_en_el_sitio(): void
    {
        Livewire::actingAs($this->admin)->test(Noticias::class)
            ->assertDontSeeHtml('role="dialog"')
            ->call('nueva')
            ->assertSeeHtml('aria-labelledby="titulo-noticia"')
            ->set('titulo', 'Muestra de fin de año de Peluquería')
            ->set('resumen', 'Los alumnos muestran sus trabajos.')
            ->set('cuerpo', 'Invitamos a toda la comunidad.')
            ->set('video_url', self::VIDEO)
            ->call('guardar', true)
            ->assertHasNoErrors()
            ->assertSet('ventanaAbierta', false)
            ->assertSee('Noticia publicada');

        $noticia = Noticia::where('titulo', 'Muestra de fin de año de Peluquería')->firstOrFail();
        $this->assertSame('publicada', $noticia->estado);

        $this->get(route('noticias.index'))->assertSee('Muestra de fin de año de Peluquería');
        $this->get(route('noticias.show', $noticia->slug))
            ->assertSee('data-video-embed="'.self::EMBED.'"', false)
            ->assertSee('Reproducir video');
    }

    public function test_un_borrador_no_aparece_en_el_sitio(): void
    {
        Livewire::actingAs($this->admin)->test(Noticias::class)
            ->call('nueva')
            ->set('titulo', 'Noticia en preparación')
            ->set('cuerpo', 'Texto que todavía no se publica.')
            ->call('guardar', false)
            ->assertHasNoErrors()
            ->assertSee('Borrador guardado');

        $this->assertSame('borrador', Noticia::where('titulo', 'Noticia en preparación')->value('estado'));
        $this->get(route('noticias.index'))->assertDontSee('Noticia en preparación');
    }

    public function test_enlace_que_no_es_de_youtube_da_error_y_la_ventana_sigue_abierta(): void
    {
        Livewire::actingAs($this->admin)->test(Noticias::class)
            ->call('nueva')
            ->set('titulo', 'Noticia con video equivocado')
            ->set('cuerpo', 'Texto.')
            ->set('video_url', 'https://www.facebook.com/video/123')
            ->call('guardar', true)
            ->assertHasErrors(['video_url'])
            ->assertSet('ventanaAbierta', true)
            ->assertSeeHtml('role="alert"');

        $this->assertFalse(Noticia::where('titulo', 'Noticia con video equivocado')->exists());
    }

    public function test_campos_obligatorios_vacios_dan_error_debajo_de_cada_campo(): void
    {
        Livewire::actingAs($this->admin)->test(Noticias::class)
            ->call('nueva')
            ->call('guardar', true)
            ->assertHasErrors(['titulo', 'cuerpo'])
            ->assertSeeHtml('aria-invalid="true"');
    }

    public function test_imagen_valida_se_guarda_y_se_muestra_en_el_sitio(): void
    {
        Livewire::actingAs($this->admin)->test(Noticias::class)
            ->call('nueva')
            ->set('titulo', 'Noticia con foto')
            ->set('cuerpo', 'Texto.')
            ->set('imagen', UploadedFile::fake()->image('foto.jpg', 800, 450))
            ->assertSeeHtml('alt="Vista previa de la imagen"')
            ->call('guardar', true)
            ->assertHasNoErrors();

        $noticia = Noticia::where('titulo', 'Noticia con foto')->firstOrFail();
        $this->assertNotNull($noticia->imagen_ruta);
        Storage::disk('public')->assertExists($noticia->imagen_ruta);
        $this->get(route('noticias.show', $noticia->slug))->assertSee('storage/'.$noticia->imagen_ruta, false);
    }

    public function test_imagen_de_tipo_no_permitido_da_error(): void
    {
        Livewire::actingAs($this->admin)->test(Noticias::class)
            ->call('nueva')
            ->set('titulo', 'Noticia con PDF')
            ->set('cuerpo', 'Texto.')
            ->set('imagen', UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'))
            ->call('guardar', true)
            ->assertHasErrors(['imagen'])
            ->assertSet('ventanaAbierta', true);
    }

    public function test_editar_carga_los_datos_y_despublicar_y_eliminar_funcionan(): void
    {
        $noticia = Noticia::where('estado', 'publicada')->firstOrFail();

        $componente = Livewire::actingAs($this->admin)->test(Noticias::class)
            ->call('editar', $noticia->id)
            ->assertSet('titulo', $noticia->titulo)
            ->assertSee('Editar noticia')
            ->assertSee('Pasar a borrador')
            ->call('cerrar')
            ->call('cambiarEstado', $noticia->id)
            ->assertSee('volvió a borrador');

        $this->assertSame('borrador', $noticia->fresh()->estado);

        $componente->call('cambiarEstado', $noticia->id);
        $this->assertSame('publicada', $noticia->fresh()->estado);

        $componente->call('eliminar', $noticia->id)->assertSee('Se eliminó la noticia');
        $this->assertNull($noticia->fresh());
    }

    public function test_buscador_y_filtro_por_estado(): void
    {
        $publicada = Noticia::where('estado', 'publicada')->firstOrFail();

        Livewire::actingAs($this->admin)->test(Noticias::class)
            ->call('nueva')->set('titulo', 'Borrador de prueba para el filtro')->set('cuerpo', 'Texto.')->call('guardar', false)
            ->set('estado', 'borrador')
            ->assertSee('Borrador de prueba para el filtro')
            ->assertDontSee($publicada->titulo)
            ->set('estado', '')
            ->set('buscar', 'filtro')
            ->assertSee('Borrador de prueba para el filtro')
            ->assertDontSee($publicada->titulo);
    }

    // ---------------- Contenidos ----------------

    public function test_editar_una_seccion_con_video_se_ve_en_el_sitio(): void
    {
        $historia = Seccion::where('clave', 'historia')->firstOrFail();

        Livewire::actingAs($this->admin)->test(Contenidos::class)
            ->assertSee('Texto de la portada (Inicio)')
            ->call('elegir', $historia->id)
            ->assertSet('titulo', $historia->titulo)
            ->set('titulo', 'Nuestra historia')
            ->set('contenido', 'La escuela nació en el barrio Luján.')
            ->set('video_url', 'https://youtu.be/dQw4w9WgXcQ')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSee('Se guardó «Historia»');

        $this->assertSame($this->admin->id, $historia->fresh()->actualizado_por);

        $this->get(route('historia'))
            ->assertSee('La escuela nació en el barrio Luján.')
            ->assertSee('data-video-embed="'.self::EMBED.'"', false);
    }

    public function test_seccion_con_errores_los_muestra_y_no_guarda(): void
    {
        $contacto = Seccion::where('clave', 'contacto')->firstOrFail();

        Livewire::actingAs($this->admin)->test(Contenidos::class)
            ->call('elegir', $contacto->id)
            ->set('titulo', '')
            ->set('video_url', 'https://vimeo.com/123')
            ->call('guardar')
            ->assertHasErrors(['titulo', 'video_url']);

        $this->assertSame($contacto->titulo, $contacto->fresh()->titulo);
    }

    public function test_la_imagen_y_el_video_de_la_portada_se_ven_en_el_inicio(): void
    {
        $inicio = Seccion::where('clave', 'inicio')->firstOrFail();

        Livewire::actingAs($this->admin)->test(Contenidos::class)
            ->call('elegir', $inicio->id)
            ->set('imagen', UploadedFile::fake()->image('sede.png', 640, 400))
            ->set('video_url', self::VIDEO)
            ->call('guardar')
            ->assertHasNoErrors();

        $inicio->refresh();
        $this->get(route('inicio'))
            ->assertSee('storage/'.$inicio->imagen_ruta, false)
            ->assertSee('data-video-embed="'.self::EMBED.'"', false);
    }

    // ---------------- Calendario ----------------

    public function test_alta_de_evento_publico_aparece_en_el_calendario_del_sitio(): void
    {
        Livewire::actingAs($this->admin)->test(Calendario::class)
            ->set('titulo', 'Acto de colación')
            ->set('tipo', 'institucional')
            ->set('fecha_inicio', '2026-12-04')
            ->set('publico', true)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('mes', '2026-12')
            ->assertSee('Acto de colación')
            ->assertSee('Se ve en el calendario del sitio');

        $this->get(route('calendario', ['mes' => '2026-12']))->assertSee('Acto de colación');
    }

    public function test_evento_solo_campus_no_se_ve_en_el_sitio(): void
    {
        Livewire::actingAs($this->admin)->test(Calendario::class)
            ->set('titulo', 'Reunión de profesores')
            ->set('tipo', 'otro')
            ->set('fecha_inicio', '2026-11-10')
            ->set('publico', false)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSee('Solo campus');

        $this->get(route('calendario', ['mes' => '2026-11']))->assertDontSee('Reunión de profesores');
    }

    public function test_el_desplegable_de_tipos_usa_la_lista_de_guardar_evento(): void
    {
        $componente = Livewire::actingAs($this->admin)->test(Calendario::class);

        foreach (\App\Actions\Contenidos\GuardarEvento::TIPOS as $tipo) {
            $componente->assertSeeHtml('<option value="'.$tipo.'">');
        }
        $this->assertSame(\App\Actions\Contenidos\GuardarEvento::TIPOS, array_keys(Calendario::tipos()));
    }

    public function test_fecha_de_fin_anterior_al_inicio_da_error(): void
    {
        Livewire::actingAs($this->admin)->test(Calendario::class)
            ->set('titulo', 'Evento mal cargado')
            ->set('fecha_inicio', '2026-11-20')
            ->set('fecha_fin', '2026-11-10')
            ->call('guardar')
            ->assertHasErrors(['fecha_fin']);

        $this->assertFalse(Evento::where('titulo', 'Evento mal cargado')->exists());
    }

    public function test_editar_y_eliminar_un_evento_y_navegar_los_meses(): void
    {
        $evento = Evento::whereNull('curso_id')->firstOrFail();
        $mes = $evento->fecha_inicio->format('Y-m');

        Livewire::withQueryParams(['mes' => $mes])->actingAs($this->admin)->test(Calendario::class)
            ->assertSee($evento->titulo)
            ->call('editar', $evento->id)
            ->assertSet('titulo', $evento->titulo)
            ->assertSee('Editar evento')
            ->set('titulo', 'Inicio de clases (cambiado)')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSee('Inicio de clases (cambiado)')
            ->assertSeeHtml('wire:key="evento-'.$evento->id.'"')
            ->call('mesSiguiente')
            ->assertDontSeeHtml('wire:key="evento-'.$evento->id.'"')   // el aviso sí lo nombra; la lista no
            ->call('mesAnterior')
            ->assertSeeHtml('wire:key="evento-'.$evento->id.'"')
            ->call('eliminar', $evento->id)
            ->assertSee('Se eliminó');

        $this->assertNull($evento->fresh());
    }
}
