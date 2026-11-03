<?php

namespace App\Actions\Notas;

use App\Models\InscripcionMateria;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * HU11 (#89): cerrar el módulo de un alumno con su nota final.
 * Contrato (#84): ejecutar(InscripcionMateria $im, float $notaFinal): InscripcionMateria
 *   - Acreditada si la nota llega a la del plan (6) y la asistencia al mínimo (80 %).
 *   - Si no, queda pendiente, con la nota final guardada.
 *   Errores: ValidationException en `nota_final` (y `inscripcion_materia_id` si no es su materia).
 * La base vuelve a controlar nota y asistencia al acreditar (validar_acreditacion) y lo audita (trg_im_au).
 */
class CerrarModulo
{
    public static function ejecutar(InscripcionMateria $im, float $notaFinal): InscripcionMateria
    {
        Validator::make(['nota_final' => $notaFinal], [
            'nota_final' => ['required', 'numeric', 'between:1,10', 'decimal:0,2'],
        ], [
            'nota_final.between' => 'La nota final va de 1 a 10.',
            'nota_final.decimal' => 'La nota final puede tener hasta dos decimales.',
        ])->validate();

        $im->loadMissing('inscripcion.curso.planEstudio');
        PuedeCargarNotas::controlar(Auth::user(), $im);

        if ($im->estado === 'acreditada') {
            throw ValidationException::withMessages(['nota_final' => 'Este módulo ya está acreditado.']);
        }

        $plan = $im->inscripcion->curso->planEstudio;
        $acredita = $notaFinal >= (float) $plan->nota_aprobacion && self::cumpleAsistencia($im);

        try {
            $im->update([
                'nota_final' => $notaFinal,
                'estado'     => $acredita ? 'acreditada' : 'pendiente',
            ]);
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? null) === '45000') {
                throw ValidationException::withMessages(['nota_final' => $e->errorInfo[2]]);
            }
            throw $e;
        }

        return $im->refresh();
    }

    /** Asistencia de la materia según la vista de la base. Sin clases dictadas todavía, no la bloquea. */
    private static function cumpleAsistencia(InscripcionMateria $im): bool
    {
        $fila = DB::table('v_asistencia_porcentaje')
            ->where('inscripcion_id', $im->inscripcion_id)
            ->where('materia_id', $im->materia_id)
            ->first();

        return $fila === null || (bool) $fila->cumple_minimo;
    }
}