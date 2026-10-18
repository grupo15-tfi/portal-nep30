-- =====================================================================
-- TFI Grupo 15 - 04_pruebas.sql - PRUEBAS DE LA BASE DE DATOS (v4.4)
--
-- SOLO EN DESARROLLO: carga datos de ejemplo. Nunca ejecutar en producción.
--
-- CÓMO USARLO
--   1) Crear la base vacía con 01_esquema.sql y cargar 03_datos_base.sql.
--   2) Ejecutar este archivo completo (phpMyAdmin > Importar, o Workbench > rayo).
--   3) Al final aparece una tabla con cada prueba y su resultado:
--        OK    = la base se comportó como dice el requerimiento
--        FALLA = hay un error en el modelo; revisar la tabla indicada
--
-- QUÉ PRUEBA
--   A. Datos de ejemplo válidos (también ejercitan los triggers).
--   B. Pruebas que dependen del orden: cupo, lista de espera, baja,
--      acreditación con asistencia y rol Auditor.
--   C. Cálculos, vistas y auditoría automática.
--   D. Datos que la base DEBE rechazar. Si los acepta, la prueba da FALLA.
--
-- Cada prueba de rechazo se ejecuta dentro de una transacción que se deshace,
-- así la base queda limpia aunque la prueba falle. Las pruebas "rechazar_con"
-- además verifican que el motivo del rechazo sea el esperado.
-- =====================================================================

USE portal_nep30;

-- Workbench trae activado el "Safe Updates", que frena los UPDATE y DELETE sin
-- WHERE por clave antes de que lleguen a los triggers. Se apaga solo en esta sesión.
SET SQL_SAFE_UPDATES = 0;

-- Modo estricto, igual al que usa Laravel.
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

DROP TABLE IF EXISTS _resultados_test;
CREATE TABLE _resultados_test (
    n          INT AUTO_INCREMENT PRIMARY KEY,
    requisito  VARCHAR(20),
    prueba     VARCHAR(150),
    resultado  VARCHAR(6),
    detalle    VARCHAR(300)
);

DROP PROCEDURE IF EXISTS debe_rechazar;
DROP PROCEDURE IF EXISTS rechazar_con;
DROP PROCEDURE IF EXISTS debe_valer;

DELIMITER //

-- Ejecuta una sentencia que la base DEBE rechazar. Si p_motivo no es NULL,
-- el mensaje de error tiene que contener ese texto.
CREATE PROCEDURE rechazar_con(IN p_req VARCHAR(20), IN p_prueba VARCHAR(150), IN p_sql TEXT, IN p_motivo VARCHAR(150))
BEGIN
    DECLARE v_error BOOLEAN DEFAULT FALSE;
    DECLARE v_msg VARCHAR(300) DEFAULT '';
    DECLARE v_errno INT DEFAULT 0;
    DECLARE v_msg_aux VARCHAR(300);
    DECLARE v_errno_aux INT;
    BEGIN
        DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
        BEGIN
            -- GET DIAGNOSTICS tiene que ser lo primero: en MySQL 8 cualquier otra
            -- sentencia (incluso un IF) borra el error que se quiere leer.
            GET DIAGNOSTICS CONDITION 1 v_msg_aux = MESSAGE_TEXT, v_errno_aux = MYSQL_ERRNO;
            IF NOT v_error THEN   -- guardar solo el primer error
                SET v_msg = v_msg_aux, v_errno = v_errno_aux, v_error = TRUE;
            END IF;
        END;
        START TRANSACTION;
        SET @s = p_sql;
        PREPARE st FROM @s;
        EXECUTE st;
        DEALLOCATE PREPARE st;
        ROLLBACK;
    END;
    -- Si el error es "la tabla/columna no existe" (1146, 1054), no es un rechazo válido:
    -- significa que al modelo le falta esa tabla o columna.
    INSERT INTO _resultados_test (requisito, prueba, resultado, detalle)
    VALUES (p_req, p_prueba,
            IF(v_error AND v_errno NOT IN (1146, 1054)
               AND (p_motivo IS NULL OR INSTR(v_msg COLLATE utf8mb4_unicode_ci, p_motivo COLLATE utf8mb4_unicode_ci) > 0), 'OK', 'FALLA'),
            CASE WHEN NOT v_error THEN 'La base ACEPTÓ un dato inválido'
                 WHEN v_errno IN (1146, 1054) THEN CONCAT('Falta en el modelo: ', v_msg)
                 WHEN p_motivo IS NOT NULL AND INSTR(v_msg COLLATE utf8mb4_unicode_ci, p_motivo COLLATE utf8mb4_unicode_ci) = 0
                      THEN CONCAT('Rechazado por OTRO motivo: ', v_msg)
                 ELSE CONCAT('Rechazado: ', v_msg) END);
END //

CREATE PROCEDURE debe_rechazar(IN p_req VARCHAR(20), IN p_prueba VARCHAR(150), IN p_sql TEXT)
BEGIN
    CALL rechazar_con(p_req, p_prueba, p_sql, NULL);
END //

-- Ejecuta una consulta que devuelve un valor y lo compara con el esperado.
CREATE PROCEDURE debe_valer(IN p_req VARCHAR(20), IN p_prueba VARCHAR(150), IN p_sql TEXT, IN p_esperado VARCHAR(100))
BEGIN
    DECLARE v_msg VARCHAR(300) DEFAULT NULL;
    DECLARE v_msg_aux VARCHAR(300);
    BEGIN
        DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
        BEGIN
            GET DIAGNOSTICS CONDITION 1 v_msg_aux = MESSAGE_TEXT;
            IF v_msg IS NULL THEN SET v_msg = v_msg_aux; END IF;   -- guardar solo el primer error
        END;
        SET @v = NULL;
        -- Se convierte a texto adentro de la consulta: así MySQL 8 no redondea los decimales.
        SET @s = CONCAT('SELECT CAST((', p_sql, ') AS CHAR) INTO @v');
        PREPARE st FROM @s;
        EXECUTE st;
        DEALLOCATE PREPARE st;
    END;
    INSERT INTO _resultados_test (requisito, prueba, resultado, detalle)
    VALUES (p_req, p_prueba,
            -- CONVERT + COLLATE: compara igual aunque la conexión use otra intercalación
            -- (Workbench usa utf8mb4_0900_ai_ci; XAMPP, utf8mb4_general_ci).
            IF(v_msg IS NULL AND CONVERT(@v USING utf8mb4) COLLATE utf8mb4_unicode_ci = p_esperado, 'OK', 'FALLA'),
            IF(v_msg IS NOT NULL, CONCAT('Error: ', v_msg),
               CONCAT('Esperado: ', p_esperado, ' | Obtenido: ', IFNULL(CAST(@v AS CHAR), 'NULL'))));
END //

DELIMITER ;

-- =====================================================================
-- A. DATOS DE EJEMPLO (casos válidos)
-- =====================================================================

