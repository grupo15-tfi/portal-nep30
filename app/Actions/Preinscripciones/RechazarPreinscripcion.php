<?php

namespace App\Actions\Preinscripciones;

use App\Models\Preinscripcion;
use App\Notifications\PreinscripcionRechazada;
use App\Support\CorreoAspirante;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * HU09 (#86): rechazar una preinscripción, con motivo obligatorio (chk_preinsc_motivo).
 * Contrato (#83): ejecutar(Preinscripcion $p, string $motivo): Preinscripcion · errores en `motivo` y `estado`.
 */
class RechazarPreinscripcion
{
    public static function ejecutar(Preinscripcion $preinscripcion, string $motivo): Preinscripcion
    {
        $datos = Validator::make(['motivo' => trim($motivo)], [
            'motivo' => ['required', 'string', 'max:500'],
        ], [
            'motivo.required' => 'Escribí el motivo: le llega al aspirante por correo.',
            'motivo.max'      => 'El motivo puede tener hasta 500 caracteres.',
        ])->validate();

        if (! in_array($preinscripcion->estado, ['pendiente', 'en_espera'], true)) {
            throw ValidationException::withMessages(['estado' => 'Esta preinscripción ya fue revisada.']);
        }

        try {
            $preinscripcion->forceFill([
                'estado'       => 'rechazada',
                'observacion'  => $datos['motivo'],
                'revisado_por' => Auth::id(),
                'revisado_en'  => now(),
            ])->save();
        } catch (QueryException $e) {
            throw ErroresDeLaBase::comoValidacion($e, 'estado');
        }

        CorreoAspirante::enviar($preinscripcion, new PreinscripcionRechazada($preinscripcion));

        return $preinscripcion;
    }
}