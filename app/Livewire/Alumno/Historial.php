<?php

namespace App\Livewire\Alumno;

use App\Models\Inscripcion;
use App\Queries\HistorialAcademico;
use Livewire\Component;

/**
 * HU17 (#127): «Historial académico» del alumno. Solo el propio: las inscripciones salen de
 * HistorialAcademico::inscripcionesDe() con el usuario que inició sesión, y cada historial de
 * HistorialAcademico::de() (#125). El PDF lo genera #126 (ruta alumno.historial.pdf).
 * Se lee con data_get() para aceptar filas como arreglo o como objeto.
 */
class Historial extends Component
{
    public function render()
    {
        $alumno = auth()->user();

        $historiales = collect(HistorialAcademico::inscripcionesDe($alumno))
            ->map(function ($fila) use ($alumno) {
                // Doble control: la inscripción tiene que ser del alumno que inició sesión
                $inscripcion = Inscripcion::where('alumno_id', $alumno->id)->find(data_get($fila, 'inscripcion_id'));

                return $inscripcion ? ['inscripcion_id' => $inscripcion->id] + HistorialAcademico::de($inscripcion) : null;
            })
            ->filter()
            ->values();

        return view('livewire.alumno.historial', [
            'historiales' => $historiales,
            'conPdf'      => \Illuminate\Support\Facades\Route::has('alumno.historial.pdf'),
        ]);
    }

    /** 8.5 → «8,5»; null → «—» */
    public static function numero(mixed $valor, int $decimales = 2): string
    {
        return $valor === null || $valor === '' ? '—' : rtrim(rtrim(number_format((float) $valor, $decimales, ',', '.'), '0'), ',');
    }
}
