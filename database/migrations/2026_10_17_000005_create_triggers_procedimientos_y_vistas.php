<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reglas de negocio que protege la propia base (modelo v4.4, secciones 9 y 10):
 * 2 procedimientos, 38 triggers y 7 vistas. El SQL está en database/sql/.
 *
 * Requiere que el usuario de migraciones pueda crear triggers: en un servidor
 * con binlog activo hace falta log_bin_trust_function_creators = 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        // "migrate:fresh" borra las tablas (y con ellas los triggers) pero no los procedimientos.
        DB::unprepared('DROP PROCEDURE IF EXISTS validar_acreditacion');
        DB::unprepared('DROP PROCEDURE IF EXISTS exigir_escritura');

        // Se ejecutan de a una sentencia para que, si una falla, el error diga cuál.
        $this->ejecutar('sql/triggers_v4.4.sql', '/\/\/\s*(\n|$)/');
        $this->ejecutar('sql/vistas_v4.4.sql', '/;\s*(\n|$)/');
    }

    private function ejecutar(string $archivo, string $separador): void
    {
        $sql = file_get_contents(database_path($archivo));
        foreach (preg_split($separador, $sql) as $sentencia) {
            $sinComentarios = trim(preg_replace('/^\s*--.*$/m', '', $sentencia));
            if ($sinComentarios !== '') {
                DB::unprepared($sentencia);
            }
        }
    }

    public function down(): void
    {
        $vistas = DB::select("SELECT TABLE_NAME AS n FROM information_schema.VIEWS WHERE TABLE_SCHEMA = DATABASE()");
        foreach ($vistas as $v) {
            DB::statement("DROP VIEW IF EXISTS `{$v->n}`");
        }
        $triggers = DB::select("SELECT TRIGGER_NAME AS n FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()");
        foreach ($triggers as $t) {
            DB::unprepared("DROP TRIGGER IF EXISTS `{$t->n}`");
        }
        DB::unprepared('DROP PROCEDURE IF EXISTS validar_acreditacion');
        DB::unprepared('DROP PROCEDURE IF EXISTS exigir_escritura');
    }
};
