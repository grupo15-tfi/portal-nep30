<?php

namespace App\Livewire\Admin\Alumnos;

use App\Actions\Alumnos\DarDeBaja;
use App\Models\Curso;
use App\Models\Inscripcion;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * HU09 (#88): alumnos inscriptos por curso, con su estado, y la baja con motivo.
 * La baja la hace DarDeBaja (#86, contrato en #83): esta pantalla no valida ni guarda por su cuenta;
 * sus errores (`motivo`, `estado`) se muestran dentro de la ventana emergente.
 */
class Index extends Component
{
    use WithPagination;

    public const ESTADOS = [
        'activa'     => 'Activa',
        'baja'       => 'Baja',
        'finalizada' => 'Finalizada',
    ];

    /** Filtros */
    public string $buscar = '';

    public ?int $cursoId = null;

    /** '' = todos los estados */
    public string $estado = '';

    public ?string $aviso = null;

    /** Ventana de baja: inscripción elegida y motivo (mismo nombre que la clave de error del contrato) */
    public ?int $bajaId = null;

    public string $motivo = '';

    /** Al cambiar un filtro, volver a la primera página */
    public function updated(string $propiedad): void
    {
        if (in_array($propiedad, ['buscar', 'cursoId', 'estado'], true)) {
            $this->resetPage();
        }
    }

    public function abrirBaja(int $id): void
    {
        $this->bajaId = Inscripcion::where('estado', 'activa')->findOrFail($id)->id;
        $this->motivo = '';
        $this->resetErrorBag();
    }

    public function cerrar(): void
    {
        $this->reset('bajaId', 'motivo');
        $this->resetErrorBag();
    }

    public function darDeBaja(): void
    {
        $inscripcion = Inscripcion::with('alumno')->findOrFail($this->bajaId);

        // Si falta el motivo o ya no está activa, DarDeBaja lanza ValidationException
        // y el error queda dentro de la ventana, que no se cierra.
        DarDeBaja::ejecutar($inscripcion, $this->motivo);

        $alumno = $inscripcion->alumno;
        $this->aviso = "Se dio de baja a {$alumno->nombreCompleto()} del curso.";
        if (! $alumno->fresh()->activo) {
            $this->aviso .= ' No le queda otra inscripción activa: ya no puede ingresar al Campus.';
        }

        $this->cerrar();
    }

    public function nombreCurso(Curso $curso): string
    {
        return "{$curso->planEstudio->nombre} · {$curso->sede->nombre} · turno {$curso->turno}";
    }

    public function render()
    {
        $buscar = trim($this->buscar);

        $inscripciones = Inscripcion::query()
            ->with(['alumno', 'curso.planEstudio', 'curso.sede'])
            ->join('users', 'users.id', '=', 'inscripciones.alumno_id')
            ->select('inscripciones.*')
            ->when($buscar !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('users.dni', 'like', $buscar.'%')
                ->orWhere('users.apellido', 'like', '%'.$buscar.'%')))
            ->when($this->cursoId, fn ($q) => $q->where('inscripciones.curso_id', $this->cursoId))
            ->when(array_key_exists($this->estado, self::ESTADOS), fn ($q) => $q->where('inscripciones.estado', $this->estado))
            ->orderBy('users.apellido')
            ->orderBy('users.nombre')
            ->orderBy('inscripciones.id')
            ->paginate(15);

        return view('livewire.admin.alumnos.index', [
            'inscripciones' => $inscripciones,
            'cursos'        => Curso::with(['sede', 'planEstudio'])->get()
                ->mapWithKeys(fn (Curso $c) => [$c->id => $this->nombreCurso($c)])
                ->sort(),
            'elegida'       => $this->bajaId ? Inscripcion::with(['alumno', 'curso.planEstudio', 'curso.sede'])->find($this->bajaId) : null,
        ]);
    }
}
