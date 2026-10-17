-- TFI Grupo 15 - Modelo v4.4 - Vistas.
-- Copiado de 01_esquema.sql (sección 10). Cada vista termina en ;
-- CREATE OR REPLACE: así "php artisan migrate:fresh" (que no borra vistas) las vuelve a crear.
-- =====================================================================
-- 10. VISTAS
-- =====================================================================

-- Cupo disponible = cupo - inscriptos activos (HU07, HU09)
CREATE OR REPLACE VIEW v_cupos_disponibles AS
SELECT c.id AS curso_id, c.cupo,
       COUNT(i.id)          AS inscriptos,
       c.cupo - COUNT(i.id) AS disponibles
FROM cursos c
LEFT JOIN inscripciones i ON i.curso_id = c.id AND i.estado = 'activa'
GROUP BY c.id, c.cupo;

-- Lista de espera de cada curso, por orden de llegada, con el teléfono para
-- avisar cuando se libera un lugar (RF09, HU09).
CREATE OR REPLACE VIEW v_lista_espera AS
SELECT p.curso_id,
       ROW_NUMBER() OVER (PARTITION BY p.curso_id ORDER BY p.created_at, p.id) AS posicion,
       p.id AS preinscripcion_id, p.apellido, p.nombre, p.dni, p.telefono, p.email,
       p.created_at AS anotado_en, p.avisado_en
FROM preinscripciones p
WHERE p.estado = 'en_espera';

-- Porcentaje de asistencia por alumno y materia (HU13):
-- (presentes + tarde + justificadas) / clases dictadas. Las suspendidas no cuentan.
CREATE OR REPLACE VIEW v_asistencia_porcentaje AS
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
CREATE OR REPLACE VIEW v_historial_academico AS
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
CREATE OR REPLACE VIEW v_historial_resumen AS
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
CREATE OR REPLACE VIEW v_oferta_publica AS
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
CREATE OR REPLACE VIEW v_resumen_sedes AS
SELECT s.id AS sede_id, s.nombre AS sede, s.activo,
       (SELECT COUNT(*) FROM cursos c WHERE c.sede_id = s.id AND c.estado = 'activo')       AS cursos_activos,
       (SELECT COUNT(*) FROM inscripciones i JOIN cursos c ON c.id = i.curso_id
         WHERE c.sede_id = s.id AND i.estado = 'activa')                                    AS alumnos_activos,
       (SELECT COUNT(*) FROM preinscripciones p JOIN cursos c ON c.id = p.curso_id
         WHERE c.sede_id = s.id AND p.estado = 'pendiente')                                 AS preinscripciones_pendientes,
       (SELECT COUNT(*) FROM preinscripciones p JOIN cursos c ON c.id = p.curso_id
         WHERE c.sede_id = s.id AND p.estado = 'en_espera')                                 AS en_lista_espera
FROM sedes s;
