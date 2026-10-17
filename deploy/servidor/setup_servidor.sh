#!/usr/bin/env bash
# =====================================================================
# Portal N.E.P. 30 - TFI Grupo 15
# setup_servidor.sh - Deja una VM Ubuntu 24.04 lista para Laravel 13.
#
# Se corre UNA sola vez, recién creada la VM:
#   sudo bash setup_servidor.sh <dominio> <correo>
# Ejemplo:
#   sudo bash setup_servidor.sh portal-nep30-tfi.northcentralus.cloudapp.azure.com alexander@ejemplo.com
#
# Instala: PHP 8.4-FPM, Composer, Node 22, MySQL 8, Nginx, Supervisor,
# Certbot (HTTPS), fail2ban, firewall, swap, actualizaciones automáticas.
# Crea: la base portal_nep30, sus usuarios (claves al azar), el sitio de
# Nginx, el worker de colas, el cron de Laravel y el de la copia diaria.
# =====================================================================
set -euo pipefail

DOMINIO="${1:?Uso: sudo bash setup_servidor.sh <dominio> <correo>}"
CORREO="${2:?Falta el correo para el certificado HTTPS}"
USUARIO="${SUDO_USER:-azureuser}"           # el usuario con el que entrás por SSH
HOME_USR="$(getent passwd "$USUARIO" | cut -d: -f6)"
APP=/var/www/portal-nep30
DB=portal_nep30
CARPETA="$(cd "$(dirname "$0")" && pwd)"    # donde están los archivos de configuración
SIN_BD="${SIN_BD:-0}"                        # SIN_BD=1: no instala MySQL ni crea la base (se agrega después)

[ "$(id -u)" -eq 0 ] || { echo "Correr con sudo"; exit 1; }
paso(){ echo; echo "==> $*"; }

paso "Zona horaria y actualización del sistema"
timedatectl set-timezone America/Argentina/Buenos_Aires
export DEBIAN_FRONTEND=noninteractive
apt-get update -y && apt-get upgrade -y
apt-get install -y software-properties-common curl git unzip ufw fail2ban unattended-upgrades

paso "Swap de 2 GB (colchón por si falta memoria al compilar con npm)"
if ! swapon --show | grep -q /swapfile; then
    fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
    echo '/swapfile none swap sw 0 0' >> /etc/fstab
    sysctl -w vm.swappiness=10 && echo 'vm.swappiness=10' > /etc/sysctl.d/99-swap.conf
fi

paso "Firewall: solo SSH, HTTP y HTTPS"
ufw allow OpenSSH
ufw allow 'Nginx Full' 2>/dev/null || { ufw allow 80/tcp; ufw allow 443/tcp; }
ufw --force enable

paso "fail2ban: bloquea IPs que prueban claves por SSH"
systemctl enable --now fail2ban

paso "PHP 8.4 (repositorio de Ondřej Surý)"
add-apt-repository -y ppa:ondrej/php
apt-get update -y
apt-get install -y php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring php8.4-xml php8.4-curl \
    php8.4-zip php8.4-bcmath php8.4-intl php8.4-gd php8.4-readline php8.4-opcache
install -m 644 "$CARPETA/php-produccion.ini" /etc/php/8.4/fpm/conf.d/99-portal.ini
install -m 644 "$CARPETA/php-produccion.ini" /etc/php/8.4/cli/conf.d/99-portal.ini
sed -i 's/^pm.max_children = .*/pm.max_children = 12/' /etc/php/8.4/fpm/pool.d/www.conf
systemctl restart php8.4-fpm

paso "Composer (verificando la firma del instalador)"
if ! command -v composer >/dev/null; then
    ESPERADA="$(curl -fsSL https://composer.github.io/installer.sig)"
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    REAL="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
    [ "$ESPERADA" = "$REAL" ] || { echo "Firma de Composer inválida"; exit 1; }
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer && rm /tmp/composer-setup.php
fi

paso "Node 22 (para compilar Tailwind/Vite)"
if ! command -v node >/dev/null; then
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
    apt-get install -y nodejs
