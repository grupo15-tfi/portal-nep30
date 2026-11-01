<?php

namespace App\Livewire\Profesor;

use App\Actions\Asistencia\GuardarClase;
use App\Actions\Asistencia\TomarAsistencia;
use App\Models\Clase;
use App\Models\CursoProfesor;
use App\Models\Inscripcion;
use App\Queries\AsistenciaDelAlumno;
use Livewire\Component;

/**
 * HU13 (#94): el profesor toma asistencia en una de sus materias.
 * Clase de hoy con todos «presente» por defecto (solo se tocan las excepciones), ver y corregir
 * clases anteriores, marcar una clase suspendida (no cuenta para el porcentaje).
 * Guarda con GuardarClase y TomarAsistencia (#92); porcentajes con AsistenciaDelAlumno::delCurso (#93).
 */
class Asistencia extends Component
{
    public const ESTADOS = [
        'presente'    => 'Presente',
        'ausente'     => 'Ausente',
        'tarde'       => 'Tarde',
        'justificado' => 'Justificado',
    ];

    public CursoProfesor $cursoProfesor;

    public ?string $aviso = null;

    // ---------- Ventana de la clase: los nombres coinciden con las claves de error ----------
    public bool $ventanaAbierta = false;

    public ?int $claseId = null;

    public string $fecha = '';

    public string $tema = '';

    /** La clase no se dictó: se guarda con estado «suspendida» */
    public bool $suspendida = false;

    /** inscripcion_id => presente|ausente|tarde|justificado */
    public array $estados = [];

    public function mount(CursoProfesor $cursoProfesor): void
    {
        abort_unless($cursoProfesor->profesor_id === auth()->id(), 403);

        $this->cursoProfesor = $cursoProfesor->load(['materia', 'curso.planEstudio', 'curso.sede']);
    }

    /** «Tomar asistencia de hoy»: si la clase de hoy ya existe, la abre para corregir */
    public function hoy(): void
    {
        $existente = $this->clases()->whereDate('fecha', today())->first();

        if ($existente) {
            $this->abrir($existente->id);

            return;
        }

        $this->cerrar();
        $this->fecha = today()->format('Y-m-d');
        $this->estados = $this->alumnos()->mapWithKeys(fn ($i) => [$i->id => 'presente'])->all();
        $this->ventanaAbierta = true;
    }

    /** Ver o corregir una clase ya cargada */
    public function abrir(int $claseId): void
    {
        $clase = $this->clases()->with('asistencias')->findOrFail($claseId);

        $this->cerrar();
        $this->claseId = $clase->id;
        $this->fecha   = $clase->fecha->format('Y-m-d');
        $this->tema    = (string) $clase->tema;
        $this->suspendida = $clase->estado === 'suspendida';
        $tomadas       = $clase->asistencias->pluck('estado', 'inscripcion_id');
        // Un alumno que se sumó después de la clase aparece como presente hasta que se corrija
        $this->estados = $this->alumnos()->mapWithKeys(fn ($i) => [$i->id => $tomadas[$i->id] ?? 'presente'])->all();
        $this->ventanaAbierta = true;
    }

    public function cerrar(): void
    {
        $this->reset(['ventanaAbierta', 'claseId', 'fecha', 'tema', 'suspendida', 'estados']);
        $this->resetErrorBag();
    }

    /** Atajo: marcar a todos con el mismo estado */
    public function todos(string $estado): void
    {
        if (isset(self::ESTADOS[$estado])) {
            $this->estados = array_fill_keys(array_keys($this->estados), $estado);
        }
    }

    public function guardar(): void
    {
        $existente = $this->claseId ? $this->clases()->findOrFail($this->claseId) : null;

        $clase = GuardarClase::ejecutar($this->cursoProfesor, [
            'fecha'  => $this->fecha,
            'tema'   => trim($this->tema) === '' ? null : trim($this->tema),
            'estado' => $this->suspendida ? 'suspendida' : 'dictada',
        ], $existente);

        // Solo las inscripciones de este curso: lo que venga de más en el formulario se descarta
        $validos = $this->alumnos()->pluck('id')->all();
        $estados = array_intersect_key($this->estados, array_flip($validos));

        if ($clase->estado === 'dictada' && $estados !== []) {
            TomarAsistencia::ejecutar($clase, $estados);
        }

        $dia = $clase->fecha->format('d/m/Y');
        $aus = collect($estados)->filter(fn ($e) => $e === 'ausente')->count();
        $this->aviso = $clase->estado === 'suspendida'
            ? "La clase del {$dia} quedó como suspendida: no cuenta para el porcentaje."
            : ($existente ? "Se corrigió la asistencia del {$dia}." : "Se guardó la asistencia del {$dia}: "
                .($aus === 1 ? '1 ausente.' : "{$aus} ausentes."));

        $this->cerrar();
    }

    private function clases()
    {
        return Clase::where('curso_profesor_id', $this->cursoProfesor->id);
    }

    /** Alumnos activos del curso, por apellido */
    private function alumnos()
    {
        return Inscripcion::with('alumno')
            ->where('curso_id', $this->cursoProfesor->curso_id)
            ->where('estado', 'activa')
            ->get()
            ->sortBy(fn ($i) => $i->alumno->nombreCompleto())
            ->values();
    }

    public static function porcentaje(mixed $valor): string
    {
        return $valor === null ? '—' : rtrim(rtrim(number_format((float) $valor, 1, ',', '.'), '0'), ',')."\u{00A0}%";
    }

    public function render()
    {
        $clases = $this->clases()->withCount([
            'asistencias as ausentes' => fn ($q) => $q->where('estado', 'ausente'),
        ])->orderByDesc('fecha')->orderByDesc('id')->get();

        $resumen = AsistenciaDelAlumno::delCurso($this->cursoProfesor)
            ->sortBy(fn ($f) => data_get($f, 'apellido').', '.data_get($f, 'nombre'))
            ->values();

        return view('livewire.profesor.asistencia', [
            'clases'  => $clases,
            'resumen' => $resumen,
            'alumnos' => $this->ventanaAbierta ? $this->alumnos() : collect(),
        ]);
    }
}
