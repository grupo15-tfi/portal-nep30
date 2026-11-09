<?php

namespace App\Actions\Contenidos;

use App\Models\Noticia;
use Illuminate\Validation\ValidationException;

/** HU10 (#74): publicar o despublicar una noticia desde el listado */
class CambiarEstadoNoticia
{
    public static function ejecutar(Noticia $noticia, bool $publicar): Noticia
    {
        // #96: una noticia con imagen o video solo se publica si se confirmó el consentimiento
        if ($publicar && $noticia->tieneImagenOVideo() && ! $noticia->consentimiento_en) {
            throw ValidationException::withMessages([
                'consentimiento' => 'Para publicarla, editá la noticia y confirmá el consentimiento de las personas de la imagen o el video.',
            ]);
        }

        $noticia->estado = $publicar ? 'publicada' : 'borrador';

        if ($publicar) {
            $noticia->publicada_en ??= now();
        }

        $noticia->save();

        return $noticia;
    }
}