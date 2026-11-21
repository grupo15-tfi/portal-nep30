<?php

namespace App\Livewire\Auditor;

use App\Queries\ResumenPorSede;
use Livewire\Component;

/**
 * HU18 (#130): «Resumen por sede» del Auditor. Solo lectura: no hay acciones ni enlaces de edición.
 * Los números salen de ResumenPorSede::todas() (#129, lee v_resumen_sedes); se leen con data_get()
 * para aceptar filas como arreglo o como objeto.
 */
class Sedes extends Component
{
    /** Columnas numéricas del contrato, en el orden en que se muestran */
    public const COLUMNAS = [
        'cursos_activos'              => 'Cursos activos',
        'alumnos_activos'             => 'Alumnos activos',
        'preinscripciones_pendientes' => 'Preinscripciones pendientes',
        'en_lista_espera'             => 'En lista de espera',
    ];

    public function render()
    {
        $sedes = collect(ResumenPorSede::todas())->values();

        $totales = collect(self::COLUMNAS)->map(fn ($texto, $clave) => $sedes->sum(fn ($s) => (int) data_get($s, $clave)));

        return view('livewire.auditor.sedes', [
            'sedes'   => $sedes,
            'totales' => $totales,
        ]);
    }
}