SET @app_usuario_id = NULL, @app_ip = '127.0.0.1';
SET @h = CONCAT('$2y$12$', REPEAT('x', 53));   -- simula un hash bcrypt de 60 caracteres

INSERT INTO sedes (id, nombre, tipo, direccion, localidad, latitud, longitud, observaciones) VALUES
 (1, 'Sede (prueba)',           'central', 'Av. Constituyentes', 'Formosa', -26.1849, -58.1731, 'Funciona en una escuela primaria'),
 (2, 'Anexo Norte (prueba)',    'anexo',   NULL,                 'Formosa', -26.1500, -58.1800, 'Mz. 25 C. 5'),
 (3, 'Anexo Cerrado (prueba)',  'anexo',   'Calle 2',            'Formosa', -26.2000, -58.2000, NULL);
UPDATE sedes SET activo = FALSE WHERE id = 3;

INSERT INTO users (id, dni, apellido, nombre, email, password, sede_id) VALUES
 (1, '30000001', 'Admin',   'Ana',    'admin@test.com',   @h, 1),
 (2, '30000002', 'Gómez',   'Pablo',  'pgomez@test.com',  @h, NULL),
 (3, '30000003', 'López',   'Marta',  'mlopez@test.com',  @h, NULL),
 (4, '20000004', 'Ramírez', 'Ramón',  'ramon@test.com',   @h, NULL),
 (5, '40000005', 'Sosa',    'Lucía',  'lucia@test.com',   @h, NULL),
 (6, '40000006', 'Benítez', 'Sofía',  'sofia@test.com',   @h, NULL),
 (7, '40000007', 'Pérez',   'Juan',   'juan@test.com',    @h, NULL),
 (8, '30000008', 'Vera',    'Mario',  'auditor@test.com', @h, NULL),
 (9, '45000009', 'Duarte',  'Tomás',  'tomas@test.com',   @h, NULL);

INSERT INTO model_has_roles (role_id, model_type, model_id)
SELECT r.id, 'App\\Models\\User', u.id FROM roles r JOIN users u
  ON (r.name = 'administrador' AND u.id = 1)
  OR (r.name = 'profesor'      AND u.id IN (2, 3))
  OR (r.name = 'alumno'        AND u.id IN (4, 5, 6, 7, 9))
  OR (r.name = 'auditor'       AND u.id = 8);

SET @app_usuario_id = 1;                   -- desde acá, cambios a nombre de la Administradora

INSERT INTO telefonos (usuario_id, numero, tipo, contacto_nombre, es_principal) VALUES
 (4, '3704000000', 'celular',    NULL,        TRUE),
 (4, '3704111111', 'emergencia', 'Hija Rosa', FALSE);

INSERT INTO periodos_lectivos (id, anio, cuatrimestre, fecha_inicio, fecha_fin) VALUES
 (1, 2027, NULL, '2027-03-01', '2027-12-15');

-- Planes de prueba (los del folleto ya están cargados por 03_datos_base.sql).
-- El plan 103 tiene reglas propias: edad mínima 16 y se acredita con 7.
INSERT INTO planes_estudio (id, nombre, tipo, cantidad_ciclos, edad_minima, nota_aprobacion) VALUES
 (101, 'Operador de Informática (prueba)', 'formacion_profesional', NULL, 18, 6),
 (102, 'Primaria para adultos (prueba)',   'terminalidad',          NULL, 18, 6),
 (103, 'Taller juvenil (prueba)',          'formacion_profesional', NULL, 16, 7);

INSERT INTO materias (id, plan_id, nombre, ciclo) VALUES
 (1, 101, 'Taller de informática', NULL),
 (2, 102, 'Lengua',     2),
 (3, 102, 'Matemática', 2),
 (4, 103, 'Módulo único', NULL);

INSERT INTO cursos (id, plan_id, sede_id, periodo_id, turno, dias, hora_inicio, hora_fin, cupo, estado) VALUES
 (1, 101, 1, 1, 'noche',  'Lunes a viernes', '18:30', '20:30', 2,  'activo'),   -- cupo chico para probar el límite
 (2, 102, 1, 1, 'tarde',  'Lunes a viernes', '13:30', '17:45', 20, 'activo'),
 (3, 101, 3, 1, 'mañana', NULL,              NULL,    NULL,    10, 'activo'),   -- en la sede inactiva
 (4, 101, 2, 1, 'noche',  NULL,              NULL,    NULL,    5,  'inactivo'),
 (5, 103, 2, 1, 'tarde',  'Lunes a jueves',  '14:00', '16:00', 10, 'activo');

INSERT INTO curso_profesores (id, curso_id, materia_id, profesor_id) VALUES
 (1, 1, 1, 2),
 (2, 1, 1, 3),                            -- dos profesores en el mismo taller
 (3, 2, 2, 2),                            -- Pablo: Lengua
 (4, 2, 3, 3),                            -- Marta: Matemática
 (5, 5, 4, 3);

-- Preinscripciones del público: sin usuario logueado.
SET @app_usuario_id = NULL;
INSERT INTO preinscripciones (id, curso_id, dni, apellido, nombre, fecha_nacimiento, email, telefono, nivel_educativo, ultimo_ciclo_aprobado, acepta_privacidad) VALUES
 (1, 2, '20000004', 'Ramírez', 'Ramón', '1980-05-01', 'ramon@test.com', '3704000000', 'primaria_incompleta', 1, TRUE),
 (2, 2, '77777782', 'Acosta',  'Ariel', '1995-01-01', 'ariel@test.com', '3704000002', NULL, NULL, TRUE);   -- queda pendiente
SET @app_usuario_id = 1;
UPDATE preinscripciones SET estado = 'aprobada', revisado_por = 1, revisado_en = NOW() WHERE id = 1;

INSERT INTO inscripciones (id, alumno_id, curso_id, preinscripcion_id, fecha_inscripcion) VALUES
 (1, 4, 2, 1,    '2027-03-01'),
 (2, 5, 1, NULL, '2027-03-01'),
 (3, 6, 1, NULL, '2027-03-01'),           -- con esta, el curso 1 queda completo (cupo 2)
 (5, 9, 5, NULL, '2027-03-01');

INSERT INTO inscripcion_materias (id, inscripcion_id, materia_id, estado, nota_final) VALUES
 (1, 1, 2, 'cursando',   NULL),           -- Ramón cursa Lengua (la adeuda)
 (2, 2, 1, 'cursando',   NULL),
 (3, 1, 3, 'acreditada', 8.00),           -- y ya acreditó Matemática (la fecha la pone la base)
 (5, 5, 4, 'cursando',   NULL);

SET @app_usuario_id = 2;                   -- el profesor Pablo: parcial desaprobado y recuperatorio
INSERT INTO evaluaciones (id, inscripcion_materia_id, tipo, nota, fecha, cargada_por) VALUES
 (1, 1, 'parcial',       4.00, '2027-05-10', 2),
 (3, 1, 'recuperatorio', 7.50, '2027-05-20', 2);
