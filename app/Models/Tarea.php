<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
/**
* HU12 (#118): una tarea que el profesor publica para una asignación (profesor + materia + curso).
* El archivo de la consigna (opcional) se guarda en el disco privado: tareas/{id}/consigna.{ext}
*/

class Tarea extends Model
    {
        protected $table = 'tareas';
        protected $fillable = ['curso_profesor_id', 'titulo', 'consigna', 'archivo_ruta', 'fecha_entrega'];
        protected $casts = [
            'fecha_entrega' => 'datetime',
        ];
    
        public function cursoProfesor(): BelongsTo
        {
            return $this->belongsTo(CursoProfesor::class);
        }
        
        /** Las entregas de los alumnos (una por alumno) */

        public function entregas(): HasMany
        {
            return $this->hasMany(Entrega::class);
        }
    }