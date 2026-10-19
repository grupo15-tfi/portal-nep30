# Entorno de pruebas y demos: VPS en Azure

**Dueño:** José Alexander Benitez (cuenta Azure for Students)
**Uso:** demos de cada sprint (al profe y a Aguayo) y defensa del 01/12/2026. Solo **datos de prueba** (Ley 25.326).

> La producción real va en el hosting de la institución (Etapa 8). Esta VPS es para mostrar avances.
**Repositorio:** https://github.com/grupo15-tfi/portal-nep30

Al terminar vas a tener `https://portal-nep30-tfi.<región>.cloudapp.azure.com` con:
PHP 8.4 · MySQL 8 · Nginx · HTTPS · worker de correos (Supervisor) · tareas programadas (cron) · copia de seguridad diaria · deploy automático al integrar en `main`.

## Archivos del repositorio que usa la VPS

| Archivo | Para qué |
|---|---|
| `deploy/servidor/setup_servidor.sh` | Deja la VM lista en un solo paso (se corre una vez) |
| `deploy/servidor/nginx-portal-nep30.conf` | Sitio de Nginx con cabeceras de seguridad y límite de 12 MB |
| `deploy/servidor/php-produccion.ini` | PHP 8.4 para producción (sin errores en pantalla, subidas de 10 MB) |
| `deploy/servidor/mysql-portal.cnf` | MySQL 8 ajustado a 4 GiB, solo accesible desde la VM |
| `deploy/servidor/supervisor-portal-nep30-worker.conf` | Worker de la cola de correos |
| `deploy/servidor/backup.sh` | Copia diaria de la base y los archivos (30 días) |
| `deploy/servidor/env.produccion.ejemplo` | Modelo del `.env` del servidor |
| `.github/workflows/deploy.yml` | Tests con MySQL real y deploy por SSH |
| `scripts/deploy.sh` | Lo que corre en la VM en cada deploy |

---

## 1. Crear la VM en el portal de Azure

**Máquinas virtuales → Crear → Máquina virtual de Azure**

| Dato | Valor |
|---|---|
| Suscripción | Azure for Students |
| Grupo de recursos | **Crear nuevo:** `rg-tfi-nep30` |
| Nombre | `vm-tfi-nep30` |
| Región | **North Central US** (la que te funcionó con la trivia) |
| Imagen | Ubuntu Server 24.04 LTS – x64 Gen2 |
| Tamaño | **Standard_B2als_v2** (2 vCPU, 4 GiB) |
| Autenticación | Clave pública SSH · **Generar nuevo par de claves** · nombre `tfi-nep30` |
| Usuario | `azureuser` |
| Puertos de entrada | SSH (22), HTTP (80), HTTPS (443) |

**Pestaña Discos:** SSD estándar (no Premium), 30 GiB, *Eliminar con la VM* marcado.

**Pestaña Administración → Apagado automático:** activado a las **01:00**, zona **(UTC-03:00) Buenos Aires**, con aviso a tu correo.