SET @app_usuario_id = 1;                   -- la Administradora también puede cargar
INSERT INTO evaluaciones (id, inscripcion_materia_id, tipo, nota, fecha, cargada_por) VALUES
 (2, 2, 'trabajo_practico', 9.00, '2027-05-12', 1);

-- Asistencia de Ramón en Lengua: presente, tarde, justificado, ausente y una suspendida.
INSERT INTO clases (id, curso_profesor_id, fecha) VALUES
 (1, 3, '2027-03-02'), (2, 3, '2027-03-03'), (3, 3, '2027-03-04'), (4, 3, '2027-03-05'), (5, 3, '2027-03-08');
UPDATE clases SET estado = 'suspendida' WHERE id = 5;
INSERT INTO asistencias (clase_id, inscripcion_id, estado) VALUES
 (1, 1, 'presente'), (2, 1, 'tarde'), (3, 1, 'justificado'), (4, 1, 'ausente'), (5, 1, 'ausente');

INSERT INTO tareas (id, curso_profesor_id, titulo, fecha_entrega) VALUES
 (1, 1, 'Práctica de Word', '2027-04-10 23:59:00');
INSERT INTO entregas (id, tarea_id, inscripcion_id, archivo_ruta, archivo_tamano, archivo_tipo, fecha_entrega, fuera_de_termino) VALUES
 (1, 1, 2, 'entregas/1/lucia.docx', 250000, 'docx', '2027-04-11 10:00:00', FALSE),  -- tarde: el trigger lo marca
 (2, 1, 3, 'entregas/1/sofia.pdf',  120000, 'pdf',  '2027-04-09 18:00:00', FALSE);
-- Sofía reemplaza su entrega antes del vencimiento: permitido.
UPDATE entregas SET archivo_ruta = 'entregas/1/sofia_v2.pdf', fecha_entrega = '2027-04-10 12:00:00' WHERE id = 2;

INSERT INTO eventos (titulo, tipo, fecha_inicio, publico, creado_por) VALUES
 ('Inicio de clases', 'institucional', '2027-03-01 08:00:00', TRUE,  1),
 ('Reunión docente',  'institucional', '2027-03-05 18:00:00', FALSE, 1);
INSERT INTO eventos (curso_id, tarea_id, titulo, tipo, fecha_inicio, creado_por) VALUES
 (1, 1, 'Entrega: Práctica de Word', 'entrega', '2027-04-10 23:59:00', 2);

INSERT INTO noticias (titulo, slug, cuerpo, estado, publicada_en, autor_id) VALUES
 ('Abren inscripciones', 'abren-inscripciones', 'Texto', 'publicada', NOW(), 1),
 ('Borrador interno',    'borrador-interno',    'Texto', 'borrador',  NULL,  1);

-- =====================================================================
-- B. PRUEBAS QUE DEPENDEN DEL ORDEN
-- =====================================================================

-- ---------- Cupo y lista de espera (HU04, HU09, RF09) ----------
CALL debe_valer('HU07', 'Curso 1 completo: 0 lugares disponibles (cupo 2, 2 inscriptos)',
     'SELECT disponibles FROM v_cupos_disponibles WHERE curso_id = 1', '0');
CALL rechazar_con('HU09', 'Inscribir con el curso completo (sin cupo)',
     'INSERT INTO inscripciones (alumno_id, curso_id, fecha_inscripcion) VALUES (7, 1, ''2027-03-02'')', 'Sin cupo');

-- Juan y Diego se preinscriben al curso 1, que está completo: quedan en lista de espera.
SET @app_usuario_id = NULL;
INSERT INTO preinscripciones (id, curso_id, dni, apellido, nombre, fecha_nacimiento, email, telefono, acepta_privacidad, created_at) VALUES
 (3, 1, '40000007', 'Pérez', 'Juan',  '1990-02-02', 'juan@test.com',  '3704000003', TRUE, '2027-03-02 10:00:00'),
 (4, 1, '77777791', 'Ruiz',  'Diego', '1988-03-03', 'diego@test.com', '3704000004', TRUE, '2027-03-02 11:00:00');
SET @app_usuario_id = 1;
CALL debe_valer('RF09', 'Sin cupo, la preinscripción queda en lista de espera automáticamente',
     'SELECT GROUP_CONCAT(estado ORDER BY id) FROM preinscripciones WHERE id IN (3, 4)', 'en_espera,en_espera');
CALL debe_valer('RF09', 'Lista de espera por orden de llegada: Diego es el 2.º',
     'SELECT posicion FROM v_lista_espera WHERE preinscripcion_id = 4', '2');
CALL debe_valer('HU09', 'La lista de espera muestra el teléfono para avisar',
     'SELECT telefono FROM v_lista_espera WHERE posicion = 1 AND curso_id = 1', '3704000003');
CALL debe_valer('HU02', 'La oferta pública avisa que el curso 1 tiene lista de espera',
     'SELECT con_lista_espera FROM v_oferta_publica WHERE curso_id = 1', '1');
CALL rechazar_con('HU09', 'Dar de alta a alguien de la lista de espera sin aprobarlo',
     'INSERT INTO inscripciones (alumno_id, curso_id, preinscripcion_id, fecha_inscripcion) VALUES (7, 1, 3, ''2027-03-02'')',
     'preinscripción aprobada');

-- Baja de Sofía (RF18): libera el lugar. La Administradora llama a Juan y lo da de alta.
UPDATE inscripciones SET estado = 'baja', fecha_baja = '2027-04-20', motivo_baja = 'Se mudó' WHERE id = 3;
CALL debe_valer('HU09', 'La baja de un alumno libera el cupo',
     'SELECT disponibles FROM v_cupos_disponibles WHERE curso_id = 1', '1');
UPDATE preinscripciones SET avisado_en = '2027-04-20 09:00:00', avisado_por = 1 WHERE id = 3;
UPDATE preinscripciones SET estado = 'pendiente' WHERE id = 3;
UPDATE preinscripciones SET estado = 'aprobada', revisado_por = 1, revisado_en = NOW() WHERE id = 3;
INSERT INTO inscripciones (id, alumno_id, curso_id, preinscripcion_id, fecha_inscripcion) VALUES (4, 7, 1, 3, '2027-04-21');
CALL debe_valer('RF09', 'Después del alta de Juan, Diego pasa a ser el 1.º de la lista',
     'SELECT posicion FROM v_lista_espera WHERE preinscripcion_id = 4', '1');
CALL rechazar_con('HU09', 'Reincorporar a Sofía cuando el lugar ya se ocupó',
     'UPDATE inscripciones SET estado = ''activa'', fecha_baja = NULL WHERE id = 3', 'Sin cupo');

-- ---------- Acreditación: nota 6 y 80 % de asistencia (HU11, HU13, HU17) ----------
CALL debe_valer('HU13', 'Asistencia de Ramón: 3 de 4 clases (la justificada cuenta, la suspendida no)',
     'SELECT porcentaje FROM v_asistencia_porcentaje WHERE inscripcion_id = 1 AND materia_id = 2', '75.0');
