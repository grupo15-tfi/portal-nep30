-- =====================================================================
-- TFI Grupo 15 - Portal Institucional y Campus Virtual
-- N.E.P. y F.P. N.º 30 "Hna. María Delgado Valero" (Formosa)
-- 01_esquema.sql - Modelo de datos v4.4 - MySQL 8 (InnoDB, utf8mb4)
--
-- ORDEN DE EJECUCIÓN
--   01_esquema.sql     tablas, restricciones, triggers y vistas (este archivo)
--   02_seguridad.sql   usuarios de MySQL y privilegios mínimos
--   03_datos_base.sql  roles, permisos, secciones y catálogo de cursos
--   04_pruebas.sql     pruebas automáticas (solo en desarrollo)
--   backup.sh          copia de seguridad diaria (cron del hosting)
--
-- En el proyecto Laravel las tablas se crean con migraciones; este script es
-- el modelo de referencia y la forma de probar el diseño directamente en MySQL.
-- Los triggers, el procedimiento y las vistas se pasan a una migración con
-- DB::unprepared().
--
-- CAMBIOS RESPECTO DE LA v4.3 (entrevista con la institución del 13/10/2026)
--   * Se quitan los CERTIFICADOS con QR y la validación pública: la
--     institución no los usa (el analítico oficial lo emite otra entidad).
--     En su lugar, HISTORIAL ACADÉMICO (HU17, RF33, RF34): vistas
--     v_historial_academico y v_historial_resumen; el PDF lo arma Laravel.
--   * Se quitan los COMENTARIOS Y SUGERENCIAS: la institución no los quiere.
--   * LISTA DE ESPERA (RF09, HU04, HU09): si el curso no tiene cupo, la
--     preinscripción queda "en_espera" automáticamente; la vista
--     v_lista_espera la ordena por llegada y muestra el teléfono, porque la
--     institución avisa por teléfono cuando se libera un lugar.
--   * EDAD MÍNIMA (18 años): la base rechaza la preinscripción de un menor.
--   * APROBACIÓN: escala de 1 a 10, se acredita con 6 o más y asistencia del
--     80 % o más; hay recuperatorio. La nota, la asistencia y la edad mínima
--     son datos de cada plan: el Administrador las puede cambiar desde el panel.
--   * ASISTENCIA: la falta justificada NO cuenta como ausente.
--   * ROL AUDITOR (HU18, RF35, RF36): solo lectura. Además del control de
--     Laravel, la base rechaza que un Auditor modifique alumnos, notas,
--     asistencia, preinscripciones o roles (procedimiento exigir_escritura).
--   * Cursos con días y horario (el folleto 2026 los publica así).
--   * Planes de tipo 'formacion_profesional' o 'terminalidad' (primaria
--     para adultos); la duración de la terminalidad queda a cargar.
--   * La auditoría registra también el alta de cada preinscripción y las
--     descargas (historial en PDF y planillas Excel).
--   * (18/10) La auditoría registra también las correcciones de asistencia
--     (trigger trg_asist_au), como muestra el wireframe del Auditor.
--
-- AUDITORÍA AUTOMÁTICA
--   Laravel, al iniciar cada pedido con sesión, ejecuta:
--       SET @app_usuario_id = <id del usuario logueado>, @app_ip = '<ip>';
--   Los triggers usan esas variables para registrar quién hizo el cambio.
--   Si no están definidas (por ejemplo, un cambio hecho a mano en phpMyAdmin)
--   el registro queda igual, con usuario NULL: nada se modifica sin rastro.
-- =====================================================================

-- SOLO EN DESARROLLO: borra la base completa. Nunca ejecutar en producción.
-- DROP DATABASE IF EXISTS portal_nep30;

CREATE DATABASE IF NOT EXISTS portal_nep30 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE portal_nep30;

-- Modo estricto (el mismo que usa Laravel): rechaza datos truncados o inválidos
-- en lugar de "arreglarlos" en silencio.
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- ---------------------------------------------------------------------
-- 1. SEDES Y ANEXOS  [RF04, RF05, RF14]
-- ---------------------------------------------------------------------