**Revisar y crear → Crear →** descargá el `tfi-nep30.pem` y guardalo en `D:\tif\claves\`. **Ese archivo no se sube a ningún lado.**

> Si te dice que el tamaño no está disponible: probá las otras regiones que permite tu suscripción (`centralus`, `westus3`, `brazilsouth`, `mexicocentral`). Si en ninguna hay B2als_v2, usá **B2ats_v2** (1 GiB, la de la trivia) y en `deploy/servidor/mysql-portal.cnf` bajá `innodb_buffer_pool_size` a `256M`.

### Etiqueta DNS

VM → **Información general → Dirección IP pública → Configuración → Etiqueta de nombre DNS:** `portal-nep30-tfi` (sin puntos) → Guardar.

Queda: `portal-nep30-tfi.northcentralus.cloudapp.azure.com` (lo llamamos **DOMINIO**).

### Alerta de gasto

**Cost Management → Presupuestos → Agregar:** USD 40 por mes, alertas al 50 %, 80 % y 100 % a tu correo.

---

## 2. Conectarte por SSH (PowerShell)

La primera vez, dejá el `.pem` legible solo para vos (si no, SSH lo rechaza):

```powershell
icacls D:\tif\claves\tfi-nep30.pem /inheritance:r
icacls D:\tif\claves\tfi-nep30.pem /grant:r "$($env:USERNAME):(R)"
```

Conectarte:

```powershell
ssh -i D:\tif\claves\tfi-nep30.pem azureuser@DOMINIO
```

---

## 3. Subir los archivos y preparar el servidor

Desde PowerShell, parado en la carpeta del repositorio (`portal-nep30`):

```powershell
scp -i D:\tif\claves\tfi-nep30.pem -r deploy\servidor azureuser@DOMINIO:~/
```

En la VM (una línea por vez):

```bash
cd ~/servidor
sudo bash setup_servidor.sh DOMINIO tu-correo@ejemplo.com
```

Tarda unos 10 minutos. Al final muestra las versiones y dónde quedaron las claves.

**Verificá:** `https://DOMINIO` muestra "Portal N.E.P. 30 — en construcción" con candado.

**Las claves de MySQL** quedaron en `~/LEEME_claves_nep30.txt`:

```bash
cat ~/LEEME_claves_nep30.txt     # copialas a tu gestor de contraseñas
rm ~/LEEME_claves_nep30.txt
```

> No pegues esas claves en el chat ni en WhatsApp.

---

## 4. Clave para que GitHub Actions entre a la VM

En la VM:

```bash
ssh-keygen -t ed25519 -f ~/.ssh/github_deploy -N "" -C "github-actions-portal-nep30"
cat ~/.ssh/github_deploy.pub >> ~/.ssh/authorized_keys
cat ~/.ssh/github_deploy          # copiá TODO el texto, incluidas las líneas BEGIN/END
```

En GitHub: **grupo15-tfi/portal-nep30 → Settings → Secrets and variables → Actions → New repository secret**

| Secret | Valor |
|---|---|
| `SSH_KEY` | La clave privada que copiaste |
| `SSH_HOST` | DOMINIO |
| `SSH_USER` | `azureuser` |

Después borrá la privada de la VM (ya está en GitHub): `rm ~/.ssh/github_deploy`

---

## 5. Primer deploy (una sola vez, cuando el proyecto base esté en `main`)

El proyecto base (Laravel 13 + base v4.4 + datos de prueba) entra por pull request a `develop`
y de ahí a `main`. Después, hacé esto **una sola vez** en la VM:

```bash
cd /var/www
sudo rm -rf portal-nep30
sudo install -d -o azureuser -g www-data portal-nep30
git clone https://github.com/grupo15-tfi/portal-nep30.git portal-nep30
cd portal-nep30

cp deploy/servidor/env.produccion.ejemplo .env   # el modelo del repo (APP_ENV=demo y ADMIN_*)
nano .env                          # DOMINIO, clave de nep30_app, Brevo y ADMIN_* (APP_ENV=demo)

composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build
php artisan key:generate

set -a; source ~/.nep30_migraciones.env; set +a
php artisan migrate --force
php artisan db:seed --force        # roles, permisos, catálogo, Administrador y datos de PRUEBA
unset DB_USERNAME DB_PASSWORD

php artisan storage:link
sudo chgrp -R www-data storage bootstrap/cache && sudo chmod -R ug+rwX storage bootstrap/cache
php artisan optimize
sudo systemctl restart supervisor   # recién ahora existe storage/logs: Supervisor puede arrancar
sudo supervisorctl status           # portal-nep30-worker_00 tiene que decir RUNNING
```

> Antes del primer deploy es normal que `systemctl is-active supervisor` diga *activating*: el worker
> guarda su log en `storage/logs`, que todavía no existe, y Supervisor reintenta hasta que aparezca.

