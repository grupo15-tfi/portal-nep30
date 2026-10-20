<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * HU05: inicio de sesión con correo y contraseña, acceso por rol
 * y bloqueo de 15 minutos tras 5 intentos fallidos (RNF12).
 */
class Login extends Component
{
    public string $email = '';
    public string $password = '';

    private const MENSAJE = 'Correo o contraseña incorrectos';

    private const PANELES = [
        'administrador' => '/admin/inicio',
        'profesor'      => '/profesor/inicio',
        'alumno'        => '/alumno/inicio',
        'auditor'       => '/auditor/inicio',
    ];

    public function ingresar()
    {
        // 1. Validación básica del formulario
        $this->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        // 2. Buscar al usuario (SoftDeletes excluye a los dados de baja)
        $user = User::where('email', $this->email)->first();

        // 3. Bloqueado: no se prueba la contraseña (RNF12)
        if ($user?->bloqueado_hasta?->isFuture()) {
            $this->addError('email', 'Demasiados intentos fallidos. Probá de nuevo en 15 minutos.');
            return;
        }

        // 4. Inexistente o inactivo: mismo mensaje, sin revelar cuál es el problema
        if (! $user || ! $user->activo) {
            $this->addError('email', self::MENSAJE);
            return;
        }

        // 5. Contraseña incorrecta: sumar intento y bloquear al 5.º
        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password])) {
            $user->intentos_fallidos++;

            if ($user->intentos_fallidos >= 5) {
                $user->bloqueado_hasta = now()->addMinutes(15);
                $user->intentos_fallidos = 0; // al vencer el bloqueo, arranca de cero
            }

            $user->save();
            $this->addError('email', self::MENSAJE);
            return;
        }

        // 6. Ingreso correcto: reiniciar contador y registrar el acceso
        $user->intentos_fallidos = 0;
        $user->bloqueado_hasta = null;
        $user->ultimo_acceso = now();
        $user->save();

        session()->regenerate(); // evita el robo de sesión (session fixation)

        $rol = $user->getRoleNames()->first();

        // Usuario sin rol: no tiene panel al que entrar
        if (! isset(self::PANELES[$rol])) {
            Auth::logout();
            $this->addError('email', self::MENSAJE);
            return;
        }

        return $this->redirect(self::PANELES[$rol]);
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
