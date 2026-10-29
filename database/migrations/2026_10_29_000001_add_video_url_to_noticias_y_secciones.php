<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HU10 (#74): video de YouTube en noticias y secciones (pedido de la institución en la hoja
 * firmada del 19/10). Se guarda solo el enlace: el video queda alojado en YouTube.
 * Migración nueva: nunca se edita una migración que ya se aplicó en la VPS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('noticias', function (Blueprint $table) {
            $table->string('video_url', 255)->nullable()->after('imagen_ruta');
        });

        Schema::table('secciones', function (Blueprint $table) {
            $table->string('video_url', 255)->nullable()->after('imagen_ruta');
        });
    }

    public function down(): void
    {
        Schema::table('noticias', fn (Blueprint $table) => $table->dropColumn('video_url'));
        Schema::table('secciones', fn (Blueprint $table) => $table->dropColumn('video_url'));
    }
};