CREATE TABLE sedes (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre         VARCHAR(120) NOT NULL UNIQUE,
    tipo           VARCHAR(10)  NOT NULL,
    direccion      VARCHAR(200) NULL,                  -- algunos anexos se ubican por manzana y casa
    localidad      VARCHAR(100) NOT NULL,
    telefono       VARCHAR(30)  NULL,
    email          VARCHAR(150) NULL,
    latitud        DECIMAL(10,7) NOT NULL,             -- se cargan a mano si Nominatim no encuentra la dirección
    longitud       DECIMAL(10,7) NOT NULL,
    observaciones  VARCHAR(300) NULL,                  -- p. ej. "funciona en la E.P.E.P. N.º 57"
    activo         BOOLEAN NOT NULL DEFAULT TRUE,
    created_at     TIMESTAMP NULL,
    updated_at     TIMESTAMP NULL,
    CONSTRAINT chk_sede_tipo  CHECK (tipo IN ('central','sede','anexo')),
    CONSTRAINT chk_sede_lat   CHECK (latitud  BETWEEN -90  AND 90),
    CONSTRAINT chk_sede_lon   CHECK (longitud BETWEEN -180 AND 180),
    CONSTRAINT chk_sede_email CHECK (email IS NULL OR email LIKE '%_@_%._%')
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. USUARIOS, ROLES Y ACCESO  [RF10-RF13, RF16, RF18, RF35, RNF08, RNF12, RNF13]
--    Roles (Spatie): administrador, profesor, alumno y auditor.
-- ---------------------------------------------------------------------

-- Tabla users de Laravel con los datos propios del sistema.
CREATE TABLE users (
    id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dni                VARCHAR(8)   NOT NULL UNIQUE,
    apellido           VARCHAR(100) NOT NULL,
    nombre             VARCHAR(100) NOT NULL,
    email              VARCHAR(150) NOT NULL UNIQUE,      -- se ingresa con correo y contraseña (HU05)
    email_verified_at  TIMESTAMP NULL,
    password           VARCHAR(255) NOT NULL,             -- siempre hash (bcrypt), nunca texto plano
    sede_id            BIGINT UNSIGNED NULL,              -- sede de referencia del personal
    fecha_nacimiento   DATE NULL,
    direccion          VARCHAR(200) NULL,
    activo             BOOLEAN NOT NULL DEFAULT TRUE,     -- FALSE = dado de baja: no puede ingresar
    intentos_fallidos  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    bloqueado_hasta    DATETIME NULL,                     -- 5 intentos fallidos = 15 minutos (RNF12)
    ultimo_acceso      DATETIME NULL,
    remember_token     VARCHAR(100) NULL,
    created_at         TIMESTAMP NULL,
    updated_at         TIMESTAMP NULL,
    deleted_at         TIMESTAMP NULL,                    -- soft delete: el historial no se pierde
    KEY idx_users_nombre (apellido, nombre),
    CONSTRAINT fk_users_sede     FOREIGN KEY (sede_id) REFERENCES sedes(id),
    CONSTRAINT chk_users_dni     CHECK (dni REGEXP '^[0-9]{7,8}$'),
    CONSTRAINT chk_users_email   CHECK (email LIKE '%_@_%._%'),
    CONSTRAINT chk_users_hash    CHECK (CHAR_LENGTH(password) >= 60),
    CONSTRAINT chk_users_intent  CHECK (intentos_fallidos <= 10),
    CONSTRAINT chk_users_nac     CHECK (fecha_nacimiento IS NULL OR fecha_nacimiento >= '1900-01-01')
) ENGINE=InnoDB;

-- Teléfonos (con tipo 'emergencia' + contacto_nombre se cubre el contacto de emergencia).
CREATE TABLE telefonos (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id       BIGINT UNSIGNED NOT NULL,
    numero           VARCHAR(30) NOT NULL,
    tipo             VARCHAR(12) NOT NULL,
    contacto_nombre  VARCHAR(120) NULL,
    es_principal     BOOLEAN NOT NULL DEFAULT FALSE,
    created_at       TIMESTAMP NULL,
    updated_at       TIMESTAMP NULL,
    CONSTRAINT fk_tel_usuario  FOREIGN KEY (usuario_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT chk_tel_tipo    CHECK (tipo IN ('celular','fijo','whatsapp','emergencia')),
    CONSTRAINT chk_tel_numero  CHECK (numero REGEXP '^[0-9 +()-]{6,30}$'),
    CONSTRAINT chk_tel_contac  CHECK (tipo <> 'emergencia' OR contacto_nombre IS NOT NULL)
) ENGINE=InnoDB;

-- Recuperación de contraseña de Laravel: guarda el token con hash; Laravel lo
-- vence a los 60 minutos (config/auth.php 'expire' => 60) y lo borra al usarlo.
CREATE TABLE password_reset_tokens (
    email       VARCHAR(150) PRIMARY KEY,
    token       VARCHAR(255) NOT NULL,
    created_at  TIMESTAMP NULL
) ENGINE=InnoDB;

-- Sesiones guardadas en la base (SESSION_DRIVER=database).
CREATE TABLE sessions (
    id             VARCHAR(255) PRIMARY KEY,
    user_id        BIGINT UNSIGNED NULL,
    ip_address     VARCHAR(45) NULL,
    user_agent     TEXT NULL,
    payload        LONGTEXT NOT NULL,
    last_activity  INT NOT NULL,
    KEY idx_sessions_user (user_id),
    KEY idx_sessions_last (last_activity)
) ENGINE=InnoDB;

-- Tablas de Spatie Laravel Permission (guard 'web', sin equipos).
CREATE TABLE permissions (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(125) NOT NULL,
    guard_name  VARCHAR(125) NOT NULL,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,
    UNIQUE KEY uq_permissions (name, guard_name)
) ENGINE=InnoDB;

CREATE TABLE roles (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(125) NOT NULL,
    guard_name  VARCHAR(125) NOT NULL,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,
    UNIQUE KEY uq_roles (name, guard_name)
) ENGINE=InnoDB;

CREATE TABLE model_has_permissions (
    permission_id  BIGINT UNSIGNED NOT NULL,
    model_type     VARCHAR(125) NOT NULL,
    model_id       BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (permission_id, model_id, model_type),
    KEY idx_mhp_model (model_id, model_type),
    CONSTRAINT fk_mhp_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE model_has_roles (
    role_id     BIGINT UNSIGNED NOT NULL,
    model_type  VARCHAR(125) NOT NULL,
    model_id    BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, model_id, model_type),
    KEY idx_mhr_model (model_id, model_type),
    CONSTRAINT fk_mhr_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE role_has_permissions (
    permission_id  BIGINT UNSIGNED NOT NULL,
    role_id        BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (permission_id, role_id),
    CONSTRAINT fk_rhp_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    CONSTRAINT fk_rhp_role FOREIGN KEY (role_id)       REFERENCES roles(id)       ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. INFRAESTRUCTURA DE LARAVEL  [correo en segundo plano con reintentos]
-- ---------------------------------------------------------------------

CREATE TABLE cache (
    `key`       VARCHAR(255) PRIMARY KEY,
    value       MEDIUMTEXT NOT NULL,
    expiration  INT NOT NULL
) ENGINE=InnoDB;

CREATE TABLE cache_locks (
    `key`       VARCHAR(255) PRIMARY KEY,
    owner       VARCHAR(255) NOT NULL,
    expiration  INT NOT NULL
) ENGINE=InnoDB;

CREATE TABLE jobs (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue         VARCHAR(255) NOT NULL,
    payload       LONGTEXT NOT NULL,
    attempts      TINYINT UNSIGNED NOT NULL,
    reserved_at   INT UNSIGNED NULL,
    available_at  INT UNSIGNED NOT NULL,
    created_at    INT UNSIGNED NOT NULL,
    KEY idx_jobs_queue (queue)
) ENGINE=InnoDB;

CREATE TABLE job_batches (
    id              VARCHAR(255) PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    total_jobs      INT NOT NULL,
    pending_jobs    INT NOT NULL,
    failed_jobs     INT NOT NULL,
    failed_job_ids  LONGTEXT NOT NULL,
    options         MEDIUMTEXT NULL,
    cancelled_at    INT NULL,
    created_at      INT NOT NULL,
    finished_at     INT NULL
) ENGINE=InnoDB;

CREATE TABLE failed_jobs (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid        VARCHAR(255) NOT NULL UNIQUE,
    connection  TEXT NOT NULL,
    queue       TEXT NOT NULL,
    payload     LONGTEXT NOT NULL,
    exception   LONGTEXT NOT NULL,
    failed_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. OFERTA ACADÉMICA  [RF03, RF15]
--    Todo lo de esta sección lo carga y cambia el Administrador desde el
--    panel: cursos, módulos, cupos, días, horarios y reglas de aprobación.
-- ---------------------------------------------------------------------

CREATE TABLE periodos_lectivos (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    anio          SMALLINT UNSIGNED NOT NULL,
    cuatrimestre  TINYINT UNSIGNED NULL,                 -- NULL = anual
    fecha_inicio  DATE NOT NULL,
    fecha_fin     DATE NOT NULL,
    estado        VARCHAR(10) NOT NULL DEFAULT 'activo',
    created_at    TIMESTAMP NULL,
    updated_at    TIMESTAMP NULL,
    UNIQUE KEY uq_periodo (anio, cuatrimestre),
    CONSTRAINT chk_periodo_anio   CHECK (anio BETWEEN 2020 AND 2100),
    CONSTRAINT chk_periodo_cuat   CHECK (cuatrimestre IS NULL OR cuatrimestre IN (1,2)),
    CONSTRAINT chk_periodo_estado CHECK (estado IN ('activo','cerrado')),
    CONSTRAINT chk_periodo_fechas CHECK (fecha_fin > fecha_inicio)
) ENGINE=InnoDB;

-- Plan de estudio: el curso de Formación Profesional o la modalidad de la
-- terminalidad primaria. Cada plan guarda sus propias reglas (entrevista 13/10):
-- edad mínima 18, se acredita con 6 o más y con 80 % de asistencia.
CREATE TABLE planes_estudio (
    id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre             VARCHAR(150) NOT NULL UNIQUE,
    tipo               VARCHAR(25)  NOT NULL,
    descripcion        TEXT NULL,
    duracion_meses     TINYINT UNSIGNED NULL,
    cantidad_ciclos    TINYINT UNSIGNED NULL,           -- terminalidad: a confirmar con la institución
    resolucion         VARCHAR(100) NULL,
    edad_minima        TINYINT UNSIGNED NOT NULL DEFAULT 18,
    nota_aprobacion    DECIMAL(4,2) NOT NULL DEFAULT 6.00,
    asistencia_minima  TINYINT UNSIGNED NOT NULL DEFAULT 80,  -- porcentaje
    activo             BOOLEAN NOT NULL DEFAULT TRUE,
    created_at         TIMESTAMP NULL,
    updated_at         TIMESTAMP NULL,
    CONSTRAINT chk_plan_tipo   CHECK (tipo IN ('formacion_profesional','terminalidad')),
    CONSTRAINT chk_plan_ciclos CHECK (cantidad_ciclos IS NULL OR cantidad_ciclos BETWEEN 1 AND 6),
    CONSTRAINT chk_plan_edad   CHECK (edad_minima BETWEEN 14 AND 99),
    CONSTRAINT chk_plan_nota   CHECK (nota_aprobacion BETWEEN 1 AND 10),
    CONSTRAINT chk_plan_asist  CHECK (asistencia_minima BETWEEN 0 AND 100)
) ENGINE=InnoDB;

-- Materias o módulos de cada plan (en FP los módulos se acreditan).
CREATE TABLE materias (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_id         BIGINT UNSIGNED NOT NULL,
    nombre          VARCHAR(150) NOT NULL,
    ciclo           TINYINT UNSIGNED NULL,              -- año o ciclo (terminalidad)
    carga_horaria   SMALLINT UNSIGNED NULL,
    es_obligatoria  BOOLEAN NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    UNIQUE KEY uq_materia (plan_id, nombre),
    CONSTRAINT fk_materia_plan  FOREIGN KEY (plan_id) REFERENCES planes_estudio(id),
    CONSTRAINT chk_materia_ciclo CHECK (ciclo IS NULL OR ciclo BETWEEN 1 AND 6)
) ENGINE=InnoDB;

-- Correlatividades: para cursar materia_id hay que tener acreditada
-- requiere_materia_id. Opcional: si la institución no las usa, queda vacía.
CREATE TABLE materia_correlativas (
    materia_id           BIGINT UNSIGNED NOT NULL,
    requiere_materia_id  BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (materia_id, requiere_materia_id),
    CONSTRAINT fk_corr_materia FOREIGN KEY (materia_id)          REFERENCES materias(id) ON DELETE CASCADE,
    CONSTRAINT fk_corr_requiere FOREIGN KEY (requiere_materia_id) REFERENCES materias(id) ON DELETE CASCADE,
    CONSTRAINT chk_corr_distinta CHECK (materia_id <> requiere_materia_id)
) ENGINE=InnoDB;

-- Curso = un plan dictado en una sede, en un período, turno y horario, con su cupo.
-- Un mismo plan se dicta en varias sedes con distinto horario (folleto 2026).
CREATE TABLE cursos (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_id      BIGINT UNSIGNED NOT NULL,
    sede_id      BIGINT UNSIGNED NOT NULL,
    periodo_id   BIGINT UNSIGNED NOT NULL,
    turno        VARCHAR(10) NOT NULL,
    dias         VARCHAR(60) NULL,                      -- p. ej. "Lunes a viernes"
    hora_inicio  TIME NULL,
    hora_fin     TIME NULL,
    cupo         SMALLINT UNSIGNED NOT NULL,
    estado       VARCHAR(10) NOT NULL DEFAULT 'activo',
    created_at   TIMESTAMP NULL,
    updated_at   TIMESTAMP NULL,
    UNIQUE KEY uq_curso (plan_id, sede_id, periodo_id, turno),
    CONSTRAINT fk_curso_plan    FOREIGN KEY (plan_id)    REFERENCES planes_estudio(id),
    CONSTRAINT fk_curso_sede    FOREIGN KEY (sede_id)    REFERENCES sedes(id),
    CONSTRAINT fk_curso_periodo FOREIGN KEY (periodo_id) REFERENCES periodos_lectivos(id),
    CONSTRAINT chk_curso_turno  CHECK (turno IN ('mañana','tarde','noche')),
    CONSTRAINT chk_curso_estado CHECK (estado IN ('activo','inactivo','finalizado')),
    CONSTRAINT chk_curso_cupo   CHECK (cupo BETWEEN 1 AND 500),
    CONSTRAINT chk_curso_horas  CHECK (hora_inicio IS NULL OR hora_fin IS NULL OR hora_fin > hora_inicio)
) ENGINE=InnoDB;

-- Profesores de cada curso y materia (puede haber dos en un mismo taller).
CREATE TABLE curso_profesores (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curso_id     BIGINT UNSIGNED NOT NULL,
    materia_id   BIGINT UNSIGNED NOT NULL,
    profesor_id  BIGINT UNSIGNED NOT NULL,
    created_at   TIMESTAMP NULL,
    updated_at   TIMESTAMP NULL,
    UNIQUE KEY uq_curso_prof (curso_id, materia_id, profesor_id),
    KEY idx_cp_profesor (profesor_id),
    CONSTRAINT fk_cp_curso    FOREIGN KEY (curso_id)    REFERENCES cursos(id),
    CONSTRAINT fk_cp_materia  FOREIGN KEY (materia_id)  REFERENCES materias(id),
    CONSTRAINT fk_cp_profesor FOREIGN KEY (profesor_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. PREINSCRIPCIÓN, LISTA DE ESPERA E INSCRIPCIÓN  [RF06, RF09, RF17, RF18]
-- ---------------------------------------------------------------------

-- Estados: pendiente -> aprobada | rechazada | en_espera
--          en_espera -> pendiente | aprobada | rechazada
-- Si el curso no tiene cupo al enviarla, la base la deja "en_espera".
CREATE TABLE preinscripciones (
    id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curso_id              BIGINT UNSIGNED NOT NULL,
    dni                   VARCHAR(8)   NOT NULL,
    apellido              VARCHAR(100) NOT NULL,
    nombre                VARCHAR(100) NOT NULL,
    fecha_nacimiento      DATE NOT NULL,                -- edad mínima del plan (18)
    email                 VARCHAR(150) NOT NULL,
    telefono              VARCHAR(30)  NOT NULL,        -- la lista de espera se avisa por teléfono
    domicilio             VARCHAR(200) NULL,
    latitud               DECIMAL(10,7) NULL,           -- resultado de Nominatim (RF05)
    longitud              DECIMAL(10,7) NULL,
    nivel_educativo       VARCHAR(25) NULL,
    ultimo_ciclo_aprobado TINYINT UNSIGNED NULL,        -- terminalidad (dato declarado)
    estado                VARCHAR(10) NOT NULL DEFAULT 'pendiente',
    observacion           VARCHAR(500) NULL,
    acepta_privacidad     BOOLEAN NOT NULL,             -- Ley 25.326 (RNF15)
    revisado_por          BIGINT UNSIGNED NULL,
    revisado_en           DATETIME NULL,
    avisado_en            DATETIME NULL,                -- último aviso telefónico de la lista de espera
    avisado_por           BIGINT UNSIGNED NULL,
    created_at            TIMESTAMP NULL,
    updated_at            TIMESTAMP NULL,
    -- Una sola solicitud ABIERTA (pendiente o en espera) por DNI y curso; tras un rechazo se puede reintentar.
    abierta_flag          TINYINT GENERATED ALWAYS AS (IF(estado IN ('pendiente','en_espera'), 1, NULL)) STORED,
    UNIQUE KEY uq_preinsc_abierta (dni, curso_id, abierta_flag),
    KEY idx_preinsc_estado (estado, created_at),
    KEY idx_preinsc_espera (curso_id, estado, created_at),
    KEY idx_preinsc_dni (dni),
    CONSTRAINT fk_preinsc_curso   FOREIGN KEY (curso_id)     REFERENCES cursos(id),
    CONSTRAINT fk_preinsc_revisor FOREIGN KEY (revisado_por) REFERENCES users(id),
    CONSTRAINT fk_preinsc_aviso   FOREIGN KEY (avisado_por)  REFERENCES users(id),
    CONSTRAINT chk_preinsc_estado CHECK (estado IN ('pendiente','en_espera','aprobada','rechazada')),
    CONSTRAINT chk_preinsc_dni    CHECK (dni REGEXP '^[0-9]{7,8}$'),
    CONSTRAINT chk_preinsc_email  CHECK (email LIKE '%_@_%._%'),
    CONSTRAINT chk_preinsc_tel    CHECK (telefono REGEXP '^[0-9 +()-]{6,30}$'),
    CONSTRAINT chk_preinsc_nac    CHECK (fecha_nacimiento >= '1900-01-01'),
    CONSTRAINT chk_preinsc_nivel  CHECK (nivel_educativo IS NULL OR nivel_educativo IN
        ('sin_escolaridad','primaria_incompleta','primaria_completa','secundaria_incompleta','secundaria_completa','superior')),
    CONSTRAINT chk_preinsc_priv   CHECK (acepta_privacidad = TRUE),
    CONSTRAINT chk_preinsc_coord  CHECK ((latitud IS NULL AND longitud IS NULL) OR
                                         (latitud BETWEEN -90 AND 90 AND longitud BETWEEN -180 AND 180)),
    -- Revisada = quién y cuándo (HU09); el rechazo exige el motivo.
    CONSTRAINT chk_preinsc_revis  CHECK (estado IN ('pendiente','en_espera') OR (revisado_por IS NOT NULL AND revisado_en IS NOT NULL)),
    CONSTRAINT chk_preinsc_motivo CHECK (estado <> 'rechazada' OR observacion IS NOT NULL),
    CONSTRAINT chk_preinsc_aviso  CHECK ((avisado_en IS NULL) = (avisado_por IS NULL))
) ENGINE=InnoDB;

CREATE TABLE inscripciones (
    id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    alumno_id          BIGINT UNSIGNED NOT NULL,
    curso_id           BIGINT UNSIGNED NOT NULL,
    preinscripcion_id  BIGINT UNSIGNED NULL UNIQUE,
    fecha_inscripcion  DATE NOT NULL,
    estado             VARCHAR(12) NOT NULL DEFAULT 'activa',
    fecha_baja         DATE NULL,
    motivo_baja        VARCHAR(300) NULL,
    created_at         TIMESTAMP NULL,
    updated_at         TIMESTAMP NULL,
    UNIQUE KEY uq_inscripcion (alumno_id, curso_id),
    KEY idx_insc_curso_estado (curso_id, estado),
    CONSTRAINT fk_insc_alumno  FOREIGN KEY (alumno_id)         REFERENCES users(id),
    CONSTRAINT fk_insc_curso   FOREIGN KEY (curso_id)          REFERENCES cursos(id),
    CONSTRAINT fk_insc_preinsc FOREIGN KEY (preinscripcion_id) REFERENCES preinscripciones(id),
    CONSTRAINT chk_insc_estado CHECK (estado IN ('activa','baja','finalizada')),
    CONSTRAINT chk_insc_baja   CHECK (estado <> 'baja' OR fecha_baja IS NOT NULL),
    CONSTRAINT chk_insc_fbaja  CHECK (fecha_baja IS NULL OR fecha_baja >= fecha_inscripcion)
) ENGINE=InnoDB;

-- Módulos o materias que cursa cada inscripto (en terminalidad, solo los adeudados).
-- 'acreditada' exige nota final >= nota de aprobación del plan y la asistencia
-- mínima (lo controla el trigger); la fecha de acreditación va al historial.
CREATE TABLE inscripcion_materias (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inscripcion_id      BIGINT UNSIGNED NOT NULL,
    materia_id          BIGINT UNSIGNED NOT NULL,
    estado              VARCHAR(12) NOT NULL DEFAULT 'cursando',
    nota_final          DECIMAL(4,2) NULL,
    fecha_acreditacion  DATE NULL,
    created_at          TIMESTAMP NULL,
    updated_at          TIMESTAMP NULL,
    UNIQUE KEY uq_insc_materia (inscripcion_id, materia_id),
    CONSTRAINT fk_im_insc    FOREIGN KEY (inscripcion_id) REFERENCES inscripciones(id),
    CONSTRAINT fk_im_materia FOREIGN KEY (materia_id)     REFERENCES materias(id),
    CONSTRAINT chk_im_estado CHECK (estado IN ('cursando','acreditada','pendiente','libre')),
    CONSTRAINT chk_im_nota   CHECK (nota_final IS NULL OR nota_final BETWEEN 1 AND 10),
    CONSTRAINT chk_im_acred  CHECK (estado <> 'acreditada' OR (nota_final IS NOT NULL AND fecha_acreditacion IS NOT NULL)),
    CONSTRAINT chk_im_fecha  CHECK (estado = 'acreditada' OR fecha_acreditacion IS NULL)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. GESTIÓN ACADÉMICA  [RF22-RF32]
-- ---------------------------------------------------------------------

-- Notas de 1 a 10. El recuperatorio solo corresponde si hay un parcial desaprobado.
CREATE TABLE evaluaciones (
    id                     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inscripcion_materia_id BIGINT UNSIGNED NOT NULL,
    tipo                   VARCHAR(16) NOT NULL,
    nota                   DECIMAL(4,2) NOT NULL,
    fecha                  DATE NOT NULL,
    cargada_por            BIGINT UNSIGNED NOT NULL,
    created_at             TIMESTAMP NULL,
    updated_at             TIMESTAMP NULL,
    UNIQUE KEY uq_evaluacion (inscripcion_materia_id, tipo, fecha),
    CONSTRAINT fk_eval_im    FOREIGN KEY (inscripcion_materia_id) REFERENCES inscripcion_materias(id),
    CONSTRAINT fk_eval_prof  FOREIGN KEY (cargada_por)            REFERENCES users(id),
    CONSTRAINT chk_eval_tipo CHECK (tipo IN ('parcial','recuperatorio','trabajo_practico','final')),
    CONSTRAINT chk_eval_nota CHECK (nota BETWEEN 1 AND 10)
) ENGINE=InnoDB;

CREATE TABLE clases (
    id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curso_profesor_id  BIGINT UNSIGNED NOT NULL,
    fecha              DATE NOT NULL,
    hora_inicio        TIME NULL,
    hora_fin           TIME NULL,
    tema               VARCHAR(200) NULL,
    estado             VARCHAR(12) NOT NULL DEFAULT 'dictada',
    created_at         TIMESTAMP NULL,
    updated_at         TIMESTAMP NULL,
    UNIQUE KEY uq_clase (curso_profesor_id, fecha, hora_inicio),
    CONSTRAINT fk_clase_cp      FOREIGN KEY (curso_profesor_id) REFERENCES curso_profesores(id),
    CONSTRAINT chk_clase_estado CHECK (estado IN ('dictada','suspendida')),
    CONSTRAINT chk_clase_horas  CHECK (hora_fin IS NULL OR hora_inicio IS NULL OR hora_fin > hora_inicio)
) ENGINE=InnoDB;

-- 'justificado' no cuenta como ausente (entrevista 13/10).
CREATE TABLE asistencias (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    clase_id        BIGINT UNSIGNED NOT NULL,
    inscripcion_id  BIGINT UNSIGNED NOT NULL,
    estado          VARCHAR(12) NOT NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    UNIQUE KEY uq_asistencia (clase_id, inscripcion_id),
    CONSTRAINT fk_asist_clase   FOREIGN KEY (clase_id)       REFERENCES clases(id) ON DELETE CASCADE,
    CONSTRAINT fk_asist_insc    FOREIGN KEY (inscripcion_id) REFERENCES inscripciones(id),
    CONSTRAINT chk_asist_estado CHECK (estado IN ('presente','ausente','tarde','justificado'))
) ENGINE=InnoDB;

CREATE TABLE tareas (
    id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curso_profesor_id  BIGINT UNSIGNED NOT NULL,
    titulo             VARCHAR(150) NOT NULL,
    consigna           TEXT NULL,
    archivo_ruta       VARCHAR(255) NULL,
    fecha_entrega      DATETIME NOT NULL,
    created_at         TIMESTAMP NULL,
    updated_at         TIMESTAMP NULL,
    CONSTRAINT fk_tarea_cp FOREIGN KEY (curso_profesor_id) REFERENCES curso_profesores(id)
) ENGINE=InnoDB;

CREATE TABLE entregas (
    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tarea_id          BIGINT UNSIGNED NOT NULL,
    inscripcion_id    BIGINT UNSIGNED NOT NULL,
    archivo_ruta      VARCHAR(255) NOT NULL,
    archivo_tamano    INT UNSIGNED NOT NULL,
    archivo_tipo      VARCHAR(5)   NOT NULL,
    fecha_entrega     DATETIME NOT NULL,
    fuera_de_termino  BOOLEAN NOT NULL DEFAULT FALSE,   -- lo calcula el trigger
    devolucion        TEXT NULL,
    devuelta_en       DATETIME NULL,
    created_at        TIMESTAMP NULL,
    updated_at        TIMESTAMP NULL,
    UNIQUE KEY uq_entrega (tarea_id, inscripcion_id),
    CONSTRAINT fk_entrega_tarea FOREIGN KEY (tarea_id)       REFERENCES tareas(id) ON DELETE CASCADE,
    CONSTRAINT fk_entrega_insc  FOREIGN KEY (inscripcion_id) REFERENCES inscripciones(id),
    CONSTRAINT chk_entrega_tam  CHECK (archivo_tamano BETWEEN 1 AND 10485760),          -- 10 MB (RNF16)
    CONSTRAINT chk_entrega_tipo CHECK (archivo_tipo IN ('pdf','docx','jpg','png','zip')),-- RNF16
    CONSTRAINT chk_entrega_dev  CHECK (devolucion IS NULL OR devuelta_en IS NOT NULL)
) ENGINE=InnoDB;

CREATE TABLE avisos (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curso_id    BIGINT UNSIGNED NOT NULL,
    creado_por  BIGINT UNSIGNED NOT NULL,
    titulo      VARCHAR(150) NOT NULL,
    mensaje     TEXT NOT NULL,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,
    CONSTRAINT fk_aviso_curso FOREIGN KEY (curso_id)   REFERENCES cursos(id),
    CONSTRAINT fk_aviso_autor FOREIGN KEY (creado_por) REFERENCES users(id)
) ENGINE=InnoDB;

-- curso_id NULL = evento institucional (calendario académico, lo carga el Administrador, HU10).
CREATE TABLE eventos (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    curso_id      BIGINT UNSIGNED NULL,
    tarea_id      BIGINT UNSIGNED NULL,
    titulo        VARCHAR(150) NOT NULL,
    descripcion   TEXT NULL,
    tipo          VARCHAR(15) NOT NULL,
    fecha_inicio  DATETIME NOT NULL,
    fecha_fin     DATETIME NULL,
    publico       BOOLEAN NOT NULL DEFAULT FALSE,
    creado_por    BIGINT UNSIGNED NOT NULL,
    created_at    TIMESTAMP NULL,
    updated_at    TIMESTAMP NULL,
    KEY idx_evento_fecha (fecha_inicio),
    CONSTRAINT fk_evento_curso   FOREIGN KEY (curso_id)   REFERENCES cursos(id),
    CONSTRAINT fk_evento_tarea   FOREIGN KEY (tarea_id)   REFERENCES tareas(id) ON DELETE CASCADE,
    CONSTRAINT fk_evento_autor   FOREIGN KEY (creado_por) REFERENCES users(id),
    CONSTRAINT chk_evento_tipo   CHECK (tipo IN ('institucional','evaluacion','entrega','feriado','otro')),
    CONSTRAINT chk_evento_fechas CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio),
    CONSTRAINT chk_evento_pub    CHECK (curso_id IS NULL OR publico = FALSE)   -- lo de un curso nunca es público
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. SITIO PÚBLICO  [RF01, RF02, RF08, RF19]
-- ---------------------------------------------------------------------

CREATE TABLE noticias (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo        VARCHAR(200) NOT NULL,
    slug          VARCHAR(220) NOT NULL UNIQUE,
    resumen       VARCHAR(400) NULL,
    cuerpo        MEDIUMTEXT NOT NULL,
    imagen_ruta   VARCHAR(255) NULL,
    estado        VARCHAR(10) NOT NULL DEFAULT 'borrador',
    publicada_en  DATETIME NULL,
    autor_id      BIGINT UNSIGNED NOT NULL,
    created_at    TIMESTAMP NULL,
    updated_at    TIMESTAMP NULL,
    KEY idx_noticia_pub (estado, publicada_en),
    CONSTRAINT fk_noticia_autor   FOREIGN KEY (autor_id) REFERENCES users(id),
    CONSTRAINT chk_noticia_estado CHECK (estado IN ('borrador','publicada')),
    CONSTRAINT chk_noticia_slug   CHECK (slug REGEXP '^[a-z0-9]+(-[a-z0-9]+)*$'),
    CONSTRAINT chk_noticia_fecha  CHECK (estado <> 'publicada' OR publicada_en IS NOT NULL)
) ENGINE=InnoDB;

CREATE TABLE secciones (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    clave            VARCHAR(40) NOT NULL UNIQUE,       -- inicio, historia, contacto, servicios, privacidad
    titulo           VARCHAR(150) NOT NULL,
    contenido        MEDIUMTEXT NOT NULL,
    imagen_ruta      VARCHAR(255) NULL,
    actualizado_por  BIGINT UNSIGNED NULL,
    created_at       TIMESTAMP NULL,
    updated_at       TIMESTAMP NULL,
    CONSTRAINT fk_seccion_usuario FOREIGN KEY (actualizado_por) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8. AUDITORÍA  [RF21, RF36, HU16, HU18, RNF14]
--    La consultan el Administrador y el Auditor (solo lectura).
-- ---------------------------------------------------------------------

CREATE TABLE auditoria (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id          BIGINT UNSIGNED NULL,           -- NULL = cambio hecho fuera del sistema o por el público
    accion              VARCHAR(15) NOT NULL,
    tabla               VARCHAR(60) NOT NULL,
    registro_id         BIGINT UNSIGNED NULL,
    valores_anteriores  JSON NULL,
    valores_nuevos      JSON NULL,
    ip                  VARCHAR(45) NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_tabla (tabla, registro_id),
    KEY idx_audit_usuario (usuario_id, created_at),
    KEY idx_audit_fecha (created_at),
    CONSTRAINT fk_audit_usuario FOREIGN KEY (usuario_id) REFERENCES users(id),
    -- 'descarga' = historial en PDF o planilla Excel (la registra Laravel).
    CONSTRAINT chk_audit_accion CHECK (accion IN ('alta','baja','modificacion','login','login_fallido','descarga'))
) ENGINE=InnoDB;

-- =====================================================================
-- 9. PROCEDIMIENTO Y TRIGGERS
--    Mensajes en español: Laravel los muestra o los registra tal cual.
--    SQLSTATE 45000 = error definido por el sistema.
-- =====================================================================

DELIMITER //

-- ---------- Rol Auditor: solo lectura (HU18, RF35) ----------
-- Lo llaman los triggers de las tablas sensibles antes de cada cambio.
-- Rechaza el cambio si el usuario logueado (@app_usuario_id) es Auditor y no
-- tiene además otro rol que pueda escribir.
CREATE PROCEDURE exigir_escritura()
READS SQL DATA
BEGIN
    IF @app_usuario_id IS NOT NULL
       AND EXISTS (SELECT 1 FROM model_has_roles mr JOIN roles r ON r.id = mr.role_id
                   WHERE mr.model_id = @app_usuario_id AND mr.model_type = 'App\\Models\\User'
                     AND r.name = 'auditor')
       AND NOT EXISTS (SELECT 1 FROM model_has_roles mr JOIN roles r ON r.id = mr.role_id
                       WHERE mr.model_id = @app_usuario_id AND mr.model_type = 'App\\Models\\User'
                         AND r.name IN ('administrador','profesor')) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El rol Auditor es de solo lectura';
    END IF;
END//

CREATE TRIGGER trg_ro_preinsc_bd BEFORE DELETE ON preinscripciones     FOR EACH ROW CALL exigir_escritura()//
CREATE TRIGGER trg_ro_insc_bd    BEFORE DELETE ON inscripciones        FOR EACH ROW CALL exigir_escritura()//
CREATE TRIGGER trg_ro_im_bd      BEFORE DELETE ON inscripcion_materias FOR EACH ROW CALL exigir_escritura()//
CREATE TRIGGER trg_ro_eval_bd    BEFORE DELETE ON evaluaciones         FOR EACH ROW CALL exigir_escritura()//
CREATE TRIGGER trg_ro_asist_bu   BEFORE UPDATE ON asistencias          FOR EACH ROW CALL exigir_escritura()//
CREATE TRIGGER trg_ro_asist_bd   BEFORE DELETE ON asistencias          FOR EACH ROW CALL exigir_escritura()//
CREATE TRIGGER trg_ro_users_bi   BEFORE INSERT ON users                FOR EACH ROW CALL exigir_escritura()//
CREATE TRIGGER trg_ro_users_bd   BEFORE DELETE ON users                FOR EACH ROW CALL exigir_escritura()//
CREATE TRIGGER trg_ro_mhr_bi     BEFORE INSERT ON model_has_roles      FOR EACH ROW CALL exigir_escritura()//
CREATE TRIGGER trg_ro_mhr_bu     BEFORE UPDATE ON model_has_roles      FOR EACH ROW CALL exigir_escritura()//
CREATE TRIGGER trg_ro_mhr_bd     BEFORE DELETE ON model_has_roles      FOR EACH ROW CALL exigir_escritura()//
-- (En las demás tablas sensibles el control está dentro de su propio trigger BEFORE.)

-- ---------- Auditoría inmodificable (RNF14) ----------
CREATE TRIGGER trg_auditoria_no_update BEFORE UPDATE ON auditoria
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La auditoría no puede modificarse'//

CREATE TRIGGER trg_auditoria_no_delete BEFORE DELETE ON auditoria
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La auditoría no puede eliminarse'//

-- ---------- Usuarios ----------
-- El Auditor solo puede cambiar su propio usuario (contraseña, último acceso).
CREATE TRIGGER trg_users_bu BEFORE UPDATE ON users
FOR EACH ROW
BEGIN
    IF NOT (NEW.id <=> @app_usuario_id) THEN
        CALL exigir_escritura();
    END IF;
END//

-- Se audita el alta y los cambios de datos, estado y contraseña (sin guardar el hash).
CREATE TRIGGER trg_users_ai AFTER INSERT ON users
FOR EACH ROW
INSERT INTO auditoria (usuario_id, accion, tabla, registro_id, valores_nuevos, ip)
VALUES (@app_usuario_id, 'alta', 'users', NEW.id,
        JSON_OBJECT('dni', NEW.dni, 'apellido', NEW.apellido, 'nombre', NEW.nombre, 'email', NEW.email, 'activo', NEW.activo),
        @app_ip)//

CREATE TRIGGER trg_users_au AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    IF NOT (OLD.dni <=> NEW.dni) OR NOT (OLD.apellido <=> NEW.apellido) OR NOT (OLD.nombre <=> NEW.nombre)
       OR NOT (OLD.email <=> NEW.email) OR NOT (OLD.activo <=> NEW.activo)
       OR NOT (OLD.deleted_at <=> NEW.deleted_at) OR NOT (OLD.password <=> NEW.password) THEN
        INSERT INTO auditoria (usuario_id, accion, tabla, registro_id, valores_anteriores, valores_nuevos, ip)
        VALUES (@app_usuario_id,
                IF(OLD.activo AND NOT NEW.activo, 'baja', 'modificacion'), 'users', NEW.id,
                JSON_OBJECT('dni', OLD.dni, 'apellido', OLD.apellido, 'nombre', OLD.nombre, 'email', OLD.email,
                            'activo', OLD.activo, 'deleted_at', OLD.deleted_at),
                JSON_OBJECT('dni', NEW.dni, 'apellido', NEW.apellido, 'nombre', NEW.nombre, 'email', NEW.email,
                            'activo', NEW.activo, 'deleted_at', NEW.deleted_at,
                            'password_cambiada', NOT (OLD.password <=> NEW.password)),
                @app_ip);
    END IF;
END//

-- Los cambios de rol quedan auditados (quién le dio o le sacó un rol a quién).
CREATE TRIGGER trg_mhr_ai AFTER INSERT ON model_has_roles
FOR EACH ROW
INSERT INTO auditoria (usuario_id, accion, tabla, registro_id, valores_nuevos, ip)
VALUES (@app_usuario_id, 'alta', 'model_has_roles', NEW.model_id,
        JSON_OBJECT('rol', (SELECT name FROM roles WHERE id = NEW.role_id)), @app_ip)//

CREATE TRIGGER trg_mhr_ad AFTER DELETE ON model_has_roles
FOR EACH ROW
INSERT INTO auditoria (usuario_id, accion, tabla, registro_id, valores_anteriores, ip)
VALUES (@app_usuario_id, 'baja', 'model_has_roles', OLD.model_id,
        JSON_OBJECT('rol', (SELECT name FROM roles WHERE id = OLD.role_id)), @app_ip)//

-- ---------- Profesores asignados ----------
-- La materia tiene que ser del plan del curso y el usuario tiene que tener el rol profesor.
CREATE TRIGGER trg_cp_bi BEFORE INSERT ON curso_profesores
FOR EACH ROW
BEGIN
    IF NOT EXISTS (SELECT 1 FROM cursos c JOIN materias m ON m.plan_id = c.plan_id
                   WHERE c.id = NEW.curso_id AND m.id = NEW.materia_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La materia no pertenece al plan del curso';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM model_has_roles mr JOIN roles r ON r.id = mr.role_id
                   WHERE mr.model_id = NEW.profesor_id AND mr.model_type = 'App\\Models\\User'
                     AND r.name = 'profesor') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El usuario asignado no tiene el rol profesor';
    END IF;
END//

-- ---------- Preinscripciones y lista de espera (HU04, HU09, RF09) ----------
CREATE TRIGGER trg_preinsc_bi BEFORE INSERT ON preinscripciones
FOR EACH ROW
BEGIN
    DECLARE v_edad_min INT;
    DECLARE v_cupo INT;
    DECLARE v_ocupados INT;
    DECLARE v_msg VARCHAR(200);
    CALL exigir_escritura();
    SELECT p.edad_minima, c.cupo INTO v_edad_min, v_cupo
    FROM cursos c
    JOIN sedes s          ON s.id = c.sede_id
    JOIN planes_estudio p ON p.id = c.plan_id
    WHERE c.id = NEW.curso_id AND c.estado = 'activo' AND s.activo AND p.activo;
    IF v_cupo IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El curso no está disponible para preinscripción';
    END IF;
    IF NEW.estado <> 'pendiente' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Una preinscripción nueva se envía como pendiente';
    END IF;
    IF TIMESTAMPDIFF(YEAR, NEW.fecha_nacimiento, CURDATE()) < v_edad_min THEN
        SET v_msg = CONCAT('El aspirante no cumple la edad mínima del curso (', v_edad_min, ' años)');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;
    -- Sin cupo: queda en lista de espera, por orden de llegada.
    SELECT COUNT(*) INTO v_ocupados FROM inscripciones WHERE curso_id = NEW.curso_id AND estado = 'activa';
    IF v_ocupados >= v_cupo THEN
        SET NEW.estado = 'en_espera';
    END IF;
END//

CREATE TRIGGER trg_preinsc_bu BEFORE UPDATE ON preinscripciones
FOR EACH ROW
BEGIN
    CALL exigir_escritura();
    IF OLD.estado IN ('aprobada','rechazada') AND NEW.estado <> OLD.estado THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Una preinscripción ya revisada no puede cambiar de estado';
    END IF;
    IF NEW.curso_id <> OLD.curso_id OR NEW.dni <> OLD.dni THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se cambia el curso ni el DNI de una preinscripción: crear otra';
    END IF;
END//

CREATE TRIGGER trg_preinsc_ai AFTER INSERT ON preinscripciones
FOR EACH ROW
INSERT INTO auditoria (usuario_id, accion, tabla, registro_id, valores_nuevos, ip)
VALUES (@app_usuario_id, 'alta', 'preinscripciones', NEW.id,
        JSON_OBJECT('curso_id', NEW.curso_id, 'dni', NEW.dni, 'estado', NEW.estado), @app_ip)//

CREATE TRIGGER trg_preinsc_au AFTER UPDATE ON preinscripciones
FOR EACH ROW
BEGIN
    IF NOT (OLD.estado <=> NEW.estado) OR NOT (OLD.avisado_en <=> NEW.avisado_en) THEN
        INSERT INTO auditoria (usuario_id, accion, tabla, registro_id, valores_anteriores, valores_nuevos, ip)
        VALUES (@app_usuario_id, 'modificacion', 'preinscripciones', NEW.id,
                JSON_OBJECT('estado', OLD.estado, 'avisado_en', OLD.avisado_en),
                JSON_OBJECT('estado', NEW.estado, 'observacion', NEW.observacion, 'revisado_por', NEW.revisado_por,
                            'avisado_en', NEW.avisado_en),
                @app_ip);
    END IF;
END//

-- ---------- Inscripciones: alumno, curso activo y cupo (HU07, HU09) ----------
CREATE TRIGGER trg_insc_bi BEFORE INSERT ON inscripciones
FOR EACH ROW
BEGIN
    DECLARE v_cupo INT;
    DECLARE v_estado VARCHAR(10);
    DECLARE v_ocupados INT;
    CALL exigir_escritura();
    IF NOT EXISTS (SELECT 1 FROM model_has_roles mr JOIN roles r ON r.id = mr.role_id
                   WHERE mr.model_id = NEW.alumno_id AND mr.model_type = 'App\\Models\\User'
                     AND r.name = 'alumno') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El usuario inscripto no tiene el rol alumno';
    END IF;
    -- Si viene de una preinscripción, tiene que estar aprobada y ser del mismo curso.
    IF NEW.preinscripcion_id IS NOT NULL AND NOT EXISTS (
        SELECT 1 FROM preinscripciones WHERE id = NEW.preinscripcion_id
                                         AND estado = 'aprobada' AND curso_id = NEW.curso_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo se da de alta una preinscripción aprobada para ese curso';
    END IF;
    -- FOR UPDATE bloquea la fila del curso: dos altas simultáneas no superan el cupo.
    SELECT cupo, estado INTO v_cupo, v_estado FROM cursos WHERE id = NEW.curso_id FOR UPDATE;
    IF v_estado <> 'activo' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se puede inscribir en un curso que no está activo';
    END IF;
    IF NEW.estado = 'activa' THEN
        SELECT COUNT(*) INTO v_ocupados FROM inscripciones WHERE curso_id = NEW.curso_id AND estado = 'activa';
        IF v_ocupados >= v_cupo THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Sin cupo: el curso está completo';
        END IF;
    END IF;
END//

CREATE TRIGGER trg_insc_bu BEFORE UPDATE ON inscripciones
FOR EACH ROW
BEGIN
    DECLARE v_cupo INT;
    DECLARE v_ocupados INT;
    CALL exigir_escritura();
    IF NEW.estado = 'activa' AND OLD.estado <> 'activa' THEN      -- reincorporación
        SELECT cupo INTO v_cupo FROM cursos WHERE id = NEW.curso_id FOR UPDATE;
        SELECT COUNT(*) INTO v_ocupados FROM inscripciones WHERE curso_id = NEW.curso_id AND estado = 'activa';
        IF v_ocupados >= v_cupo THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Sin cupo: el curso está completo';
        END IF;
    END IF;
    IF NEW.curso_id <> OLD.curso_id OR NEW.alumno_id <> OLD.alumno_id THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se cambia el alumno ni el curso de una inscripción: dar de baja y crear otra';
    END IF;
END//

CREATE TRIGGER trg_insc_ai AFTER INSERT ON inscripciones
FOR EACH ROW
INSERT INTO auditoria (usuario_id, accion, tabla, registro_id, valores_nuevos, ip)
VALUES (@app_usuario_id, 'alta', 'inscripciones', NEW.id,
        JSON_OBJECT('alumno_id', NEW.alumno_id, 'curso_id', NEW.curso_id, 'estado', NEW.estado), @app_ip)//

CREATE TRIGGER trg_insc_au AFTER UPDATE ON inscripciones
FOR EACH ROW
BEGIN
    IF NOT (OLD.estado <=> NEW.estado) THEN
        INSERT INTO auditoria (usuario_id, accion, tabla, registro_id, valores_anteriores, valores_nuevos, ip)
        VALUES (@app_usuario_id, IF(NEW.estado = 'baja', 'baja', 'modificacion'), 'inscripciones', NEW.id,
                JSON_OBJECT('estado', OLD.estado),
                JSON_OBJECT('estado', NEW.estado, 'fecha_baja', NEW.fecha_baja, 'motivo_baja', NEW.motivo_baja),
                @app_ip);
    END IF;
END//

-- ---------- Módulos del inscripto: plan del curso y acreditación (HU11, HU17) ----------
-- Acreditar exige nota final >= nota de aprobación del plan (6) y, si hay clases
-- registradas, asistencia >= mínima del plan (80 %), contando las justificadas.
CREATE PROCEDURE validar_acreditacion(IN p_inscripcion_id BIGINT UNSIGNED, IN p_materia_id BIGINT UNSIGNED,
                                      IN p_nota DECIMAL(4,2))
READS SQL DATA
BEGIN
    DECLARE v_nota_min DECIMAL(4,2);
    DECLARE v_asist_min INT;
    DECLARE v_dictadas INT;
    DECLARE v_presentes INT;
    DECLARE v_msg VARCHAR(200);
    SELECT p.nota_aprobacion, p.asistencia_minima INTO v_nota_min, v_asist_min
    FROM inscripciones i JOIN cursos c ON c.id = i.curso_id JOIN planes_estudio p ON p.id = c.plan_id
    WHERE i.id = p_inscripcion_id;
    IF p_nota < v_nota_min THEN
        SET v_msg = CONCAT('Para acreditar el módulo hace falta una nota final de ',
                           TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM v_nota_min)), ' o más');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;
    SELECT COUNT(*), SUM(a.estado IN ('presente','tarde','justificado'))
      INTO v_dictadas, v_presentes
    FROM asistencias a
    JOIN clases cl           ON cl.id = a.clase_id AND cl.estado = 'dictada'
    JOIN curso_profesores cp ON cp.id = cl.curso_profesor_id
    WHERE a.inscripcion_id = p_inscripcion_id AND cp.materia_id = p_materia_id;
    IF v_dictadas > 0 AND 100 * v_presentes / v_dictadas < v_asist_min THEN
        SET v_msg = CONCAT('Para acreditar el módulo hace falta el ', v_asist_min, ' % de asistencia');
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
    END IF;
END//

CREATE TRIGGER trg_im_bi BEFORE INSERT ON inscripcion_materias
FOR EACH ROW
BEGIN
    CALL exigir_escritura();
    IF NOT EXISTS (SELECT 1 FROM inscripciones i JOIN cursos c ON c.id = i.curso_id
                   JOIN materias m ON m.plan_id = c.plan_id
                   WHERE i.id = NEW.inscripcion_id AND m.id = NEW.materia_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La materia no pertenece al plan del curso del alumno';
    END IF;
    IF NEW.estado = 'acreditada' THEN
        IF NEW.fecha_acreditacion IS NULL THEN SET NEW.fecha_acreditacion = CURDATE(); END IF;
        IF NEW.nota_final IS NOT NULL THEN
            CALL validar_acreditacion(NEW.inscripcion_id, NEW.materia_id, NEW.nota_final);
        END IF;
    END IF;
END//

CREATE TRIGGER trg_im_bu BEFORE UPDATE ON inscripcion_materias
FOR EACH ROW
BEGIN
    CALL exigir_escritura();
    IF NEW.inscripcion_id <> OLD.inscripcion_id OR NEW.materia_id <> OLD.materia_id THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se puede pasar un módulo a otro alumno o materia';
    END IF;
    IF NEW.estado = 'acreditada' THEN
        IF NEW.fecha_acreditacion IS NULL THEN SET NEW.fecha_acreditacion = CURDATE(); END IF;
        IF NEW.nota_final IS NOT NULL THEN
            CALL validar_acreditacion(NEW.inscripcion_id, NEW.materia_id, NEW.nota_final);
        END IF;
    ELSE
        SET NEW.fecha_acreditacion = NULL;
    END IF;
END//

CREATE TRIGGER trg_im_au AFTER UPDATE ON inscripcion_materias
FOR EACH ROW
BEGIN
    IF NOT (OLD.estado <=> NEW.estado) OR NOT (OLD.nota_final <=> NEW.nota_final) THEN
        INSERT INTO auditoria (usuario_id, accion, tabla, registro_id, valores_anteriores, valores_nuevos, ip)
        VALUES (@app_usuario_id, 'modificacion', 'inscripcion_materias', NEW.id,
                JSON_OBJECT('estado', OLD.estado, 'nota_final', OLD.nota_final),
                JSON_OBJECT('estado', NEW.estado, 'nota_final', NEW.nota_final,
                            'fecha_acreditacion', NEW.fecha_acreditacion), @app_ip);
    END IF;
END//

-- ---------- Notas: solo el profesor de esa materia y curso, o un Administrador (HU11) ----------
CREATE TRIGGER trg_eval_bi BEFORE INSERT ON evaluaciones
FOR EACH ROW
BEGIN
    CALL exigir_escritura();
    IF NOT EXISTS (
        SELECT 1 FROM inscripcion_materias im
        JOIN inscripciones i      ON i.id = im.inscripcion_id
        JOIN curso_profesores cp  ON cp.curso_id = i.curso_id AND cp.materia_id = im.materia_id
        WHERE im.id = NEW.inscripcion_materia_id AND cp.profesor_id = NEW.cargada_por)
       AND NOT EXISTS (
        SELECT 1 FROM model_has_roles mr JOIN roles r ON r.id = mr.role_id
        WHERE mr.model_id = NEW.cargada_por AND mr.model_type = 'App\\Models\\User' AND r.name = 'administrador') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El profesor no tiene asignada esta materia en este curso';
    END IF;
    -- Recuperatorio: solo si antes hubo un parcial desaprobado (nota menor a la de aprobación).
    IF NEW.tipo = 'recuperatorio' AND NOT EXISTS (
        SELECT 1 FROM evaluaciones e
        JOIN inscripcion_materias im ON im.id = e.inscripcion_materia_id
        JOIN inscripciones i         ON i.id = im.inscripcion_id
        JOIN cursos c                ON c.id = i.curso_id
        JOIN planes_estudio p        ON p.id = c.plan_id
        WHERE e.inscripcion_materia_id = NEW.inscripcion_materia_id AND e.tipo = 'parcial'
          AND e.nota < p.nota_aprobacion AND e.fecha <= NEW.fecha) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El recuperatorio solo corresponde si hay un parcial desaprobado';
    END IF;
END//

CREATE TRIGGER trg_eval_bu BEFORE UPDATE ON evaluaciones
FOR EACH ROW
BEGIN
    CALL exigir_escritura();
    IF NEW.inscripcion_materia_id <> OLD.inscripcion_materia_id THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se puede mover una nota a otro alumno o materia';
    END IF;
END//

CREATE TRIGGER trg_eval_ai AFTER INSERT ON evaluaciones
FOR EACH ROW
INSERT INTO auditoria (usuario_id, accion, tabla, registro_id, valores_nuevos, ip)
VALUES (IFNULL(@app_usuario_id, NEW.cargada_por), 'alta', 'evaluaciones', NEW.id,
        JSON_OBJECT('inscripcion_materia_id', NEW.inscripcion_materia_id, 'tipo', NEW.tipo,
                    'nota', NEW.nota, 'fecha', NEW.fecha), @app_ip)//

CREATE TRIGGER trg_eval_au AFTER UPDATE ON evaluaciones
FOR EACH ROW
INSERT INTO auditoria (usuario_id, accion, tabla, registro_id, valores_anteriores, valores_nuevos, ip)
VALUES (@app_usuario_id, 'modificacion', 'evaluaciones', NEW.id,
        JSON_OBJECT('tipo', OLD.tipo, 'nota', OLD.nota, 'fecha', OLD.fecha),
        JSON_OBJECT('tipo', NEW.tipo, 'nota', NEW.nota, 'fecha', NEW.fecha), @app_ip)//

CREATE TRIGGER trg_eval_ad AFTER DELETE ON evaluaciones
FOR EACH ROW
INSERT INTO auditoria (usuario_id, accion, tabla, registro_id, valores_anteriores, ip)
VALUES (@app_usuario_id, 'baja', 'evaluaciones', OLD.id,
        JSON_OBJECT('inscripcion_materia_id', OLD.inscripcion_materia_id, 'tipo', OLD.tipo,
                    'nota', OLD.nota, 'fecha', OLD.fecha), @app_ip)//

-- ---------- Asistencia: el alumno tiene que ser del curso de la clase (HU13) ----------
CREATE TRIGGER trg_asist_bi BEFORE INSERT ON asistencias
FOR EACH ROW
BEGIN
    CALL exigir_escritura();
    IF NOT EXISTS (SELECT 1 FROM clases cl
                   JOIN curso_profesores cp ON cp.id = cl.curso_profesor_id
                   JOIN inscripciones i     ON i.curso_id = cp.curso_id
                   WHERE cl.id = NEW.clase_id AND i.id = NEW.inscripcion_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El alumno no pertenece al curso de esta clase';
    END IF;
END//

-- Cada corrección de asistencia queda auditada con el estado anterior y el nuevo (HU16).
CREATE TRIGGER trg_asist_au AFTER UPDATE ON asistencias
FOR EACH ROW
BEGIN
    IF NOT (OLD.estado <=> NEW.estado) THEN
        INSERT INTO auditoria (usuario_id, accion, tabla, registro_id, valores_anteriores, valores_nuevos, ip)
        VALUES (@app_usuario_id, 'modificacion', 'asistencias', NEW.id,
                JSON_OBJECT('estado', OLD.estado, 'clase_id', OLD.clase_id, 'inscripcion_id', OLD.inscripcion_id),
                JSON_OBJECT('estado', NEW.estado), @app_ip);
    END IF;
END//

-- ---------- Entregas: alumno del curso, fuera de término y reemplazo (HU12) ----------
CREATE TRIGGER trg_entrega_bi BEFORE INSERT ON entregas
FOR EACH ROW
BEGIN
    DECLARE v_limite DATETIME;
    SELECT t.fecha_entrega INTO v_limite
    FROM tareas t
    JOIN curso_profesores cp ON cp.id = t.curso_profesor_id
    JOIN inscripciones i     ON i.curso_id = cp.curso_id
    WHERE t.id = NEW.tarea_id AND i.id = NEW.inscripcion_id;
    IF v_limite IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El alumno no pertenece al curso de esta tarea';
    END IF;
    SET NEW.fuera_de_termino = (NEW.fecha_entrega > v_limite);
END//

CREATE TRIGGER trg_entrega_bu BEFORE UPDATE ON entregas
FOR EACH ROW
BEGIN
    DECLARE v_limite DATETIME;
    SELECT fecha_entrega INTO v_limite FROM tareas WHERE id = NEW.tarea_id;
    IF NOT (NEW.archivo_ruta <=> OLD.archivo_ruta) AND NEW.fecha_entrega > v_limite THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Vencida la fecha límite, la entrega ya no se puede reemplazar';
    END IF;
    IF NEW.inscripcion_id <> OLD.inscripcion_id OR NEW.tarea_id <> OLD.tarea_id THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se puede pasar una entrega a otro alumno o tarea';
    END IF;
    SET NEW.fuera_de_termino = (NEW.fecha_entrega > v_limite);
END//

DELIMITER ;

-- =====================================================================
-- 10. VISTAS
-- =====================================================================

-- Cupo disponible = cupo - inscriptos activos (HU07, HU09)
CREATE VIEW v_cupos_disponibles AS
SELECT c.id AS curso_id, c.cupo,
       COUNT(i.id)          AS inscriptos,
       c.cupo - COUNT(i.id) AS disponibles
FROM cursos c
LEFT JOIN inscripciones i ON i.curso_id = c.id AND i.estado = 'activa'
GROUP BY c.id, c.cupo;

-- Lista de espera de cada curso, por orden de llegada, con el teléfono para
-- avisar cuando se libera un lugar (RF09, HU09).
CREATE VIEW v_lista_espera AS
SELECT p.curso_id,
       ROW_NUMBER() OVER (PARTITION BY p.curso_id ORDER BY p.created_at, p.id) AS posicion,
       p.id AS preinscripcion_id, p.apellido, p.nombre, p.dni, p.telefono, p.email,
       p.created_at AS anotado_en, p.avisado_en
FROM preinscripciones p
WHERE p.estado = 'en_espera';

-- Porcentaje de asistencia por alumno y materia (HU13):
-- (presentes + tarde + justificadas) / clases dictadas. Las suspendidas no cuentan.
CREATE VIEW v_asistencia_porcentaje AS
SELECT a.inscripcion_id, cp.materia_id,
       COUNT(*)                                           AS clases_dictadas,
       SUM(a.estado IN ('presente','tarde'))              AS presentes,
       SUM(a.estado = 'justificado')                      AS justificadas,
       SUM(a.estado = 'ausente')                          AS ausentes,
       ROUND(100 * SUM(a.estado IN ('presente','tarde','justificado')) / COUNT(*), 1) AS porcentaje,
       MAX(p.asistencia_minima)                           AS asistencia_minima,
       (100 * SUM(a.estado IN ('presente','tarde','justificado')) / COUNT(*) >= MAX(p.asistencia_minima)) AS cumple_minimo
FROM asistencias a
JOIN clases cl           ON cl.id = a.clase_id AND cl.estado = 'dictada'
JOIN curso_profesores cp ON cp.id = cl.curso_profesor_id
JOIN cursos c            ON c.id  = cp.curso_id
JOIN planes_estudio p    ON p.id  = c.plan_id
GROUP BY a.inscripcion_id, cp.materia_id;

-- Historial académico: un renglón por módulo cursado, con nota, fecha y estado
-- (HU15 planilla, HU17 PDF). El PDF agrega: "No reemplaza al certificado analítico oficial".
CREATE VIEW v_historial_academico AS
SELECT u.id AS alumno_id, u.dni, u.apellido, u.nombre,
       i.id AS inscripcion_id, i.estado AS estado_inscripcion,
       pl.nombre AS plan, s.nombre AS sede, per.anio,
       m.id AS materia_id, m.nombre AS materia, m.ciclo,
       im.estado, im.nota_final, im.fecha_acreditacion,
       ap.porcentaje AS asistencia
FROM inscripcion_materias im
JOIN inscripciones i          ON i.id   = im.inscripcion_id
JOIN users u                  ON u.id   = i.alumno_id
JOIN materias m               ON m.id   = im.materia_id
JOIN cursos c                 ON c.id   = i.curso_id
JOIN planes_estudio pl        ON pl.id  = c.plan_id
JOIN sedes s                  ON s.id   = c.sede_id
JOIN periodos_lectivos per    ON per.id = c.periodo_id
LEFT JOIN v_asistencia_porcentaje ap ON ap.inscripcion_id = i.id AND ap.materia_id = m.id;

-- Resumen del historial por inscripción: acreditados, promedio y pendientes (HU17).
CREATE VIEW v_historial_resumen AS
SELECT i.id AS inscripcion_id, i.alumno_id, c.plan_id,
       (SELECT COUNT(*) FROM inscripcion_materias im
         WHERE im.inscripcion_id = i.id AND im.estado = 'acreditada')            AS modulos_acreditados,
       (SELECT ROUND(AVG(im.nota_final), 2) FROM inscripcion_materias im
         WHERE im.inscripcion_id = i.id AND im.estado = 'acreditada')            AS promedio,
       (SELECT COUNT(*) FROM materias m
         WHERE m.plan_id = c.plan_id AND m.es_obligatoria
           AND NOT EXISTS (SELECT 1 FROM inscripcion_materias im
                            WHERE im.inscripcion_id = i.id AND im.materia_id = m.id
                              AND im.estado = 'acreditada'))                     AS modulos_pendientes
FROM inscripciones i
JOIN cursos c ON c.id = i.curso_id;

-- Oferta pública: cursos activos de planes y sedes activos, con horario y cupo (HU02)
CREATE VIEW v_oferta_publica AS
SELECT c.id AS curso_id, p.nombre AS plan, p.tipo, p.edad_minima,
       s.id AS sede_id, s.nombre AS sede, s.localidad,
       c.turno, c.dias, c.hora_inicio, c.hora_fin, pl.anio, pl.cuatrimestre,
       v.disponibles, (v.disponibles <= 0) AS con_lista_espera
FROM cursos c
JOIN planes_estudio p      ON p.id  = c.plan_id    AND p.activo
JOIN sedes s               ON s.id  = c.sede_id    AND s.activo
JOIN periodos_lectivos pl  ON pl.id = c.periodo_id AND pl.estado = 'activo'
JOIN v_cupos_disponibles v ON v.curso_id = c.id
WHERE c.estado = 'activo';

-- Resumen por sede para los paneles del Administrador y del Auditor (HU18).
CREATE VIEW v_resumen_sedes AS
SELECT s.id AS sede_id, s.nombre AS sede, s.activo,
       (SELECT COUNT(*) FROM cursos c WHERE c.sede_id = s.id AND c.estado = 'activo')       AS cursos_activos,
       (SELECT COUNT(*) FROM inscripciones i JOIN cursos c ON c.id = i.curso_id
         WHERE c.sede_id = s.id AND i.estado = 'activa')                                    AS alumnos_activos,
       (SELECT COUNT(*) FROM preinscripciones p JOIN cursos c ON c.id = p.curso_id
         WHERE c.sede_id = s.id AND p.estado = 'pendiente')                                 AS preinscripciones_pendientes,
       (SELECT COUNT(*) FROM preinscripciones p JOIN cursos c ON c.id = p.curso_id
         WHERE c.sede_id = s.id AND p.estado = 'en_espera')                                 AS en_lista_espera
FROM sedes s;
