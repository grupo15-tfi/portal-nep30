<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Auditoría de las correcciones de asistencia (HU16, revisión de los wireframes del 18/10).
 * Cuando un profesor cambia el estado de una asistencia (por ejemplo, ausente -> justificado),
 * queda en la tabla auditoria con el valor anterior, el nuevo, el usuario y la IP.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_asist_au AFTER UPDATE ON asistencias
            FOR EACH ROW
            BEGIN
                IF NOT (OLD.estado <=> NEW.estado) THEN
                    INSERT INTO auditoria (usuario_id, accion, tabla, registro_id, valores_anteriores, valores_nuevos, ip)
                    VALUES (@app_usuario_id, 'modificacion', 'asistencias', NEW.id,
                            JSON_OBJECT('estado', OLD.estado, 'clase_id', OLD.clase_id, 'inscripcion_id', OLD.inscripcion_id),
                            JSON_OBJECT('estado', NEW.estado), @app_ip);
                END IF;
            END
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_asist_au');
    }
};
