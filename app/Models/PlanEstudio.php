<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanEstudio extends Model
{
    protected $table = 'planes_estudio';
    
    /** Tipos de plan (chk_plan_tipo). «Capacitación laboral»: #95, pedido del PO del 30/10. */
    public const TIPOS = [
        'formacion_profesional' => 'Formación profesional',
        'capacitacion_laboral'  => 'Capacitación laboral',
        'terminalidad'          => 'Terminalidad',
    ];
    
    protected $fillable = 
    [
        'nombre', 
        'tipo', 
        'descripcion', 
        'duracion_meses', 
        'cantidad_ciclos', 
        'resolucion', 
        'edad_minima', 
        'nota_aprobacion', 
        'asistencia_minima', 
        'activo'
    ];

    protected function casts(): array {
        return
        [
            'activo' => 'boolean',
            'nota_aprobacion' => 'decimal:2'
        ]; 
        }

    public function materias() { return $this->hasMany(Materia::class, 'plan_id'); }
    public function cursos() { return $this->hasMany(Curso::class, 'plan_id'); }
}