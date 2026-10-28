<?php

namespace App\Http\Controllers;

use App\Models\Noticia;
use App\Models\Seccion;
use Illuminate\Support\Facades\DB;

/**
 * HU01 (#11): sitio público institucional. No requiere iniciar sesión.
 * Los textos salen de la tabla secciones y las noticias de la tabla noticias.
 */
class SitioController extends Controller
{
    public function inicio()
    {
        // #60: cursos vigentes de la oferta (HU02). La vista v_oferta_publica ya filtra lo inactivo.
        $oferta = DB::table('v_oferta_publica')->orderBy('plan')->orderByRaw("FIELD(turno, 'mañana', 'tarde', 'noche')")->get();

        return view('publico.inicio', [
            'seccion'      => Seccion::where('clave', 'inicio')->firstOrFail(),
            'noticias'     => Noticia::publicadas()->limit(3)->get(),
            // Hasta 4 cursos de formación profesional, uno por plan (el primero de cada plan)
            'cursos'       => $oferta->where('tipo', 'formacion_profesional')->unique('plan')->take(4)->values(),
            // Planes de terminalidad primaria, para la franja de abajo
            'terminalidad' => $oferta->where('tipo', 'terminalidad')->pluck('plan')->unique()->values(),
            'anio'         => $oferta->first()?->anio ?? now()->year,
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
