<?php

namespace App\Livewire\Perfil;

use App\Models\Telefono;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * HU06 (#16): el usuario edita su nombre, apellido y teléfonos.
 * El DNI y el correo se muestran pero no se editan (los cambia el Administrador).
 */
class Datos extends Component
{
    public string $nombre = '';
    public string $apellido = '';

    /** @var array<int, array{numero: string, tipo: string, contacto_nombre: string, es_principal: bool}> */
    public array $telefonos = [];

    public function mount(): void
    {
        $usuario = Auth::user();
        $this->nombre = $usuario->nombre;
        $this->apellido = $usuario->apellido;
        $this->telefonos = $usuario->telefonos->map(fn (Telefono $t) => [
            'numero'          => $t->numero,
            'tipo'            => $t->tipo,
            'contacto_nombre' => $t->contacto_nombre ?? '',
            'es_principal'    => $t->es_principal,
        ])->all();
    }

    /** Reglas alineadas con los CHECK de las tablas users y telefonos */
    protected function rules(): array
    {
        return [
            'nombre'                        => ['required', 'string', 'max:100'],
            'apellido'                      => ['required', 'string', 'max:100'],
            'telefonos'                     => ['array', 'max:5'],
            'telefonos.*.numero'            => ['required', 'string', 'regex:'.Telefono::PATRON_NUMERO],
            'telefonos.*.tipo'              => ['required', Rule::in(Telefono::TIPOS)],
            'telefonos.*.contacto_nombre'   => ['nullable', 'string', 'max:120', 'required_if:telefonos.*.tipo,emergencia'],
            'telefonos.*.es_principal'      => ['boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'telefonos.*.numero.required'          => 'Escribí el número.',
            'telefonos.*.numero.regex'             => 'Usá solo números, espacios, +, paréntesis o guiones (de 6 a 30).',
            'telefonos.*.contacto_nombre.required_if' => 'Para un teléfono de emergencia escribí el nombre del contacto.',
        ];
    }

    public function agregarTelefono(): void
    {
        $this->telefonos[] = ['numero' => '', 'tipo' => 'celular', 'contacto_nombre' => '', 'es_principal' => $this->telefonos === []];
    }

    public function quitarTelefono(int $indice): void
    {
        unset($this->telefonos[$indice]);
        $this->telefonos = array_values($this->telefonos);
        $this->resetValidation();
    }

    public function guardar(): void
    {
        $datos = $this->validate();
        $usuario = Auth::user();

        DB::transaction(function () use ($usuario, $datos) {
            $usuario->update(['nombre' => $datos['nombre'], 'apellido' => $datos['apellido']]);

            // Se reemplaza la lista completa: es corta y así el orden queda como lo dejó el usuario
            $usuario->telefonos()->delete();
            $hayPrincipal = false;
            foreach ($datos['telefonos'] ?? [] as $t) {
                $principal = ! $hayPrincipal && ($t['es_principal'] ?? false);
                $hayPrincipal = $hayPrincipal || $principal;
                $usuario->telefonos()->create([
                    'numero'          => trim($t['numero']),
                    'tipo'            => $t['tipo'],
                    'contacto_nombre' => ($t['contacto_nombre'] ?? '') !== '' ? $t['contacto_nombre'] : null,
                    'es_principal'    => $principal,
                ]);
            }
        });

        session()->flash('perfil-ok', 'Tus datos se guardaron.');
        $this->dispatch('perfil-actualizado');
    }

    public function render()
    {
        return view('livewire.perfil.datos', [
            'usuario' => Auth::user(),
            'tipos'   => Telefono::TIPOS,
        ]);
    }
}
