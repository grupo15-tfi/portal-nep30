<?php

namespace App\Actions\Contenidos;
   use Illuminate\Support\Facades\Auth;
use App\Models\Noticia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * HU10 (#74): alta y edición de noticias.
 * Contrato (#47): ejecutar(array $datos, ?UploadedFile $imagen = null, ?Noticia $noticia = null): Noticia
 *   $datos: titulo, resumen?, cuerpo, video_url?, publicar (bool), quitar_imagen?
 *   Errores: ValidationException con esas claves y «imagen».
 */
class GuardarNoticia
{
    public static function ejecutar(array $datos, ?UploadedFile $imagen = null, ?Noticia $noticia = null): Noticia
    {
        $validos = Validator::make($datos, [
            'titulo'        => ['required', 'string', 'max:200'],
            'resumen'       => ['nullable', 'string', 'max:400'],
            'cuerpo'        => ['required', 'string'],
            'video_url'     => Archivos::reglaVideo(),
            'publicar'      => ['boolean'],
            'quitar_imagen' => ['boolean'],
        ], [
            'titulo.required' => 'Escribí el título.',
            'titulo.max'      => 'El título puede tener hasta 200 caracteres.',
            'resumen.max'     => 'El resumen puede tener hasta 400 caracteres.',
            'cuerpo.required' => 'Escribí el texto de la noticia.',
        ])->validate();

        Archivos::validarImagen($imagen);

        $noticia ??= new Noticia(['autor_id' => Auth::id()]);
        $publicar = (bool) ($validos['publicar'] ?? false);

        $noticia->fill([
            'titulo'      => $validos['titulo'],
            'resumen'     => $validos['resumen'] ?? null,
            'cuerpo'      => $validos['cuerpo'],
            'video_url'   => $validos['video_url'] ?? null,
            'estado'      => $publicar ? 'publicada' : 'borrador',
            'imagen_ruta' => Archivos::imagen($imagen, $noticia->imagen_ruta, (bool) ($validos['quitar_imagen'] ?? false), 'noticias'),
        ]);

        // El slug se arma una sola vez: si se cambia el título, el enlace de la noticia no cambia
        $noticia->slug ??= self::slugUnico($validos['titulo']);

        // Primera vez que se publica: fecha de hoy. Si ya tenía fecha, la conserva.
        if ($publicar) {
            $noticia->publicada_en ??= now();
        }

        $noticia->save();

        return $noticia;
    }

    /** «¡Abrió la preinscripción 2026!» → «abrio-la-preinscripcion-2026» (y -2, -3… si ya existe) */
    public static function slugUnico(string $titulo): string
    {
        $base = Str::limit(Str::slug($titulo), 200, '') ?: 'noticia';
        $base = trim($base, '-');
        $slug = $base;

        for ($n = 2; Noticia::where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }
}