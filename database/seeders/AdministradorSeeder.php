<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Administrador inicial (Nery Alfredo Aguayo lo usa para crear al resto desde el panel).
 *
 * Los datos salen del .env (ADMIN_DNI, ADMIN_APELLIDO, ADMIN_NOMBRE, ADMIN_EMAIL, ADMIN_PASSWORD;
 * ver config/nep30.php).
 * En la PC (APP_ENV=local) hay valores de prueba; en la VPS y en producción son obligatorios,
 * para que nunca quede un administrador con una clave conocida.
 */
class AdministradorSeeder extends Seeder
{
    public function run(): void
    {
        $local = app()->environment(['local', 'testing']);

        $prueba = ['dni' => '10000001', 'apellido' => 'Administrador', 'nombre' => 'Prueba',
                   'email' => 'admin@nep30.test', 'password' => 'password'];

        $datos = [];
        foreach ($prueba as $campo => $valorDePrueba) {
            $datos[$campo] = config("nep30.admin.{$campo}") ?: ($local ? $valorDePrueba : null);
        }

        foreach ($datos as $campo => $valor) {
            if (blank($valor)) {
                throw new RuntimeException('Falta ADMIN_'.strtoupper($campo).' en el .env para crear el Administrador inicial.');
            }
        }
        if (! $local && strlen($datos['password']) < 12) {
            throw new RuntimeException('ADMIN_PASSWORD debe tener al menos 12 caracteres fuera de la PC.');
        }

        $admin = User::withTrashed()->firstOrNew(['email' => $datos['email']]);
        if (! $admin->exists) {
            $admin->fill([...$datos, 'password' => Hash::make($datos['password'])]);
            $admin->email_verified_at = now();
            $admin->save();
        }
        $admin->assignRole('administrador');
    }
}
