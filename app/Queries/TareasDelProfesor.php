<?php

namespace App\Queries;

use App\Models\Inscripcion;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * HU12 (#119): las tareas que publicó un profesor, una fila por tarea.
 * Contrato (#117): de(User $profesor): Collection. Cada fila tiene:
 *   tarea_id, titulo, materia, curso («Plan · Sede»), curso_profesor_id, fecha_entrega,
 *   entregadas (de alumnos activos) y alumnos (activos del curso).
 * Solo las tareas de sus asignaciones, ordenadas por fecha de entrega. La usa «Tareas» del profesor (#120).
 */
class TareasDelProfesor
{
    public static function de(User $profesor): Collection
    {
        $tareas = Tarea::query()
            ->whereHas('cursoProfesor', fn ($q) => $q->where('profesor_id', $profesor->id))
            ->with(['cursoProfesor.materia', 'cursoProfesor.curso.planEstudio', 'cursoProfesor.curso.sede'])
            ->withCount(['entregas as entregadas' => fn ($q) => $q->whereHas('inscripcion', fn ($i) => $i->activas())])
            ->orderBy('fecha_entrega')->orderBy('id')
            ->get();

        // curso_id => cantidad de alumnos activos, de una sola consulta
        $alumnos = Inscripcion::activas()
            ->whereIn('curso_id', $tareas->pluck('cursoProfesor.curso_id')->unique())
            ->selectRaw('curso_id, count(*) as total')->groupBy('curso_id')
            ->pluck('total', 'curso_id');

        return $tareas->map(fn (Tarea $t) => [
            'tarea_id'          => $t->id,
            'titulo'            => $t->titulo,
            'materia'           => $t->cursoProfesor->materia->nombre,
            'curso'             => TareasDelAlumno::curso($t),
            'curso_profesor_id' => $t->curso_profesor_id,
            'fecha_entrega'     => $t->fecha_entrega,
            'entregadas'        => (int) $t->entregadas,
            'alumnos'           => (int) ($alumnos[$t->cursoProfesor->curso_id] ?? 0),
        ])->values();
    }
}