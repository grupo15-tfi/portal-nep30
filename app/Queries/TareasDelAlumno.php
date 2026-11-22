<?php

namespace App\Queries;

use App\Models\Entrega;
use App\Models\Inscripcion;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * HU12 (#119): las tareas de un alumno, una fila por tarea.
 * Contrato (#117): de(User $alumno): Collection. Cada fila tiene:
 *   tarea_id, titulo, consigna, materia, curso («Plan · Sede»), fecha_entrega, vencida, tiene_archivo
 *   y entrega (null, o id, fecha_entrega, fuera_de_termino, devolucion, devuelta_en).
 * Solo las tareas de los cursos donde el alumno tiene inscripción activa, ordenadas por fecha de entrega.
 * La usa «Tareas» del alumno (#120). Las filas son arreglos; la vista las lee con data_get().
 */
class TareasDelAlumno
{
    public static function de(User $alumno): Collection
    {
        // curso_id => inscripcion_id de las inscripciones activas del alumno
        $inscripciones = Inscripcion::activas()->where('alumno_id', $alumno->id)->pluck('id', 'curso_id');

        if ($inscripciones->isEmpty()) {
            return collect();
        }

        $tareas = Tarea::query()
            ->whereHas('cursoProfesor', fn ($q) => $q->whereIn('curso_id', $inscripciones->keys()))
            ->with(['cursoProfesor.materia', 'cursoProfesor.curso.planEstudio', 'cursoProfesor.curso.sede'])
            ->orderBy('fecha_entrega')->orderBy('id')
            ->get();

        // Las entregas del alumno en esas tareas, de una sola consulta
        $entregas = Entrega::whereIn('tarea_id', $tareas->pluck('id'))
            ->whereIn('inscripcion_id', $inscripciones->values())
            ->get()->keyBy('tarea_id');

        return $tareas->map(function (Tarea $t) use ($entregas) {
            $e = $entregas->get($t->id);

            return [
                'tarea_id'      => $t->id,
                'titulo'        => $t->titulo,
                'consigna'      => $t->consigna,
                'materia'       => $t->cursoProfesor->materia->nombre,
                'curso'         => self::curso($t),
                'fecha_entrega' => $t->fecha_entrega,
                'vencida'       => $t->fecha_entrega->isPast(),
                'tiene_archivo' => (bool) $t->archivo_ruta,
                'entrega'       => $e ? [
                    'id'               => $e->id,
                    'fecha_entrega'    => $e->fecha_entrega,
                    'fuera_de_termino' => $e->fuera_de_termino,
                    'devolucion'       => $e->devolucion,
                    'devuelta_en'      => $e->devuelta_en,
                ] : null,
            ];
        })->values();
    }

    /** «Peluquería · Sede Central» */
    public static function curso(Tarea $t): string
    {
        $curso = $t->cursoProfesor->curso;

        return "{$curso->planEstudio->nombre} · {$curso->sede->nombre}";
    }
}