<?php

namespace App\Models\Concerns;

use App\Models\Telefono;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Teléfonos del usuario (HU06 #16). Se usa en User con `use TieneTelefonos;`.
 */
trait TieneTelefonos
{
    public function telefonos(): HasMany
    {
        return $this->hasMany(Telefono::class, 'usuario_id')->orderByDesc('es_principal')->orderBy('id');
    }
}
