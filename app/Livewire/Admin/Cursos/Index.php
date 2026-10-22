<?php

namespace App\Livewire\Admin\Cursos;

use App\Models\Curso;
use App\Models\PeriodoLectivo;
use App\Models\PlanEstudio;
use App\Models\Sede;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * HU07 (#9): ABM de cursos (RF15). Un curso = un plan dictado en una sede,
 * en un período y un turno, con su horario y su cupo.
 * La baja es pasar a "inactivo": nunca se borra (hay inscripciones y notas).
 */
class Index extends Component
{
    // null = alta; con valor = edición de ese curso
    public ?int $cursoId = null;

    public string $planId = '';
    public string $sedeId = '';
    public string $periodoId = '';
    public string $turno = 'mañana';
    public string $dias = '';
    public string $horaInicio = '';
    public string $horaFin = '';
    public string $cupo = '';

    /** Reglas alineadas con los CHECK y el índice único de la tabla cursos */
    protected function rules(): array
    {
        return [
            'planId'     => ['required', 'exists:planes_estudio,id'],
            'sedeId'     => ['required', 'exists:sedes,id'],
            'periodoId'  => ['required', 'exists:periodos_lectivos,id'],
            'turno'      => [
                'required',
                Rule::in(['mañana', 'tarde', 'noche']),
                // uq_curso: no puede haber dos cursos del mismo plan, sede, período y turno
                Rule::unique('cursos', 'turno')
                    ->where(fn ($q) => $q->where('plan_id', $this->planId)
                                         ->where('sede_id', $this->sedeId)
                                         ->where('periodo_id', $this->periodoId))
                    ->ignore($this->cursoId),
            ],
            'dias'       => ['nullable', 'string', 'max:60'],
            'horaInicio' => ['required', 'date_format:H:i'],
            'horaFin'    => ['required', 'date_format:H:i', 'after:horaInicio'],
            'cupo'       => ['required', 'integer', 'between:1,500'],
        ];
    }

    protected function messages(): array
    {
        return [
            'turno.unique' => 'Ya existe un curso de ese plan en esa sede, período y turno.',
            'horaFin.after' => 'La hora de fin tiene que ser posterior a la de inicio.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'planId' => 'plan', 'sedeId' => 'sede', 'periodoId' => 'período',
            'horaInicio' => 'hora de inicio', 'horaFin' => 'hora de fin',
        ];
    }

    public function guardar(): void
    {
        $this->validate();

        $datos = [
            'plan_id'     => (int) $this->planId,
            'sede_id'     => (int) $this->sedeId,
            'periodo_id'  => (int) $this->periodoId,
            'turno'       => $this->turno,
            'dias'        => $this->dias ?: null,
            'hora_inicio' => $this->horaInicio,
            'hora_fin'    => $this->horaFin,
            'cupo'        => (int) $this->cupo,
        ];

        if ($this->cursoId) {
            Curso::findOrFail($this->cursoId)->update($datos);
            session()->flash('ok', 'Curso actualizado.');
        } else {
            Curso::create($datos); // estado = 'activo' por defecto en la base
            session()->flash('ok', 'Curso creado.');
        }

        $this->limpiar();
    }

    public function editar(int $id): void
    {
        $curso = Curso::findOrFail($id);

        $this->cursoId    = $curso->id;
        $this->planId     = (string) $curso->plan_id;
        $this->sedeId     = (string) $curso->sede_id;
        $this->periodoId  = (string) $curso->periodo_id;
        $this->turno      = $curso->turno;
        $this->dias       = $curso->dias ?? '';
        $this->horaInicio = substr((string) $curso->hora_inicio, 0, 5); // la base guarda HH:MM:SS
        $this->horaFin    = substr((string) $curso->hora_fin, 0, 5);
        $this->cupo       = (string) $curso->cupo;

        $this->resetValidation();
    }

    /** Activa o desactiva el curso. Un curso finalizado no se toca. */
    public function cambiarEstado(int $id): void
    {
        $curso = Curso::findOrFail($id);

        if ($curso->estado === 'finalizado') {
            session()->flash('error', 'Un curso finalizado no se puede reactivar.');
            return;
        }

        $curso->estado = $curso->estado === 'activo' ? 'inactivo' : 'activo';
        $curso->save();

        session()->flash('ok', $curso->estado === 'activo' ? 'Curso activado.' : 'Curso desactivado.');
    }

    public function limpiar(): void
    {
        $this->reset(['cursoId', 'planId', 'sedeId', 'periodoId', 'turno', 'dias', 'horaInicio', 'horaFin', 'cupo']);
        $this->resetValidation();
    }

    public function render()
    {
        // Una sola consulta con el cupo disponible de la vista (no una por curso)
        $cursos = Curso::query()
            ->join('v_cupos_disponibles as v', 'v.curso_id', '=', 'cursos.id')
            ->select('cursos.*', 'v.inscriptos', 'v.disponibles')
            ->with(['sede', 'planEstudio', 'periodoLectivo'])
            ->orderByDesc('cursos.id')
            ->get();

        return view('livewire.admin.cursos.index', [
            'cursos'   => $cursos,
            'planes'   => PlanEstudio::where('activo', true)->orderBy('nombre')->get(),
            'sedes'    => Sede::where('activo', true)->orderBy('nombre')->get(),
            'periodos' => PeriodoLectivo::where('estado', 'activo')->orderByDesc('anio')->get(),
        ]);
    }
}