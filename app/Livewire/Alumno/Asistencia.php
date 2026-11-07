<?php

namespace App\Livewire\Alumno;

use App\Queries\AsistenciaDelAlumno;
use Livewire\Component;

/**
 * HU13 (#94): «Mi asistencia». Solo la propia: AsistenciaDelAlumno::de() con el usuario
 * que inició sesión (#93). Por materia: porcentaje, mínimo del plan y faltas con fecha.
 * Se lee con data_get() para aceptar filas como arreglo o como objeto.
 */
class Asistencia extends Component
{
    public function render()
    {
        return view('livewire.alumno.asistencia', [
            'materias' => AsistenciaDelAlumno::de(auth()->user()),
        ]);
    }
}
