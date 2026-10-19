<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Secciones editables del sitio público (03_datos_base.sql, punto 2). */
class SeccionesSeeder extends Seeder
{
    public function run(): void
    {
        $secciones = [
            ['inicio', 'Inicio', 'Tú puedes lograrlo. TE AYUDAMOS. (Texto de bienvenida editable desde el panel.)'],
            ['historia', 'Nuestra historia', 'Historia de la institución (editable desde el panel).'],
            ['contacto', 'Contacto', 'E.P.E.P. N.º 57 "Gendarme Argentino", Av. de los Constituyentes, barrio Luján, Formosa.'],
            ['servicios', 'Servicios', 'Sala de Lactancia en la Sede (editable desde el panel).'],
            ['privacidad', 'Política de privacidad', 'Tratamiento de datos personales según la Ley 25.326.'],
        ];

        foreach ($secciones as [$clave, $titulo, $contenido]) {
            DB::table('secciones')->updateOrInsert(
                ['clave' => $clave],
                ['titulo' => $titulo, 'contenido' => $contenido, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }
}
