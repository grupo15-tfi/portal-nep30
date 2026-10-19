<?php

use App\Support\Restricciones;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sitio público y auditoría (modelo v4.4, secciones 7 y 8).
 * RF01, RF02, RF08, RF19, RF21, RF36, HU16, HU18, RNF14.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('noticias', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 200);
            $table->string('slug', 220)->unique();
            $table->string('resumen', 400)->nullable();
            $table->mediumText('cuerpo');
            $table->string('imagen_ruta')->nullable();
            $table->string('estado', 10)->default('borrador');
            $table->dateTime('publicada_en')->nullable();
            $table->unsignedBigInteger('autor_id');
            $table->timestamps();
            $table->index(['estado', 'publicada_en'], 'idx_noticia_pub');
            $table->foreign('autor_id', 'fk_noticia_autor')->references('id')->on('users');
        });
        Restricciones::agregar('noticias', [
            'chk_noticia_estado' => "estado IN ('borrador','publicada')",
            'chk_noticia_slug'   => "slug REGEXP '^[a-z0-9]+(-[a-z0-9]+)*$'",
            'chk_noticia_fecha'  => "estado <> 'publicada' OR publicada_en IS NOT NULL",
        ]);

        // inicio, historia, contacto, servicios, privacidad (editables desde el panel).
        Schema::create('secciones', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 40)->unique();
            $table->string('titulo', 150);
            $table->mediumText('contenido');
            $table->string('imagen_ruta')->nullable();
            $table->unsignedBigInteger('actualizado_por')->nullable();
            $table->timestamps();
            $table->foreign('actualizado_por', 'fk_seccion_usuario')->references('id')->on('users');
        });

        // La consultan el Administrador y el Auditor. No se puede modificar ni borrar (triggers).
        Schema::create('auditoria', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('usuario_id')->nullable();   // null = fuera del sistema o público
            $table->string('accion', 15);
            $table->string('tabla', 60);
            $table->unsignedBigInteger('registro_id')->nullable();
            $table->json('valores_anteriores')->nullable();
            $table->json('valores_nuevos')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tabla', 'registro_id'], 'idx_audit_tabla');
            $table->index(['usuario_id', 'created_at'], 'idx_audit_usuario');
            $table->index('created_at', 'idx_audit_fecha');
            $table->foreign('usuario_id', 'fk_audit_usuario')->references('id')->on('users');
        });
        Restricciones::agregar('auditoria', [
            // 'descarga' = historial en PDF o planilla Excel (la registra Laravel).
            'chk_audit_accion' => "accion IN ('alta','baja','modificacion','login','login_fallido','descarga')",
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria');
        Schema::dropIfExists('secciones');
        Schema::dropIfExists('noticias');
    }
};
