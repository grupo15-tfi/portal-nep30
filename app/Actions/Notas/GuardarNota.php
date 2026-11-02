<?php

namespace App\Actions\Notas;

use App\Models\Evaluacion;
use App\Models\InscripcionMateria;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * HU11 (#89): cargar o corregir una nota.
 * Contrato (#84): ejecutar(array $datos, ?Evaluacion $evaluacion = null): Evaluacion
 *   $datos: inscripcion_materia_id, tipo, nota, fecha
 *   Errores: ValidationException con esas mismas claves.
 * Al cargar, cargada_por = usuario autenticado. Al corregir no se cambia ni el alumno ni la materia,
 * y la base guarda el valor anterior y el nuevo en la auditoría (trg_eval_au).
 */
class GuardarNota
{
    public static function ejecutar(array $datos, ?Evaluacion $evaluacion = null): Evaluacion
    {
        $validos = Validator::make($datos, [
            'inscripcion_materia_id' => ['required', 'integer', Rule::exists('inscripcion_materias', 'id')],
            'tipo'                   => ['required', Rule::in(array_keys(Evaluacion::TIPOS))],
            'nota'                   => ['required', 'numeric', 'between:1,10', 'decimal:0,2'],
            'fecha'                  => ['required', 'date', 'before_or_equal:today'],
        ], [
            'inscripcion_materia_id.required' => 'Elegí el alumno y la materia.',
            'inscripcion_materia_id.exists'   => 'Ese alumno no cursa esa materia.',
            'tipo.required'                   => 'Elegí el tipo de evaluación.',
            'tipo.in'                         => 'El tipo tiene que ser parcial, recuperatorio, trabajo práctico o final.',
            'nota.required'                   => 'Escribí la nota.',
            'nota.numeric'                    => 'La nota tiene que ser un número.',
            'nota.between'                    => 'La nota va de 1 a 10.',
            'nota.decimal'                    => 'La nota puede tener hasta dos decimales.',
            'fecha.required'                  => 'Escribí la fecha.',
            'fecha.date'                      => 'La fecha no es válida.',
            'fecha.before_or_equal'           => 'La fecha no puede ser futura.',
        ])->validate();

        // Al corregir, la nota sigue siendo del mismo alumno y materia (la base también lo impide).
        if ($evaluacion && (int) $validos['inscripcion_materia_id'] !== $evaluacion->inscripcion_materia_id) {
            throw ValidationException::withMessages([
                'inscripcion_materia_id' => 'No se puede pasar una nota a otro alumno o materia.',
            ]);
        }

        $im = InscripcionMateria::with('inscripcion')->findOrFail($validos['inscripcion_materia_id']);
        PuedeCargarNotas::controlar(Auth::user(), $im);

        try {
            if ($evaluacion) {
                $evaluacion->update($validos);

                return $evaluacion;
            }

            return Evaluacion::create([...$validos, 'cargada_por' => Auth::id()]);
        } catch (QueryException $e) {
            throw ValidationException::withMessages(self::errorDeLaBase($e));
        }
    }

    /** Traduce los errores de la base a errores del formulario */
    private static function errorDeLaBase(QueryException $e): array
    {
        $estado = $e->errorInfo[0] ?? null;
        $codigo = $e->errorInfo[1] ?? null;
        $texto = $e->errorInfo[2] ?? '';

        // uq_evaluacion: misma materia, tipo y fecha
        if ($codigo === 1062) {
            return ['fecha' => 'Ya hay una nota de ese tipo en esa fecha para este alumno.'];
        }

        if ($estado === '45000') {
            return str_contains($texto, 'recuperatorio')
                ? ['tipo' => 'El recuperatorio solo corresponde si hay un parcial desaprobado antes.']
                : ['inscripcion_materia_id' => $texto];
        }

        throw $e;
    }
}