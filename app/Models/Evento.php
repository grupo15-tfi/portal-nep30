<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Eventos del calendario. curso_id null = evento institucional (HU10 los carga);
 * los de un curso (evaluaciones, entregas) nunca son públicos (CHECK chk_evento_pub).
 */
class Evento extends Model
{
    protected $fillable = ['curso_id', 'tarea_id', 'titulo', 'descripcion', 'tipo', 'fecha_inicio', 'fecha_fin', 'publico', 'creado_por'];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'datetime',
            'fecha_fin'    => 'datetime',
            'publico'      => 'boolean',
        ];
    }

    /** Eventos que se muestran en el sitio público (HU02): públicos e institucionales, por fecha */
    public function scopePublicos(Builder $query): Builder
    {
        return $query->where('publico', true)->whereNull('curso_id')->orderBy('fecha_inicio')->orderBy('id');
    }

    /** Eventos que empiezan, terminan o transcurren dentro del rango */
    public function scopeEntre(Builder $query, \DateTimeInterface $desde, \DateTimeInterface $hasta): Builder
    {
        return $query->where('fecha_inicio', '<=', $hasta)
            ->whereRaw('COALESCE(fecha_fin, fecha_inicio) >= ?', [$desde]);
    }

    /** Nombre del tipo para mostrar */
    public function tipoTexto(): string
    {
        return [
            'institucional' => 'Institucional',
            'evaluacion'    => 'Evaluación',
            'entrega'       => 'Entrega',
            'feriado'       => 'Feriado',
            'otro'          => 'Otro',
        ][$this->tipo] ?? ucfirst($this->tipo);
    }
}
