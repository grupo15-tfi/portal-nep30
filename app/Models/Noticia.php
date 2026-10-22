<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Noticias del sitio público (HU01 las muestra, HU10 las administra).
 * Estado: borrador o publicada. Solo se ven las publicadas con fecha ya cumplida.
 */
class Noticia extends Model
{
    protected $fillable = ['titulo', 'slug', 'resumen', 'cuerpo', 'imagen_ruta', 'estado', 'publicada_en', 'autor_id'];

    protected function casts(): array
    {
        return ['publicada_en' => 'datetime'];
    }

    /** Publicadas, con fecha de publicación cumplida, de la más reciente a la más antigua */
    public function scopePublicadas(Builder $query): Builder
    {
        return $query->where('estado', 'publicada')
            ->whereNotNull('publicada_en')
            ->where('publicada_en', '<=', now())
            ->orderByDesc('publicada_en')
            ->orderByDesc('id');
    }

    public function autor()
    {
        return $this->belongsTo(User::class, 'autor_id');
    }
}
