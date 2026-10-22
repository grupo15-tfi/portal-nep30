<?php

namespace App\Actions\Profesores;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * HU08 (#13): alta y edición de profesores.
 * Contrato con el front (#14): ejecutar(array $datos, ?User $profesor = null): User
 * $datos: dni, apellido, nombre, email, sede_id. No envía correos (eso lo hace el front con
 * $profesor->enviarEnlaceCrearContrasena(), de #15).
 */
class GuardarProfesor
{
    public static function ejecutar(array $datos, ?User $profesor = null): User
    {
        $validos = Validator::make($datos, [
            'dni'      => ['required', 'regex:/^[0-9]{7,8}$/', Rule::unique('users', 'dni')->ignore($profesor?->id)],
            'apellido' => ['required', 'string', 'max:100'],
            'nombre'   => ['required', 'string', 'max:100'],
            'email'    => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($profesor?->id)],
            'sede_id'  => ['nullable', 'integer', 'exists:sedes,id'],
        ], [
            'dni.required'      => 'Escribí el DNI.',
            'dni.regex'         => 'El DNI tiene que tener 7 u 8 números, sin puntos.',
            'dni.unique'        => 'Ya existe un usuario con ese DNI',
            'email.required'    => 'Escribí el correo.',
            'email.email'       => 'El correo no es válido.',
            'email.unique'      => 'Ya existe un usuario con ese correo',
            'apellido.required' => 'Escribí el apellido.',
            'nombre.required'   => 'Escribí el nombre.',
            'sede_id.exists'    => 'La sede elegida no existe.',
        ])->validate(); // lanza ValidationException con las claves del contrato

        // Edición: solo los datos; la contraseña y el rol no se tocan
        if ($profesor) {
            $profesor->update($validos);

            return $profesor;
        }

        // Alta: rol profesor, activo y una contraseña aleatoria que nadie conoce.
        // El profesor crea la suya con el enlace del correo (#15).
        return DB::transaction(function () use ($validos) {
            $nuevo = User::create([
                ...$validos,
                'password' => Str::password(32),   // el cast "hashed" la guarda con bcrypt
                'activo'   => true,
            ]);
            $nuevo->assignRole('profesor');

            return $nuevo;
        });
    }
}