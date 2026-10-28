<?php

namespace App\Events;

use App\Models\Preinscripcion;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * HU04 (#51): se dispara cuando se guarda una preinscripción nueva.
 
 */
class PreinscripcionRecibida
{
    use Dispatchable, SerializesModels;

    public function __construct(public Preinscripcion $preinscripcion)
    {
    }
}