<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * HU04 (#51): solicitud de un aspirante a un curso. No es un usuario: el aspirante
 * recién tiene cuenta cuando el administrador aprueba la preinscripción (HU09).
 *
 * Estados: pendiente | en_espera | aprobada | rechazada.
 * El estado inicial lo decide la base (trigger trg_preinsc_bi): si el curso no tiene
 * cupo, la deja en_espera.
 */
class Preinscripcion extends Model
{
    protected $table = 'preinscripciones';

    // Solo los datos que carga el aspirante. Estado, revisión y avisos los maneja el sistema.
    protected $fillable = [
        'curso_id', 'dni', 'apellido', 'nombre', 'fecha_nacimiento', 'email', 'telefono',
        'domicilio', 'nivel_educativo', 'ultimo_ciclo_aprobado', 'acepta_privacidad',
    ];

    protected $casts = [
        'fecha_nacimiento'      => 'date',
        'acepta_privacidad'     => 'boolean',
        'ultimo_ciclo_aprobado' => 'integer',
    ];

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /** La inscripción que nació de esta preinscripción (HU09), si ya se dio de alta */
    public function inscripcion(): HasOne
    {
        return $this->hasOne(Inscripcion::class);
    }

    public function enEspera(): bool
    {
        return $this->estado === 'en_espera';
    }
}