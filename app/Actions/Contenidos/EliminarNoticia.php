<?php

namespace App\Actions\Contenidos;

use App\Models\Noticia;

/** HU10 (#74): elimina la noticia y su imagen */
class EliminarNoticia
{
    public static function ejecutar(Noticia $noticia): void
    {
        Archivos::borrar($noticia->imagen_ruta);
        $noticia->delete();
    }
}