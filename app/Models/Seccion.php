<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Secciones editables del sitio público (inicio, historia, contacto, servicios, privacidad).
 * Las carga SeccionesSeeder; el texto se edita desde el panel (HU10).
 */
class Seccion extends Model
{
    protected $table = 'secciones'; // Laravel buscaría "seccions"

    protected $fillable = ['clave', 'titulo', 'contenido', 'imagen_ruta', 'actualizado_por'];
}
