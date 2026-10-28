<?php

namespace App\Livewire\Admin\Planes;

use App\Models\Materia;
use App\Models\PlanEstudio;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * HU07 (#9): ABM de planes de estudio y de sus materias o módulos (RF15).
 * La baja de un plan es desactivarlo: sus cursos salen de la oferta pública.
 * Las materias no se borran (hay notas y asistencia que dependen de ellas).
 */
class Index extends Component
{
    // #73: los formularios se abren en la ventana emergente (como Profesores)
    public bool $ventanaPlan = false;

    // ---------- Plan ----------
    public ?int $planId = null;

    public string $nombre = '';
    public string $tipo = 'formacion_profesional';
    public string $descripcion = '';
    public string $duracionMeses = '';
    public string $cantidadCiclos = '';
    public string $resolucion = '';
    public string $edadMinima = '18';
    public string $notaAprobacion = '6';
    public string $asistenciaMinima = '80';

    // ---------- Materias del plan elegido ----------
    public ?int $planSeleccionado = null;
    public ?int $materiaId = null;

    public string $materiaNombre = '';
    public string $materiaCiclo = '';
    public string $materiaCarga = '';
    public bool $materiaObligatoria = true;

    /** Reglas del plan, alineadas con los CHECK de planes_estudio */
    protected function reglasPlan(): array
    {
        return [
            'nombre'           => ['required', 'string', 'max:150', Rule::unique('planes_estudio', 'nombre')->ignore($this->planId)],
            'tipo'             => ['required', Rule::in(['formacion_profesional', 'terminalidad'])],
            'descripcion'      => ['nullable', 'string'],
            'duracionMeses'    => ['nullable', 'integer', 'between:1,255'],
            'cantidadCiclos'   => ['nullable', 'integer', 'between:1,6'],
            'resolucion'       => ['nullable', 'string', 'max:100'],
            'edadMinima'       => ['required', 'integer', 'between:14,99'],
            'notaAprobacion'   => ['required', 'numeric', 'between:1,10'],
            'asistenciaMinima' => ['required', 'integer', 'between:0,100'],
        ];
    }

    /** Reglas de la materia: nombre único dentro del mismo plan (uq_materia) */
    protected function reglasMateria(): array
    {
        return [
            'materiaNombre' => [
                'required', 'string', 'max:150',
                Rule::unique('materias', 'nombre')
                    ->where(fn ($q) => $q->where('plan_id', $this->planSeleccionado))
                    ->ignore($this->materiaId),
            ],
            'materiaCiclo'  => ['nullable', 'integer', 'between:1,6'],
            'materiaCarga'  => ['nullable', 'integer', 'between:1,65535'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'duracionMeses' => 'duración en meses', 'cantidadCiclos' => 'cantidad de ciclos',
            'edadMinima' => 'edad mínima', 'notaAprobacion' => 'nota de aprobación',
            'asistenciaMinima' => 'asistencia mínima', 'materiaNombre' => 'nombre de la materia',
            'materiaCiclo' => 'ciclo', 'materiaCarga' => 'carga horaria',
        ];
    }

    // ================= Plan =================

    public function guardarPlan(): void
    {
        $this->validate($this->reglasPlan());

        $datos = [
            'nombre'            => $this->nombre,
            'tipo'              => $this->tipo,
            'descripcion'       => $this->descripcion ?: null,
            'duracion_meses'    => $this->duracionMeses !== '' ? (int) $this->duracionMeses : null,
            'cantidad_ciclos'   => $this->cantidadCiclos !== '' ? (int) $this->cantidadCiclos : null,
            'resolucion'        => $this->resolucion ?: null,
            'edad_minima'       => (int) $this->edadMinima,
            'nota_aprobacion'   => $this->notaAprobacion,
            'asistencia_minima' => (int) $this->asistenciaMinima,
        ];

        if ($this->planId) {
            PlanEstudio::findOrFail($this->planId)->update($datos);
            session()->flash('ok', 'Plan actualizado.');
        } else {
            PlanEstudio::create($datos);
            session()->flash('ok', 'Plan creado.');
        }

        $this->limpiarPlan();
    }

    /** «Nuevo plan»: abre la ventana con el formulario vacío */
    public function nuevoPlan(): void
    {
        $this->limpiarPlan();
        $this->ventanaPlan = true;
    }

    public function editarPlan(int $id): void
    {
        $plan = PlanEstudio::findOrFail($id);

        $this->planId           = $plan->id;
        $this->nombre           = $plan->nombre;
        $this->tipo             = $plan->tipo;
        $this->descripcion      = $plan->descripcion ?? '';
        $this->duracionMeses    = (string) ($plan->duracion_meses ?? '');
        $this->cantidadCiclos   = (string) ($plan->cantidad_ciclos ?? '');
        $this->resolucion       = $plan->resolucion ?? '';
        $this->edadMinima       = (string) $plan->edad_minima;
        $this->notaAprobacion   = (string) $plan->nota_aprobacion;
        $this->asistenciaMinima = (string) $plan->asistencia_minima;

        $this->resetValidation();
        $this->ventanaPlan = true;
    }

    /** Activa o desactiva el plan. Desactivado, sus cursos salen de la oferta pública. */
    public function cambiarEstadoPlan(int $id): void
    {
        $plan = PlanEstudio::findOrFail($id);
        $plan->activo = ! $plan->activo;
        $plan->save();

        session()->flash('ok', $plan->activo ? 'Plan activado.' : 'Plan desactivado.');
    }

    /** Cierra la ventana del plan y vacía el formulario (Cancelar, Esc o después de guardar) */
    public function limpiarPlan(): void
    {
        $this->reset(['ventanaPlan', 'planId', 'nombre', 'tipo', 'descripcion', 'duracionMeses', 'cantidadCiclos',
                      'resolucion', 'edadMinima', 'notaAprobacion', 'asistenciaMinima']);
        $this->resetValidation();
    }

    // ================= Materias =================

    /** «Materias»: abre la ventana con las materias del plan y el formulario para agregar o editar */
    public function verMaterias(int $planId): void
    {
        $this->planSeleccionado = PlanEstudio::findOrFail($planId)->id;
        $this->limpiarMateria();
    }

    public function guardarMateria(): void
    {
        if (! $this->planSeleccionado) {
            return;
        }

        $this->validate($this->reglasMateria());

        $datos = [
            'plan_id'        => $this->planSeleccionado,
            'nombre'         => $this->materiaNombre,
            'ciclo'          => $this->materiaCiclo !== '' ? (int) $this->materiaCiclo : null,
            'carga_horaria'  => $this->materiaCarga !== '' ? (int) $this->materiaCarga : null,
            'es_obligatoria' => $this->materiaObligatoria,
        ];

        if ($this->materiaId) {
            Materia::where('plan_id', $this->planSeleccionado)->findOrFail($this->materiaId)->update($datos);
            session()->flash('ok', 'Materia actualizada.');
        } else {
            Materia::create($datos);
            session()->flash('ok', 'Materia agregada.');
        }

        $this->limpiarMateria();
    }

    public function editarMateria(int $id): void
    {
        $materia = Materia::where('plan_id', $this->planSeleccionado)->findOrFail($id);

        $this->materiaId          = $materia->id;
        $this->materiaNombre      = $materia->nombre;
        $this->materiaCiclo       = (string) ($materia->ciclo ?? '');
        $this->materiaCarga       = (string) ($materia->carga_horaria ?? '');
        $this->materiaObligatoria = $materia->es_obligatoria;

        $this->resetValidation();
    }

    /** Cierra la ventana de materias (Cerrar o Esc) */
    public function cerrarMaterias(): void
    {
        $this->planSeleccionado = null;
        $this->limpiarMateria();
    }

    /** Vacía el formulario de materia (la ventana de materias sigue abierta) */
    public function limpiarMateria(): void
    {
        $this->reset(['materiaId', 'materiaNombre', 'materiaCiclo', 'materiaCarga', 'materiaObligatoria']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.admin.planes.index', [
            'planes'   => PlanEstudio::withCount('materias')->orderBy('nombre')->get(),
            'elegido'  => $this->planSeleccionado ? PlanEstudio::find($this->planSeleccionado) : null,
            'materias' => $this->planSeleccionado
                ? Materia::where('plan_id', $this->planSeleccionado)->orderBy('ciclo')->orderBy('nombre')->get()
                : collect(),
        ]);
    }
}