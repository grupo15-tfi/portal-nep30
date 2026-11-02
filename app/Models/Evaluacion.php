<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * HU11 (#89): una nota de un alumno en una materia (parcial, recuperatorio, trabajo práctico o final).
 * Notas de 1 a 10 con hasta dos decimales. La base controla que el profesor tenga la materia
 * asignada y que el recuperatorio corresponda (trg_eval_bi), y audita cada cambio (trg_eval_au).
 */
class Evaluacion extends Model
{
    protected $table = 'evaluaciones';

    public const TIPOS = [
        'parcial'          => 'Parcial',
        'recuperatorio'    => 'Recuperatorio',
        'trabajo_practico' => 'Trabajo práctico',
        'final'            => 'Final',
    ];

    protected $fillable = ['inscripcion_materia_id', 'tipo', 'nota', 'fecha', 'cargada_por'];

    protected $casts = [
        'nota'  => 'decimal:2',
        'fecha' => 'date',
    ];

    public function inscripcionMateria(): BelongsTo
    {
        return $this->belongsTo(InscripcionMateria::class);
    }

    public function cargadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cargada_por');
    }
}