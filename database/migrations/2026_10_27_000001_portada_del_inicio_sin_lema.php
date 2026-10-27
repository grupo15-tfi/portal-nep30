<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * #60: la portada del Inicio va sin el lema «Tú puedes lograrlo. Te ayudamos.» (decisión de Aguayo, 26/10).
 * Actualiza las bases que ya tienen la sección cargada (PC de cada uno y la VPS de demos).
 * Solo cambia el texto si todavía es el de ejemplo con el lema: si alguien ya lo editó, no lo toca.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('secciones')
            ->where('clave', 'inicio')
            ->where('contenido', 'like', 'Tú puedes lograrlo%')
            ->update([
                'titulo'     => 'Formación profesional y terminalidad primaria en Formosa',
                'contenido'  => 'Cursos con salida laboral y terminalidad primaria para jóvenes y adultos, en la sede del barrio Luján y en anexos cerca de tu barrio.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // No se vuelve a poner el lema: el PO no lo aprobó.
    }
};
