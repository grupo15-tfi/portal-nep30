<?php

namespace App\Actions\Profesores;

use App\Models\User;
use Illuminate\Support\Facades\Password;

/**
 * HU08 (#13): alta y baja lógica de un profesor.
 * Contrato: ejecutar(User $profesor, bool $activo): void
 * Nunca se borra: hay asignaciones, clases, notas y auditoría que dependen del usuario.
 * Con activo = false no puede iniciar sesión (lo controla el login de HU05).
 */
class CambiarEstadoProfesor
{
    public static function ejecutar(User $profesor, bool $activo): void
    {
        $profesor->update(['activo' => $activo]);

        // #28: al dar de baja, el enlace «Creá tu contraseña» pendiente deja de servir
        if (! $activo) {
            Password::broker()->deleteToken($profesor);
        }
    }
}