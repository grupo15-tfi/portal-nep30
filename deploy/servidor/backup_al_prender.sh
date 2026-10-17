#!/usr/bin/env bash
# Portal N.E.P. 30 - Copia de seguridad al prender la VM.
# La VM se apaga a mano: si estuvo apagada a las 00:30, ese día no hubo copia.
# Este script corre al arrancar (cron @reboot) y hace la copia solo si la
# última tiene más de 20 horas.
set -u
DESTINO="$HOME/backups"
# Esperar a que MySQL termine de arrancar (hasta 3 minutos)
for i in $(seq 1 36); do
    mysqladmin --defaults-extra-file="$HOME/.nep30_backup.cnf" ping >/dev/null 2>&1 && break
    sleep 5
done
ULTIMA=$(find "$DESTINO" -maxdepth 1 -name 'portal_nep30_*.sql.gz' -mmin -1200 | head -1)
if [ -z "$ULTIMA" ]; then
    echo "$(date '+%F %T') Al prender: la última copia tiene más de 20 h, se hace una nueva"
    /bin/bash "$HOME/backup.sh"
else
    echo "$(date '+%F %T') Al prender: copia reciente ($(basename "$ULTIMA")), no hace falta otra"
fi
