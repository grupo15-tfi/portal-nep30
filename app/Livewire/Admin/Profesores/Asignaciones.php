<?php

namespace App\Livewire\Admin\Profesores;

use App\Actions\Profesores\AsignarMateria;
use App\Actions\Profesores\QuitarMateria;
use App\Models\Curso;
use App\Models\User;
use Livewire\Component;

/**
 * HU08 (#14): materias que dicta un profesor.
 * Se elige un curso y solo se ofrecen las materias del plan de ese curso (Curso::materiasDelPlan()).
 * Los errores de las acciones de #13 llegan con la clave materia_id y se muestran debajo del combo.
 */
class Asignaciones extends Component
{
    public User $profesor;

    public string $curso_id = '';
    public string $materia_id = '';

    public ?string $aviso = null;

    public function mount(User $profesor): void
    {
        abort_unless($profesor->hasRole('profesor'), 404);

        $this->profesor = $profesor;
    }

    /** Al cambiar de curso, la materia elegida ya no vale */
    public function updatedCursoId(): void
    {
        $this->materia_id = '';
        $this->resetErrorBag();
    }

    public function asignar(): void
    {
        $this->validate(
            ['curso_id' => ['required'], 'materia_id' => ['required']],
            ['curso_id.required' => 'Elegí un curso.', 'materia_id.required' => 'Elegí una materia.'],
        );

        AsignarMateria::ejecutar($this->profesor, (int) $this->curso_id, (int) $this->materia_id);

        $this->aviso = 'Materia asignada.';
        $this->materia_id = '';
    }

    public function quitar(int $cursoId, int $materiaId): void
    {
        QuitarMateria::ejecutar($this->profesor, $cursoId, $materiaId);

        $this->aviso = 'Se quitó la asignación.';
    }

    public function render()
    {
        $curso = $this->curso_id !== '' ? Curso::find((int) $this->curso_id) : null;

        return view('livewire.admin.profesores.asignaciones', [
            'asignaciones' => $this->profesor->asignaciones()->get(),
            'cursos'       => Curso::where('estado', 'activo')->with(['sede', 'planEstudio', 'periodoLectivo'])->orderByDesc('id')->get(),
            'materias'     => $curso ? $curso->materiasDelPlan : collect(),
        ]);
    }
}
