<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Restricciones CHECK del modelo v4.4.
 * El Schema builder de Laravel no tiene CHECK, así que se agregan con ALTER TABLE
 * después de crear cada tabla. Así la base rechaza los datos inválidos aunque
 * falle una validación de Laravel.
 */
class Restricciones
{
    /** @param array<string, string> $checks nombre => expresión */
    public static function agregar(string $tabla, array $checks): void
    {
        foreach ($checks as $nombre => $expresion) {
            DB::statement("ALTER TABLE `{$tabla}` ADD CONSTRAINT `{$nombre}` CHECK ({$expresion})");
        }
    }
}
