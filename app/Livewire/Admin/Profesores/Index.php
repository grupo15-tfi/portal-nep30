<?php

namespace App\Livewire\Admin\Profesores;

use App\Actions\Profesores\CambiarEstadoProfesor;
use App\Models\Sede;
use App\Models\User;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * HU08 (#14): listado de profesores con búsqueda, paginado de 15, estado y alta/baja.
 * El alta y la edición se hacen en el modal (Formulario); las materias, en Asignaciones.
 */
class Index extends Component
{
    use WithPagination;

    public string $buscar = '';

    public ?string $aviso = null;

    /** Al escribir en el buscador, volver a la primera página */
    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    /** Baja o alta lógica (activo). Nunca se borra: lo resuelve la acción de #13. */
    public function cambiarEstado(int $id): void
    {
        $profesor = User::profesores()->findOrFail($id);

        CambiarEstadoProfesor::ejecutar($profesor, ! $profesor->activo);

        $this->aviso = $profesor->activo
            ? "{$profesor->nombreCompleto()} quedó activo."
            : "{$profesor->nombreCompleto()} quedó dado de baja: ya no puede ingresar.";
    }

    /** El modal avisa cuando guardó (alta o edición) */
    #[On('profesor-guardado')]
    public function profesorGuardado(string $mensaje): void
    {
        $this->aviso = $mensaje;
    }

    public function render()
    {
        $texto = trim($this->buscar);

        $profesores = User::profesores()
            ->withCount('asignaciones')
            ->when($texto !== '', fn ($q) => $q->where(function ($w) use ($texto) {
                $w->where('dni', 'like', "%{$texto}%")
                  ->orWhere('apellido', 'like', "%{$texto}%")
                  ->orWhere('email', 'like', "%{$texto}%");
            }))
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->paginate(15);

        return view('livewire.admin.profesores.index', [
            'profesores' => $profesores,
            'sedes'      => Sede::pluck('nombre', 'id'),
        ]);
    }
}
