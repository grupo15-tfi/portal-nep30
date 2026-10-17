-- =====================================================================
-- TFI Grupo 15 - 03_datos_base.sql - Datos iniciales (v4.4)
-- Roles y permisos de Spatie, secciones editables del sitio y catálogo
-- inicial de cursos (folleto "Oferta Educativa 2026").
-- En Laravel esto mismo va en seeders (RolesYPermisosSeeder, CatalogoSeeder)
-- para que se cargue con "php artisan db:seed". El Administrador inicial se
-- crea desde el seeder, porque su contraseña se guarda con Hash::make() (bcrypt).
-- =====================================================================

USE portal_nep30;

-- ---------------------------------------------------------------------
-- 1. Roles: Administrador, Profesor, Alumno y Auditor (solo lectura)
-- ---------------------------------------------------------------------
INSERT INTO roles (name, guard_name, created_at, updated_at) VALUES
 ('administrador', 'web', NOW(), NOW()),
 ('profesor',      'web', NOW(), NOW()),
 ('alumno',        'web', NOW(), NOW()),
 ('auditor',       'web', NOW(), NOW());

INSERT INTO permissions (name, guard_name, created_at, updated_at) VALUES
 -- Gestión (solo Administrador)
 ('gestionar sedes',            'web', NOW(), NOW()),
 ('gestionar cursos',           'web', NOW(), NOW()),
 ('gestionar profesores',       'web', NOW(), NOW()),
 ('gestionar alumnos',          'web', NOW(), NOW()),
 ('gestionar preinscripciones', 'web', NOW(), NOW()),   -- incluye la lista de espera
 ('gestionar contenidos',       'web', NOW(), NOW()),
 ('gestionar calendario',       'web', NOW(), NOW()),
 -- Consulta de todas las sedes (Administrador y Auditor)
 ('ver alumnos',                'web', NOW(), NOW()),
 ('ver preinscripciones',       'web', NOW(), NOW()),
 ('ver notas',                  'web', NOW(), NOW()),
 ('ver asistencia',             'web', NOW(), NOW()),
 ('ver auditoria',              'web', NOW(), NOW()),
 ('exportar planillas',         'web', NOW(), NOW()),
 ('descargar historiales',      'web', NOW(), NOW()),
 -- Profesor
 ('cargar notas',               'web', NOW(), NOW()),
 ('gestionar tareas',           'web', NOW(), NOW()),
 ('registrar asistencia',       'web', NOW(), NOW()),
 ('publicar avisos',            'web', NOW(), NOW()),
 ('gestionar calendario curso', 'web', NOW(), NOW()),
 ('exportar planillas curso',   'web', NOW(), NOW()),
 -- Alumno
 ('ver mis notas',              'web', NOW(), NOW()),
 ('entregar tareas',            'web', NOW(), NOW()),
 ('ver mi asistencia',          'web', NOW(), NOW()),
 ('ver mi calendario',          'web', NOW(), NOW()),
 ('ver mi historial',           'web', NOW(), NOW());

INSERT INTO role_has_permissions (permission_id, role_id)
SELECT p.id, r.id FROM permissions p JOIN roles r ON r.name = 'administrador'
WHERE p.name IN ('gestionar sedes','gestionar cursos','gestionar profesores','gestionar alumnos',
                 'gestionar preinscripciones','gestionar contenidos','gestionar calendario',
                 'ver alumnos','ver preinscripciones','ver notas','ver asistencia','ver auditoria',
                 'exportar planillas','descargar historiales');

INSERT INTO role_has_permissions (permission_id, role_id)
SELECT p.id, r.id FROM permissions p JOIN roles r ON r.name = 'profesor'
WHERE p.name IN ('cargar notas','gestionar tareas','registrar asistencia','publicar avisos',
                 'gestionar calendario curso','exportar planillas curso');

INSERT INTO role_has_permissions (permission_id, role_id)
SELECT p.id, r.id FROM permissions p JOIN roles r ON r.name = 'alumno'
WHERE p.name IN ('ver mis notas','entregar tareas','ver mi asistencia','ver mi calendario','ver mi historial');

-- El Auditor solo tiene permisos de consulta y exportación (HU18, RF35, RF36).
INSERT INTO role_has_permissions (permission_id, role_id)
SELECT p.id, r.id FROM permissions p JOIN roles r ON r.name = 'auditor'
WHERE p.name IN ('ver alumnos','ver preinscripciones','ver notas','ver asistencia','ver auditoria',
                 'exportar planillas','descargar historiales');

-- ---------------------------------------------------------------------
-- 2. Secciones editables del sitio público
-- ---------------------------------------------------------------------
INSERT INTO secciones (clave, titulo, contenido, created_at, updated_at) VALUES
 ('inicio',     'Inicio',                 'Tú puedes lograrlo. TE AYUDAMOS. (Texto de bienvenida editable desde el panel.)', NOW(), NOW()),
 ('historia',   'Nuestra historia',       'Historia de la institución (editable desde el panel).',      NOW(), NOW()),
 ('contacto',   'Contacto',               'E.P.E.P. N.º 57 "Gendarme Argentino", Av. de los Constituyentes, barrio Luján, Formosa.', NOW(), NOW()),
 ('servicios',  'Servicios',              'Sala de Lactancia en la Sede (editable desde el panel).',   NOW(), NOW()),
 ('privacidad', 'Política de privacidad', 'Tratamiento de datos personales según la Ley 25.326.',      NOW(), NOW());

-- ---------------------------------------------------------------------
-- 3. Catálogo inicial de planes (folleto "Oferta Educativa 2026")
--    Solo los nombres: el Administrador agrega módulos, cursos por sede,
--    cupos, días y horarios desde el panel. Reglas por defecto: edad
--    mínima 18, se acredita con 6 y con 80 % de asistencia.
-- ---------------------------------------------------------------------
INSERT INTO planes_estudio (nombre, tipo, created_at, updated_at) VALUES
 ('Microemprendimientos y proyectos productivos',             'formacion_profesional', NOW(), NOW()),
 ('Peluquería',                                               'formacion_profesional', NOW(), NOW()),
 ('Organización de Eventos Sociales',                         'formacion_profesional', NOW(), NOW()),
 ('Operador de Informática para la Administración y Gestión', 'formacion_profesional', NOW(), NOW()),
 ('Confeccionista a medida, modista/o',                       'formacion_profesional', NOW(), NOW()),
 ('Belleza Integral',                                         'formacion_profesional', NOW(), NOW()),
 ('Servicios Integrales',                                     'formacion_profesional', NOW(), NOW()),
 ('Tejido Artesanal',                                         'formacion_profesional', NOW(), NOW()),
 ('Montador Electricista Domiciliario',                       'formacion_profesional', NOW(), NOW()),
 ('Primaria para adultos: Alfabetización',                    'terminalidad',          NOW(), NOW()),
 ('Primaria para adultos: Formación Integral',                'terminalidad',          NOW(), NOW()),
 ('Primaria para adultos: Formación por Proyectos',           'terminalidad',          NOW(), NOW());

-- Las sedes y anexos NO se cargan acá: el mapa necesita coordenadas exactas,
-- y varias direcciones son "manzana y casa" que Nominatim no encuentra.
-- Se cargan desde el panel ubicando cada punto en el mapa (ver LEEME.txt).
