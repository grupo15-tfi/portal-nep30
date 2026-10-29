<?php

namespace Tests\Feature\Admin;
use App\Actions\Contenidos\CambiarEstadoNoticia;
use App\Actions\Contenidos\EliminarEvento;
use App\Actions\Contenidos\EliminarNoticia;
use App\Actions\Contenidos\GuardarEvento;
use App\Actions\Contenidos\GuardarNoticia;
use App\Actions\Contenidos\GuardarSeccion;
use App\Models\Evento;
use App\Models\Noticia;
use App\Models\Seccion;
use App\Models\User;
use App\Support\YouTube;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * HU10 (#74): acciones de noticias, secciones y eventos (RF19).
 * Storage::fake('public'): las imágenes se guardan en un disco de mentira que se borra solo.
 */
class ContenidosAccionesTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const VIDEO = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actingAs(User::role('administrador')->firstOrFail());
    }

    private function noticia(array $cambios = []): array
    {
        return ['titulo' => 'Abrió la inscripción a Peluquería', 'resumen' => 'Resumen de prueba.',
            'cuerpo' => 'Texto de prueba de la noticia.', 'publicar' => true, ...$cambios];
    }

    /** Ejecuta algo que tiene que fallar y devuelve los errores por campo */
    private function errores(callable $accion): array
    {
        try {
            $accion();
        } catch (ValidationException $e) {
            return $e->errors();
        }
        $this->fail('Se esperaba un error de validación.');
    }

    // ---------- YouTube ----------

    public function test_reconoce_los_enlaces_de_youtube_y_rechaza_los_demas(): void
    {
        foreach ([
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://youtube.com/watch?v=dQw4w9WgXcQ&t=30s',
            'https://youtu.be/dQw4w9WgXcQ',
            'https://www.youtube.com/shorts/dQw4w9WgXcQ',
            'https://m.youtube.com/watch?v=dQw4w9WgXcQ',
        ] as $url) {
            $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', YouTube::embed($url), $url);
        }

        foreach (['https://www.facebook.com/video/123', 'https://vimeo.com/123456', 'https://youtube.com.malo.com/watch?v=dQw4w9WgXcQ', 'hola'] as $url) {
            $this->assertNull(YouTube::embed($url), $url);
        }
    }

    // ---------- Noticias ----------

    public function test_una_noticia_publicada_se_ve_en_el_sitio_y_un_borrador_no(): void
    {
        $publicada = GuardarNoticia::ejecutar($this->noticia());
        $borrador = GuardarNoticia::ejecutar($this->noticia(['titulo' => 'Borrador de prueba', 'publicar' => false]));

        $this->assertSame('publicada', $publicada->estado);
        $this->assertNotNull($publicada->publicada_en);
        $this->assertSame('borrador', $borrador->estado);

        $publicas = Noticia::publicadas()->pluck('id');
        $this->assertContains($publicada->id, $publicas);
        $this->assertNotContains($borrador->id, $publicas);

        $this->get('/')->assertSee('Abrió la inscripción a Peluquería')->assertDontSee('Borrador de prueba');
    }

    public function test_el_slug_se_arma_solo_es_unico_y_no_cambia_al_editar(): void
    {
        $una = GuardarNoticia::ejecutar($this->noticia(['titulo' => '¡Abrió la preinscripción 2026!']));
        $otra = GuardarNoticia::ejecutar($this->noticia(['titulo' => '¡Abrió la preinscripción 2026!']));

        // La de prueba del seeder ya usa «abrio-la-preinscripcion-2026»
        $this->assertSame('abrio-la-preinscripcion-2026-2', $una->slug);
        $this->assertSame('abrio-la-preinscripcion-2026-3', $otra->slug);

        $editada = GuardarNoticia::ejecutar($this->noticia(['titulo' => 'Título nuevo']), null, $una);
        $this->assertSame('abrio-la-preinscripcion-2026-2', $editada->slug);
    }

    public function test_guarda_la_imagen_y_al_reemplazarla_o_quitarla_borra_la_anterior(): void
    {
        $noticia = GuardarNoticia::ejecutar($this->noticia(), UploadedFile::fake()->image('foto.jpg'));
        $primera = $noticia->imagen_ruta;
        Storage::disk('public')->assertExists($primera);
        $this->assertStringStartsWith('noticias/', $primera);

        $noticia = GuardarNoticia::ejecutar($this->noticia(), UploadedFile::fake()->image('otra.png'), $noticia);
        Storage::disk('public')->assertMissing($primera);
        Storage::disk('public')->assertExists($noticia->imagen_ruta);

        $segunda = $noticia->imagen_ruta;
        $noticia = GuardarNoticia::ejecutar($this->noticia(['quitar_imagen' => true]), null, $noticia);
        $this->assertNull($noticia->imagen_ruta);
        Storage::disk('public')->assertMissing($segunda);
    }

    public function test_imagen_de_tipo_o_tamano_no_permitido_da_error(): void
    {
        $pdf = $this->errores(fn () => GuardarNoticia::ejecutar($this->noticia(), UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf')));
        $this->assertSame(['La imagen tiene que ser JPG, PNG o WEBP.'], $pdf['imagen']);

        $grande = $this->errores(fn () => GuardarNoticia::ejecutar($this->noticia(), UploadedFile::fake()->image('enorme.jpg')->size(3000)));
        $this->assertSame(['La imagen no puede pesar más de 2 MB.'], $grande['imagen']);

        $this->assertDatabaseMissing('noticias', ['titulo' => 'Abrió la inscripción a Peluquería']);
    }

    public function test_solo_acepta_videos_de_youtube(): void
    {
        $errores = $this->errores(fn () => GuardarNoticia::ejecutar($this->noticia(['video_url' => 'https://www.facebook.com/video/123'])));
        $this->assertArrayHasKey('video_url', $errores);

        $noticia = GuardarNoticia::ejecutar($this->noticia(['video_url' => self::VIDEO]));
        $this->assertSame(self::VIDEO, $noticia->video_url);
    }

    public function test_publicar_despublicar_y_eliminar(): void
    {
        $noticia = GuardarNoticia::ejecutar($this->noticia(['publicar' => false]), UploadedFile::fake()->image('foto.jpg'));
        $ruta = $noticia->imagen_ruta;

        CambiarEstadoNoticia::ejecutar($noticia, true);
        $this->assertSame('publicada', $noticia->estado);
        $fecha = $noticia->publicada_en;

        CambiarEstadoNoticia::ejecutar($noticia, false);
        CambiarEstadoNoticia::ejecutar($noticia, true);
        $this->assertEquals($fecha, $noticia->fresh()->publicada_en); // conserva la fecha de la primera publicación

        EliminarNoticia::ejecutar($noticia);
        $this->assertDatabaseMissing('noticias', ['id' => $noticia->id]);
        Storage::disk('public')->assertMissing($ruta);
    }

    // ---------- Secciones ----------

    public function test_editar_una_seccion_cambia_el_sitio_y_registra_quien(): void
    {
        $inicio = Seccion::where('clave', 'inicio')->firstOrFail();

        GuardarSeccion::ejecutar($inicio, [
            'titulo' => 'Título de portada de prueba', 'contenido' => 'Texto nuevo.', 'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
        ], UploadedFile::fake()->image('portada.webp'));

        $inicio->refresh();
        $this->assertSame(auth()->id(), $inicio->actualizado_por);
        $this->assertStringStartsWith('secciones/', $inicio->imagen_ruta);
        $this->assertSame('https://youtu.be/dQw4w9WgXcQ', $inicio->video_url);
        $this->get('/')->assertSee('Título de portada de prueba');
    }

    public function test_una_seccion_sin_titulo_o_con_video_que_no_es_de_youtube_da_error(): void
    {
        $historia = Seccion::where('clave', 'historia')->firstOrFail();

        $errores = $this->errores(fn () => GuardarSeccion::ejecutar($historia, ['titulo' => '', 'contenido' => 'x', 'video_url' => 'https://vimeo.com/1']));

        $this->assertArrayHasKey('titulo', $errores);
        $this->assertArrayHasKey('video_url', $errores);
    }

    // ---------- Eventos ----------

    public function test_un_evento_publico_aparece_en_el_calendario_y_uno_privado_no(): void
    {
        GuardarEvento::ejecutar(['titulo' => 'Acto de colación', 'tipo' => 'institucional',
            'fecha_inicio' => '2026-12-04 18:00', 'publico' => true]);
        GuardarEvento::ejecutar(['titulo' => 'Reunión de profesores', 'tipo' => 'institucional',
            'fecha_inicio' => '2026-12-05 18:00', 'publico' => false]);

        $this->assertContains('Acto de colación', Evento::publicos()->pluck('titulo'));
        $this->assertNotContains('Reunión de profesores', Evento::publicos()->pluck('titulo'));

        $this->get('/calendario?mes=2026-12')->assertSee('Acto de colación')->assertDontSee('Reunión de profesores');
    }

    public function test_un_evento_con_fin_antes_del_inicio_o_tipo_invalido_da_error(): void
    {
        $errores = $this->errores(fn () => GuardarEvento::ejecutar([
            'titulo' => 'Mal cargado', 'tipo' => 'fiesta', 'fecha_inicio' => '2026-12-04', 'fecha_fin' => '2026-12-01',
        ]));

        $this->assertArrayHasKey('tipo', $errores);
        $this->assertArrayHasKey('fecha_fin', $errores);
    }

    public function test_editar_y_eliminar_un_evento(): void
    {
        $evento = GuardarEvento::ejecutar(['titulo' => 'Feriado de prueba', 'tipo' => 'feriado', 'fecha_inicio' => '2026-11-20', 'publico' => true]);
        $evento = GuardarEvento::ejecutar(['titulo' => 'Feriado corregido', 'tipo' => 'feriado', 'fecha_inicio' => '2026-11-23', 'publico' => true], $evento);

        $this->assertSame('Feriado corregido', $evento->fresh()->titulo);
        $this->assertNull($evento->curso_id);

        EliminarEvento::ejecutar($evento);
        $this->assertDatabaseMissing('eventos', ['id' => $evento->id]);
    }
}