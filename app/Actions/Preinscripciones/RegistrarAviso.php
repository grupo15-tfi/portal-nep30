<?php

namespace App\Actions\Preinscripciones;

use App\Models\Preinscripcion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * HU09 (#86): el administrador registra que llamó a alguien de la lista de espera.
 * Contrato (#83): ejecutar(Preinscripcion $p): Preinscripcion · error en `estado`.
 * Guarda la fecha y quién avisó (chk_preinsc_aviso: los dos juntos).
 */
class RegistrarAviso
{
    public static function ejecutar(Preinscripcion $preinscripcion): Preinscripcion
    {
        if (! $preinscripcion->enEspera()) {
            throw ValidationException::withMessages(['estado' => 'Solo se registra el aviso a quien está en lista de espera.']);
        }

        $preinscripcion->forceFill([
            'avisado_en'  => now(),
            'avisado_por' => Auth::id(),
        ])->save();

        return $preinscripcion;
    }
}