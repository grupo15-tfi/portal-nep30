<?php

namespace App\Livewire\Profesor;

use App\Actions\Notas\GuardarNota;
use App\Models\CursoProfesor;
use App\Models\Evaluacion;
use App\Models\Inscripcion;
use App\Models\InscripcionMateria;
use Livewire\Component;

/**
 * HU11 (#91): el profesor carga y corrige las notas de una de sus materias.
 * Solo su asignación (si no, 403). Guarda con GuardarNota (#89, contrato en #84):
 * la pantalla no valida ni guarda por su cuenta; los errores quedan dentro de la ventana.
 */
class Notas extends Component
{
    /** Los tipos salen del modelo (#89): una sola lista para validar y para mostrar */
    public const TIPOS = Evaluacion::TIPOS;

    public const ESTADOS = [
        'cursando'   => 'Cursando',
        'acreditada' => 'Acreditada',
        'pendiente'  => 'Pendiente',
        'libre'      => 'Libre',
    ];

    public CursoProfesor $cursoProfesor;

    public ?string $aviso = null;

    // ---------- Ventana: los nombres coinciden con las claves de error de GuardarNota ----------
    public bool $ventanaAbierta = false;

    public ?int $evaluacionId = null;

    public ?int $inscripcion_materia_id = null;

    public string $tipo = 'parcial';

    public string $nota = '';

    public string $fecha = '';

    public function mount(CursoProfesor $cursoProfesor): void
    {
        // Solo el profesor de esta asignación (la base también lo controla con trg_eval_bi)
        abort_unless($cursoProfesor->profesor_id === auth()->id(), 403);

        $this->cursoProfesor = $cursoProfesor->load(['materia', 'curso.planEstudio', 'curso.sede']);
    }

    /** «Cargar nota» para un alumno */
    public function nueva(int $inscripcionMateriaId): void
    {
        $im = $this->deEstaMateria()->findOrFail($inscripcionMateriaId);

        $this->cerrar();
        $this->inscripcion_materia_id = $im->id;
        $this->fecha = today()->format('Y-m-d');
        $this->ventanaAbierta = true;
    }

    /** Corregir una nota ya cargada */
    public function editar(int $evaluacionId): void
    {
        $evaluacion = Evaluacion::whereIn('inscripcion_materia_id', $this->deEstaMateria()->select('inscripcion_materias.id'))
            ->findOrFail($evaluacionId);

        $this->cerrar();
        $this->evaluacionId           = $evaluacion->id;
        $this->inscripcion_materia_id = $evaluacion->inscripcion_materia_id;
        $this->tipo                   = $evaluacion->tipo;
        $this->nota                   = rtrim(rtrim(number_format((float) $evaluacion->nota, 2, ',', ''), '0'), ',');
        $this->fecha                  = $evaluacion->fecha->format('Y-m-d');
        $this->ventanaAbierta         = true;
    }

    public function cerrar(): void
    {
        $this->reset(['ventanaAbierta', 'evaluacionId', 'inscripcion_materia_id', 'tipo', 'nota', 'fecha']);
        $this->resetErrorBag();
    }

    public function guardar(): void
    {
        $existente = $this->evaluacionId
            ? Evaluacion::whereIn('inscripcion_materia_id', $this->deEstaMateria()->select('inscripcion_materias.id'))->findOrFail($this->evaluacionId)
            : null;

        $evaluacion = GuardarNota::ejecutar([
            'inscripcion_materia_id' => $this->inscripcion_materia_id,
            'tipo'                   => $this->tipo,
            'nota'                   => str_replace(',', '.', trim($this->nota)),
            'fecha'                  => $this->fecha,
        ], $existente);

        $alumno = InscripcionMateria::with('inscripcion.alumno')->find($evaluacion->inscripcion_materia_id)->inscripcion->alumno;
        $this->aviso = ($existente ? 'Se corrigió' : 'Se cargó').' la nota de '.$alumno->nombreCompleto().': '
            .self::TIPOS[$evaluacion->tipo].' '.self::formatoNota($evaluacion->nota).'.';

        $this->cerrar();
    }

    /** Materias de los alumnos de este curso que corresponden a esta asignación */
    private function deEstaMateria()
    {
        return InscripcionMateria::query()
            ->where('inscripcion_materias.materia_id', $this->cursoProfesor->materia_id)
            ->whereHas('inscripcion', fn ($q) => $q->where('curso_id', $this->cursoProfesor->curso_id));
    }

    /** 8.5 → «8,50» → «8,5»; 7 → «7» */
    public static function formatoNota(mixed $nota): string
    {
        return $nota === null || $nota === '' ? '—' : rtrim(rtrim(number_format((float) $nota, 2, ',', '.'), '0'), ',');
    }

    public function render()
    {
        $filas = $this->deEstaMateria()
            ->with('inscripcion.alumno')
            ->get()
            ->sortBy(fn (InscripcionMateria $im) => $im->inscripcion->alumno->nombreCompleto())
            ->values();

        $evaluaciones = Evaluacion::whereIn('inscripcion_materia_id', $filas->pluck('id'))
            ->orderBy('fecha')->orderBy('id')
            ->get()
            ->groupBy('inscripcion_materia_id');

        $elegida = $this->inscripcion_materia_id ? $filas->firstWhere('id', $this->inscripcion_materia_id) : null;

        return view('livewire.profesor.notas', [
            'filas'        => $filas,
            'evaluaciones' => $evaluaciones,
            'elegida'      => $elegida,
        ]);
    }
}
