<?php

namespace App\Actions\Asistencia;

use App\Models\Clase;
use App\Models\CursoProfesor;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * HU13 (#92): crear o editar una clase de una asignación.
 * Contrato (#85): ejecutar(CursoProfesor $cp, array $datos, ?Clase $clase = null): Clase
 *   $datos: fecha, hora_inicio?, hora_fin?, tema?, estado (dictada | suspendida)
 *   Errores: ValidationException con esas claves, y `clase` si la asignación no es del profesor.
 */
class GuardarClase
{
    public static function ejecutar(CursoProfesor $cp, array $datos, ?Clase $clase = null): Clase
    {
        self::controlarProfesor($cp);

        if ($clase && $clase->curso_profesor_id !== $cp->id) {
            throw ValidationException::withMessages(['clase' => 'Esa clase es de otra materia o curso.']);
        }

        $validos = Validator::make($datos, [
            'fecha'       => ['required', 'date', 'before_or_equal:today'],
            'hora_inicio' => ['nullable', 'date_format:H:i'],
            'hora_fin'    => ['nullable', 'date_format:H:i', 'after:hora_inicio'],
            'tema'        => ['nullable', 'string', 'max:200'],
            'estado'      => ['required', Rule::in(array_keys(Clase::ESTADOS))],
        ], [
            'fecha.required'          => 'Escribí la fecha de la clase.',
            'fecha.date'              => 'La fecha no es válida.',
            'fecha.before_or_equal'   => 'No se registra una clase con fecha futura.',
            'hora_inicio.date_format' => 'La hora tiene que ser como 14:00.',
            'hora_fin.date_format'    => 'La hora tiene que ser como 18:00.',
            'hora_fin.after'          => 'La hora de fin tiene que ser posterior a la de inicio.',
            'tema.max'                => 'El tema puede tener hasta 200 caracteres.',
            'estado.required'         => 'Elegí si la clase se dictó o se suspendió.',
            'estado.in'               => 'La clase está dictada o suspendida.',
        ])->validate();

        // uq_clase no frena dos clases el mismo día sin hora (en MySQL, NULL no se repite en un índice único)
        $repetida = Clase::where('curso_profesor_id', $cp->id)
            ->whereDate('fecha', $validos['fecha'])
            ->where(fn ($q) => isset($validos['hora_inicio'])
                ? $q->whereTime('hora_inicio', $validos['hora_inicio'])
                : $q->whereNull('hora_inicio'))
            ->when($clase, fn ($q) => $q->whereKeyNot($clase->id))
            ->exists();

        if ($repetida) {
            throw ValidationException::withMessages(['fecha' => 'Ya hay una clase de esta materia ese día a esa hora.']);
        }

        try {
            if ($clase) {
                $clase->update($validos);

                return $clase;
            }

            return Clase::create([...$validos, 'curso_profesor_id' => $cp->id]);
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                throw ValidationException::withMessages(['fecha' => 'Ya hay una clase de esta materia ese día a esa hora.']);
            }
            throw $e;
        }
    }

    /** Solo el profesor de la asignación registra sus clases */
    public static function controlarProfesor(CursoProfesor $cp): void
    {
        if ($cp->profesor_id !== Auth::id()) {
            throw ValidationException::withMessages(['clase' => 'Esta materia no está asignada a vos en este curso.']);
        }
    }
}