CALL rechazar_con('HU17', 'Acreditar Lengua con 75 % de asistencia (mínimo 80 %)',
     'UPDATE inscripcion_materias SET estado = ''acreditada'', nota_final = 8 WHERE id = 1', '80 % de asistencia');
INSERT INTO clases (id, curso_profesor_id, fecha) VALUES (6, 3, '2027-03-09');
INSERT INTO asistencias (clase_id, inscripcion_id, estado) VALUES (6, 1, 'presente');
CALL debe_valer('HU13', 'Con una clase más llega al 80 % y cumple el mínimo',
     'SELECT CONCAT(porcentaje, '' '', cumple_minimo) FROM v_asistencia_porcentaje WHERE inscripcion_id = 1 AND materia_id = 2', '80.0 1');
CALL rechazar_con('HU11', 'Acreditar Lengua con nota final 5,50 (se acredita con 6)',
     'UPDATE inscripcion_materias SET estado = ''acreditada'', nota_final = 5.50 WHERE id = 1', 'nota final de 6');
UPDATE inscripcion_materias SET estado = 'acreditada', nota_final = 7.50 WHERE id = 1;

-- El profesor Pablo corrige la nota del parcial 4 -> 4,50 (queda auditado a su nombre).
SET @app_usuario_id = 2;
UPDATE evaluaciones SET nota = 4.50 WHERE id = 1;
SET @app_usuario_id = 1;
-- Cambio de contraseña de Ramón.
UPDATE users SET password = CONCAT('$2y$12$', REPEAT('z', 53)) WHERE id = 4;

-- ---------- Rol Auditor: ve todo, no modifica nada (HU18, RF35, RF36) ----------
SET @app_usuario_id = 8;
CALL debe_valer('HU18', 'El Auditor consulta el resumen de todas las sedes',
     'SELECT COUNT(*) FROM v_resumen_sedes', '3');
CALL rechazar_con('HU18', 'Auditor: modificar una nota',
     'UPDATE evaluaciones SET nota = 10 WHERE id = 3', 'solo lectura');
CALL rechazar_con('HU18', 'Auditor: cargar una nota',
     'INSERT INTO evaluaciones (inscripcion_materia_id, tipo, nota, fecha, cargada_por) VALUES (2, ''final'', 10, ''2027-07-01'', 1)', 'solo lectura');
CALL rechazar_con('HU18', 'Auditor: borrar una nota',
     'DELETE FROM evaluaciones WHERE id = 2', 'solo lectura');
CALL rechazar_con('HU18', 'Auditor: modificar la asistencia',
     'UPDATE asistencias SET estado = ''presente'' WHERE clase_id = 4 AND inscripcion_id = 1', 'solo lectura');
CALL rechazar_con('HU18', 'Auditor: registrar asistencia',
     'INSERT INTO asistencias (clase_id, inscripcion_id, estado) VALUES (1, 1, ''presente'')', 'solo lectura');
CALL rechazar_con('HU18', 'Auditor: aprobar una preinscripción',
     'UPDATE preinscripciones SET estado = ''aprobada'', revisado_por = 8, revisado_en = NOW() WHERE id = 2', 'solo lectura');
CALL rechazar_con('HU18', 'Auditor: dar de baja a un alumno',
     'UPDATE inscripciones SET estado = ''baja'', fecha_baja = ''2027-05-01'' WHERE id = 2', 'solo lectura');
CALL rechazar_con('HU18', 'Auditor: acreditar un módulo',
     'UPDATE inscripcion_materias SET estado = ''acreditada'', nota_final = 9 WHERE id = 2', 'solo lectura');
CALL rechazar_con('HU18', 'Auditor: darse el rol de Administrador',
     'INSERT INTO model_has_roles (role_id, model_type, model_id) SELECT id, ''App\\\\Models\\\\User'', 8 FROM roles WHERE name = ''administrador''', 'solo lectura');
CALL rechazar_con('HU18', 'Auditor: desactivar a otro usuario',
     'UPDATE users SET activo = FALSE WHERE id = 4', 'solo lectura');
