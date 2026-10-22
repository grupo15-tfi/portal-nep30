<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // HU06 (#15): reglas de contraseña de todo el sistema (recuperación, alta y perfil).
        // Mínimo 8 caracteres, con letras y números (contrato con #16).
        Password::defaults(fn () => Password::min(8)->letters()->numbers());
    }
}