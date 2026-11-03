<?php

namespace App\Actions\Asistencia;

use App\Models\Asistencia;
use App\Models\Clase;
use App\Models\Inscripcion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * HU13 (#92): registrar o corregir la asistencia de una clase.
 * Contrato (#85): ejecutar(Clase $clase, array $estados): void
 *   $estados = [inscripcion_id => 'presente' | 'ausente' | 'tarde' | 'justificado']
 *   Errores: ValidationException en `estados.{inscripcion_id}` y en `clase`.
 * Crea la asistencia que falta y corrige la que cambió. Las correcciones quedan en la auditoría
 * (trg_asist_au). Todo o nada: si un alumno da error, no se guarda ninguno.
 */
class TomarAsistencia
{
    public static function ejecutar(Clase $clase, array $estados): void
    {
        $clase->loadMissing('cursoProfesor');
        GuardarClase::controlarProfesor($clase->cursoProfesor);

        if ($clase->estado === 'suspendida') {
            throw ValidationException::withMessages(['clase' => 'La clase está suspendida: no se toma asistencia.']);
        }

        // Alumnos que hoy cursan en ese curso
        $delCurso = Inscripcion::activas()
            ->where('curso_id', $clase->cursoProfesor->curso_id)
            ->pluck('id')
            ->all();

        $errores = [];
        foreach ($estados as $inscripcionId => $estado) {
            if (! in_array((int) $inscripcionId, $delCurso, true)) {
                $errores["estados.{$inscripcionId}"] = 'Ese alumno no cursa en este curso.';
            } elseif (! array_key_exists($estado, Asistencia::ESTADOS)) {
                $errores["estados.{$inscripcionId}"] = 'El estado tiene que ser presente, ausente, tarde o justificado.';
            }
        }

        if ($errores) {
            throw ValidationException::withMessages($errores);
        }

        DB::transaction(function () use ($clase, $estados) {
            foreach ($estados as $inscripcionId => $estado) {
                $asistencia = Asistencia::firstOrNew(['clase_id' => $clase->id, 'inscripcion_id' => (int) $inscripcionId]);

                // Solo se guarda si es nueva o cambió: así la auditoría registra solo las correcciones reales
                if (! $asistencia->exists || $asistencia->estado !== $estado) {
                    $asistencia->estado = $estado;
                    $asistencia->save();
                }
            }
        });
    }
}