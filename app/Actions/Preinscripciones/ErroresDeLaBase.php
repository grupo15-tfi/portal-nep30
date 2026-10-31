<?php

namespace App\Actions\Preinscripciones;

use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

/**
 * Traduce un error de MySQL (trigger o CHECK) a un error de formulario con la clave pedida.
 * Si no es un error de reglas, se vuelve a lanzar: que se vea, no se esconde.
 */
class ErroresDeLaBase
{
    public static function comoValidacion(QueryException $e, string $clave): ValidationException
    {
        $estado = $e->errorInfo[0] ?? null;   // SQLSTATE
        $codigo = $e->errorInfo[1] ?? null;   // 3819 = CHECK

        if ($estado === '45000' || $codigo === 3819) {
            return ValidationException::withMessages([$clave => $e->errorInfo[2] ?? 'La base de datos rechazó el cambio.']);
        }

        throw $e;
    }
}