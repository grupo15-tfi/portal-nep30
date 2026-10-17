<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * php artisan db:seed
     *   Siempre: roles y permisos, secciones, catálogo de cursos y el Administrador inicial.
     *   Solo en local y en la VPS de demos (APP_ENV=local o demo): datos de prueba.
     */
    public function run(): void
    {
        $this->call([
            RolesYPermisosSeeder::class,
            SeccionesSeeder::class,
            CatalogoSeeder::class,
            AdministradorSeeder::class,
        ]);

        if (app()->environment(['local', 'demo', 'testing'])) {
            $this->call(DatosDePruebaSeeder::class);
        }
    }
}