CALL rechazar_con('HU18', 'Auditor: crear un usuario',
     CONCAT('INSERT INTO users (dni, apellido, nombre, email, password) VALUES (''11111199'', ''X'', ''Y'', ''x99@t.com'', ''', @h, ''')'), 'solo lectura');
-- Lo que el Auditor SÍ puede hacer: cambiar su propia contraseña y registrar una descarga.
UPDATE users SET password = CONCAT('$2y$12$', REPEAT('a', 53)) WHERE id = 8;
INSERT INTO auditoria (usuario_id, accion, tabla, ip) VALUES (8, 'descarga', 'v_historial_academico', '127.0.0.1');
SET @app_usuario_id = 1;

-- =====================================================================
-- C. PRUEBAS DE CÁLCULO Y DE REGLAS (deben dar el valor esperado)
-- =====================================================================

CALL debe_valer('HU02', 'La oferta pública no muestra cursos de sedes inactivas',
     'SELECT COUNT(*) FROM v_oferta_publica WHERE curso_id = 3', '0');
CALL debe_valer('HU02', 'La oferta pública no muestra cursos inactivos',
     'SELECT COUNT(*) FROM v_oferta_publica WHERE curso_id = 4', '0');
CALL debe_valer('HU02', 'La oferta pública muestra días y horario del curso',
     'SELECT CONCAT(dias, '' '', TIME_FORMAT(hora_inicio, ''%H:%i''), ''-'', TIME_FORMAT(hora_fin, ''%H:%i'')) FROM v_oferta_publica WHERE curso_id = 1',
     'Lunes a viernes 18:30-20:30');
CALL debe_valer('HU13', 'Asistencia: las justificadas se cuentan aparte de las ausencias',
     'SELECT CONCAT(justificadas, ''/'', ausentes) FROM v_asistencia_porcentaje WHERE inscripcion_id = 1 AND materia_id = 2', '1/1');
CALL debe_valer('HU08', 'Dos profesores asignados al mismo taller',
     'SELECT COUNT(*) FROM curso_profesores WHERE curso_id = 1 AND materia_id = 1', '2');
CALL debe_valer('RF07', 'Calendario público: solo eventos públicos',
     'SELECT COUNT(*) FROM eventos WHERE publico = TRUE', '1');
CALL debe_valer('HU10', 'Sitio público: solo noticias publicadas',
     'SELECT COUNT(*) FROM noticias WHERE estado = ''publicada''', '1');
CALL debe_valer('HU12', 'Entrega tardía: la base la marca "fuera de término" aunque se envió en FALSE',
     'SELECT fuera_de_termino FROM entregas WHERE id = 1', '1');
CALL debe_valer('HU12', 'Entrega reemplazada antes del vencimiento: sigue en término',
     'SELECT CONCAT(archivo_ruta, '' '', fuera_de_termino) FROM entregas WHERE id = 2', 'entregas/1/sofia_v2.pdf 0');
CALL debe_valer('HU11', 'Recuperatorio aceptado después de un parcial desaprobado',
     'SELECT COUNT(*) FROM evaluaciones WHERE inscripcion_materia_id = 1 AND tipo = ''recuperatorio''', '1');

-- Historial académico (HU17, RF33, RF34)
CALL debe_valer('HU17', 'Al acreditar, la base guarda la fecha de acreditación',
     'SELECT fecha_acreditacion = CURDATE() FROM inscripcion_materias WHERE id = 3', '1');
CALL debe_valer('HU17', 'Historial de Ramón: 2 módulos con nota, fecha y estado',
     'SELECT COUNT(*) FROM v_historial_academico WHERE alumno_id = 4 AND estado = ''acreditada'' AND nota_final IS NOT NULL AND fecha_acreditacion IS NOT NULL', '2');
CALL debe_valer('HU17', 'Historial: muestra la asistencia de cada módulo',
     'SELECT asistencia FROM v_historial_academico WHERE alumno_id = 4 AND materia = ''Lengua''', '80.0');
CALL debe_valer('HU17', 'Resumen de Ramón: 2 módulos acreditados',
     'SELECT modulos_acreditados FROM v_historial_resumen WHERE inscripcion_id = 1', '2');
CALL debe_valer('HU17', 'Resumen de Ramón: promedio (7,50 y 8) = 7,75',
     'SELECT promedio FROM v_historial_resumen WHERE inscripcion_id = 1', '7.75');
CALL debe_valer('HU17', 'Resumen de Lucía: 1 módulo pendiente',
     'SELECT modulos_pendientes FROM v_historial_resumen WHERE inscripcion_id = 2', '1');

-- Resumen por sede (HU18)
CALL debe_valer('HU18', 'Resumen de la Sede: alumnos activos, pendientes y en espera',
     'SELECT CONCAT(alumnos_activos, ''/'', preinscripciones_pendientes, ''/'', en_lista_espera) FROM v_resumen_sedes WHERE sede_id = 1', '3/1/1');

-- Roles y permisos (HU05, HU18)
CALL debe_valer('HU05', 'Hay 4 roles: administrador, profesor, alumno y auditor',
     'SELECT GROUP_CONCAT(name ORDER BY id) FROM roles', 'administrador,profesor,alumno,auditor');
CALL debe_valer('HU05', 'El Administrador tiene 14 permisos',
     'SELECT COUNT(*) FROM role_has_permissions rp JOIN roles r ON r.id = rp.role_id WHERE r.name = ''administrador''', '14');
CALL debe_valer('HU18', 'El Auditor no tiene ningún permiso de gestión, carga o publicación',
     'SELECT COUNT(*) FROM role_has_permissions rp JOIN roles r ON r.id = rp.role_id JOIN permissions p ON p.id = rp.permission_id WHERE r.name = ''auditor'' AND (p.name LIKE ''gestionar%'' OR p.name LIKE ''cargar%'' OR p.name LIKE ''registrar%'' OR p.name LIKE ''publicar%'')', '0');
CALL debe_valer('RF15', 'Catálogo del folleto: 9 cursos de FP y 3 modalidades de primaria',
     'SELECT CONCAT(SUM(tipo = ''formacion_profesional''), ''+'', SUM(tipo = ''terminalidad'')) FROM planes_estudio WHERE id < 100', '9+3');

-- Auditoría automática (HU16)
CALL debe_valer('HU11', 'Corrección de nota auditada a nombre del profesor',
     'SELECT usuario_id FROM auditoria WHERE tabla = ''evaluaciones'' AND accion = ''modificacion'' AND registro_id = 1', '2');
CALL debe_valer('HU11', 'Auditoría guarda el valor anterior de la nota (4,00)',
     'SELECT JSON_EXTRACT(valores_anteriores, ''$.nota'') FROM auditoria WHERE tabla = ''evaluaciones'' AND accion = ''modificacion'' AND registro_id = 1', '4.00');
CALL debe_valer('HU16', 'El alta de cada usuario queda auditada (9 usuarios)',
     'SELECT COUNT(*) FROM auditoria WHERE tabla = ''users'' AND accion = ''alta''', '9');
CALL debe_valer('HU16', 'La asignación de roles queda auditada (9 roles asignados)',
     'SELECT COUNT(*) FROM auditoria WHERE tabla = ''model_has_roles'' AND accion = ''alta''', '9');
CALL debe_valer('HU16', 'Cambio de contraseña auditado sin guardar el hash',
     'SELECT COUNT(*) FROM auditoria WHERE tabla = ''users'' AND registro_id = 4 AND accion = ''modificacion'' AND INSTR(valores_nuevos, ''$2y$'') = 0', '1');
CALL debe_valer('HU16', 'La baja del alumno queda auditada como "baja"',
     'SELECT COUNT(*) FROM auditoria WHERE tabla = ''inscripciones'' AND registro_id = 3 AND accion = ''baja''', '1');
CALL debe_valer('HU16', 'El alta de cada preinscripción queda auditada (4)',
     'SELECT COUNT(*) FROM auditoria WHERE tabla = ''preinscripciones'' AND accion = ''alta''', '4');
CALL debe_valer('RF09', 'El aviso telefónico de la lista de espera queda auditado',
     'SELECT COUNT(*) FROM auditoria WHERE tabla = ''preinscripciones'' AND registro_id = 3 AND JSON_EXTRACT(valores_nuevos, ''$.avisado_en'') IS NOT NULL AND JSON_TYPE(JSON_EXTRACT(valores_anteriores, ''$.avisado_en'')) = ''NULL''', '1');
CALL debe_valer('HU09', 'La aprobación de la preinscripción queda auditada',
     'SELECT JSON_UNQUOTE(JSON_EXTRACT(valores_nuevos, ''$.estado'')) FROM auditoria WHERE tabla = ''preinscripciones'' AND registro_id = 1 AND accion = ''modificacion''', 'aprobada');
CALL debe_valer('HU17', 'La acreditación del módulo queda auditada',
     'SELECT COUNT(*) FROM auditoria WHERE tabla = ''inscripcion_materias'' AND registro_id = 1 AND JSON_UNQUOTE(JSON_EXTRACT(valores_nuevos, ''$.estado'')) = ''acreditada''', '1');
CALL debe_valer('RF36', 'La descarga del Auditor queda registrada',
     'SELECT COUNT(*) FROM auditoria WHERE usuario_id = 8 AND accion = ''descarga''', '1');
CALL debe_valer('HU18', 'El Auditor pudo cambiar su propia contraseña',
     'SELECT COUNT(*) FROM auditoria WHERE tabla = ''users'' AND registro_id = 8 AND usuario_id = 8 AND accion = ''modificacion''', '1');

-- =====================================================================
-- D. PRUEBAS DE RECHAZO (la base DEBE dar error)
-- =====================================================================

-- Usuarios
CALL debe_rechazar('RF10', 'Usuario sin contraseña',
     'INSERT INTO users (dni, apellido, nombre, email) VALUES (''11111111'', ''X'', ''Y'', ''x@t.com'')');
CALL debe_rechazar('RNF08', 'Contraseña guardada en texto plano',
     'INSERT INTO users (dni, apellido, nombre, email, password) VALUES (''11111112'', ''X'', ''Y'', ''x2@t.com'', ''123456'')');
CALL debe_rechazar('HU08', 'DNI repetido',
     CONCAT('INSERT INTO users (dni, apellido, nombre, email, password) VALUES (''30000002'', ''X'', ''Y'', ''otro@t.com'', ''', @h, ''')'));
CALL debe_rechazar('HU08', 'Correo repetido',
     CONCAT('INSERT INTO users (dni, apellido, nombre, email, password) VALUES (''11111113'', ''X'', ''Y'', ''pgomez@test.com'', ''', @h, ''')'));
CALL debe_rechazar('HU04', 'DNI con letras',
     CONCAT('INSERT INTO users (dni, apellido, nombre, email, password) VALUES (''12A45678'', ''X'', ''Y'', ''z1@t.com'', ''', @h, ''')'));
CALL debe_rechazar('HU04', 'Correo sin @',
     CONCAT('INSERT INTO users (dni, apellido, nombre, email, password) VALUES (''11111114'', ''X'', ''Y'', ''sinarroba.com'', ''', @h, ''')'));
CALL debe_rechazar('-', 'Tipo de teléfono inválido',
     'INSERT INTO telefonos (usuario_id, numero, tipo) VALUES (4, ''3704222222'', ''fax'')');
CALL debe_rechazar('-', 'Teléfono de emergencia sin nombre de contacto',
     'INSERT INTO telefonos (usuario_id, numero, tipo) VALUES (4, ''3704222222'', ''emergencia'')');

-- Sedes y oferta
CALL debe_rechazar('RF04', 'Sede sin coordenadas para el mapa',
     'INSERT INTO sedes (nombre, tipo, direccion, localidad) VALUES (''Nueva'', ''anexo'', ''x'', ''y'')');
CALL debe_rechazar('HU07', 'Nombre de sede repetido',
     'INSERT INTO sedes (nombre, tipo, direccion, localidad, latitud, longitud) VALUES (''Sede (prueba)'', ''sede'', ''x'', ''y'', 0, 0)');
CALL debe_rechazar('RF04', 'Latitud imposible (95)',
     'INSERT INTO sedes (nombre, tipo, direccion, localidad, latitud, longitud) VALUES (''Z'', ''sede'', ''x'', ''y'', 95, 0)');
CALL debe_rechazar('HU07', 'Curso con cupo 0',
     'INSERT INTO cursos (plan_id, sede_id, periodo_id, turno, cupo) VALUES (101, 2, 1, ''mañana'', 0)');
CALL debe_rechazar('HU07', 'Turno inválido',
     'INSERT INTO cursos (plan_id, sede_id, periodo_id, turno, cupo) VALUES (101, 2, 1, ''madrugada'', 10)');
CALL debe_rechazar('HU07', 'Curso que termina antes de empezar (21:00 a 18:30)',
     'INSERT INTO cursos (plan_id, sede_id, periodo_id, turno, hora_inicio, hora_fin, cupo) VALUES (101, 2, 1, ''tarde'', ''21:00'', ''18:30'', 10)');
CALL debe_rechazar('-', 'Período con fecha de fin anterior al inicio',
     'INSERT INTO periodos_lectivos (anio, cuatrimestre, fecha_inicio, fecha_fin) VALUES (2028, 1, ''2028-07-01'', ''2028-03-01'')');
CALL debe_rechazar('RF15', 'Plan con tipo inválido (''oficio'' ya no existe)',
     'INSERT INTO planes_estudio (nombre, tipo) VALUES (''Plan X'', ''oficio'')');
CALL debe_rechazar('RF15', 'Plan con nota de aprobación 11',
     'INSERT INTO planes_estudio (nombre, tipo, nota_aprobacion) VALUES (''Plan Y'', ''formacion_profesional'', 11)');
CALL debe_rechazar('RF15', 'Plan con asistencia mínima de 120 %',
     'INSERT INTO planes_estudio (nombre, tipo, asistencia_minima) VALUES (''Plan Z'', ''formacion_profesional'', 120)');
CALL debe_rechazar('-', 'Una materia correlativa de sí misma',
     'INSERT INTO materia_correlativas (materia_id, requiere_materia_id) VALUES (2, 2)');
CALL debe_rechazar('HU08', 'Mismo profesor asignado dos veces a la misma materia y curso',
     'INSERT INTO curso_profesores (curso_id, materia_id, profesor_id) VALUES (1, 1, 2)');
CALL rechazar_con('HU08', 'Asignar una materia que no es del plan del curso',
     'INSERT INTO curso_profesores (curso_id, materia_id, profesor_id) VALUES (1, 2, 3)', 'no pertenece al plan');
CALL rechazar_con('HU08', 'Asignar como profesor a un usuario sin ese rol',
     'INSERT INTO curso_profesores (curso_id, materia_id, profesor_id) VALUES (2, 3, 4)', 'rol profesor');
CALL rechazar_con('HU08', 'Asignar como profesor al Auditor',
     'INSERT INTO curso_profesores (curso_id, materia_id, profesor_id) VALUES (2, 3, 8)', 'rol profesor');

-- Preinscripción y lista de espera
CALL debe_rechazar('RNF15', 'Preinscripción sin aceptar la política de privacidad',
     'INSERT INTO preinscripciones (curso_id, dni, apellido, nombre, fecha_nacimiento, email, telefono, acepta_privacidad) VALUES (2, ''77777777'', ''X'', ''Y'', ''1990-01-01'', ''x@t.com'', ''3704999999'', FALSE)');
CALL debe_rechazar('HU04', 'Preinscripción sin correo',
     'INSERT INTO preinscripciones (curso_id, dni, apellido, nombre, fecha_nacimiento, telefono, acepta_privacidad) VALUES (2, ''77777778'', ''X'', ''Y'', ''1990-01-01'', ''3704999999'', TRUE)');
CALL debe_rechazar('RF09', 'Preinscripción sin teléfono (hace falta para avisar)',
     'INSERT INTO preinscripciones (curso_id, dni, apellido, nombre, fecha_nacimiento, email, acepta_privacidad) VALUES (2, ''77777779'', ''X'', ''Y'', ''1990-01-01'', ''x@t.com'', TRUE)');
CALL debe_rechazar('HU04', 'Preinscripción con teléfono inválido',
     'INSERT INTO preinscripciones (curso_id, dni, apellido, nombre, fecha_nacimiento, email, telefono, acepta_privacidad) VALUES (2, ''77777779'', ''X'', ''Y'', ''1990-01-01'', ''x@t.com'', ''llamar a la tarde'', TRUE)');
CALL debe_rechazar('HU04', 'Preinscripción sin fecha de nacimiento',
     'INSERT INTO preinscripciones (curso_id, dni, apellido, nombre, email, telefono, acepta_privacidad) VALUES (2, ''77777779'', ''X'', ''Y'', ''x@t.com'', ''3704999999'', TRUE)');
CALL rechazar_con('HU04', 'Preinscripción de un menor de 18 años',
     'INSERT INTO preinscripciones (curso_id, dni, apellido, nombre, fecha_nacimiento, email, telefono, acepta_privacidad) VALUES (2, ''55555555'', ''X'', ''Y'', DATE_SUB(CURDATE(), INTERVAL 17 YEAR), ''x@t.com'', ''3704999999'', TRUE)',
     'edad mínima del curso (18 años)');
CALL rechazar_con('RF15', 'La edad mínima es de cada plan: 15 años no alcanza en el plan de 16',
     'INSERT INTO preinscripciones (curso_id, dni, apellido, nombre, fecha_nacimiento, email, telefono, acepta_privacidad) VALUES (5, ''55555556'', ''X'', ''Y'', DATE_SUB(CURDATE(), INTERVAL 15 YEAR), ''x@t.com'', ''3704999999'', TRUE)',
     '(16 años)');
CALL debe_rechazar('HU04', 'Preinscripción con DNI inválido',
     'INSERT INTO preinscripciones (curso_id, dni, apellido, nombre, fecha_nacimiento, email, telefono, acepta_privacidad) VALUES (2, ''123'', ''X'', ''Y'', ''1990-01-01'', ''x@t.com'', ''3704999999'', TRUE)');
CALL debe_rechazar('HU04', 'Segunda preinscripción pendiente del mismo DNI al mismo curso',
     'INSERT INTO preinscripciones (curso_id, dni, apellido, nombre, fecha_nacimiento, email, telefono, acepta_privacidad) VALUES (2, ''77777782'', ''A'', ''A'', ''1990-01-01'', ''a@t.com'', ''3704999999'', TRUE)');
CALL debe_rechazar('RF09', 'Anotarse dos veces en la lista de espera del mismo curso',
     'INSERT INTO preinscripciones (curso_id, dni, apellido, nombre, fecha_nacimiento, email, telefono, acepta_privacidad) VALUES (1, ''77777791'', ''Ruiz'', ''Diego'', ''1988-03-03'', ''d@t.com'', ''3704999999'', TRUE)');
CALL rechazar_con('HU04', 'Una preinscripción nueva no puede enviarse ya aprobada',
     'INSERT INTO preinscripciones (curso_id, dni, apellido, nombre, fecha_nacimiento, email, telefono, estado, revisado_por, revisado_en, acepta_privacidad) VALUES (2, ''77777783'', ''X'', ''Y'', ''1990-01-01'', ''x@t.com'', ''3704999999'', ''aprobada'', 1, NOW(), TRUE)',
     'como pendiente');
CALL rechazar_con('HU02', 'Preinscripción a un curso de una sede inactiva',
     'INSERT INTO preinscripciones (curso_id, dni, apellido, nombre, fecha_nacimiento, email, telefono, acepta_privacidad) VALUES (3, ''77777780'', ''X'', ''Y'', ''1990-01-01'', ''x@t.com'', ''3704999999'', TRUE)',
     'no está disponible');
CALL rechazar_con('HU02', 'Preinscripción a un curso inactivo',
     'INSERT INTO preinscripciones (curso_id, dni, apellido, nombre, fecha_nacimiento, email, telefono, acepta_privacidad) VALUES (4, ''77777781'', ''X'', ''Y'', ''1990-01-01'', ''x@t.com'', ''3704999999'', TRUE)',
     'no está disponible');
CALL debe_rechazar('Integridad', 'Estado escrito distinto (''espera'' en vez de ''en_espera'')',
     'UPDATE preinscripciones SET estado = ''espera'' WHERE id = 2');
CALL debe_rechazar('HU09', 'Rechazar sin indicar el motivo',
     'UPDATE preinscripciones SET estado = ''rechazada'', revisado_por = 1, revisado_en = NOW() WHERE id = 2');
CALL debe_rechazar('HU09', 'Aprobar sin registrar quién la revisó',
     'UPDATE preinscripciones SET estado = ''aprobada'' WHERE id = 2');
CALL rechazar_con('HU09', 'Volver a "pendiente" una preinscripción ya aprobada',
     'UPDATE preinscripciones SET estado = ''pendiente'' WHERE id = 1', 'ya revisada');
CALL rechazar_con('RF09', 'Mandar a la lista de espera una preinscripción ya aprobada',
     'UPDATE preinscripciones SET estado = ''en_espera'' WHERE id = 1', 'ya revisada');
CALL debe_rechazar('RF09', 'Registrar un aviso telefónico sin quién lo hizo',
     'UPDATE preinscripciones SET avisado_en = NOW() WHERE id = 4');
CALL rechazar_con('HU09', 'Cambiar el curso de una preinscripción',
     'UPDATE preinscripciones SET curso_id = 5 WHERE id = 2', 'crear otra');

-- Inscripción
CALL debe_rechazar('HU09', 'Inscribir dos veces al mismo alumno en el mismo curso',
     'INSERT INTO inscripciones (alumno_id, curso_id, fecha_inscripcion) VALUES (4, 2, ''2027-03-02'')');
CALL debe_rechazar('HU09', 'Inscribir a un usuario que no existe',
     'INSERT INTO inscripciones (alumno_id, curso_id, fecha_inscripcion) VALUES (999, 2, ''2027-03-02'')');
CALL rechazar_con('HU09', 'Inscribir como alumno a un profesor',
     'INSERT INTO inscripciones (alumno_id, curso_id, fecha_inscripcion) VALUES (2, 2, ''2027-03-02'')', 'rol alumno');
CALL rechazar_con('HU09', 'Dar de alta una preinscripción que sigue pendiente',
     'INSERT INTO inscripciones (alumno_id, curso_id, preinscripcion_id, fecha_inscripcion) VALUES (6, 2, 2, ''2027-03-02'')', 'preinscripción aprobada');
CALL rechazar_con('HU09', 'Inscribir en un curso inactivo',
     'INSERT INTO inscripciones (alumno_id, curso_id, fecha_inscripcion) VALUES (7, 4, ''2027-03-02'')', 'no está activo');
CALL debe_rechazar('RF18', 'Dar de baja sin fecha de baja',
     'UPDATE inscripciones SET estado = ''baja'' WHERE id = 1');
CALL rechazar_con('RF18', 'Cambiar el curso de una inscripción existente',
     'UPDATE inscripciones SET curso_id = 1 WHERE id = 1', 'dar de baja y crear otra');
CALL rechazar_con('HU11', 'Inscribir en una materia de otro plan',
     'INSERT INTO inscripcion_materias (inscripcion_id, materia_id) VALUES (2, 3)', 'no pertenece al plan');

-- Notas y acreditación
CALL debe_rechazar('HU11', 'Nota 11 (fuera de rango)',
     'INSERT INTO evaluaciones (inscripcion_materia_id, tipo, nota, fecha, cargada_por) VALUES (1, ''final'', 11, ''2027-06-01'', 2)');
CALL debe_rechazar('HU11', 'Nota 0 (fuera de rango)',
     'INSERT INTO evaluaciones (inscripcion_materia_id, tipo, nota, fecha, cargada_por) VALUES (1, ''final'', 0, ''2027-06-01'', 2)');
CALL rechazar_con('HU11', 'Un profesor carga nota en una materia que no tiene asignada',
     'INSERT INTO evaluaciones (inscripcion_materia_id, tipo, nota, fecha, cargada_por) VALUES (1, ''final'', 8, ''2027-06-01'', 3)', 'no tiene asignada');
CALL rechazar_con('HU11', 'Recuperatorio sin parcial desaprobado',
     'INSERT INTO evaluaciones (inscripcion_materia_id, tipo, nota, fecha, cargada_por) VALUES (2, ''recuperatorio'', 8, ''2027-06-01'', 2)', 'parcial desaprobado');
CALL rechazar_con('HU11', 'Recuperatorio con fecha anterior al parcial desaprobado',
     'INSERT INTO evaluaciones (inscripcion_materia_id, tipo, nota, fecha, cargada_por) VALUES (1, ''recuperatorio'', 8, ''2027-05-01'', 2)', 'parcial desaprobado');
CALL debe_rechazar('HU11', 'Nota final de módulo fuera de rango',
     'UPDATE inscripcion_materias SET nota_final = 12 WHERE id = 2');
CALL debe_rechazar('HU17', 'Módulo acreditado sin nota final',
     'UPDATE inscripcion_materias SET estado = ''acreditada'' WHERE id = 2');
CALL debe_rechazar('HU17', 'Estado de módulo viejo (''aprobada'') ya no existe',
     'UPDATE inscripcion_materias SET estado = ''aprobada'', nota_final = 8 WHERE id = 2');
CALL debe_rechazar('HU17', 'Fecha de acreditación en un módulo que se está cursando',
     'INSERT INTO inscripcion_materias (inscripcion_id, materia_id, estado, fecha_acreditacion) VALUES (4, 1, ''cursando'', ''2027-05-01'')');
CALL rechazar_con('RF15', 'La nota de aprobación es de cada plan: 6,50 no alcanza en el plan de 7',
     'UPDATE inscripcion_materias SET estado = ''acreditada'', nota_final = 6.50 WHERE id = 5', 'nota final de 7');

-- Asistencia y tareas
CALL debe_rechazar('HU13', 'Estado de asistencia inválido',
     'UPDATE asistencias SET estado = ''medio_dia'' WHERE clase_id = 1');
CALL debe_rechazar('HU13', 'Dos registros de asistencia del mismo alumno en la misma clase',
     'INSERT INTO asistencias (clase_id, inscripcion_id, estado) VALUES (1, 1, ''ausente'')');
CALL rechazar_con('HU13', 'Asistencia de un alumno de otro curso',
     'INSERT INTO asistencias (clase_id, inscripcion_id, estado) VALUES (1, 2, ''presente'')', 'no pertenece al curso');
CALL debe_rechazar('HU12', 'Entrega de más de 10 MB',
     'INSERT INTO entregas (tarea_id, inscripcion_id, archivo_ruta, archivo_tamano, archivo_tipo, fecha_entrega) VALUES (1, 4, ''x.zip'', 20000000, ''zip'', NOW())');
CALL debe_rechazar('RNF16', 'Entrega con tipo de archivo no permitido (.exe)',
     'INSERT INTO entregas (tarea_id, inscripcion_id, archivo_ruta, archivo_tamano, archivo_tipo, fecha_entrega) VALUES (1, 4, ''x.exe'', 1000, ''exe'', NOW())');
CALL rechazar_con('HU12', 'Entrega de un alumno que no es del curso de la tarea',
     'INSERT INTO entregas (tarea_id, inscripcion_id, archivo_ruta, archivo_tamano, archivo_tipo, fecha_entrega) VALUES (1, 1, ''r.pdf'', 1000, ''pdf'', ''2027-04-01'')', 'no pertenece al curso');
CALL debe_rechazar('HU12', 'Segunda entrega del mismo alumno para la misma tarea',
     'INSERT INTO entregas (tarea_id, inscripcion_id, archivo_ruta, archivo_tamano, archivo_tipo, fecha_entrega) VALUES (1, 2, ''y.docx'', 1000, ''docx'', ''2027-04-01'')');
CALL rechazar_con('HU12', 'Reemplazar la entrega después del vencimiento',
     'UPDATE entregas SET archivo_ruta = ''entregas/1/lucia_v2.docx'', fecha_entrega = ''2027-04-12 09:00:00'' WHERE id = 1', 'Vencida');

-- Contenidos y calendario
CALL debe_rechazar('HU10', 'Noticia con estado inválido',
     'UPDATE noticias SET estado = ''oculta'' WHERE slug = ''borrador-interno''');
CALL debe_rechazar('HU10', 'Noticia publicada sin fecha de publicación',
     'UPDATE noticias SET estado = ''publicada'' WHERE slug = ''borrador-interno''');
CALL debe_rechazar('HU10', 'Noticia con dirección (slug) inválida',
     'INSERT INTO noticias (titulo, slug, cuerpo, autor_id) VALUES (''T'', ''Con Espacios!'', ''x'', 1)');
CALL debe_rechazar('RF07', 'Evento de un curso marcado como público',
     'INSERT INTO eventos (curso_id, titulo, tipo, fecha_inicio, publico, creado_por) VALUES (1, ''Parcial'', ''evaluacion'', ''2027-05-01'', TRUE, 2)');
CALL debe_rechazar('RF07', 'Evento que termina antes de empezar',
     'INSERT INTO eventos (titulo, tipo, fecha_inicio, fecha_fin, creado_por) VALUES (''X'', ''otro'', ''2027-05-02'', ''2027-05-01'', 1)');

-- Auditoría
CALL rechazar_con('RNF14', 'Modificar un registro de auditoría',
     'UPDATE auditoria SET tabla = ''otra'' WHERE id > 0', 'no puede modificarse');
CALL rechazar_con('RNF14', 'Borrar un registro de auditoría',
     'DELETE FROM auditoria WHERE id > 0', 'no puede eliminarse');
CALL debe_rechazar('HU16', 'Acción de auditoría inexistente (''anulacion'' ya no se usa)',
     'INSERT INTO auditoria (accion, tabla) VALUES (''anulacion'', ''x'')');

-- Lo que se quitó en la v4.4 ya no existe
CALL debe_valer('v4.4', 'Ya no hay tablas de certificados ni de comentarios',
     'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN (''certificados'', ''comentarios'')', '0');

-- =====================================================================
-- E. RESULTADO
-- =====================================================================

SELECT n, requisito, prueba, resultado, detalle FROM _resultados_test ORDER BY resultado DESC, n;

SELECT COUNT(*) AS total_pruebas,
       SUM(resultado = 'OK')    AS ok,
       SUM(resultado = 'FALLA') AS fallas
FROM _resultados_test;
