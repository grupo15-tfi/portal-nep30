<?php

namespace App\Http\Controllers;

use App\Models\Noticia;
use App\Models\Seccion;

/**
 * HU01 (#11): sitio público institucional. No requiere iniciar sesión.
 * Los textos salen de la tabla secciones y las noticias de la tabla noticias.
 */
class SitioController extends Controller
{
    public function inicio()
    {
        return view('publico.inicio', [
            'seccion'  => Seccion::where('clave', 'inicio')->firstOrFail(),
            'noticias' => Noticia::publicadas()->limit(3)->get(),
        ]);
    }

    /** Historia, Contacto y Política de privacidad: misma vista, distinto texto */
    public function seccion(string $clave)
    {
        return view('publico.seccion', [
            'seccion' => Seccion::where('clave', $clave)->firstOrFail(),
        ]);
    }

    public function noticias()
    {
        return view('publico.noticias.index', [
            'noticias' => Noticia::publicadas()->paginate(10),
        ]);
    }

    /** Detalle por slug. Un borrador o una noticia con fecha futura da 404. */
    public function noticia(string $slug)
    {
        return view('publico.noticias.show', [
            'noticia' => Noticia::publicadas()->where('slug', $slug)->firstOrFail(),
        ]);
    }
}
