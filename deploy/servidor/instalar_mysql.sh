#!/usr/bin/env bash
# =====================================================================
# Portal N.E.P. 30 - instalar_mysql.sh
# Agrega MySQL 8, la base portal_nep30 y sus usuarios a un servidor que se
# preparó con SIN_BD=1. También activa la copia de seguridad diaria.
# Uso (en la VM, dentro de ~/servidor):  sudo bash instalar_mysql.sh
# =====================================================================
set -euo pipefail
USUARIO="${SUDO_USER:-azureuser}"
HOME_USR="$(getent passwd "$USUARIO" | cut -d: -f6)"
DB=portal_nep30
CARPETA="$(cd "$(dirname "$0")" && pwd)"
export DEBIAN_FRONTEND=noninteractive
[ "$(id -u)" -eq 0 ] || { echo "Correr con sudo"; exit 1; }
paso(){ echo; echo "==> $*"; }
paso "MySQL 8"
apt-get install -y mysql-server
install -m 644 "$CARPETA/mysql-portal.cnf" /etc/mysql/mysql.conf.d/99-portal.cnf
systemctl restart mysql

paso "Base de datos y usuarios con privilegio mínimo (claves al azar)"
clave(){ openssl rand -base64 24 | tr -d '/+=' | cut -c1-24; }
P_MIG="$(clave)"; P_APP="$(clave)"; P_BAK="$(clave)"
mysql <<SQL
DELETE FROM mysql.user WHERE User='';
DROP DATABASE IF EXISTS test;
CREATE DATABASE IF NOT EXISTS $DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'nep30_migraciones'@'localhost' IDENTIFIED BY '$P_MIG' WITH MAX_USER_CONNECTIONS 3;
CREATE USER IF NOT EXISTS 'nep30_app'@'localhost'         IDENTIFIED BY '$P_APP' WITH MAX_USER_CONNECTIONS 30;
CREATE USER IF NOT EXISTS 'nep30_backup'@'localhost'      IDENTIFIED BY '$P_BAK' WITH MAX_USER_CONNECTIONS 2;
ALTER USER 'nep30_migraciones'@'localhost' IDENTIFIED BY '$P_MIG';
ALTER USER 'nep30_app'@'localhost'         IDENTIFIED BY '$P_APP';
ALTER USER 'nep30_backup'@'localhost'      IDENTIFIED BY '$P_BAK';
GRANT ALL PRIVILEGES ON $DB.* TO 'nep30_migraciones'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE TEMPORARY TABLES ON $DB.* TO 'nep30_app'@'localhost';
GRANT SELECT, SHOW VIEW, TRIGGER, LOCK TABLES, EVENT ON $DB.* TO 'nep30_backup'@'localhost';
FLUSH PRIVILEGES;
SQL
# Las claves quedan en archivos que solo lee tu usuario (permiso 600), nunca en el repositorio.
umask 077
cat > "$HOME_USR/.nep30_backup.cnf" <<EOF
[client]
user=nep30_backup
password=$P_BAK
host=localhost
EOF
cat > "$HOME_USR/.nep30_migraciones.env" <<EOF
DB_USERNAME=nep30_migraciones
DB_PASSWORD=$P_MIG
EOF
cat > "$HOME_USR/LEEME_claves_nep30.txt" <<EOF
Claves generadas el $(date '+%F %T'). Guardalas en un gestor de contraseñas y borrá este archivo.
  nep30_app          $P_APP   -> va en DB_PASSWORD del .env de Laravel
  nep30_migraciones  $P_MIG   -> ya está en ~/.nep30_migraciones.env (lo usa deploy.sh)
  nep30_backup       $P_BAK   -> ya está en ~/.nep30_backup.cnf (lo usa backup.sh)
EOF
chown "$USUARIO:$USUARIO" "$HOME_USR/.nep30_backup.cnf" "$HOME_USR/.nep30_migraciones.env" "$HOME_USR/LEEME_claves_nep30.txt"
umask 022

paso "Copia de seguridad diaria a las 00:30"
grep -q backup.sh /etc/cron.d/portal-nep30 || \
  echo "30 0 * * * $USUARIO /bin/bash $HOME_USR/backup.sh >> $HOME_USR/backups/backup.log 2>&1" >> /etc/cron.d/portal-nep30

echo
echo "MySQL $(mysql --version | awk '{print $3}') listo. Claves: $HOME_USR/LEEME_claves_nep30.txt (guardalas y borrá el archivo)"
