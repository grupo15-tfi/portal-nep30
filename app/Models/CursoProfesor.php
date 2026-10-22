<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asignación de un profesor a una materia de un curso (HU08 #13).
 * Tabla curso_profesores: puede haber dos profesores en la misma materia (taller compartido).
 * El trigger trg_cp_bi frena una materia de otro plan o un usuario sin rol profesor.
 */
class CursoProfesor extends Model
{
    protected $table = 'curso_profesores'; // Laravel buscaría "curso_profesors"

    protected $fillable = ['curso_id', 'materia_id', 'profesor_id'];

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function materia(): BelongsTo
    {
        return $this->belongsTo(Materia::class);
    }

    public function profesor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profesor_id');
    }
}