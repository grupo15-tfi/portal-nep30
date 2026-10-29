<?php

namespace App\Actions\Contenidos;

use App\Models\Evento;
use Illuminate\Validation\ValidationException;

/** HU10 (#74): borra un evento institucional. Los de un curso no se tocan desde acá. */
class EliminarEvento
{
    public static function ejecutar(Evento $evento): void
    {
        if ($evento->curso_id !== null) {
            throw ValidationException::withMessages(['evento' => 'Ese evento es de un curso: lo maneja su profesor.']);
        }

        $evento->delete();
    }
}