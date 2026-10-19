<?php

use App\Support\Restricciones;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preinscripción, lista de espera e inscripción (modelo v4.4, sección 5).
 * RF06, RF09, RF17, RF18.
 *
 * Estados de la preinscripción: pendiente -> aprobada | rechazada | en_espera
 *                               en_espera -> pendiente | aprobada | rechazada
 * Si el curso no tiene cupo al enviarla, la base la deja "en_espera" (trigger).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preinscripciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('curso_id');
            $table->string('dni', 8);
            $table->string('apellido', 100);
            $table->string('nombre', 100);
            $table->date('fecha_nacimiento');                 // edad mínima del plan (18)
            $table->string('email', 150);
            $table->string('telefono', 30);                   // la lista de espera se avisa por teléfono
            $table->string('domicilio', 200)->nullable();
            $table->decimal('latitud', 10, 7)->nullable();    // resultado de Nominatim (RF05)
            $table->decimal('longitud', 10, 7)->nullable();
            $table->string('nivel_educativo', 25)->nullable();
            $table->unsignedTinyInteger('ultimo_ciclo_aprobado')->nullable();
            $table->string('estado', 10)->default('pendiente');
            $table->string('observacion', 500)->nullable();
            $table->boolean('acepta_privacidad');             // Ley 25.326 (RNF15)
            $table->unsignedBigInteger('revisado_por')->nullable();
            $table->dateTime('revisado_en')->nullable();
            $table->dateTime('avisado_en')->nullable();       // último aviso telefónico de la lista de espera
            $table->unsignedBigInteger('avisado_por')->nullable();
            $table->timestamps();
            // Una sola solicitud ABIERTA (pendiente o en espera) por DNI y curso.
            $table->tinyInteger('abierta_flag')->nullable()
                ->storedAs("IF(estado IN ('pendiente','en_espera'), 1, NULL)");
            $table->unique(['dni', 'curso_id', 'abierta_flag'], 'uq_preinsc_abierta');
            $table->index(['estado', 'created_at'], 'idx_preinsc_estado');
            $table->index(['curso_id', 'estado', 'created_at'], 'idx_preinsc_espera');
            $table->index('dni', 'idx_preinsc_dni');
            $table->foreign('curso_id', 'fk_preinsc_curso')->references('id')->on('cursos');
            $table->foreign('revisado_por', 'fk_preinsc_revisor')->references('id')->on('users');
            $table->foreign('avisado_por', 'fk_preinsc_aviso')->references('id')->on('users');
        });
        Restricciones::agregar('preinscripciones', [
            'chk_preinsc_estado' => "estado IN ('pendiente','en_espera','aprobada','rechazada')",
            'chk_preinsc_dni'    => "dni REGEXP '^[0-9]{7,8}$'",
            'chk_preinsc_email'  => "email LIKE '%_@_%._%'",
            'chk_preinsc_tel'    => "telefono REGEXP '^[0-9 +()-]{6,30}$'",
            'chk_preinsc_nac'    => "fecha_nacimiento >= '1900-01-01'",
            'chk_preinsc_nivel'  => "nivel_educativo IS NULL OR nivel_educativo IN ('sin_escolaridad','primaria_incompleta',"
                                  ."'primaria_completa','secundaria_incompleta','secundaria_completa','superior')",
            'chk_preinsc_priv'   => 'acepta_privacidad = TRUE',
            'chk_preinsc_coord'  => '(latitud IS NULL AND longitud IS NULL) OR '
                                  .'(latitud BETWEEN -90 AND 90 AND longitud BETWEEN -180 AND 180)',
            // Revisada = quién y cuándo (HU09); el rechazo exige el motivo.
            'chk_preinsc_revis'  => "estado IN ('pendiente','en_espera') OR (revisado_por IS NOT NULL AND revisado_en IS NOT NULL)",
            'chk_preinsc_motivo' => "estado <> 'rechazada' OR observacion IS NOT NULL",
            'chk_preinsc_aviso'  => '(avisado_en IS NULL) = (avisado_por IS NULL)',
        ]);

        Schema::create('inscripciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('alumno_id');
            $table->unsignedBigInteger('curso_id');
            $table->unsignedBigInteger('preinscripcion_id')->nullable()->unique();
            $table->date('fecha_inscripcion');
            $table->string('estado', 12)->default('activa');
            $table->date('fecha_baja')->nullable();
            $table->string('motivo_baja', 300)->nullable();
            $table->timestamps();
            $table->unique(['alumno_id', 'curso_id'], 'uq_inscripcion');
            $table->index(['curso_id', 'estado'], 'idx_insc_curso_estado');
            $table->foreign('alumno_id', 'fk_insc_alumno')->references('id')->on('users');
            $table->foreign('curso_id', 'fk_insc_curso')->references('id')->on('cursos');
            $table->foreign('preinscripcion_id', 'fk_insc_preinsc')->references('id')->on('preinscripciones');
        });
        Restricciones::agregar('inscripciones', [
            'chk_insc_estado' => "estado IN ('activa','baja','finalizada')",
            'chk_insc_baja'   => "estado <> 'baja' OR fecha_baja IS NOT NULL",
            'chk_insc_fbaja'  => 'fecha_baja IS NULL OR fecha_baja >= fecha_inscripcion',
        ]);

        // Módulos o materias que cursa cada inscripto. 'acreditada' exige nota y
        // asistencia mínimas del plan (lo controla el trigger).
        Schema::create('inscripcion_materias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inscripcion_id');
            $table->unsignedBigInteger('materia_id');
            $table->string('estado', 12)->default('cursando');
            $table->decimal('nota_final', 4, 2)->nullable();
            $table->date('fecha_acreditacion')->nullable();
            $table->timestamps();
            $table->unique(['inscripcion_id', 'materia_id'], 'uq_insc_materia');
            $table->foreign('inscripcion_id', 'fk_im_insc')->references('id')->on('inscripciones');
            $table->foreign('materia_id', 'fk_im_materia')->references('id')->on('materias');
        });
        Restricciones::agregar('inscripcion_materias', [
            'chk_im_estado' => "estado IN ('cursando','acreditada','pendiente','libre')",
            'chk_im_nota'   => 'nota_final IS NULL OR nota_final BETWEEN 1 AND 10',
            'chk_im_acred'  => "estado <> 'acreditada' OR (nota_final IS NOT NULL AND fecha_acreditacion IS NOT NULL)",
            'chk_im_fecha'  => "estado = 'acreditada' OR fecha_acreditacion IS NULL",
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('inscripcion_materias');
        Schema::dropIfExists('inscripciones');
        Schema::dropIfExists('preinscripciones');
    }
};
