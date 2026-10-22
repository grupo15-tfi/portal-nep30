# portal-nep30

Portal Institucional y Campus Virtual con Geolocalización de Sedes – N.E.P. y F.P. N.º 30
"Hna. María Delgado Valero" (Formosa). TFI Grupo 15 – UTN FRRe.

**Equipo:** Benitez José Alexander (coordinación), Arce Ángel Gabriel, Castillo Rodrigo Fabian.
**Docentes:** Mg. Federico Horacio Britez y Lic. Eduardo Roig.
**Tutor de la institución (Product Owner):** Nery Alfredo Aguayo.

## Stack

Laravel 13 · PHP 8.4 · MySQL 8 · Livewire · Spatie Laravel Permission · Tailwind (Vite) ·
Leaflet + Nominatim (mapa de sedes) · Brevo (correo).

## Entornos

| Entorno | Dónde | Guía |
|---|---|---|
| Desarrollo | La PC de cada integrante | [docs/ENTORNO_DESARROLLO.md](docs/ENTORNO_DESARROLLO.md) |
| Pruebas / demo | VPS en Azure (`APP_ENV=demo`, solo datos de prueba) | [docs/ENTORNO_DEMO.md](docs/ENTORNO_DEMO.md) |
| Producción | Hosting de la institución (Etapa 8) | A definir con la institución |

Arranque rápido (con PHP 8.4, Composer, MySQL 8 y Node 22 instalados y la base
`portal_nep30` creada):

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan serve        # http://localhost:8000  (admin@nep30.test / password)
```

## Base de datos (modelo v4.4)

Las migraciones crean el modelo v4.4 completo: 34 tablas con sus restricciones CHECK,
39 triggers, 2 procedimientos y 7 vistas (`database/sql/`). La base protege por sí misma
las reglas del negocio: cupo y lista de espera, edad mínima, acreditación con nota 6 y
80 % de asistencia, recuperatorio, rol Auditor de solo lectura y auditoría automática.

- El middleware `ContextoAuditoria` le pasa a MySQL quién hace cada pedido
  (`@app_usuario_id`, `@app_ip`).
- Los errores de los triggers llegan como `QueryException` (SQLSTATE 45000) con el
  mensaje en español.
- Referencia del modelo, DER y las 139 pruebas en SQL: [docs/base-de-datos](docs/base-de-datos).

## Seeders

| Seeder | Qué carga | Dónde |
|---|---|---|
| `RolesYPermisosSeeder` | Administrador, Profesor, Alumno y Auditor con sus permisos | Siempre |
| `SeccionesSeeder` | Textos editables del sitio público | Siempre |
| `CatalogoSeeder` | 12 planes del folleto 2026 (9 de FP y 3 de primaria) | Siempre |
| `AdministradorSeeder` | Administrador inicial (datos del `.env`) | Siempre |
| `DatosDePruebaSeeder` | Sedes, cursos, usuarios y lista de espera inventados | Solo `local`, `demo` y `testing` |

## Ramas y commits

- `main`: lo que está en la VPS (protegida, deploy automático).
- `develop`: integración, rama por defecto (protegida).
- `feature/HUxx-nombre`, `fix/...`: una por historia o corrección, con PR a `develop`.
- Commits: `tipo(área): descripción (#issue)`. Versiones: v0.1 a v0.4 (una por sprint), v1.0 en producción.

Cada PR corre `php artisan test` contra MySQL 8 real en GitHub Actions.

## Reglas

- Solo datos de prueba fuera del hosting de la institución (Ley 25.326).
- El `.env`, las claves `.pem` y las claves de MySQL nunca se suben ni se pegan en chats.
- Laravel se conecta con `nep30_app`, nunca con `root`, fuera de la PC.
