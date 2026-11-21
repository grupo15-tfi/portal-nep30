<?php

namespace App\Livewire\Alumno;

use App\Actions\Tareas\EntregarTarea;
use App\Models\Entrega;
use App\Models\Inscripcion;
use App\Models\Tarea;
use App\Queries\TareasDelAlumno;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * HU12 (#120): «Tareas» del alumno. Solo las de sus cursos: TareasDelAlumno::de() (#119).
 * Entrega o reemplaza con EntregarTarea (#118); los errores (tipo, tamaño, vencida) quedan en la ventana.
 * Se lee con data_get() para aceptar filas como arreglo o como objeto.
 */
class Tareas extends Component
{
    use WithFileUploads;

    public ?string $aviso = null;

    /** Tarea que se está entregando */
    public ?int $tareaId = null;

    public $archivo = null;

    public function entregar(int $tareaId): void
    {
        [$tarea] = $this->tareaYInscripcion($tareaId);

        $this->cerrar();
        $this->tareaId = $tarea->id;
    }

    public function guardar(): void
    {
        [$tarea, $inscripcion] = $this->tareaYInscripcion($this->tareaId);

        if (! $this->archivo) {
            throw \Illuminate\Validation\ValidationException::withMessages(['archivo' => 'Elegí el archivo de tu entrega.']);
        }

        $entrega = EntregarTarea::ejecutar($tarea, $inscripcion, $this->archivo);

        $this->aviso = 'Se entregó «'.$tarea->titulo.'»'.($entrega->fuera_de_termino ? ' (fuera de término).' : '.');
        $this->cerrar();
    }

    public function cerrar(): void
    {
        $this->reset(['tareaId', 'archivo']);
        $this->resetErrorBag();
    }

    /** La tarea tiene que ser de un curso donde el alumno está activo; si no, 404 */
    private function tareaYInscripcion(?int $tareaId): array
    {
        $tarea = Tarea::with('cursoProfesor')->findOrFail($tareaId);
        $inscripcion = Inscripcion::where('alumno_id', auth()->id())->where('estado', 'activa')
            ->where('curso_id', $tarea->cursoProfesor->curso_id)->first();
        abort_unless($inscripcion, 404);

        return [$tarea, $inscripcion];
    }

    /** pendiente | entregada | fuera_de_termino | vencida */
    public static function estado(mixed $t): string
    {
        $entrega = data_get($t, 'entrega');

        return match (true) {
            $entrega && data_get($entrega, 'fuera_de_termino') => 'fuera_de_termino',
            (bool) $entrega                                    => 'entregada',
            (bool) data_get($t, 'vencida')                     => 'vencida',
            default                                            => 'pendiente',
        };
    }

    public function render()
    {
        $tareas = collect(TareasDelAlumno::de(auth()->user()))->values();

        return view('livewire.alumno.tareas', [
            'tareas'       => $tareas,
            'elegida'      => $this->tareaId ? $tareas->first(fn ($t) => (int) data_get($t, 'tarea_id') === $this->tareaId) : null,
            'tipos'        => Entrega::TIPOS,
            'maximoMb'     => intdiv(Entrega::TAMANO_MAXIMO, 1024 * 1024),
            'conConsigna'  => Route::has('tareas.archivo'),
            'conDescargas' => Route::has('entregas.archivo'),
        ]);
    }
}
