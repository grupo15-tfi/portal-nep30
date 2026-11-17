<?php

namespace App\Queries;

use App\Models\Evaluacion;
use App\Models\InscripcionMateria;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * HU11 (#90): las notas de un alumno, una fila por materia que cursa.
 * Contrato (#84): de(User $alumno): Collection. Cada fila tiene:
 *   materia, curso («Plan · Sede»), evaluaciones (tipo, nota, fecha; ordenadas por fecha),
 *   nota_final (o null) y estado (cursando | acreditada | pendiente | libre).
 * Solo las inscripciones activas del alumno: nunca devuelve datos de otros alumnos.
 * La usa «Mis notas» (#91). Las filas son arreglos; la vista las lee con data_get().
 */
class NotasDelAlumno
{
    public static function de(User $alumno): Collection
    {
        return InscripcionMateria::query()
            ->whereHas('inscripcion', fn ($q) => $q->activas()->where('alumno_id', $alumno->id))
            ->with(['materia', 'inscripcion.curso.planEstudio', 'inscripcion.curso.sede', 'evaluaciones'])
            ->get()
            ->sortBy(fn (InscripcionMateria $im) => self::orden($im))
            ->map(fn (InscripcionMateria $im) => [
                'inscripcion_materia_id' => $im->id,
                'materia'                => $im->materia->nombre,
                'curso'                  => self::curso($im),
                'evaluaciones'           => $im->evaluaciones->map(fn (Evaluacion $e) => [
                    'tipo'  => $e->tipo,
                    'nota'  => $e->nota,
                    'fecha' => $e->fecha,
                ])->values(),
                'nota_final'             => $im->nota_final,
                'estado'                 => $im->estado,
            ])
            ->values();
    }

    /** «Peluquería · Sede Central» */
    public static function curso(InscripcionMateria $im): string
    {
        $curso = $im->inscripcion->curso;

        return "{$curso->planEstudio->nombre} · {$curso->sede->nombre}";
    }

    /** Por plan, después por ciclo (terminalidad) y por nombre de la materia */
    public static function orden(InscripcionMateria $im): string
    {
        return self::curso($im).'|'.str_pad((string) ($im->materia->ciclo ?? 0), 2, '0', STR_PAD_LEFT).'|'.$im->materia->nombre;
    }
}