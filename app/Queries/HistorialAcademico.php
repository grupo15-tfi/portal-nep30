<?php

namespace App\Queries;

use App\Models\Inscripcion;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * HU17 (#125): el historial académico de una inscripción, leído de las vistas
 * v_historial_academico (un renglón por materia) y v_historial_resumen (los totales).
 * No recalcula nada en PHP: el estado, la nota final, la asistencia, el promedio y los
 * pendientes salen de la base, igual que en el resto del campus.
 *
 * Contrato (#124):
 *   de(Inscripcion $inscripcion): array → alumno (dni, apellido, nombre), plan, sede, anio,
 *     estado_inscripcion, modulos (materia, ciclo, estado, nota_final, fecha_acreditacion, asistencia),
 *     resumen (modulos_acreditados, promedio, modulos_pendientes) y emitido_en.
 *   inscripcionesDe(User $alumno): Collection → inscripcion_id, plan, sede, anio, estado.
 * Lo usan el PDF (#126) y la pantalla «Historial académico» (#127).
 */
class HistorialAcademico
{
    public static function de(Inscripcion $inscripcion): array
    {
        $inscripcion->loadMissing(['alumno', 'curso.planEstudio', 'curso.sede', 'curso.periodoLectivo']);

        $modulos = DB::table('v_historial_academico')
            ->where('inscripcion_id', $inscripcion->id)
            ->orderByRaw('ciclo IS NULL, ciclo')->orderBy('materia')
            ->get()
            ->map(fn (object $m) => [
                'materia'            => $m->materia,
                'ciclo'              => $m->ciclo === null ? null : (int) $m->ciclo,
                'estado'             => $m->estado,
                'nota_final'         => $m->nota_final === null ? null : (float) $m->nota_final,
                'fecha_acreditacion' => $m->fecha_acreditacion,
                'asistencia'         => $m->asistencia === null ? null : (float) $m->asistencia,
            ])
            ->values()
            ->all();

        $resumen = DB::table('v_historial_resumen')->where('inscripcion_id', $inscripcion->id)->first();

        return [
            'alumno' => [
                'dni'      => $inscripcion->alumno->dni,
                'apellido' => $inscripcion->alumno->apellido,
                'nombre'   => $inscripcion->alumno->nombre,
            ],
            'plan'               => $inscripcion->curso->planEstudio->nombre,
            'sede'               => $inscripcion->curso->sede->nombre,
            'anio'               => (int) $inscripcion->curso->periodoLectivo->anio,
            'estado_inscripcion' => $inscripcion->estado,
            'modulos'            => $modulos,
            'resumen'            => [
                'modulos_acreditados' => (int) ($resumen->modulos_acreditados ?? 0),
                'promedio'            => $resumen?->promedio === null ? null : (float) $resumen->promedio,
                'modulos_pendientes'  => (int) ($resumen->modulos_pendientes ?? 0),
            ],
            'emitido_en'         => now(),
        ];
    }

    /** Todas las inscripciones del alumno (activas, finalizadas y de baja), la más reciente primero */
    public static function inscripcionesDe(User $alumno): Collection
    {
        return Inscripcion::where('alumno_id', $alumno->id)
            ->with(['curso.planEstudio', 'curso.sede', 'curso.periodoLectivo'])
            ->orderByDesc('fecha_inscripcion')->orderByDesc('id')
            ->get()
            ->map(fn (Inscripcion $i) => [
                'inscripcion_id' => $i->id,
                'plan'           => $i->curso->planEstudio->nombre,
                'sede'           => $i->curso->sede->nombre,
                'anio'           => (int) $i->curso->periodoLectivo->anio,
                'estado'         => $i->estado,
            ])
            ->values();
    }
}