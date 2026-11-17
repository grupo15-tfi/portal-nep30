<?php

namespace App\Actions\Preinscripciones;

use App\Models\Preinscripcion;
use App\Notifications\PreinscripcionAprobada;
use App\Support\CorreoAspirante;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * HU09 (#86): aprobar una preinscripción pendiente o en espera.
 * Contrato (#83): ejecutar(Preinscripcion $p): Preinscripcion · errores en `estado` y `cupo`.
 * Exige un lugar disponible: si el curso está completo, la persona sigue en la lista de espera.
 * Guarda quién y cuándo (chk_preinsc_revis) y le avisa por correo al aspirante.
 */
class AprobarPreinscripcion
{
    public static function ejecutar(Preinscripcion $preinscripcion): Preinscripcion
    {
        if (! in_array($preinscripcion->estado, ['pendiente', 'en_espera'], true)) {
            throw ValidationException::withMessages(['estado' => 'Esta preinscripción ya fue revisada.']);
        }

        if ($preinscripcion->curso->cupo_disponible <= 0) {
            throw ValidationException::withMessages([
                'cupo' => 'El curso no tiene lugares disponibles. Se puede aprobar cuando se libere uno.',
            ]);
        }

        try {
            $preinscripcion->forceFill([
                'estado'       => 'aprobada',
                'revisado_por' => Auth::id(),
                'revisado_en'  => now(),
            ])->save();
        } catch (QueryException $e) {
            throw ErroresDeLaBase::comoValidacion($e, 'estado');
        }

        CorreoAspirante::enviar($preinscripcion, new PreinscripcionAprobada($preinscripcion));

        return $preinscripcion;
    }
}