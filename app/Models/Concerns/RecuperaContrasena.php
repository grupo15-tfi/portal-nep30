<?php

namespace App\Models\Concerns;

use App\Notifications\CrearContrasena;
use App\Notifications\RestablecerContrasena;
use Illuminate\Support\Facades\Password;

/**
 * Correos de contraseña del usuario (HU06 #15). Se usa en User con `use RecuperaContrasena;`.
 */
trait RecuperaContrasena
{
    /** Laravel lo llama al pedir «¿Olvidaste tu contraseña?»: usamos nuestro correo en español */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new RestablecerContrasena($token));
    }

    /** Correo de alta (contrato con HU08 #14): genera un enlace y lo envía por la cola */
    public function enviarEnlaceCrearContrasena(): void
    {
        $token = Password::broker()->createToken($this);

        $this->notify(new CrearContrasena($token));
    }
}