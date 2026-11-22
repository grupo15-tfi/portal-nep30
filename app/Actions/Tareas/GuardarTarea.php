<?php

namespace App\Actions\Tareas;

use App\Models\CursoProfesor;
use App\Models\Entrega;
use App\Models\Tarea;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * HU12 (#118): el profesor publica o edita una tarea de una asignación suya.
 * Contrato (#117): ejecutar(array $datos, ?Tarea $tarea = null): Tarea
 * $datos: curso_profesor_id, titulo, consigna?, fecha_entrega, archivo? (UploadedFile o null)
 * Errores: ValidationException con esas claves; `curso_profesor_id` si la asignación no es del profesor.
 * Archivo de la consigna: disco local (privado), tareas/{id}/consigna.{ext}
 */
class GuardarTarea
{
    public static function ejecutar(array $datos, ?Tarea $tarea = null): Tarea
    {
        // Al editar, la tarea tiene que ser de una asignación del profesor
        if ($tarea) {
            self::controlarProfesor($tarea->cursoProfesor);
        }

        $validos = Validator::make($datos, [
            'curso_profesor_id' => ['required', 'integer', 'exists:curso_profesores,id'],
            'titulo'            => ['required', 'string', 'max:150'],
            'consigna'          => ['nullable', 'string'],
            'fecha_entrega'     => ['required', 'date', ...($tarea ? [] : ['after:now'])],
            'archivo'           => ['nullable', 'file', 'extensions:'.implode(',', [...Entrega::TIPOS, 'jpeg']),
                                    'mimes:'.implode(',', Entrega::TIPOS), 'max:'.intdiv(Entrega::TAMANO_MAXIMO,
                                    1024)],
        ], [
            'curso_profesor_id.required' => 'Elegí la materia y el curso.',
            'curso_profesor_id.exists'   => 'Esa materia no existe.',
            'titulo.required'            => 'Escribí el título de la tarea.',
            'titulo.max'                 => 'El título puede tener hasta 150 caracteres.',
            'fecha_entrega.required'     => 'Elegí la fecha límite de entrega.',
            'fecha_entrega.date'         => 'La fecha límite no es válida.',
            'fecha_entrega.after'        => 'La fecha límite tiene que ser posterior a hoy.',
            'archivo.extensions'         => 'El archivo tiene que ser PDF, DOCX, JPG, PNG o ZIP.',
            'archivo.mimes'              => 'El archivo tiene que ser PDF, DOCX, JPG, PNG o ZIP.',
            'archivo.max'                => 'El archivo puede pesar hasta 10 MB.',
        ])->validate();

        self::controlarProfesor(CursoProfesor::find($validos['curso_profesor_id']));

        $archivo = $validos['archivo'] ?? null;
        unset($validos['archivo']);

        if ($tarea) {
            $tarea->update($validos);
        } else {
            $tarea = Tarea::create($validos);
        }

        if ($archivo instanceof UploadedFile) {
            $anterior = $tarea->archivo_ruta;
            $ruta = $archivo->storeAs('tareas/'.$tarea->id, 'consigna.'.self::extension($archivo), 'local');
            if ($anterior && $anterior !== $ruta) {
                Storage::disk('local')->delete($anterior);
            }

            $tarea->update(['archivo_ruta' => $ruta]);
        }

        return $tarea;
    }

    /** Solo el profesor de la asignación publica o edita sus tareas */
    public static function controlarProfesor(?CursoProfesor $cp): void
    {
        if (! $cp || $cp->profesor_id !== Auth::id()) {
            throw ValidationException::withMessages(['curso_profesor_id' => 'Esta materia no está asignada a vos en este curso.']);
        }
    }

    /** Extensión en minúsculas, con jpeg como jpg (igual que chk_entrega_tipo) */
    public static function extension(UploadedFile $archivo): string
    {
        $ext = strtolower($archivo->getClientOriginalExtension());
        return $ext === 'jpeg' ? 'jpg' : $ext;
    }
}
