<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\fillable;

#[fillable(['anio', 'cuatrimestre', 'fecha_inicio', 'fecha_fin', 'estado'])]

class PeriodoLectivo extends Model
{
    protected $table = 'periodos_lectivos';

    protected function casts(): array {
        return['fecha_inicio' => 'date', 'fecha_fin' => 'date'];
    }

    public function cursos(){
        return $this->hasMany(Curso::class, 'periodo_id');
    }
}
