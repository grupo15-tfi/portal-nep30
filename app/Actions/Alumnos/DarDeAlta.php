<?php

namespace App\Actions\Alumnos;

use App\Models\Inscripcion;
use App\Models\Preinscripcion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * HU09 (#86): dar de alta una preinscripción aprobada.
 * Contrato (#83): ejecutar(Preinscripcion $p): Inscripcion · errores en `estado`, `cupo` y `alta`.
 *
 * En una transacción: crea el usuario Alumno (o reutiliza el del mismo DNI), lo inscribe en el
 * curso y en todas las materias del plan. El cupo lo controla la base (trg_insc_bi, con bloqueo).
 * Si el usuario es nuevo, le llega «Creá tu contraseña» (#15), igual que a los profesores.
 */
class DarDeAlta
{
    public static function ejecutar(Preinscripcion $preinscripcion): Inscripcion
    {
        if ($preinscripcion->estado !== 'aprobada') {
            throw ValidationException::withMessages(['estado' => 'Solo se da de alta una preinscripción aprobada.']);
        }

        if ($preinscripcion->inscripcion()->exists()) {
            throw ValidationException::withMessages(['estado' => 'Esta preinscripción ya fue dada de alta.']);
        }

        try {
            [$inscripcion, $alumno, $esNuevo] = DB::transaction(function () use ($preinscripcion) {
                [$alumno, $esNuevo] = self::alumno($preinscripcion);

                $inscripcion = Inscripcion::create([
                    'alumno_id'         => $alumno->id,
                    'curso_id'          => $preinscripcion->curso_id,
                    'preinscripcion_id' => $preinscripcion->id,
                    'fecha_inscripcion' => today(),
                    'estado'            => 'activa',
                ]);

                foreach ($preinscripcion->curso->materiasDelPlan as $materia) {
                    $inscripcion->materias()->create(['materia_id' => $materia->id]);
                }

                return [$inscripcion, $alumno, $esNuevo];
            });
        } catch (QueryException $e) {
            throw self::errorDeLaBase($e);
        }

        // Fuera de la transacción: si el correo falla, el alta ya está hecha.
        if ($esNuevo) {
            try {
                $alumno->enviarEnlaceCrearContrasena();
            } catch (Throwable $e) {
                Log::warning('No se pudo enviar el correo de alta al alumno', ['usuario_id' => $alumno->id, 'error' => $e::class]);
            }
        }

        return $inscripcion;
    }

    /** El alumno de ese DNI: lo reutiliza si ya existe como alumno, si no lo crea. */
    private static function alumno(Preinscripcion $p): array
    {
        $existente = User::withTrashed()->where('dni', $p->dni)->first();

        if ($existente) {
            if (! $existente->hasRole('alumno')) {
                throw ValidationException::withMessages([
                    'alta' => 'Ese DNI pertenece a un usuario del personal: no se puede inscribir como alumno.',
                ]);
            }
            if ($existente->trashed()) {
                $existente->restore();
            }
            if (! $existente->activo) {
                $existente->update(['activo' => true]);
            }

            return [$existente, false];
        }

        if (User::withTrashed()->where('email', $p->email)->exists()) {
            throw ValidationException::withMessages([
                'alta' => 'Ya hay un usuario con ese correo y otro DNI. Revisá los datos con el aspirante.',
            ]);
        }

        $nuevo = User::create([
            'dni'              => $p->dni,
            'apellido'         => $p->apellido,
            'nombre'           => $p->nombre,
            'email'            => $p->email,
            'fecha_nacimiento' => $p->fecha_nacimiento,
            'password'         => Str::password(32),   // nadie la conoce: crea la suya con el enlace
            'activo'           => true,
        ]);
        $nuevo->assignRole('alumno');

        return [$nuevo, true];
    }

    private static function errorDeLaBase(QueryException $e): ValidationException
    {
        $estado = $e->errorInfo[0] ?? null;
        $codigo = $e->errorInfo[1] ?? null;
        $texto = $e->errorInfo[2] ?? '';

        if ($codigo === 1062) {
            return ValidationException::withMessages(['alta' => 'Esta persona ya está inscripta en este curso.']);
        }

        if ($estado === '45000') {
            return ValidationException::withMessages([
                str_contains($texto, 'cupo') ? 'cupo' : 'alta' => $texto,
            ]);
        }

        throw $e;
    }
}