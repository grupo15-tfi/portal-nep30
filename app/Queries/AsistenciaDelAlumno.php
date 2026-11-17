<?php

namespace App\Queries;

use App\Models\CursoProfesor;
use App\Models\Inscripcion;
use App\Models\InscripcionMateria;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * HU13 (#93): porcentaje de asistencia, leído de la vista v_asistencia_porcentaje (no se recalcula en PHP).
 * Contrato (#85):
 *   de(User $alumno): Collection → una fila por materia que cursa:
 *     materia, curso («Plan · Sede»), clases_dictadas, porcentaje (o null sin clases),
 *     asistencia_minima (la del plan) e inasistencias (fecha y estado: ausente | justificado).
 *   delCurso(CursoProfesor $cp): Collection → una fila por alumno del curso en esa materia:
 *     inscripcion_id, apellido, nombre, clases_dictadas, porcentaje (o null) y asistencia_minima.
 * Solo inscripciones activas. Cuentan las clases dictadas; las suspendidas no (lo resuelve la vista).
 * Las filas son arreglos; las pantallas (#94) las leen con data_get().
 */
class AsistenciaDelAlumno
{
    public static function de(User $alumno): Collection
    {
        $materias = InscripcionMateria::query()
            ->whereHas('inscripcion', fn ($q) => $q->activas()->where('alumno_id', $alumno->id))
            ->with(['materia', 'inscripcion.curso.planEstudio', 'inscripcion.curso.sede'])
            ->get()
            ->sortBy(fn (InscripcionMateria $im) => self::orden($im));

        if ($materias->isEmpty()) {
            return collect();
        }

        $inscripciones = $materias->pluck('inscripcion_id')->unique()->all();

        // Lo que ya calcula la base, una fila por inscripción y materia
        $porcentajes = DB::table('v_asistencia_porcentaje')
            ->whereIn('inscripcion_id', $inscripciones)
            ->get()
            ->keyBy(fn ($fila) => "{$fila->inscripcion_id}-{$fila->materia_id}");

        // Faltas (ausente o justificado) de clases dictadas, por fecha
        $faltas = DB::table('asistencias as a')
            ->join('clases as cl', 'cl.id', '=', 'a.clase_id')
            ->join('curso_profesores as cp', 'cp.id', '=', 'cl.curso_profesor_id')
            ->whereIn('a.inscripcion_id', $inscripciones)
            ->where('cl.estado', 'dictada')
            ->whereIn('a.estado', ['ausente', 'justificado'])
            ->orderBy('cl.fecha')
            ->orderBy('cl.id')
            ->get(['a.inscripcion_id', 'cp.materia_id', 'cl.fecha', 'a.estado'])
            ->groupBy(fn ($fila) => "{$fila->inscripcion_id}-{$fila->materia_id}");

        return $materias->map(function (InscripcionMateria $im) use ($porcentajes, $faltas) {
            $clave = "{$im->inscripcion_id}-{$im->materia_id}";
            $fila = $porcentajes->get($clave);

            return [
                'inscripcion_materia_id' => $im->id,
                'materia'                => $im->materia->nombre,
                'curso'                  => self::curso($im),
                'clases_dictadas'        => (int) ($fila->clases_dictadas ?? 0),
                'porcentaje'             => $fila ? (float) $fila->porcentaje : null,
                'asistencia_minima'      => (int) $im->inscripcion->curso->planEstudio->asistencia_minima,
                'inasistencias'          => collect($faltas->get($clave, []))
                    ->map(fn ($f) => ['fecha' => $f->fecha, 'estado' => $f->estado])
                    ->values(),
            ];
        })->values();
    }

    public static function delCurso(CursoProfesor $cp): Collection
    {
        $cp->loadMissing('curso.planEstudio');

        $inscripciones = Inscripcion::activas()
            ->where('curso_id', $cp->curso_id)
            ->with('alumno:id,apellido,nombre')
            ->get();

        $porcentajes = DB::table('v_asistencia_porcentaje')
            ->whereIn('inscripcion_id', $inscripciones->pluck('id'))
            ->where('materia_id', $cp->materia_id)
            ->get()
            ->keyBy('inscripcion_id');

        $minima = (int) $cp->curso->planEstudio->asistencia_minima;

        return $inscripciones->map(function (Inscripcion $i) use ($porcentajes, $minima) {
            $fila = $porcentajes->get($i->id);

            return [
                'inscripcion_id'    => $i->id,
                'apellido'          => $i->alumno->apellido,
                'nombre'            => $i->alumno->nombre,
                'clases_dictadas'   => (int) ($fila->clases_dictadas ?? 0),
                'porcentaje'        => $fila ? (float) $fila->porcentaje : null,
                'asistencia_minima' => $minima,
            ];
        })->values();
    }

    /** «Peluquería · Sede Central» (el mismo texto que NotasDelAlumno) */
    private static function curso(InscripcionMateria $im): string
    {
        $curso = $im->inscripcion->curso;

        return "{$curso->planEstudio->nombre} · {$curso->sede->nombre}";
    }

    /** Por plan, después por ciclo (terminalidad) y por nombre de la materia */
    private static function orden(InscripcionMateria $im): string
    {
        return self::curso($im).'|'.str_pad((string) ($im->materia->ciclo ?? 0), 2, '0', STR_PAD_LEFT).'|'.$im->materia->nombre;
    }
}