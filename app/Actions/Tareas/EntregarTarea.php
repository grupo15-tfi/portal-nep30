<?php

namespace App\Actions\Tareas;

use App\Actions\Preinscripciones\ErroresDeLaBase;
use App\Models\Entrega;
use App\Models\Inscripcion;
use App\Models\Tarea;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
* HU12 (#118): el alumno entrega (o reemplaza) el archivo de una tarea.
* Contrato (#117): ejecutar(Tarea $tarea, Inscripcion $inscripcion, UploadedFile $archivo): Entrega
*Todos los errores van en la clave `archivo`. Si el archivo no es válido, no se guarda nada en el disco.
*La primera entrega fuera de término se acepta (la base marca fuera_de_termino);
*reemplazar después de la fecha límite, no.
*Archivo: disco local (privado), entregas/{tarea_id}/{inscripcion_id}.{ext}
*/

class EntregarTarea
{
    public static function ejecutar(Tarea $tarea, Inscripcion $inscripcion, UploadedFile $archivo): Entrega
    {
        // 1) El archivo, antes de tocar el disco
        Validator::make(['archivo' => $archivo], [
            'archivo' => ['required', 'file', 'extensions:'.implode(',', [...Entrega::TIPOS, 'jpeg']),
                'mimes:'.implode(',', Entrega::TIPOS), 'max:'.intdiv(Entrega::TAMANO_MAXIMO, 1024)],
        ], [
            'archivo.required'   => 'Elegí el archivo de tu entrega.',
            'archivo.extensions' => 'El archivo tiene que ser PDF, DOCX, JPG, PNG o ZIP.',
            'archivo.mimes'      => 'El archivo tiene que ser PDF, DOCX, JPG, PNG o ZIP.',
            'archivo.max'        => 'El archivo puede pesar hasta 10 MB.',
        ])->validate();

        // 2) El alumno: activo y en el curso de la tarea (la pantalla ya elige la inscripción del que inició sesión)

        if ($inscripcion->estado !== 'activa') {
            self::error('Tu inscripción no está activa, no podés entregar tareas.');
        }

        if ($inscripcion->curso_id !== $tarea->cursoProfesor->curso_id) {
            self::error('El alumno no pertenece al curso de esta tarea');
        }

        // 3) Reemplazar solo hasta la fecha límite (lo mismo controla trg_entrega_bu)

        $entrega = Entrega::where('tarea_id', $tarea->id)->where('inscripcion_id', $inscripcion->id)->first();
        if ($entrega && now()->greaterThan($tarea->fecha_entrega)) {
            self::error('Vencida la fecha límite, la entrega ya no se puede reemplazar');
        }

        // 4) Guardar el archivo y la fila

        $extension = GuardarTarea::extension($archivo);
        $ruta = $archivo->storeAs('entregas/'.$tarea->id, $inscripcion->id.'.'.$extension, 'local');
        $anterior = $entrega?->archivo_ruta;
        $datos = [
            'archivo_ruta'   => $ruta,
            'archivo_tamano' => $archivo->getSize(),
            'archivo_tipo'   => $extension,
            'fecha_entrega'  => now(),
        ];

        try {
            if ($entrega) {
                $entrega->update($datos);
            } else {
                $entrega = Entrega::create([...$datos, 'tarea_id' => $tarea->id, 'inscripcion_id' => $inscripcion->id]);
            }

        } catch (QueryException $e) {
            if ($ruta !== $anterior) {
                Storage::disk('local')->delete($ruta);
            }

            if (($e->errorInfo[1] ?? null) === 1062) {
                self::error('Ya entregaste esta tarea, volvé a intentar.');
            }

            throw ErroresDeLaBase::comoValidacion($e, 'archivo');
        }

        // Si cambió el tipo (por ejemplo de pdf a zip), el archivo viejo queda con otra ruta: se borra

        if ($anterior && $anterior !== $ruta) {
            Storage::disk('local')->delete($anterior);
        }

        // fuera_de_termino lo pone la base

        return $entrega->refresh();
    }

    private static function error(string $mensaje): never
    {
        throw ValidationException::withMessages(['archivo' => $mensaje]);
    }
}