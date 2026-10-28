<?php

namespace App\Http\Controllers\Profesor;

use App\Http\Controllers\Controller;

/**
 * HU08 (#54): «Mis materias» en el panel del profesor (GET /profesor/materias → profesor.materias).
 * Muestra solo las asignaciones del profesor que inició sesión: asignaciones() de #13 ya filtra por
 * profesor_id y carga la sede del curso y la materia; acá se suma el plan del curso.
 */
class MisMateriasController extends Controller
{
    public function __invoke()
    {
        $asignaciones = auth()->user()->asignaciones()
            ->with('curso.planEstudio')
            ->get()
            ->sortBy(fn ($a) => [$a->curso->planEstudio->nombre, $a->materia->nombre])
            ->values();

        return view('profesor.materias', ['asignaciones' => $asignaciones]);
    }

    /** «Lunes a viernes · 14:00 a 18:00» (omite lo que no esté cargado) */
    public static function horario(object $curso): string
    {
        $partes = array_filter([
            $curso->dias,
            $curso->hora_inicio && $curso->hora_fin
                ? substr($curso->hora_inicio, 0, 5).' a '.substr($curso->hora_fin, 0, 5)
                : null,
        ]);

        return $partes ? implode(' · ', $partes) : 'A confirmar';
    }
}