Desde ahí, **cada integración en `main` se publica sola** con `scripts/deploy.sh`.

**Verificá:** `https://DOMINIO` muestra el portal y podés entrar con el `ADMIN_EMAIL` del `.env`.
Los usuarios de prueba (`profesor@nep30.test`, `alumno@nep30.test`, `auditor@nep30.test`) tienen la clave
`password`: **cambiásela desde el panel antes de mostrar la VPS a otros**, o no la compartas fuera del grupo.

### Regla de GitHub

En **Settings → Rules**, agregá a la regla `proteger-main-develop` el requisito de que pase el check **tests**: así nada roto llega a `develop` ni a `main`.

### Flujo de cada sprint

```
feature/HUxx → PR a develop (corren los tests) → aprobada → develop
fin del sprint: PR develop → main → tests → deploy automático → demo → release v0.x
```

---

## 6. Uso diario y costos

| Situación | Qué hacer |
|---|---|
| Antes de una demo | Portal → VM → **Iniciar** (unos 15 min antes). La IP y el dominio no cambian |
| Después de la demo | Portal → VM → **Detener** (queda *Desasignada*). **Apagarla desde Linux sigue cobrando** |
| Te olvidaste | El apagado automático la detiene a la 01:00 |
| Copia de seguridad | Se hace sola a las 00:30. Los domingos queda una en `~/backups/semanal`: bajala con `scp` |

**Costo estimado:** B2als_v2 ≈ USD 0,038 por hora en North Central US, más disco e IP. Prendida todo el día son unos USD 30 por mes; prendida solo para trabajar y hacer las demos, bastante menos. Con el crédito de USD 98,87 alcanza hasta la defensa, aunque conviene apagar la VM de la trivia cuando no la uses.

---

## 7. Problemas frecuentes

| Síntoma | Causa probable | Solución |
|---|---|---|
| 502 Bad Gateway | PHP-FPM caído | `sudo systemctl restart php8.4-fpm` |
| Los correos no llegan | Worker detenido o error en Brevo | `sudo supervisorctl status` · `php artisan queue:failed` · `tail storage/logs/worker.log` |
| `Permission denied` en `storage` | Permisos después de un cambio manual | `sudo chgrp -R www-data storage bootstrap/cache && sudo chmod -R ug+rwX storage bootstrap/cache` |
| El deploy falla y el sitio da 503 (mantenimiento) | El script se cortó después de `php artisan down` | `cd /var/www/portal-nep30 && php artisan up`; la causa está en **Actions** → ejecución en rojo → paso "Deploy por SSH" |
| `SQLSTATE[42000] ... 1142 command denied` | Una migración corrió con `nep30_app` | Correr `migrate` con `source ~/.nep30_migraciones.env` (deploy.sh ya lo hace) |
| Certbot falla | La etiqueta DNS no existe o el puerto 80 está cerrado | Revisar la etiqueta DNS y los puertos de la VM |
| La página no cambia después del deploy | JS en caché del navegador | Recargar con Ctrl+F5 |
| `No se puede acceder a este sitio` | VM detenida | Iniciarla desde el portal |
| El deploy no corrió | Algún test falló | Ver la pestaña **Actions** en GitHub |

---

## 8. Reglas de seguridad

- El `.env`, el `.pem` y las claves de MySQL **nunca** se suben al repositorio ni se pegan en el chat.
- `APP_DEBUG=false` siempre en la VM.
- Laravel se conecta con `nep30_app`, **nunca con root**. MySQL solo escucha en `127.0.0.1`.
- La clave privada del deploy vive **solo** en los secrets de GitHub.
- No editar archivos en el servidor (el deploy los pisa), salvo el `.env`.
- Solo datos de prueba: los datos reales de alumnos van al hosting de la institución.
- Después de la defensa: backup final y **eliminar el grupo de recursos `rg-tfi-nep30`** si ya no se usa (borra todo y deja de cobrar).
