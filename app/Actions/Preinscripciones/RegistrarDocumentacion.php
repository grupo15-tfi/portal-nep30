<?php

namespace App\Actions\Preinscripciones;

use App\Models\Preinscripcion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * #107: el administrador registra que el aspirante presentó la documentación en papel.
 * ejecutar(Preinscripcion $p, bool $presentada, ?string $observacion = null): Preinscripcion
 *   - Solo en una preinscripción aprobada y sin alta; si no, error en `documentacion`.
 *   - Presentada: guarda cuándo y quién (el usuario logueado) y la observación (máx. 300, opcional).
 *   - No presentada: la desmarca (se puede mientras no se haya dado el alta).
 * El sistema no recibe ni guarda copias de los documentos (Ley 25.326).
 */
class RegistrarDocumentacion
{
    public static function ejecutar(Preinscripcion $preinscripcion, bool $presentada, ?string $observacion = null): Preinscripcion
    {
        if ($preinscripcion->estado !== 'aprobada') {
            throw ValidationException::withMessages([
                'documentacion' => 'La documentación se registra en una preinscripción aprobada.',
            ]);
        }

        if ($preinscripcion->inscripcion()->exists()) {
            throw ValidationException::withMessages([
                'documentacion' => 'Ya se dio de alta: la documentación no se puede cambiar.',
            ]);
        }

        $observacion = trim((string) $observacion);
        Validator::make(['documentacion_obs' => $observacion], [
            'documentacion_obs' => ['max:300'],
        ], [
            'documentacion_obs.max' => 'La observación puede tener hasta 300 caracteres.',
        ])->validate();

        $preinscripcion->forceFill($presentada
            ? ['documentacion_en' => now(), 'documentacion_por' => Auth::id(), 'documentacion_obs' => $observacion ?: null]
            : ['documentacion_en' => null, 'documentacion_por' => null, 'documentacion_obs' => null]
        )->save();

        return $preinscripcion;
    }
}