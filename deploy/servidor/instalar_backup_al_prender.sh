#!/usr/bin/env bash
# Instala backup_al_prender.sh y lo agrega al cron. Uso: sudo bash instalar_backup_al_prender.sh
set -euo pipefail
USUARIO="${SUDO_USER:-azureuser}"
HOME_USR="$(getent passwd "$USUARIO" | cut -d: -f6)"
CARPETA="$(cd "$(dirname "$0")" && pwd)"
install -m 755 -o "$USUARIO" -g "$USUARIO" "$CARPETA/backup_al_prender.sh" "$HOME_USR/backup_al_prender.sh"
sed -i '/desactivada hasta instalar MySQL/d' /etc/cron.d/portal-nep30
grep -q backup_al_prender /etc/cron.d/portal-nep30 || cat >> /etc/cron.d/portal-nep30 <<CRON
# Al prender la VM: copia de seguridad si la última tiene más de 20 horas
@reboot $USUARIO /bin/bash $HOME_USR/backup_al_prender.sh >> $HOME_USR/backups/backup.log 2>&1
CRON
echo "Listo. Cron actual:"; cat /etc/cron.d/portal-nep30
