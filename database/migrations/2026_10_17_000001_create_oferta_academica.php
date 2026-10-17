<?php

use App\Support\Restricciones;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Oferta académica (modelo v4.4, sección 4). RF03, RF15.
 * Todo lo carga y lo cambia el Administrador desde el panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periodos_lectivos', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('cuatrimestre')->nullable();   // null = anual
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->string('estado', 10)->default('activo');
            $table->timestamps();
            $table->unique(['anio', 'cuatrimestre'], 'uq_periodo');
        });
        Restricciones::agregar('periodos_lectivos', [
            'chk_periodo_anio'   => 'anio BETWEEN 2020 AND 2100',
            'chk_periodo_cuat'   => 'cuatrimestre IS NULL OR cuatrimestre IN (1,2)',
            'chk_periodo_estado' => "estado IN ('activo','cerrado')",
            'chk_periodo_fechas' => 'fecha_fin > fecha_inicio',
        ]);

        // Cada plan guarda sus reglas (entrevista 13/10): edad mínima 18,
        // se acredita con 6 o más y con 80 % de asistencia.
        Schema::create('planes_estudio', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150)->unique();
            $table->string('tipo', 25);
            $table->text('descripcion')->nullable();
            $table->unsignedTinyInteger('duracion_meses')->nullable();
            $table->unsignedTinyInteger('cantidad_ciclos')->nullable();  // terminalidad: a confirmar
            $table->string('resolucion', 100)->nullable();
            $table->unsignedTinyInteger('edad_minima')->default(18);
            $table->decimal('nota_aprobacion', 4, 2)->default(6.00);
            $table->unsignedTinyInteger('asistencia_minima')->default(80);  // porcentaje
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Restricciones::agregar('planes_estudio', [
            'chk_plan_tipo'   => "tipo IN ('formacion_profesional','terminalidad')",
            'chk_plan_ciclos' => 'cantidad_ciclos IS NULL OR cantidad_ciclos BETWEEN 1 AND 6',
            'chk_plan_edad'   => 'edad_minima BETWEEN 14 AND 99',
            'chk_plan_nota'   => 'nota_aprobacion BETWEEN 1 AND 10',
            'chk_plan_asist'  => 'asistencia_minima BETWEEN 0 AND 100',
        ]);

        // Materias o módulos de cada plan (en FP los módulos se acreditan).
        Schema::create('materias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id');
            $table->string('nombre', 150);
            $table->unsignedTinyInteger('ciclo')->nullable();
            $table->unsignedSmallInteger('carga_horaria')->nullable();
            $table->boolean('es_obligatoria')->default(true);
            $table->timestamps();
            $table->unique(['plan_id', 'nombre'], 'uq_materia');
            $table->foreign('plan_id', 'fk_materia_plan')->references('id')->on('planes_estudio');
        });
        Restricciones::agregar('materias', [
            'chk_materia_ciclo' => 'ciclo IS NULL OR ciclo BETWEEN 1 AND 6',
        ]);

        // Correlatividades (opcional: si la institución no las usa, queda vacía).
        Schema::create('materia_correlativas', function (Blueprint $table) {
            $table->unsignedBigInteger('materia_id');
            $table->unsignedBigInteger('requiere_materia_id');
            $table->primary(['materia_id', 'requiere_materia_id']);
            $table->foreign('materia_id', 'fk_corr_materia')->references('id')->on('materias')->cascadeOnDelete();
            $table->foreign('requiere_materia_id', 'fk_corr_requiere')->references('id')->on('materias')->cascadeOnDelete();
        });
        Restricciones::agregar('materia_correlativas', [
            'chk_corr_distinta' => 'materia_id <> requiere_materia_id',
        ]);

        // Curso = un plan dictado en una sede, en un período, turno y horario, con su cupo.
        Schema::create('cursos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id');
            $table->unsignedBigInteger('sede_id');
            $table->unsignedBigInteger('periodo_id');
            $table->string('turno', 10);
            $table->string('dias', 60)->nullable();       // p. ej. "Lunes a viernes"
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();
            $table->unsignedSmallInteger('cupo');
            $table->string('estado', 10)->default('activo');
            $table->timestamps();
            $table->unique(['plan_id', 'sede_id', 'periodo_id', 'turno'], 'uq_curso');
            $table->foreign('plan_id', 'fk_curso_plan')->references('id')->on('planes_estudio');
            $table->foreign('sede_id', 'fk_curso_sede')->references('id')->on('sedes');
            $table->foreign('periodo_id', 'fk_curso_periodo')->references('id')->on('periodos_lectivos');
        });
        Restricciones::agregar('cursos', [
            'chk_curso_turno'  => "turno IN ('mañana','tarde','noche')",
            'chk_curso_estado' => "estado IN ('activo','inactivo','finalizado')",
            'chk_curso_cupo'   => 'cupo BETWEEN 1 AND 500',
            'chk_curso_horas'  => 'hora_inicio IS NULL OR hora_fin IS NULL OR hora_fin > hora_inicio',
        ]);

        // Profesores de cada curso y materia (puede haber dos en un mismo taller).
        Schema::create('curso_profesores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('curso_id');
            $table->unsignedBigInteger('materia_id');
            $table->unsignedBigInteger('profesor_id');
            $table->timestamps();
            $table->unique(['curso_id', 'materia_id', 'profesor_id'], 'uq_curso_prof');
            $table->index('profesor_id', 'idx_cp_profesor');
            $table->foreign('curso_id', 'fk_cp_curso')->references('id')->on('cursos');
            $table->foreign('materia_id', 'fk_cp_materia')->references('id')->on('materias');
            $table->foreign('profesor_id', 'fk_cp_profesor')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curso_profesores');
        Schema::dropIfExists('cursos');
        Schema::dropIfExists('materia_correlativas');
        Schema::dropIfExists('materias');
        Schema::dropIfExists('planes_estudio');
        Schema::dropIfExists('periodos_lectivos');
    }
};
