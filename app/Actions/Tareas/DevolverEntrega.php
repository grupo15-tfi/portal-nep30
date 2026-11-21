<?php

namespace App\Actions\Tareas;

use App\Models\Entrega;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
* HU12 (#118): el profesor escribe la devolución de una entrega.
* Contrato (#117): ejecutar(Entrega $entrega, string $devolucion): Entrega
*Errores en la clave `devolucion`: texto vacío, o la tarea no es de una asignación del profesor.
*Pone devuelta_en = ahora (chk_entrega_dev pide las dos cosas juntas).
*/

class DevolverEntrega
{
    public static function ejecutar(Entrega $entrega, string $devolucion): Entrega
    {
        if ($entrega->tarea->cursoProfesor->profesor_id !== Auth::id()) {
            throw ValidationException::withMessages(['devolucion' => 'Esta tarea no es de una materia tuya.']);
        }

        $texto = trim($devolucion);

        if ($texto === '') {
            throw ValidationException::withMessages(['devolucion' => 'Escribí la devolución.']);
        }

        $entrega->update(['devolucion' => $texto, 'devuelta_en' => now()]);

        return $entrega;
    }
}