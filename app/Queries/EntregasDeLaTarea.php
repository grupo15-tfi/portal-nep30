<?php

namespace App\Queries;

use App\Models\Inscripcion;
use App\Models\Tarea;
use Illuminate\Support\Collection;

/**
 * HU12 (#119): quién entregó y quién no, una fila por alumno activo del curso de la tarea.
 * Contrato (#117): de(Tarea $tarea): Collection. Cada fila tiene:
 *   inscripcion_id, apellido, nombre, entrega_id (null si no entregó), fecha_entrega, fuera_de_termino, devuelta.
 * Ordenadas por apellido y nombre. La usa «Tareas» del profesor (#120).
 */
class EntregasDeLaTarea
{
    public static function de(Tarea $tarea): Collection
    {
        $entregas = $tarea->entregas()->get()->keyBy('inscripcion_id');

        return Inscripcion::activas()
            ->where('curso_id', $tarea->cursoProfesor->curso_id)
            ->with('alumno')
            ->get()
            ->sortBy(fn (Inscripcion $i) => mb_strtolower($i->alumno->apellido.'|'.$i->alumno->nombre))
            ->map(function (Inscripcion $i) use ($entregas) {
                $e = $entregas->get($i->id);

                return [
                    'inscripcion_id'   => $i->id,
                    'apellido'         => $i->alumno->apellido,
                    'nombre'           => $i->alumno->nombre,
                    'entrega_id'       => $e?->id,
                    'fecha_entrega'    => $e?->fecha_entrega,
                    'fuera_de_termino' => (bool) $e?->fuera_de_termino,
                    'devuelta'         => (bool) $e?->devuelta_en,
                ];
            })
            ->values();
    }
}