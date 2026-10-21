<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sede extends Model
{
    protected $fillable = ['nombre', 'tipo', 'direccion', 'localidad', 'telefono', 'email', 'latitud', 'longitud', 'observaciones', 'activo'];
    protected $casts = ['activo' => 'boolean', 'latitud' => 'decimal:7', 'longitud' => 'decimal:7'];

    public function cursos() { return $this->hasMany(Curso::class); }
}


