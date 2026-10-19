#!/usr/bin/env bash
# Portal N.E.P. 30 - deploy en la VM (lo ejecuta GitHub Actions por SSH al integrar en main).
# El bloque { } hace que bash lea todo el script antes de ejecutarlo, porque
# git reset lo actualiza mientras corre.
{
set -euo pipefail
cd /var/www/portal-nep30

php artisan down --render="errors::503" --retry=15 || true

git fetch origin main
git reset --hard origin/main

composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build

# Las migraciones (tablas, triggers y vistas) se corren con el usuario de MySQL
# que puede crear tablas. Laravel en el día a día usa nep30_app, que no puede.
set -a; source "$HOME/.nep30_migraciones.env"; set +a
php artisan migrate --force
unset DB_USERNAME DB_PASSWORD

php artisan storage:link 2>/dev/null || true
php artisan optimize

# Permisos de escritura para PHP en storage y bootstrap/cache
# Solo los archivos del usuario del deploy: worker.log es de root (Supervisor) y los
# que crea PHP son de www-data; chgrp sobre ellos falla y cortaba el deploy.
find storage bootstrap/cache -user "$(id -un)" -exec chgrp www-data {} + -exec chmod ug+rwX {} +

sudo systemctl reload php8.4-fpm
php artisan queue:restart
sudo supervisorctl restart portal-nep30-worker:*

php artisan up
echo "Deploy OK: $(git log -1 --format='%h %s')"
exit
}
