<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as ReglaPassword;
use Livewire\Component;

/**
 * HU06 (#15): pantalla del enlace del correo (restablecer o crear la contraseña).
 * El token vence a los 60 minutos y sirve una sola vez (lo maneja el broker de Laravel).
 */
class RestablecerContrasena extends Component
{
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public bool $listo = false;

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = (string) request()->query('email', '');
    }

    public function guardar(): void
    {
        $this->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', ReglaPassword::defaults()],
        ], [
            'password.confirmed' => 'Las dos contraseñas no coinciden.',
        ]);

        $estado = Password::reset(
            [
                'email'                 => $this->email,
                'password'              => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token'                 => $this->token,
                'activo'                => true,   // #28: el broker no encuentra a un usuario dado de baja
            ],
            function (User $usuario, string $password) {
                $usuario->forceFill([
                    'password'          => $password,   // el cast "hashed" la guarda con bcrypt
                    'remember_token'    => Str::random(60),
                    'intentos_fallidos' => 0,           // si estaba bloqueado por intentos (HU05), se desbloquea
                    'bloqueado_hasta'   => null,
                ])->save();

                event(new PasswordReset($usuario));
            }
        );

        if ($estado !== Password::PASSWORD_RESET) {
            // Token vencido, ya usado o correo que no coincide: mismo mensaje
            $this->addError('email', 'El enlace no es válido o ya venció. Pedí uno nuevo desde «¿Olvidaste tu contraseña?».');

            return;
        }

        $this->listo = true;
        $this->reset(['password', 'password_confirmation']);
    }

    public function render()
    {
        return view('livewire.auth.restablecer-contrasena');
    }
}