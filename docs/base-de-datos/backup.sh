#!/usr/bin/env bash
# =====================================================================
# TFI Grupo 15 - backup.sh - Copia de seguridad diaria (RNF17)
#
# Qué hace:
#   1. Vuelca la base completa (datos, triggers y vistas) sin bloquear el sitio.
#   2. Comprime los archivos subidos (tareas, entregas e imágenes).
#   3. Verifica que la copia se pueda leer.
#   4. Borra las copias de más de 30 días.
#   5. Los domingos deja una copia aparte para llevar FUERA del servidor.
#
# Configuración (una sola vez, en el servidor):
#   a) Crear ~/.nep30_backup.cnf con permisos 600 (solo lo lee su dueño):
#        [client]
#        user=nep30_backup
#        password=LA_CLAVE_DE_BACKUP
#        host=localhost
#      chmod 600 ~/.nep30_backup.cnf
#      Así la contraseña no aparece en el comando ni en la lista de procesos.
#   b) Ajustar DESTINO y ARCHIVOS_APP abajo.
#   c) Programarlo en el cron del hosting (cPanel > Trabajos de cron), todos los días 03:15:
#        15 3 * * * /bin/bash /home/USUARIO/backup.sh >> /home/USUARIO/backups/backup.log 2>&1
#
# Restaurar una copia (en una base vacía):
#   gunzip -c portal_nep30_2026-11-25.sql.gz | mysql -u nep30_migraciones -p portal_nep30
# =====================================================================
set -euo pipefail
umask 077                                       # las copias solo las lee su dueño

DB=portal_nep30
CNF="$HOME/.nep30_backup.cnf"
DESTINO="$HOME/backups"
ARCHIVOS_APP="$HOME/portal-nep30/storage/app"   # carpeta de archivos subidos de Laravel
DIAS=30
FECHA=$(date +%F)

mkdir -p "$DESTINO/semanal"

# 1. Base de datos: --single-transaction copia sin frenar el sitio (InnoDB).
mysqldump --defaults-extra-file="$CNF" \
    --single-transaction --quick --routines --triggers --events \
    --no-tablespaces --default-character-set=utf8mb4 \
    "$DB" | gzip -9 > "$DESTINO/${DB}_${FECHA}.sql.gz"

# 2. Archivos subidos por los usuarios.
if [ -d "$ARCHIVOS_APP" ]; then
    tar -czf "$DESTINO/archivos_${FECHA}.tar.gz" -C "$(dirname "$ARCHIVOS_APP")" "$(basename "$ARCHIVOS_APP")"
fi

# 3. Verificación: el .gz no está dañado y el volcado terminó bien.
gzip -t "$DESTINO/${DB}_${FECHA}.sql.gz"
if ! gunzip -c "$DESTINO/${DB}_${FECHA}.sql.gz" | tail -n 1 | grep -q "Dump completed"; then
    echo "$(date '+%F %T') ERROR: el volcado de $FECHA está incompleto" >&2
    exit 1
fi

# 4. Retención de 30 días.
find "$DESTINO" -maxdepth 1 -type f -name "*.gz" -mtime +"$DIAS" -delete

# 5. Domingos: copia semanal para llevar fuera del servidor (Google Drive de la
#    institución, pendrive, etc.). Si el hosting tiene rclone, se puede subir
#    automáticamente:  rclone copy "$DESTINO/semanal" drive:backups-nep30
if [ "$(date +%u)" = "7" ]; then
    cp "$DESTINO/${DB}_${FECHA}.sql.gz" "$DESTINO/semanal/"
    [ -f "$DESTINO/archivos_${FECHA}.tar.gz" ] && cp "$DESTINO/archivos_${FECHA}.tar.gz" "$DESTINO/semanal/"
    find "$DESTINO/semanal" -type f -name "*.gz" -mtime +90 -delete
fi

echo "$(date '+%F %T') OK: copia ${FECHA} ($(du -h "$DESTINO/${DB}_${FECHA}.sql.gz" | cut -f1))"
