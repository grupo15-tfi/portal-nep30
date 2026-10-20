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

    // Accesor para leer el cupo disponible desde la vista SQL sin recalcular en PHP
    public function getCupoDisponibleAttribute()
    {
        return DB::table('v_cupos_disponibles')
            ->where('curso_id', $this->id)
            ->value('disponibles');
    }
}