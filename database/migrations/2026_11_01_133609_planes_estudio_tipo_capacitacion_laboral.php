<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * #95: tipo de plan «Capacitación laboral» (revisión del Sprint 2, acta del 30/10, punto 4.3).
 * Tejido Artesanal y Belleza Integral ya estaban en el catálogo como formación profesional:
 * se pasan al tipo nuevo. Migración nueva: no se edita la que creó la tabla (ya está aplicada).
 */
return new class extends Migration
{
    private const PLANES = ['Tejido Artesanal', 'Belleza Integral'];

    public function up(): void
    {
        DB::statement('ALTER TABLE `planes_estudio` DROP CONSTRAINT `chk_plan_tipo`');

        DB::table('planes_estudio')->whereIn('nombre', self::PLANES)->update(['tipo' => 'capacitacion_laboral']);

        DB::statement("ALTER TABLE `planes_estudio` ADD CONSTRAINT `chk_plan_tipo` "
            ."CHECK (tipo IN ('formacion_profesional','capacitacion_laboral','terminalidad'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `planes_estudio` DROP CONSTRAINT `chk_plan_tipo`');

        DB::table('planes_estudio')->where('tipo', 'capacitacion_laboral')->update(['tipo' => 'formacion_profesional']);

        DB::statement("ALTER TABLE `planes_estudio` ADD CONSTRAINT `chk_plan_tipo` "
            ."CHECK (tipo IN ('formacion_profesional','terminalidad'))");
    }
};