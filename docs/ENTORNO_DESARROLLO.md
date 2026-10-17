# Entorno de desarrollo (la PC de cada integrante)

Guía para que **Benitez, Arce y Castillo** tengan el portal funcionando en su
computadora, todos con las mismas versiones. Se hace **una sola vez** por PC.

> Entorno de desarrollo = tu PC. Programás y probás en `http://localhost:8000`;
> nadie más lo ve y podés romper todo sin problema. Los datos son **de prueba**.

| Programa | Versión | Para qué |
|---|---|---|
| PHP | 8.4 | Lenguaje de Laravel |
| Composer | 2.x | Instala las librerías de PHP (Laravel, Livewire, Spatie) |
| MySQL Community Server | 8.0 u 8.4 | La base de datos (con triggers y vistas) |
| MySQL Workbench | 8.0 | Ver y consultar la base (opcional, ya lo usamos) |
| Node.js | 22 LTS | Compila estilos y JavaScript (Vite + Tailwind) |
| Git | última | Bajar y subir código a GitHub |
| VS Code | última | Editor |

Todo es gratis. **No usamos XAMPP** (trae MariaDB y PHP viejo) **ni Laragon**
(desde 2025 la versión nueva es paga).

---

## 1. Instalar los programas (Windows)

### PHP 8.4 y Composer

Abrí **PowerShell como administrador** y pegá esta línea (es el instalador oficial
que recomienda Laravel, [php.new](https://php.new)):

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.4'))
```

**Cerrá y volvé a abrir** PowerShell, y verificá:

```powershell
php -v            # PHP 8.4.x
composer -V       # Composer 2.x
php -m | findstr /i "pdo_mysql mbstring intl zip"
```

Si `pdo_mysql` no aparece, abrí el `php.ini` (lo muestra `php --ini`) y sacale el
`;` a la línea `extension=pdo_mysql`.

### MySQL 8

Si ya tenés MySQL 8 con Workbench (lo usamos para probar la base v4.4), **saltá este paso**.

Si no: [MySQL Installer para Windows](https://dev.mysql.com/downloads/installer/) →
*Server only* (o *Server + Workbench*) → puerto **3306** → anotá la clave de `root`.

### Node.js, Git y VS Code

- Node.js **22 LTS**: <https://nodejs.org> (instalador .msi, opciones por defecto).
- Git: <https://git-scm.com/download/win> (opciones por defecto).
- VS Code: <https://code.visualstudio.com>. Extensiones útiles: *PHP Intelephense*,
  *Laravel Blade Snippets*, *Tailwind CSS IntelliSense*.

Verificá en una PowerShell nueva:

```powershell
node -v    # v22.x
npm -v
git --version
```

### Configurar Git (una vez)

```powershell
git config --global user.name "Tu Nombre y Apellido"
git config --global user.email "el-correo-de-tu-cuenta-de-github"
git config --global core.autocrlf true
```

---

## 2. Bajar el proyecto

```powershell
cd D:\tif                     # o la carpeta que uses
git clone https://github.com/grupo15-tfi/portal-nep30.git
cd portal-nep30
git switch develop
```

## 3. Crear las bases de datos

En **MySQL Workbench** (o en la consola `mysql -u root -p`):

```sql
CREATE DATABASE portal_nep30      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE portal_nep30_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

`portal_nep30` es la que usás para programar; `portal_nep30_test` la usan las
pruebas automáticas (se borra y se crea sola cada vez).

## 4. Configurar y levantar Laravel

```powershell
composer install
npm install
copy .env.example .env
php artisan key:generate
```

Abrí el `.env` y, si tu `root` de MySQL tiene clave, completala:

```
DB_USERNAME=root
DB_PASSWORD=tu-clave-de-root
```

> El `.env` **nunca** se sube a GitHub (ya está en `.gitignore`).

Crear las tablas, triggers y vistas, y cargar los datos de prueba:

```powershell
php artisan migrate --seed
```

Tiene que terminar sin errores. Si dice *"You do not have the SUPER privilege and
binary logging is enabled"*, en Workbench ejecutá
`SET GLOBAL log_bin_trust_function_creators = 1;` y repetí con
`php artisan migrate:fresh --seed`.

Levantar el sitio (dejá las dos ventanas abiertas mientras programás):

```powershell
php artisan serve      # ventana 1: el sitio en http://localhost:8000
npm run dev            # ventana 2: recompila estilos al guardar
```

## 5. Usuarios de prueba

Clave de todos: **`password`**

| Rol | Correo |
|---|---|
| Administrador | `admin@nep30.test` |
| Profesor | `profesor@nep30.test` |
| Alumno | `alumno@nep30.test` (también `alumno2@nep30.test`) |
| Auditor | `auditor@nep30.test` |

Los datos de prueba ya traen: 2 sedes con coordenadas, el período 2026, 3 cursos,
Peluquería con el cupo lleno y **2 aspirantes en lista de espera**, notas,
asistencia, una tarea, un aviso, un evento y una noticia. Todo inventado
(Ley 25.326).

## 6. Comprobar que todo anda

```powershell
php artisan test
```

Tiene que decir **todas las pruebas OK**. Esas mismas pruebas corren solas en
GitHub en cada pull request; si fallan, la PR no se puede integrar.

---

## Uso diario

```powershell
git switch develop
git pull                                   # traer lo que subieron los demás
git switch -c feature/HU04-preinscripcion  # una rama por historia
# ... programar ...
php artisan test
git add .
git commit -m "feat(preinscripcion): formulario con lista de espera (#12)"
git push -u origin feature/HU04-preinscripcion
# en GitHub: abrir la pull request hacia develop
```

Si alguien agregó una migración o una librería nueva:

```powershell
composer install
npm install
php artisan migrate
```

Volver la base a cero (borra todo y carga de nuevo los datos de prueba):

```powershell
php artisan migrate:fresh --seed
```

## Problemas frecuentes

| Síntoma | Solución |
|---|---|
| `could not find driver` | Falta `extension=pdo_mysql` en el `php.ini` |
| `Access denied for user 'root'` | Clave de root mal puesta en el `.env` |
| `Unknown database 'portal_nep30'` | Falta el paso 3 |
| `SUPER privilege and binary logging` | `SET GLOBAL log_bin_trust_function_creators = 1;` |
| `Vite manifest not found` | Correr `npm run dev` (o `npm run build`) |
| La página no toma cambios del `.env` | `php artisan config:clear` |
| Un error de la base en español (p. ej. *Sin cupo*) | Es un trigger cuidando una regla: está bien que avise |
