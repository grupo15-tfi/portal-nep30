<?php

namespace App\Queries;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * HU18 (#129): los números de cada sede para el Auditor, leídos de la vista v_resumen_sedes.
 * Contrato (#128): todas(): Collection. Cada fila tiene:
 *   sede_id, sede, activo, cursos_activos, alumnos_activos, preinscripciones_pendientes, en_lista_espera.
 * Incluye las sedes inactivas y se ordena por nombre. No calcula nada en PHP: los números son de la base.
 * La usa «Resumen por sede» del Auditor (#130). Las filas son arreglos; la vista las lee con data_get().
 */
class ResumenPorSede
{
    public static function todas(): Collection
    {
        return DB::table('v_resumen_sedes')
            ->orderBy('sede')->orderBy('sede_id')
            ->get()
            ->map(fn (object $s) => [
                'sede_id'                     => (int) $s->sede_id,
                'sede'                        => $s->sede,
                'activo'                      => (bool) $s->activo,
                'cursos_activos'              => (int) $s->cursos_activos,
                'alumnos_activos'             => (int) $s->alumnos_activos,
                'preinscripciones_pendientes' => (int) $s->preinscripciones_pendientes,
                'en_lista_espera'             => (int) $s->en_lista_espera,
            ])
            ->values();
    }
}