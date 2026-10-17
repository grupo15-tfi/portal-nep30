-- =====================================================================
-- TFI Grupo 15 - 02_seguridad.sql - Usuarios de MySQL y privilegios (v4.4)
--
-- PRINCIPIO: cada programa entra a la base con un usuario que solo puede
-- hacer lo que necesita (privilegio mínimo). Nunca se usa root desde Laravel.
--
--   nep30_migraciones  crea y modifica tablas. Solo se usa al desplegar
--                      (php artisan migrate). Es el dueño (DEFINER) de los
--                      triggers y vistas: no borrar este usuario.
--   nep30_app          el que usa Laravel en el día a día (.env DB_USERNAME).
--                      Lee y escribe datos, pero no puede crear, borrar ni
--                      modificar tablas, y en la auditoría solo puede agregar.
--   nep30_backup       solo lectura, para la copia de seguridad diaria.
--   nep30_lectura      solo consulta las vistas (informes); opcional.
--
-- ANTES DE EJECUTAR: reemplazar cada CAMBIAR_... por una contraseña larga
-- (16 caracteres o más, generada al azar) y guardarlas fuera del repositorio.
-- Las contraseñas van en el archivo .env del servidor, que nunca se sube a GitHub.
--
-- HOSTING COMPARTIDO (cPanel): ahí los usuarios se crean desde "Bases de
-- datos MySQL" y solo se pueden elegir privilegios para toda la base. En ese
-- caso darle a nep30_app solo SELECT, INSERT, UPDATE, DELETE, EXECUTE y
-- CREATE TEMPORARY TABLES. La auditoría sigue protegida igual, porque los
-- triggers rechazan modificarla o borrarla con cualquier usuario.
-- =====================================================================

USE portal_nep30;

-- ---------------------------------------------------------------------
-- 1. Usuarios (solo conexiones desde el mismo servidor)
-- ---------------------------------------------------------------------
CREATE USER IF NOT EXISTS 'nep30_migraciones'@'localhost' IDENTIFIED BY 'CAMBIAR_clave_migraciones'
    WITH MAX_USER_CONNECTIONS 3;
CREATE USER IF NOT EXISTS 'nep30_app'@'localhost'         IDENTIFIED BY 'CAMBIAR_clave_app'
    WITH MAX_USER_CONNECTIONS 30;
CREATE USER IF NOT EXISTS 'nep30_backup'@'localhost'      IDENTIFIED BY 'CAMBIAR_clave_backup'
    WITH MAX_USER_CONNECTIONS 2;
CREATE USER IF NOT EXISTS 'nep30_lectura'@'localhost'     IDENTIFIED BY 'CAMBIAR_clave_lectura'
    WITH MAX_USER_CONNECTIONS 5;

-- ---------------------------------------------------------------------
-- 2. Migraciones: control total, pero solo sobre esta base
-- ---------------------------------------------------------------------
GRANT ALL PRIVILEGES ON portal_nep30.* TO 'nep30_migraciones'@'localhost';

