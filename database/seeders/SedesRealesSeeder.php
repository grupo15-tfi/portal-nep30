<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * HU03 (#50): sedes y anexos reales de la institución.
 *
 * NO se llama desde DatabaseSeeder: los datos de prueba siguen en DatosDePruebaSeeder.
 * Se corre a mano, una sola vez, cuando la escuela confirme el listado con las coordenadas:
 *
 *     php artisan db:seed --class=SedesRealesSeeder
 *
 * Las coordenadas se toman en la puerta de cada sede (tarjeta «Relevar coordenadas» de Trello)
 * o con clic derecho sobre el edificio en Google Maps. Son obligatorias: muchas direcciones
 * son «manzana y parcela» y el buscador de direcciones no las encuentra.
 *
 * Si una sede ya existe con el mismo nombre, se actualiza (no se duplica).
 * Las sedes también se pueden cargar desde el panel: Administrador → Sedes.
 */
class SedesRealesSeeder extends Seeder
{
    /**
     * Completar con el listado de la escuela. Formato de cada sede:
     *
     * [
     *     'nombre'        => 'Anexo …',                         // único
     *     'tipo'          => 'anexo',                           // central, sede o anexo
     *     'direccion'     => 'Mz. 25, Parcela 5',               // opcional
     *     'localidad'     => 'Formosa',
     *     'telefono'      => null,                              // opcional
     *     'email'         => null,                              // opcional
     *     'latitud'       => -26.0000000,                       // obligatoria
     *     'longitud'      => -58.0000000,                       // obligatoria
     *     'observaciones' => 'Funciona en …',                   // opcional
     * ],
     */
    public const SEDES = [
        // Vacío hasta tener el listado confirmado por la institución.
    ];

    public function run(): void
    {
        foreach (static::SEDES as $sede) {
            if (! isset($sede['latitud'], $sede['longitud'])) {
                $this->command?->warn("Sin coordenadas, no se carga: {$sede['nombre']}");

                continue;
            }

            DB::table('sedes')->updateOrInsert(
                ['nombre' => $sede['nombre']],
                [...$sede, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        $this->command?->info('Sedes reales cargadas: '.count(static::SEDES));
    }
}