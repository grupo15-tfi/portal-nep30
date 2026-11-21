<?php

namespace App\Livewire\Profesor;

use App\Actions\Tareas\DevolverEntrega;
use App\Actions\Tareas\GuardarTarea;
use App\Models\CursoProfesor;
use App\Models\Entrega;
use App\Models\Tarea;
use App\Queries\EntregasDeLaTarea;
use App\Queries\TareasDelProfesor;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * HU12 (#120): el profesor publica y edita tareas, ve quién entregó y devuelve las entregas.
 * Guarda con GuardarTarea y DevolverEntrega (#118); lee con TareasDelProfesor y EntregasDeLaTarea (#119).
 * La pantalla no valida ni guarda por su cuenta: los errores del back quedan dentro de la ventana.
 * Solo tareas de sus asignaciones: si no, 404.
 */
class Tareas extends Component
{
    use WithFileUploads;

    public ?string $aviso = null;

    /** Tarea cuyas entregas se están viendo */
    public ?int $verTareaId = null;

    // ---------- Ventana de la tarea: los nombres coinciden con las claves de error de GuardarTarea ----------
    public bool $ventanaTarea = false;

    public ?int $tareaId = null;

    public ?int $curso_profesor_id = null;

    public string $titulo = '';

    public string $consigna = '';

    public string $fecha_entrega = '';

    public $archivo = null;

    // ---------- Ventana de la devolución ----------
    public ?int $entregaId = null;

    public string $devolucion = '';

    public function nueva(): void
    {
        $this->cerrar();
        $this->curso_profesor_id = $this->asignaciones()->first()?->id;
        $this->fecha_entrega = now()->addWeek()->setTime(23, 59)->format('Y-m-d\TH:i');
        $this->ventanaTarea = true;
    }

    public function editar(int $tareaId): void
    {
        $tarea = $this->tareaPropia($tareaId);

        $this->cerrar();
        $this->tareaId           = $tarea->id;
        $this->curso_profesor_id = $tarea->curso_profesor_id;
        $this->titulo            = $tarea->titulo;
        $this->consigna          = (string) $tarea->consigna;
        $this->fecha_entrega     = $tarea->fecha_entrega->format('Y-m-d\TH:i');
        $this->ventanaTarea      = true;
    }

    public function guardar(): void
    {
        $existente = $this->tareaId ? $this->tareaPropia($this->tareaId) : null;

        $tarea = GuardarTarea::ejecutar([
            'curso_profesor_id' => $this->curso_profesor_id,
            'titulo'            => trim($this->titulo),
            'consigna'          => trim($this->consigna) === '' ? null : trim($this->consigna),
            'fecha_entrega'     => $this->fecha_entrega,
            'archivo'           => $this->archivo,
        ], $existente);

        $this->aviso = ($existente ? 'Se guardaron los cambios de «' : 'Se publicó la tarea «').$tarea->titulo.'».';
        $this->cerrar();
    }

    public function verEntregas(int $tareaId): void
    {
        $this->verTareaId = $this->tareaPropia($tareaId)->id;
    }

    public function devolver(int $entregaId): void
    {
        $entrega = $this->entregaPropia($entregaId);

        $this->cerrar();
        $this->entregaId  = $entrega->id;
        $this->devolucion = (string) $entrega->devolucion;
    }

    public function guardarDevolucion(): void
    {
        $entrega = DevolverEntrega::ejecutar($this->entregaPropia($this->entregaId), $this->devolucion);

        $alumno = $entrega->inscripcion->alumno;
        $this->aviso = 'Se guardó la devolución para '.$alumno->apellido.', '.$alumno->nombre.'.';
        $this->cerrar();
    }

    public function cerrar(): void
    {
        $this->reset(['ventanaTarea', 'tareaId', 'curso_profesor_id', 'titulo', 'consigna', 'fecha_entrega', 'archivo', 'entregaId', 'devolucion']);
        $this->resetErrorBag();
    }

    private function asignaciones()
    {
        return CursoProfesor::with('materia', 'curso.planEstudio', 'curso.sede')->where('profesor_id', auth()->id())->orderBy('id')->get();
    }

    private function tareaPropia(int $id): Tarea
    {
        return Tarea::whereHas('cursoProfesor', fn ($q) => $q->where('profesor_id', auth()->id()))->findOrFail($id);
    }

    private function entregaPropia(?int $id): Entrega
    {
        return Entrega::with('inscripcion.alumno')
            ->whereHas('tarea.cursoProfesor', fn ($q) => $q->where('profesor_id', auth()->id()))->findOrFail($id);
    }

    public function render()
    {
        $verTarea = $this->verTareaId ? $this->tareaPropia($this->verTareaId) : null;

        return view('livewire.profesor.tareas', [
            'tareas'         => collect(TareasDelProfesor::de(auth()->user()))->values(),
            'asignaciones'   => $this->ventanaTarea ? $this->asignaciones() : collect(),
            'verTarea'       => $verTarea,
            'entregas'       => $verTarea ? collect(EntregasDeLaTarea::de($verTarea))->values() : collect(),
            'entregaElegida' => $this->entregaId ? $this->entregaPropia($this->entregaId) : null,
            'conDescargas'   => Route::has('entregas.archivo'),
            'conConsigna'    => Route::has('tareas.archivo'),
        ]);
    }
}
