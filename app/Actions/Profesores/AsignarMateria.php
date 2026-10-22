<?php

namespace App\Actions\Profesores;

use App\Models\Curso;
use App\Models\CursoProfesor;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

/**
 * HU08 (#13): asigna una materia de un curso a un profesor.
 * Contrato: ejecutar(User $profesor, int $cursoId, int $materiaId): void
 * Errores con la clave materia_id (el front los muestra con @error('materia_id')).
 */
class AsignarMateria
{
    public static function ejecutar(User $profesor, int $cursoId, int $materiaId): void
    {
        $curso = Curso::find($cursoId);

        if (! $curso) {
            throw ValidationException::withMessages(['materia_id' => 'El curso elegido no existe.']);
        }
        if (! $profesor->hasRole('profesor')) {
            throw ValidationException::withMessages(['materia_id' => 'El usuario no tiene el rol profesor.']);
        }
        // Mismo control que el trigger trg_cp_bi, antes de llegar a la base (mensaje más claro)
        if (! $curso->materiasDelPlan()->whereKey($materiaId)->exists()) {
            throw ValidationException::withMessages(['materia_id' => 'La materia no pertenece al plan del curso.']);
        }

        try {
            CursoProfesor::create([
                'curso_id'    => $cursoId,
                'materia_id'  => $materiaId,
                'profesor_id' => $profesor->id,
            ]);
        } catch (QueryException $e) {
            throw ValidationException::withMessages(['materia_id' => self::mensaje($e)]);
        }
    }

    /** Traduce los errores de la base a un mensaje para el formulario */
    private static function mensaje(QueryException $e): string
    {
        $codigo = $e->errorInfo[1] ?? null;   // código de MySQL / MariaDB
        $estado = $e->errorInfo[0] ?? null;   // SQLSTATE

        if ($codigo === 1062) {
            return 'Ese profesor ya tiene asignada esa materia en el curso.';   // índice uq_curso_prof
        }
        if ($estado === '45000') {
            return $e->errorInfo[2] ?? 'La base rechazó la asignación.';      // mensaje del trigger trg_cp_bi
        }

        throw $e; // otro error: que se vea, no se esconde
    }
}