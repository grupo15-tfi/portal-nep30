<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * HU09 (#86): cada materia o módulo que cursa un inscripto.
 * Estados: cursando | acreditada | pendiente | libre. Acreditar exige nota y asistencia
 * mínimas del plan (lo controla la base). Las notas (HU11) cuelgan de acá.
 */
class InscripcionMateria extends Model
{
    protected $table = 'inscripcion_materias';

    protected $fillable = ['inscripcion_id', 'materia_id', 'estado', 'nota_final', 'fecha_acreditacion'];

    protected $casts = [
        'nota_final'         => 'decimal:2',
        'fecha_acreditacion' => 'date',
    ];

    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class);
    }

    public function materia(): BelongsTo
    {
        return $this->belongsTo(Materia::class);
    }

    /** HU11 (#89): notas de esta materia, de la más vieja a la más nueva */
    public function evaluaciones(): HasMany
    {
        return $this->hasMany(Evaluacion::class)->orderBy('fecha')->orderBy('id');
    }
}