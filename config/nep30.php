<?php

/*
 * Configuración propia del Portal N.E.P. 30.
 */
return [
    // Administrador inicial que crea "php artisan db:seed" (AdministradorSeeder).
    // En la PC, si quedan vacíos, se usa admin@nep30.test / password.
    'admin' => [
        'dni' => env('ADMIN_DNI'),
        'apellido' => env('ADMIN_APELLIDO'),
        'nombre' => env('ADMIN_NOMBRE'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],
];
