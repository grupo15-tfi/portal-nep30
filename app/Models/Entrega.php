<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
* HU12 (#118): la entrega de un alumno para una tarea (una sola por alumno; se puede reemplazar hasta la fecha
límite).
* fuera_de_termino lo calcula la base (trg_entrega_bi / trg_entrega_bu), no se escribe desde PHP.
* El archivo se guarda en el disco privado: entregas/{tarea_id}/{inscripcion_id}.{ext}
*/
class Entrega extends Model
{
    protected $table = 'entregas';
    /** Igual que chk_entrega_tipo */
    public const TIPOS = ['pdf', 'docx', 'jpg', 'png', 'zip'];
    /** 10 MB en bytes, igual que chk_entrega_tam */
    public const TAMANO_MAXIMO = 10485760;
    protected $fillable = [
        'tarea_id', 'inscripcion_id', 'archivo_ruta', 'archivo_tamano', 'archivo_tipo',
        'fecha_entrega', 'devolucion', 'devuelta_en',
    ];
    protected $casts = [
        'fecha_entrega'
        => 'datetime',
        'fuera_de_termino' => 'boolean',
        'devuelta_en'
        => 'datetime',
    ];
    public function tarea(): BelongsTo
    {
    return $this->belongsTo(Tarea::class);
    }
    public function inscripcion(): BelongsTo
    {
    return $this->belongsTo(Inscripcion::class);
    }
}