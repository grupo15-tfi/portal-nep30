<?php

namespace App\Livewire\Perfil;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

/**
 * HU06 (#16): cambio de contraseña desde el perfil.
 * Exige la contraseña actual y usa las mismas reglas que la recuperación (Password::defaults(),
 * que configura Benitez en AppServiceProvider; mientras tanto Laravel aplica mínimo 8 caracteres).
 */
class CambiarContrasena extends Component
{
    public string $actual = '';
    public string $nueva = '';
    public string $nueva_confirmation = '';

    protected function rules(): array
    {
        return [
            'actual' => ['required', 'current_password'],
            'nueva'  => ['required', 'confirmed', Password::defaults()],
        ];
    }

    protected function messages(): array
    {
        return [
            'actual.current_password' => 'La contraseña actual no es correcta.',
            'nueva.confirmed'         => 'Las dos contraseñas nuevas no coinciden.',
        ];
    }

    public function guardar(): void
    {
        $this->validate();

        Auth::user()->update(['password' => $this->nueva]); // el cast "hashed" la guarda con bcrypt

        $this->reset(['actual', 'nueva', 'nueva_confirmation']);
        session()->flash('contrasena-ok', 'Tu contraseña se cambió.');
    }

    public function render()
    {
        return view('livewire.perfil.cambiar-contrasena');
    }
}
