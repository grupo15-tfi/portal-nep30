<?php

namespace App\Actions\Notas;

use App\Models\CursoProfesor;
use App\Models\InscripcionMateria;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * HU11 (#89): solo el profesor que tiene asignada la materia en ese curso (o un administrador)
 * carga o corrige notas. La base lo controla al insertar (trg_eval_bi); acá se controla también
 * al corregir y al cerrar el módulo, y el mensaje sale más claro.
 */
class PuedeCargarNotas
{
    public const MENSAJE = 'No tenés asignada esta materia en este curso.';

    public static function controlar(?User $usuario, InscripcionMateria $im): void
    {
        if ($usuario?->hasRole('administrador')) {
            return;
        }

        $asignado = $usuario && CursoProfesor::where('curso_id', $im->inscripcion->curso_id)
            ->where('materia_id', $im->materia_id)
            ->where('profesor_id', $usuario->id)
            ->exists();

        if (! $asignado) {
            throw ValidationException::withMessages(['inscripcion_materia_id' => self::MENSAJE]);
        }
    }
}