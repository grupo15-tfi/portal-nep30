<?php

namespace App\Livewire\Alumno;

use App\Queries\NotasDelAlumno;
use Livewire\Component;

/**
 * HU11 (#91): «Mis notas» del alumno. Solo las propias: la consulta es NotasDelAlumno::de()
 * con el usuario que inició sesión (#90, contrato en #84). Por materia: evaluaciones, nota final y estado.
 * Se lee con data_get() para aceptar filas como arreglo o como objeto.
 */
class Notas extends Component
{
    public function render()
    {
        return view('livewire.alumno.notas', [
            'materias' => NotasDelAlumno::de(auth()->user()),
        ]);
    }
}
