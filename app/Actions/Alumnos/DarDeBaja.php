<?php

namespace App\Actions\Alumnos;

use App\Models\Inscripcion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * HU09 (#86): dar de baja una inscripción, con motivo (máx. 300).
 * Contrato (#83): ejecutar(Inscripcion $i, string $motivo): Inscripcion · errores en `motivo` y `estado`.
 * Libera el lugar del cupo. Si al alumno no le queda otra inscripción activa, no puede ingresar
 * (activo = false) y su enlace de contraseña pendiente deja de servir, como la baja de profesores (#28).
 */
class DarDeBaja
{
    public static function ejecutar(Inscripcion $inscripcion, string $motivo): Inscripcion
    {
        $datos = Validator::make(['motivo' => trim($motivo)], [
            'motivo' => ['required', 'string', 'max:300'],
        ], [
            'motivo.required' => 'Escribí el motivo de la baja.',
            'motivo.max'      => 'El motivo puede tener hasta 300 caracteres.',
        ])->validate();

        if ($inscripcion->estado !== 'activa') {
            throw ValidationException::withMessages(['estado' => 'Esta inscripción no está activa.']);
        }

        DB::transaction(function () use ($inscripcion, $datos) {
            $inscripcion->update([
                'estado'      => 'baja',
                'fecha_baja'  => today(),
                'motivo_baja' => $datos['motivo'],
            ]);

            $alumno = $inscripcion->alumno;

            if (! $alumno->inscripciones()->activas()->exists()) {
                $alumno->update(['activo' => false]);
                Password::broker()->deleteToken($alumno);
            }
        });

        return $inscripcion->refresh();
    }
}