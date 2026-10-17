<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo inicial de planes del folleto "Oferta Educativa 2026" (03_datos_base.sql, punto 3).
 * Solo los nombres: módulos, cursos por sede, cupos, días y horarios se cargan desde el panel.
 * Reglas por defecto de cada plan: edad mínima 18, se acredita con 6 y con 80 % de asistencia.
 *
 * Las sedes y anexos NO se cargan acá: el mapa necesita coordenadas exactas y varias
 * direcciones son "manzana y casa" que Nominatim no encuentra. Se ubican en el panel.
 */
class CatalogoSeeder extends Seeder
{
    public const FORMACION_PROFESIONAL = [
        'Microemprendimientos y proyectos productivos',
        'Peluquería',
        'Organización de Eventos Sociales',
        'Operador de Informática para la Administración y Gestión',
        'Confeccionista a medida, modista/o',
        'Belleza Integral',
        'Servicios Integrales',
        'Tejido Artesanal',
        'Montador Electricista Domiciliario',
    ];

    public const TERMINALIDAD = [
        'Primaria para adultos: Alfabetización',
        'Primaria para adultos: Formación Integral',
        'Primaria para adultos: Formación por Proyectos',
    ];

    public function run(): void
    {
        foreach ([...array_fill_keys(self::FORMACION_PROFESIONAL, 'formacion_profesional'),
                  ...array_fill_keys(self::TERMINALIDAD, 'terminalidad')] as $nombre => $tipo) {
            DB::table('planes_estudio')->updateOrInsert(
                ['nombre' => $nombre],
                ['tipo' => $tipo, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }
}
