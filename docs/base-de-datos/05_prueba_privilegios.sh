#!/usr/bin/env bash
# =====================================================================
# TFI Grupo 15 - 05_prueba_privilegios.sh - Verifica 02_seguridad.sql
#
# Se conecta con cada usuario de MySQL e intenta cosas que DEBE poder hacer
# y cosas que NO debe poder hacer. Solo lectura o cambios que se deshacen.
#
# Uso (en el servidor o en la PC de desarrollo):
#   APP_PASS='...' LECTURA_PASS='...' BACKUP_PASS='...' bash 05_prueba_privilegios.sh
# =====================================================================
set -u
DB=portal_nep30
ok=0; falla=0

prueba() {   # prueba <usuario> <clave> <debe: si|no> <descripción> <sql>
    local usuario=$1 clave=$2 debe=$3 desc=$4 sql=$5 salida
    salida=$(MYSQL_PWD="$clave" mysql -u "$usuario" -h localhost -N -e "$sql" "$DB" 2>&1)
    local estado=$?
    if { [ "$debe" = si ] && [ $estado -eq 0 ]; } || { [ "$debe" = no ] && [ $estado -ne 0 ]; }; then
        echo "OK     $usuario: $desc"; ok=$((ok+1))
    else
        echo "FALLA  $usuario: $desc -> $salida"; falla=$((falla+1))
    fi
}

A="${APP_PASS:?Falta APP_PASS}"; L="${LECTURA_PASS:?Falta LECTURA_PASS}"; B="${BACKUP_PASS:?Falta BACKUP_PASS}"

# --- nep30_app (Laravel) ---
prueba nep30_app "$A" si "lee la oferta pública"            "SELECT COUNT(*) FROM v_oferta_publica"
prueba nep30_app "$A" si "lee usuarios (para el login)"     "SELECT COUNT(*) FROM users"
prueba nep30_app "$A" si "escribe datos (en transacción)"   "START TRANSACTION; UPDATE sedes SET telefono = telefono; ROLLBACK;"
prueba nep30_app "$A" si "agrega a la auditoría"            "START TRANSACTION; INSERT INTO auditoria (accion, tabla) VALUES ('login','users'); ROLLBACK;"
prueba nep30_app "$A" no "modifica la auditoría"            "UPDATE auditoria SET tabla = 'x' WHERE id = 0"
prueba nep30_app "$A" no "borra la auditoría"               "DELETE FROM auditoria WHERE id = 0"
prueba nep30_app "$A" si "lee la lista de espera"           "SELECT COUNT(*) FROM v_lista_espera"
prueba nep30_app "$A" no "borra una tabla (DROP)"           "DROP TABLE noticias"
prueba nep30_app "$A" no "crea una tabla"                   "CREATE TABLE x (id INT)"
prueba nep30_app "$A" no "modifica una tabla (ALTER)"       "ALTER TABLE users ADD COLUMN x INT"
prueba nep30_app "$A" no "crea usuarios de MySQL"           "CREATE USER 'intruso'@'%' IDENTIFIED BY 'x'"
prueba nep30_app "$A" no "entra a la base del sistema"      "SELECT COUNT(*) FROM mysql.user"

# --- nep30_lectura (informes) ---
prueba nep30_lectura "$L" si "lee la vista de cupos"        "SELECT COUNT(*) FROM v_cupos_disponibles"
prueba nep30_lectura "$L" no "lee la tabla users"           "SELECT password FROM users LIMIT 1"
prueba nep30_lectura "$L" no "escribe datos"                "UPDATE sedes SET telefono = telefono"
prueba nep30_lectura "$L" no "lee el historial con DNI"     "SELECT dni FROM v_historial_academico LIMIT 1"
prueba nep30_lectura "$L" no "lee la lista de espera (teléfonos)" "SELECT telefono FROM v_lista_espera LIMIT 1"

# --- nep30_backup (copia de seguridad) ---
prueba nep30_backup "$B" si "lee todas las tablas"          "SELECT COUNT(*) FROM users"
prueba nep30_backup "$B" no "escribe datos"                 "UPDATE sedes SET telefono = telefono"
if MYSQL_PWD="$B" mysqldump -u nep30_backup --single-transaction --no-tablespaces --routines --triggers "$DB" > /dev/null 2>&1; then
    echo "OK     nep30_backup: mysqldump completo"; ok=$((ok+1))
else
    echo "FALLA  nep30_backup: mysqldump completo"; falla=$((falla+1))
fi

echo "-------------------------------------------"
echo "Total: $((ok+falla))   OK: $ok   FALLAS: $falla"
[ $falla -eq 0 ]
