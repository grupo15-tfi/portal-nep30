<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #96: consentimiento de las personas que aparecen en la imagen o el video de una noticia, o que se nombran
 * (revisión del Sprint 2, acta del 30/10, punto 4.2; Ley 25.326). Guarda cuándo se confirmó:
 * sin esa fecha, una noticia con imagen o video no se puede publicar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('noticias', function (Blueprint $table) {
            $table->dateTime('consentimiento_en')->nullable()->after('video_url');
        });
    }

    public function down(): void
    {
        Schema::table('noticias', fn (Blueprint $table) => $table->dropColumn('consentimiento_en'));
    }
};
