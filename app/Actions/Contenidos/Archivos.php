<?php

namespace App\Actions\Contenidos;

use App\Support\YouTube;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * HU10 (#74): lo que comparten noticias y secciones: la imagen y el video de YouTube.
 * Las imágenes van al disco «public» (storage/app/public), que se ve en /storage/... (storage:link).
 */
class Archivos
{
    public const MAX_KB = 2048; // 2 MB

    /** Regla del enlace de video: vacío, o un video de YouTube */
    public static function reglaVideo(): array
    {
        return ['nullable', 'string', 'max:255', function (string $campo, mixed $valor, \Closure $falla) {
            if (! YouTube::esValido($valor)) {
                $falla('El enlace tiene que ser de un video de YouTube (youtube.com o youtu.be).');
            }
        }];
    }

    /** Valida la imagen: jpg, png o webp, hasta 2 MB. Error con la clave «imagen». */
    public static function validarImagen(?UploadedFile $imagen): void
    {
        Validator::make(['imagen' => $imagen], [
            'imagen' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_KB],
        ], [
            'imagen.mimes' => 'La imagen tiene que ser JPG, PNG o WEBP.',
            'imagen.max'   => 'La imagen no puede pesar más de 2 MB.',
            'imagen.file'  => 'No se pudo subir la imagen. Probá de nuevo.',
        ])->validate();
    }

    /**
     * Devuelve la ruta que queda guardada:
     *  - si llega una imagen nueva: la guarda y borra la anterior;
     *  - si piden quitarla: borra la anterior y devuelve null;
     *  - si no: deja la que estaba.
     */
    public static function imagen(?UploadedFile $nueva, ?string $anterior, bool $quitar, string $carpeta): ?string
    {
        if ($nueva) {
            $ruta = $nueva->store($carpeta, 'public');
            self::borrar($anterior);

            return $ruta;
        }

        if ($quitar) {
            self::borrar($anterior);

            return null;
        }

        return $anterior;
    }

    public static function borrar(?string $ruta): void
    {
        if ($ruta) {
            Storage::disk('public')->delete($ruta);
        }
    }
}