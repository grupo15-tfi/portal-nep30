-- TFI Grupo 15 - Modelo v4.4 - Procedimientos y triggers.
-- Copiado de 01_esquema.sql (sección 9). Cada sentencia termina en una línea con //
-- y la migración las ejecuta de a una (no hace falta DELIMITER).
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
