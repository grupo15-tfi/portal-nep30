<?php

namespace App\Actions\Contenidos;
use Illuminate\Support\Facades\Auth;
use App\Models\Seccion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

/**
 * HU10 (#74): edita una sección fija del sitio (inicio, historia, contacto, servicios, privacidad).
 * Contrato (#47): ejecutar(Seccion $seccion, array $datos, ?UploadedFile $imagen = null): Seccion
 *   $datos: titulo, contenido, video_url?, quitar_imagen?
 * Las secciones no se crean ni se borran desde el panel: las define SeccionesSeeder.
 */
class GuardarSeccion
{
    public static function ejecutar(Seccion $seccion, array $datos, ?UploadedFile $imagen = null): Seccion
    {
        $validos = Validator::make($datos, [
            'titulo'        => ['required', 'string', 'max:150'],
            'contenido'     => ['required', 'string'],
            'video_url'     => Archivos::reglaVideo(),
            'quitar_imagen' => ['boolean'],
        ], [
            'titulo.required'    => 'Escribí el título.',
            'titulo.max'         => 'El título puede tener hasta 150 caracteres.',
            'contenido.required' => 'Escribí el contenido.',
        ])->validate();

        Archivos::validarImagen($imagen);

        $seccion->update([
            'titulo'          => $validos['titulo'],
            'contenido'       => $validos['contenido'],
            'video_url'       => $validos['video_url'] ?? null,
            'imagen_ruta'     => Archivos::imagen($imagen, $seccion->imagen_ruta, (bool) ($validos['quitar_imagen'] ?? false), 'secciones'),
            'actualizado_por' => Auth::id(),
        ]);

        return $seccion;
    }
}