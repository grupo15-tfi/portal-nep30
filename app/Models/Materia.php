<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['plan_id', 'nombre', 'ciclo', 'carga_horaria', 'es_obligatoria'])]

class Materia extends Model
{
    protected function casts(): array {
        return['es_obligatoria' => 'boolean'];
    }
    public function plan(){
        return $this->belongsTo(PlanEstudio::class, 'plan_id');
    }
}
