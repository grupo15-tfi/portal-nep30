<?php

use App\Support\Restricciones;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gestión académica del Campus Virtual (modelo v4.4, sección 6). RF22-RF32.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Notas de 1 a 10. El recuperatorio solo si hay un parcial desaprobado (trigger).
        Schema::create('evaluaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inscripcion_materia_id');
            $table->string('tipo', 16);
            $table->decimal('nota', 4, 2);
            $table->date('fecha');
            $table->unsignedBigInteger('cargada_por');
            $table->timestamps();
            $table->unique(['inscripcion_materia_id', 'tipo', 'fecha'], 'uq_evaluacion');
            $table->foreign('inscripcion_materia_id', 'fk_eval_im')->references('id')->on('inscripcion_materias');
            $table->foreign('cargada_por', 'fk_eval_prof')->references('id')->on('users');
        });
        Restricciones::agregar('evaluaciones', [
            'chk_eval_tipo' => "tipo IN ('parcial','recuperatorio','trabajo_practico','final')",
            'chk_eval_nota' => 'nota BETWEEN 1 AND 10',
        ]);

        Schema::create('clases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('curso_profesor_id');
            $table->date('fecha');
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();
            $table->string('tema', 200)->nullable();
            $table->string('estado', 12)->default('dictada');
            $table->timestamps();
            $table->unique(['curso_profesor_id', 'fecha', 'hora_inicio'], 'uq_clase');
            $table->foreign('curso_profesor_id', 'fk_clase_cp')->references('id')->on('curso_profesores');
        });
        Restricciones::agregar('clases', [
            'chk_clase_estado' => "estado IN ('dictada','suspendida')",
            'chk_clase_horas'  => 'hora_fin IS NULL OR hora_inicio IS NULL OR hora_fin > hora_inicio',
        ]);

        // 'justificado' no cuenta como ausente (entrevista 13/10).
        Schema::create('asistencias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clase_id');
            $table->unsignedBigInteger('inscripcion_id');
            $table->string('estado', 12);
            $table->timestamps();
            $table->unique(['clase_id', 'inscripcion_id'], 'uq_asistencia');
            $table->foreign('clase_id', 'fk_asist_clase')->references('id')->on('clases')->cascadeOnDelete();
            $table->foreign('inscripcion_id', 'fk_asist_insc')->references('id')->on('inscripciones');
        });
        Restricciones::agregar('asistencias', [
            'chk_asist_estado' => "estado IN ('presente','ausente','tarde','justificado')",
        ]);

        Schema::create('tareas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('curso_profesor_id');
            $table->string('titulo', 150);
            $table->text('consigna')->nullable();
            $table->string('archivo_ruta')->nullable();
            $table->dateTime('fecha_entrega');
            $table->timestamps();
            $table->foreign('curso_profesor_id', 'fk_tarea_cp')->references('id')->on('curso_profesores');
        });

        Schema::create('entregas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tarea_id');
            $table->unsignedBigInteger('inscripcion_id');
            $table->string('archivo_ruta');
            $table->unsignedInteger('archivo_tamano');
            $table->string('archivo_tipo', 5);
            $table->dateTime('fecha_entrega');
            $table->boolean('fuera_de_termino')->default(false);   // lo calcula el trigger
            $table->text('devolucion')->nullable();
            $table->dateTime('devuelta_en')->nullable();
            $table->timestamps();
            $table->unique(['tarea_id', 'inscripcion_id'], 'uq_entrega');
            $table->foreign('tarea_id', 'fk_entrega_tarea')->references('id')->on('tareas')->cascadeOnDelete();
            $table->foreign('inscripcion_id', 'fk_entrega_insc')->references('id')->on('inscripciones');
        });
        Restricciones::agregar('entregas', [
            'chk_entrega_tam'  => 'archivo_tamano BETWEEN 1 AND 10485760',           // 10 MB (RNF16)
            'chk_entrega_tipo' => "archivo_tipo IN ('pdf','docx','jpg','png','zip')",  // RNF16
            'chk_entrega_dev'  => 'devolucion IS NULL OR devuelta_en IS NOT NULL',
        ]);

        Schema::create('avisos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('curso_id');
            $table->unsignedBigInteger('creado_por');
            $table->string('titulo', 150);
            $table->text('mensaje');
            $table->timestamps();
            $table->foreign('curso_id', 'fk_aviso_curso')->references('id')->on('cursos');
            $table->foreign('creado_por', 'fk_aviso_autor')->references('id')->on('users');
        });

        // curso_id null = evento institucional del calendario académico (HU10).
        Schema::create('eventos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('curso_id')->nullable();
            $table->unsignedBigInteger('tarea_id')->nullable();
            $table->string('titulo', 150);
            $table->text('descripcion')->nullable();
            $table->string('tipo', 15);
            $table->dateTime('fecha_inicio');
            $table->dateTime('fecha_fin')->nullable();
            $table->boolean('publico')->default(false);
            $table->unsignedBigInteger('creado_por');
            $table->timestamps();
            $table->index('fecha_inicio', 'idx_evento_fecha');
            $table->foreign('curso_id', 'fk_evento_curso')->references('id')->on('cursos');
            $table->foreign('tarea_id', 'fk_evento_tarea')->references('id')->on('tareas')->cascadeOnDelete();
            $table->foreign('creado_por', 'fk_evento_autor')->references('id')->on('users');
        });
        Restricciones::agregar('eventos', [
            'chk_evento_tipo'   => "tipo IN ('institucional','evaluacion','entrega','feriado','otro')",
            'chk_evento_fechas' => 'fecha_fin IS NULL OR fecha_fin >= fecha_inicio',
            'chk_evento_pub'    => 'curso_id IS NULL OR publico = FALSE',   // lo de un curso nunca es público
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos');
        Schema::dropIfExists('avisos');
        Schema::dropIfExists('entregas');
        Schema::dropIfExists('tareas');
        Schema::dropIfExists('asistencias');
        Schema::dropIfExists('clases');
        Schema::dropIfExists('evaluaciones');
    }
};
