<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Livewire\Component;

/**
 * HU06 (#15): «¿Olvidaste tu contraseña?».
 * Siempre muestra el mismo mensaje, exista o no el correo: así nadie puede averiguar
 * qué correos están registrados. Un usuario inactivo o dado de baja no recibe enlace.
 */
class OlvideContrasena extends Component
{
    public const MENSAJE = 'Si el correo está registrado, te enviamos un enlace para restablecer la contraseña. Revisá tu casilla y el correo no deseado.';

    public string $email = '';

    public bool $enviado = false;

    public function enviar(): void
    {
        $this->validate(
            ['email' => ['required', 'email']],
            ['email.required' => 'Escribí tu correo.', 'email.email' => 'El correo no es válido.'],
        );

        // activo = true: el usuario dado de baja no recibe enlace (SoftDeletes ya excluye a los borrados)
        Password::sendResetLink(['email' => $this->email, 'activo' => true]);

        $this->enviado = true;
        $this->reset('email');
    }

    public function render()
    {
        return view('livewire.auth.olvide-contrasena');
    }
}