fi

if [ "$SIN_BD" != "1" ]; then
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
else
paso "MySQL: se omite (SIN_BD=1). Se agrega después con: sudo bash instalar_mysql.sh"
fi

paso "Carpeta del sistema y página provisoria"
mkdir -p "$APP/public"
[ -f "$APP/public/index.html" ] || echo '<!doctype html><meta charset="utf-8"><title>Portal N.E.P. 30</title><h1>Portal N.E.P. 30 — en construcción</h1>' > "$APP/public/index.html"
chown -R "$USUARIO:www-data" "$APP"
usermod -aG www-data "$USUARIO"

paso "Nginx"
apt-get install -y nginx
sed "s/__DOMINIO__/$DOMINIO/g" "$CARPETA/nginx-portal-nep30.conf" > /etc/nginx/sites-available/portal-nep30
ln -sf /etc/nginx/sites-available/portal-nep30 /etc/nginx/sites-enabled/portal-nep30
rm -f /etc/nginx/sites-enabled/default
sed -i 's/# server_tokens off;/server_tokens off;/' /etc/nginx/nginx.conf
nginx -t && systemctl reload nginx

paso "HTTPS con Let's Encrypt (se renueva solo)"
apt-get install -y certbot python3-certbot-nginx
certbot --nginx -d "$DOMINIO" -m "$CORREO" --agree-tos --redirect --non-interactive

paso "Supervisor: worker de la cola de correos"
apt-get install -y supervisor
install -m 644 "$CARPETA/supervisor-portal-nep30-worker.conf" /etc/supervisor/conf.d/portal-nep30-worker.conf
supervisorctl reread && supervisorctl update || true   # arranca recién cuando exista artisan

paso "Cron: tareas programadas de Laravel y copia de seguridad diaria"
install -m 755 "$CARPETA/backup.sh" "$HOME_USR/backup.sh"
chown "$USUARIO:$USUARIO" "$HOME_USR/backup.sh"
sed -i "s#^ARCHIVOS_APP=.*#ARCHIVOS_APP=\"$APP/storage/app\"#" "$HOME_USR/backup.sh"
cat > /etc/cron.d/portal-nep30 <<EOF
# Tareas programadas de Laravel (cada minuto)
* * * * * www-data cd $APP && [ -f artisan ] && php artisan schedule:run >> /dev/null 2>&1
# Copia de seguridad: 00:30, antes del apagado automático de la VM (01:00)
$( [ "$SIN_BD" = "1" ] && echo "# (copia de seguridad desactivada hasta instalar MySQL)" || echo "30 0 * * * $USUARIO /bin/bash $HOME_USR/backup.sh >> $HOME_USR/backups/backup.log 2>&1" )
EOF
mkdir -p "$HOME_USR/backups" && chown "$USUARIO:$USUARIO" "$HOME_USR/backups"

paso "Permiso para que el deploy recargue PHP y el worker sin pedir clave"
cat > /etc/sudoers.d/portal-nep30-deploy <<EOF
$USUARIO ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.4-fpm, /usr/bin/supervisorctl restart portal-nep30-worker\:*
EOF
chmod 440 /etc/sudoers.d/portal-nep30-deploy && visudo -cf /etc/sudoers.d/portal-nep30-deploy

paso "Actualizaciones de seguridad automáticas"
dpkg-reconfigure -f noninteractive unattended-upgrades

echo
echo "============================================================"
echo " Servidor listo: https://$DOMINIO"
echo " Versiones: PHP $(php -r 'echo PHP_VERSION;') · node $(node -v) · MySQL: $(command -v mysql >/dev/null && mysql --version | awk '{print $3}' || echo 'no instalado')"
[ "$SIN_BD" = "1" ] || echo " Claves de MySQL: $HOME_USR/LEEME_claves_nep30.txt (guardalas y borrá el archivo)"
echo " Próximo paso: el primer deploy (ver la guía, paso 7)."
echo "============================================================"
