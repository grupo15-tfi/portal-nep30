<?php

namespace App\Livewire\Admin\Sedes;

use App\Models\Sede;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * HU07 (#9): ABM de sedes con coordenadas (RF14).
 * La baja es desactivar (activo = false): nunca se borra, porque hay cursos,
 * inscripciones e historial que dependen de la sede.
 */
class Index extends Component
{
    // null = alta; con valor = edición de esa sede
    public ?int $sedeId = null;

    public string $nombre = '';
    public string $tipo = 'sede';
    public string $direccion = '';
    public string $localidad = '';
    public string $telefono = '';
    public string $email = '';
    public string $latitud = '';
    public string $longitud = '';
    public string $observaciones = '';

    /** Reglas alineadas con los CHECK de la tabla sedes */
    protected function rules(): array
    {
        return [
            'nombre'        => ['required', 'string', 'max:120', Rule::unique('sedes', 'nombre')->ignore($this->sedeId)],
            'tipo'          => ['required', Rule::in(['central', 'sede', 'anexo'])],
            'localidad'     => ['required', 'string', 'max:100'],
            'direccion'     => ['nullable', 'string', 'max:200'],
            'telefono'      => ['nullable', 'string', 'max:30'],
            'email'         => ['nullable', 'email', 'max:150'],
            'latitud'       => ['required', 'numeric', 'between:-90,90'],
            'longitud'      => ['required', 'numeric', 'between:-180,180'],
            'observaciones' => ['nullable', 'string', 'max:300'],
        ];
    }

    public function guardar(): void
    {
        $datos = $this->validate();

        // Los campos opcionales vacíos se guardan como NULL, no como texto vacío
        foreach (['direccion', 'telefono', 'email', 'observaciones'] as $campo) {
            $datos[$campo] = $datos[$campo] ?: null;
        }

        if ($this->sedeId) {
            Sede::findOrFail($this->sedeId)->update($datos);
            session()->flash('ok', 'Sede actualizada.');
        } else {
            Sede::create($datos);
            session()->flash('ok', 'Sede creada.');
        }

        $this->limpiar();
    }

    public function editar(int $id): void
    {
        $sede = Sede::findOrFail($id);

        $this->sedeId        = $sede->id;
        $this->nombre        = $sede->nombre;
        $this->tipo          = $sede->tipo;
        $this->direccion     = $sede->direccion ?? '';
        $this->localidad     = $sede->localidad;
        $this->telefono      = $sede->telefono ?? '';
        $this->email         = $sede->email ?? '';
        $this->latitud       = (string) $sede->latitud;
        $this->longitud      = (string) $sede->longitud;
        $this->observaciones = $sede->observaciones ?? '';

        $this->resetValidation();
    }

    /** Activa o desactiva la sede. Una sede inactiva desaparece del mapa y de la oferta pública. */
    public function cambiarEstado(int $id): void
    {
        $sede = Sede::findOrFail($id);
        $sede->activo = ! $sede->activo;
        $sede->save();

        session()->flash('ok', $sede->activo ? 'Sede activada.' : 'Sede desactivada.');
    }

    public function limpiar(): void
    {
        $this->reset(['sedeId', 'nombre', 'tipo', 'direccion', 'localidad', 'telefono', 'email', 'latitud', 'longitud', 'observaciones']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.admin.sedes.index', [
            'sedes' => Sede::orderBy('nombre')->get(),
        ]);
    }
}