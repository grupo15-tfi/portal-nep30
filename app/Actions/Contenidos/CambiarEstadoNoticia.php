<?php

namespace App\Actions\Contenidos;

use App\Models\Noticia;

/** HU10 (#74): publicar o despublicar una noticia desde el listado */
class CambiarEstadoNoticia
{
    public static function ejecutar(Noticia $noticia, bool $publicar): Noticia
    {
        $noticia->estado = $publicar ? 'publicada' : 'borrador';

        if ($publicar) {
            $noticia->publicada_en ??= now();
        }

        $noticia->save();

        return $noticia;
    }
}