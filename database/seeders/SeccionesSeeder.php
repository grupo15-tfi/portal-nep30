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
            // #60: la portada va sin el lema (decisión de Aguayo, 26/10). Título y texto editables desde el panel.
            ['inicio', 'Formación profesional y terminalidad primaria en Formosa', 'Cursos con salida laboral y terminalidad primaria para jóvenes y adultos, en la sede del barrio Luján y en anexos cerca de tu barrio.'],
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
