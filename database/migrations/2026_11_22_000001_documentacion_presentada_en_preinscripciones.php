<?php

use App\Support\Restricciones;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #107 (pedido de la institución, 02/11): registrar que el aspirante presentó la documentación en papel
 * antes de darle el alta. El sistema NO guarda copias de los documentos (Ley 25.326): solo cuándo,
 * quién lo registró y una observación opcional.
 * También se suma la documentación a la auditoría de preinscripciones (trg_preinsc_au, HU16).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preinscripciones', function (Blueprint $table) {
            $table->dateTime('documentacion_en')->nullable()->after('avisado_por');
            $table->unsignedBigInteger('documentacion_por')->nullable()->after('documentacion_en');
            $table->string('documentacion_obs', 300)->nullable()->after('documentacion_por');
            $table->foreign('documentacion_por', 'fk_preinsc_doc')->references('id')->on('users');
        });

        Restricciones::agregar('preinscripciones', [
            // Fecha y usuario van juntos, y solo en una preinscripción aprobada
            'chk_preinsc_doc'     => '(documentacion_en IS NULL) = (documentacion_por IS NULL)',
            'chk_preinsc_doc_est' => "documentacion_en IS NULL OR estado = 'aprobada'",
        ]);

        DB::unprepared('DROP TRIGGER IF EXISTS trg_preinsc_au');
        DB::unprepared(self::trigger(conDocumentacion: true));
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_preinsc_au');
        DB::unprepared(self::trigger(conDocumentacion: false));

        DB::statement('ALTER TABLE preinscripciones DROP CONSTRAINT chk_preinsc_doc_est');
        DB::statement('ALTER TABLE preinscripciones DROP CONSTRAINT chk_preinsc_doc');
        Schema::table('preinscripciones', function (Blueprint $table) {
            $table->dropForeign('fk_preinsc_doc');
            $table->dropColumn(['documentacion_en', 'documentacion_por', 'documentacion_obs']);
        });
    }

    /** El trigger de auditoría de preinscripciones; sin documentación es el original (triggers_v4.4.sql) */
    private static function trigger(bool $conDocumentacion): string
    {
        $cambio = $conDocumentacion
            ? ' OR NOT (OLD.documentacion_en <=> NEW.documentacion_en) OR NOT (OLD.documentacion_obs <=> NEW.documentacion_obs)'
            : '';
        $antes = $conDocumentacion ? ", 'documentacion_en', OLD.documentacion_en, 'documentacion_obs', OLD.documentacion_obs" : '';
        $despues = $conDocumentacion ? ", 'documentacion_en', NEW.documentacion_en, 'documentacion_obs', NEW.documentacion_obs" : '';

        return <<<SQL
            CREATE TRIGGER trg_preinsc_au AFTER UPDATE ON preinscripciones
            FOR EACH ROW
            BEGIN
                IF NOT (OLD.estado <=> NEW.estado) OR NOT (OLD.avisado_en <=> NEW.avisado_en){$cambio} THEN
                    INSERT INTO auditoria (usuario_id, accion, tabla, registro_id, valores_anteriores, valores_nuevos, ip)
                    VALUES (@app_usuario_id, 'modificacion', 'preinscripciones', NEW.id,
                            JSON_OBJECT('estado', OLD.estado, 'avisado_en', OLD.avisado_en{$antes}),
                            JSON_OBJECT('estado', NEW.estado, 'observacion', NEW.observacion, 'revisado_por', NEW.revisado_por,
                                        'avisado_en', NEW.avisado_en{$despues}),
                            @app_ip);
                END IF;
            END
            SQL;
    }
};