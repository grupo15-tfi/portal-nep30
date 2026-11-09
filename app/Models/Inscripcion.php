<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * HU09 (#86): inscripción de un alumno a un curso. Nace del alta de una preinscripción aprobada.
 * Estados: activa | baja | finalizada. El cupo y la auditoría los controla la base
 * (trg_insc_bi, trg_insc_bu, trg_insc_ai, trg_insc_au). Nunca se borra.
 * La usan HU11 (notas) y HU13 (asistencia).
 */
class Inscripcion extends Model
{
    protected $table = 'inscripciones';

    protected $fillable = [
        'alumno_id', 'curso_id', 'preinscripcion_id', 'fecha_inscripcion',
        'estado', 'fecha_baja', 'motivo_baja',
    ];

    protected $casts = [
        'fecha_inscripcion' => 'date',
        'fecha_baja'        => 'date',
    ];

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(User::class, 'alumno_id');
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function preinscripcion(): BelongsTo
    {
        return $this->belongsTo(Preinscripcion::class);
    }

    /** Materias o módulos que cursa (inscripcion_materias) */
    public function materias(): HasMany
    {
        return $this->hasMany(InscripcionMateria::class);
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('estado', 'activa');
    }
}