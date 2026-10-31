<?php

namespace App\Livewire\Admin\Preinscripciones;

use App\Models\Curso;
use App\Models\Preinscripcion;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * HU09 (#87): el administrador revisa las preinscripciones (HU04) y la lista de espera.
 * Las acciones (aprobar, rechazar, alta y aviso) son de Benitez (#86); contrato en #83.
 * Privacidad: el listado no muestra domicilio ni correo; solo el detalle.
 */
class Index extends Component
{
    use WithPagination;

    /** Filtros. '' = todos los estados. Arranca en «pendiente»: es lo que hay que revisar. */
    public string $estado = 'pendiente';

    public ?int $cursoId = null;

    public string $buscar = '';

    /** Ventanas emergentes */
    public ?int $verId = null;

    public ?int $rechazarId = null;

    public string $motivo = '';

    public ?string $aviso = null;

    public const ESTADOS = [
        'pendiente' => 'Pendiente',
        'en_espera' => 'En espera',
        'aprobada'  => 'Aprobada',
        'rechazada' => 'Rechazada',
    ];

    public const NIVELES = [
        'sin_escolaridad'       => 'Sin escolaridad',
        'primaria_incompleta'   => 'Primaria incompleta',
        'primaria_completa'     => 'Primaria completa',
        'secundaria_incompleta' => 'Secundaria incompleta',
        'secundaria_completa'   => 'Secundaria completa',
        'superior'              => 'Superior',
    ];

    /** Al cambiar un filtro, volver a la primera página */
    public function updated(string $propiedad): void
    {
        if (in_array($propiedad, ['estado', 'cursoId', 'buscar'], true)) {
            $this->resetPage();
        }
    }

    public function ver(int $id): void
    {
        $this->verId = $id;
    }

    public function abrirRechazo(int $id): void
    {
        $this->verId = null;
        $this->rechazarId = $id;
        $this->motivo = '';
        $this->resetErrorBag();
    }

    public function cerrar(): void
    {
        $this->reset('verId', 'rechazarId', 'motivo');
        $this->resetErrorBag();
    }

    // FASE 2: acá van aprobar(), rechazar(), darDeAlta() y registrarAviso().

    public function render()
    {
        $texto = trim($this->buscar);

        $preinscripciones = Preinscripcion::with(['curso.sede', 'curso.planEstudio'])
            ->when($this->estado !== '', fn ($q) => $q->where('estado', $this->estado))
            ->when($this->cursoId, fn ($q) => $q->where('curso_id', $this->cursoId))
            ->when($texto !== '', fn ($q) => $q->where(function ($w) use ($texto) {
                $w->where('dni', 'like', "%{$texto}%")
                  ->orWhere('apellido', 'like', "%{$texto}%")
                  ->orWhere('nombre', 'like', "%{$texto}%");
            }))
            ->orderBy('created_at')   // por orden de llegada
            ->orderBy('id')
            ->paginate(15);

        // Lista de espera: solo cuando hay un curso elegido. La vista SQL ya ordena por llegada.
        $espera = $this->cursoId
            ? DB::table('v_lista_espera')->where('curso_id', $this->cursoId)->orderBy('posicion')->get()
            : collect();

        $cursoElegido = $this->cursoId ? Curso::with(['sede', 'planEstudio'])->find($this->cursoId) : null;

        return view('livewire.admin.preinscripciones.index', [
            'preinscripciones' => $preinscripciones,
            'cursos'           => Curso::with(['sede', 'planEstudio'])->get()
                ->mapWithKeys(fn (Curso $c) => [$c->id => $this->nombreCurso($c)])
                ->sort(),
            'espera'           => $espera,
            'cursoElegido'     => $cursoElegido,
            'disponibles'      => $cursoElegido?->cupo_disponible,
            'detalle'          => $this->verId ? Preinscripcion::with(['curso.sede', 'curso.planEstudio'])->find($this->verId) : null,
            'aRechazar'        => $this->rechazarId ? Preinscripcion::find($this->rechazarId) : null,
        ]);
    }

    public function nombreCurso(Curso $curso): string
    {
        return "{$curso->planEstudio->nombre} · {$curso->sede->nombre} · turno {$curso->turno}";
    }
}