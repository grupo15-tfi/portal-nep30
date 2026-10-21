<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Teléfono de un usuario (HU06 #16). Tipos: celular, fijo, whatsapp o emergencia.
 * Uno de emergencia exige el nombre del contacto (CHECK chk_tel_contac de la base).
 */
class Telefono extends Model
{
    public const TIPOS = ['celular', 'fijo', 'whatsapp', 'emergencia'];

    /** Mismo patrón que el CHECK chk_tel_numero: dígitos, espacios, +, paréntesis y guiones (6 a 30) */
    public const PATRON_NUMERO = '/^[0-9 +()-]{6,30}$/';

    protected $fillable = ['usuario_id', 'numero', 'tipo', 'contacto_nombre', 'es_principal'];

    protected function casts(): array
    {
        return ['es_principal' => 'boolean'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
