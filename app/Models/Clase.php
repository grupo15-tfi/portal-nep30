<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * HU13 (#92): una clase de una asignación (profesor + materia + curso).
 * Estado: dictada | suspendida. Las suspendidas no cuentan para el porcentaje (v_asistencia_porcentaje).
 */
class Clase extends Model
{
    protected $table = 'clases';

    public const ESTADOS = [
        'dictada'    => 'Dictada',
        'suspendida' => 'Suspendida',
    ];

    protected $fillable = ['curso_profesor_id', 'fecha', 'hora_inicio', 'hora_fin', 'tema', 'estado'];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function cursoProfesor(): BelongsTo
    {
        return $this->belongsTo(CursoProfesor::class);
    }

    /** La asistencia de cada alumno en esta clase */
    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class);
    }
}