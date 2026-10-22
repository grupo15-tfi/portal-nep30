<?php

namespace App\Models\Concerns;

use App\Models\CursoProfesor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lo propio de un usuario con rol profesor (HU08 #13). Se usa en User con `use EsProfesor;`.
 */
trait EsProfesor
{
    /** User::profesores(): solo los usuarios con rol profesor (activos e inactivos) */
    public function scopeProfesores(Builder $query): Builder
    {
        return $query->role('profesor');
    }

    /** Sus asignaciones (curso + materia), con la sede del curso y la materia ya cargadas */
    public function asignaciones(): HasMany
    {
        return $this->hasMany(CursoProfesor::class, 'profesor_id')->with(['curso.sede', 'materia']);
    }
}