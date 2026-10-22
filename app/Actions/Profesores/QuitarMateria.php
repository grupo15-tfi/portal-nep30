<?php

namespace App\Actions\Profesores;

use App\Models\CursoProfesor;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

/**
 * HU08 (#13): quita una asignación de materia a un profesor.
 * Contrato: ejecutar(User $profesor, int $cursoId, int $materiaId): void
 * Si la asignación ya tiene clases (asistencia) o tareas cargadas, la base no deja borrarla
 * (claves foráneas fk_clase_cp y fk_tarea_cp): se informa con la clave materia_id.
 */
class QuitarMateria
{
    public static function ejecutar(User $profesor, int $cursoId, int $materiaId): void
    {
        $asignacion = CursoProfesor::where('profesor_id', $profesor->id)
            ->where('curso_id', $cursoId)
            ->where('materia_id', $materiaId)
            ->first();

        if (! $asignacion) {
            throw ValidationException::withMessages(['materia_id' => 'Esa asignación no existe.']);
        }

        try {
            $asignacion->delete();
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) === 1451) {   // fila referenciada por otra tabla
                throw ValidationException::withMessages([
                    'materia_id' => 'No se puede quitar: ya tiene clases o tareas cargadas. Asigná otro profesor en su lugar.',
                ]);
            }
            throw $e;
        }
    }
}