-- ---------------------------------------------------------------------
-- 3. Aplicación: datos sí, estructura no. Permisos tabla por tabla.
-- ---------------------------------------------------------------------
GRANT SELECT, INSERT, UPDATE, DELETE ON portal_nep30.`asistencias` TO 'nep30_app'@'localhost';
GRANT SELECT, INSERT ON portal_nep30.`auditoria` TO 'nep30_app'@'localhost';   -- solo agregar (RNF14)
GRANT SELECT, INSERT, UPDATE, DELETE ON portal_nep30.`avisos` TO 'nep30_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON portal_nep30.`cache` TO 'nep30_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON portal_nep30.`cache_locks` TO 'nep30_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON portal_nep30.`clases` TO 'nep30_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON portal_nep30.`curso_profesores` TO 'nep30_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON portal_nep30.`cursos` TO 'nep30_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON portal_nep30.`entregas` TO 'nep30_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON portal_nep30.`evaluaciones` TO 'nep30_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON portal_nep30.`eventos` TO 'nep30_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON portal_nep30.`failed_jobs` TO 'nep30_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON portal_nep30.`inscripcion_materias` TO 'nep30_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON portal_nep30.`inscripciones` TO 'nep30_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON portal_nep30.`job_batches` TO 'nep30_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON portal_nep30.`jobs` TO 'nep30_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON portal_nep30.`materia_correlativas` TO 'nep30_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON portal_nep30.`materias` TO 'nep30_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON portal_nep30.`model_has_permissions` TO 'nep30_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON portal_nep30.`model_has_roles` TO 'nep30_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON portal_nep30.`noticias` TO 'nep30_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON portal_nep30.`password_reset_tokens` TO 'nep30_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON portal_nep30.`periodos_lectivos` TO 'nep30_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON portal_nep30.`permissions` TO 'nep30_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON portal_nep30.`planes_estudio` TO 'nep30_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON portal_nep30.`preinscripciones` TO 'nep30_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON portal_nep30.`role_has_permissions` TO 'nep30_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON portal_nep30.`roles` TO 'nep30_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON portal_nep30.`secciones` TO 'nep30_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON portal_nep30.`sedes` TO 'nep30_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON portal_nep30.`sessions` TO 'nep30_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON portal_nep30.`tareas` TO 'nep30_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON portal_nep30.`telefonos` TO 'nep30_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON portal_nep30.`users` TO 'nep30_app'@'localhost';
GRANT SELECT ON portal_nep30.`v_asistencia_porcentaje` TO 'nep30_app'@'localhost';
GRANT SELECT ON portal_nep30.`v_cupos_disponibles` TO 'nep30_app'@'localhost';
GRANT SELECT ON portal_nep30.`v_historial_academico` TO 'nep30_app'@'localhost';
GRANT SELECT ON portal_nep30.`v_historial_resumen` TO 'nep30_app'@'localhost';
GRANT SELECT ON portal_nep30.`v_lista_espera` TO 'nep30_app'@'localhost';
GRANT SELECT ON portal_nep30.`v_oferta_publica` TO 'nep30_app'@'localhost';
GRANT SELECT ON portal_nep30.`v_resumen_sedes` TO 'nep30_app'@'localhost';

-- ---------------------------------------------------------------------
-- 4. Copia de seguridad: leer todo, incluidas vistas y triggers
-- ---------------------------------------------------------------------
GRANT SELECT, SHOW VIEW, TRIGGER, LOCK TABLES, EVENT ON portal_nep30.* TO 'nep30_backup'@'localhost';

-- ---------------------------------------------------------------------
-- 5. Informes: solo las vistas (no ve contraseñas ni datos sueltos)
-- ---------------------------------------------------------------------
GRANT SELECT ON portal_nep30.`v_asistencia_porcentaje` TO 'nep30_lectura'@'localhost';
GRANT SELECT ON portal_nep30.`v_cupos_disponibles` TO 'nep30_lectura'@'localhost';
GRANT SELECT ON portal_nep30.`v_historial_resumen` TO 'nep30_lectura'@'localhost';
GRANT SELECT ON portal_nep30.`v_oferta_publica` TO 'nep30_lectura'@'localhost';
GRANT SELECT ON portal_nep30.`v_resumen_sedes` TO 'nep30_lectura'@'localhost';

FLUSH PRIVILEGES;

-- ---------------------------------------------------------------------
-- 6. Verificación: revisar que cada usuario tenga solo lo indicado
-- ---------------------------------------------------------------------
SHOW GRANTS FOR 'nep30_app'@'localhost';
SHOW GRANTS FOR 'nep30_backup'@'localhost';
SHOW GRANTS FOR 'nep30_lectura'@'localhost';

-- ---------------------------------------------------------------------
-- 7. Otras medidas de seguridad (configuración, no SQL)
-- ---------------------------------------------------------------------
--  * Laravel consulta siempre con parámetros (Eloquent / Query Builder):
--    nunca se arma SQL concatenando lo que escribe el usuario (inyección SQL).
--  * La base no acepta conexiones desde afuera del servidor (usuarios @'localhost'
--    y puerto 3306 cerrado en el firewall del hosting).
--  * phpMyAdmin solo para el responsable del despliegue, con usuario propio.
--  * Contraseñas de usuarios del sistema: bcrypt (CHECK chk_users_hash en users).
--  * Datos personales (Ley 25.326): los informes usan nep30_lectura, que solo ve
--    vistas con totales y porcentajes: no ve nombres, DNI, correos ni teléfonos.
--  * Rol Auditor: entra al sistema con su propio usuario (rol 'auditor' de Spatie) y
--    Laravel usa nep30_app; además del control de Laravel, los triggers rechazan
--    sus intentos de modificar datos (procedimiento exigir_escritura).
--  * Los triggers y procedimientos se ejecutan con los permisos de su DEFINER
--    (nep30_migraciones): nep30_app no necesita permiso EXECUTE.
--  * Copias de seguridad diarias con backup.sh, conservadas 30 días, con una
--    copia semanal fuera del servidor (RNF17).
