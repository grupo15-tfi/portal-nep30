<?php

namespace App\Livewire\Admin\Calendario;

use App\Actions\Contenidos\EliminarEvento;
use App\Actions\Contenidos\GuardarEvento;
use App\Models\Evento;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * HU10 (#75): eventos institucionales del calendario (curso_id null), mes por mes.
 * Los públicos se ven en el calendario del sitio (HU02). Las evaluaciones y entregas
 * de cada curso las cargan los profesores y nunca son públicas.
 * Guarda y borra con GuardarEvento / EliminarEvento (#74).
 */
class Index extends Component
{
    /** Tipos que puede cargar el administrador (los de curso no van acá) */
    public const TIPOS = [
        'institucional' => 'Institucional',
        'feriado'       => 'Feriado',
        'otro'          => 'Otro',
    ];

    /** Mes que se ve, AAAA-MM */
    #[Url]
    public string $mes = '';

    /** '' = todos */
    public string $filtroTipo = '';

    public ?string $aviso = null;

    // ---------- Formulario (nombres = claves de error de GuardarEvento) ----------
    public ?int $eventoId = null;

    public string $titulo = '';
    public string $tipo = 'institucional';
    public string $fecha_inicio = '';
    public string $fecha_fin = '';
    public string $descripcion = '';
    public bool $publico = true;

    public function mount(): void
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->mes)) {
            $this->mes = now()->format('Y-m');
        }
    }

    private function inicioDelMes(): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $this->mes.'-01')->startOfDay();
    }

    public function mesAnterior(): void
    {
        $this->mes = $this->inicioDelMes()->subMonth()->format('Y-m');
    }

    public function mesSiguiente(): void
    {
        $this->mes = $this->inicioDelMes()->addMonth()->format('Y-m');
    }

    public function editar(int $id): void
    {
        $evento = Evento::whereNull('curso_id')->findOrFail($id);

        $this->resetErrorBag();
        $this->eventoId     = $evento->id;
        $this->titulo       = $evento->titulo;
        $this->tipo         = $evento->tipo;
        $this->fecha_inicio = $evento->fecha_inicio->format('Y-m-d');
        $this->fecha_fin    = $evento->fecha_fin?->format('Y-m-d') ?? '';
        $this->descripcion  = $evento->descripcion ?? '';
        $this->publico      = $evento->publico;
    }

    /** Vacía el formulario y vuelve a «Nuevo evento» */
    public function limpiar(): void
    {
        $this->reset(['eventoId', 'titulo', 'tipo', 'fecha_inicio', 'fecha_fin', 'descripcion', 'publico']);
        $this->resetErrorBag();
    }

    public function guardar(): void
    {
        $existente = $this->eventoId ? Evento::whereNull('curso_id')->findOrFail($this->eventoId) : null;

        $evento = GuardarEvento::ejecutar([
            'titulo'       => trim($this->titulo),
            'descripcion'  => trim($this->descripcion) !== '' ? trim($this->descripcion) : null,
            'tipo'         => $this->tipo,
            'fecha_inicio' => $this->fecha_inicio,
            'fecha_fin'    => $this->fecha_fin !== '' ? $this->fecha_fin : null,
            'publico'      => $this->publico,
        ], $existente);

        $this->aviso = $existente ? "Se guardaron los cambios de «{$evento->titulo}»." : "Se agregó «{$evento->titulo}» al calendario.";
        if ($evento->publico) {
            $this->aviso .= ' Se ve en el calendario del sitio.';
        }

        // Ir al mes del evento para verlo en la lista
        $this->mes = $evento->fecha_inicio->format('Y-m');
        $this->limpiar();
    }

    public function eliminar(int $id): void
    {
        $evento = Evento::whereNull('curso_id')->findOrFail($id);
        $titulo = $evento->titulo;

        EliminarEvento::ejecutar($evento);

        if ($this->eventoId === $id) {
            $this->limpiar();
        }
        $this->aviso = "Se eliminó «{$titulo}» del calendario.";
    }

    public function render()
    {
        $inicio = $this->inicioDelMes();

        $eventos = Evento::query()
            ->whereNull('curso_id')
            ->entre($inicio, $inicio->copy()->endOfMonth())
            ->when(array_key_exists($this->filtroTipo, self::TIPOS), fn ($q) => $q->where('tipo', $this->filtroTipo))
            ->orderBy('fecha_inicio')
            ->orderBy('id')
            ->get();

        return view('livewire.admin.calendario.index', [
            'eventos'     => $eventos,
            'nombreMes'   => ucfirst($inicio->locale('es')->translatedFormat('F Y')),
        ]);
    }
}
