<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Curso extends Model
{
    protected $fillable = 
    [
        'plan_id', 
        'sede_id', 
        'periodo_id', 
        'turno', 
        'dias', 
        'hora_inicio', 
        'hora_fin', 
        'cupo', 
        'estado'
    ];

    public function sede() { return $this->belongsTo(Sede::class); }
    public function planEstudio() { return $this->belongsTo(PlanEstudio::class, 'plan_id'); }
    public function periodoLectivo() { return $this->belongsTo(PeriodoLectivo::class, 'periodo_id'); }
    /** Materias del plan de este curso (para el combo de asignaciones de HU08) */
    public function materiasDelPlan(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Materia::class, 'plan_id', 'plan_id')->orderBy('ciclo')->orderBy('nombre');
    }
    // Accesor para leer el cupo disponible desde la vista SQL sin recalcular en PHP
    public function getCupoDisponibleAttribute(): int
    {
        return (int) DB::table('v_cupos_disponibles')
            ->where('curso_id', $this->id)
            ->value('disponibles');
    }
}