<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * HU13 (#92): el estado de un alumno en una clase.
 * «Justificado» no cuenta como ausente (entrevista del 13/10). Las correcciones quedan
 * en la auditoría con el valor anterior y el nuevo (trigger trg_asist_au).
 */
class Asistencia extends Model
{
    protected $table = 'asistencias';

    public const ESTADOS = [
        'presente'    => 'Presente',
        'ausente'     => 'Ausente',
        'tarde'       => 'Tarde',
        'justificado' => 'Justificado',
    ];

    protected $fillable = ['clase_id', 'inscripcion_id', 'estado'];

    public function clase(): BelongsTo
    {
        return $this->belongsTo(Clase::class);
    }

    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class);
    }
}