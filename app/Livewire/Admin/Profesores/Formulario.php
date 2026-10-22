<?php

namespace App\Livewire\Admin\Profesores;

use App\Actions\Profesores\GuardarProfesor;
use App\Models\Sede;
use App\Models\User;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * HU08 (#14): modal de alta y edición de profesores.
 * Valida y guarda con GuardarProfesor (#13). Las propiedades se llaman igual que las claves
 * de error del contrato (dni, apellido, nombre, email, sede_id), así @error('campo') las muestra solas.
 * Al CREAR se envía el correo «Creá tu contraseña» (#15); al editar, no.
 */
class Formulario extends Component
{
    public bool $abierto = false;

    public ?int $profesorId = null;

    public string $dni = '';
    public string $apellido = '';
    public string $nombre = '';
    public string $email = '';
    public string $sede_id = '';

    #[On('abrir-formulario')]
    public function abrir(?int $id = null): void
    {
        $this->resetErrorBag();
        $this->reset(['profesorId', 'dni', 'apellido', 'nombre', 'email', 'sede_id']);

        if ($id) {
            $profesor = User::profesores()->findOrFail($id);
            $this->profesorId = $profesor->id;
            $this->dni        = $profesor->dni;
            $this->apellido   = $profesor->apellido;
            $this->nombre     = $profesor->nombre;
            $this->email      = $profesor->email;
            $this->sede_id    = (string) ($profesor->sede_id ?? '');
        }

        $this->abierto = true;
    }

    public function cerrar(): void
    {
        $this->abierto = false;
    }

    public function guardar(): void
    {
        $existente = $this->profesorId ? User::profesores()->findOrFail($this->profesorId) : null;

        // Si los datos no son válidos, GuardarProfesor lanza ValidationException
        // y Livewire muestra cada error debajo de su campo.
        $profesor = GuardarProfesor::ejecutar([
            'dni'      => trim($this->dni),
            'apellido' => trim($this->apellido),
            'nombre'   => trim($this->nombre),
            'email'    => trim($this->email),
            'sede_id'  => $this->sede_id !== '' ? (int) $this->sede_id : null,
        ], $existente);

        if ($existente) {
            $mensaje = "Se guardaron los cambios de {$profesor->nombreCompleto()}.";
        } else {
            $profesor->enviarEnlaceCrearContrasena();   // #15: correo «Creá tu contraseña»
            $mensaje = "Se dio de alta a {$profesor->nombreCompleto()}. Le enviamos un correo a {$profesor->email} para que cree su contraseña.";
        }

        $this->abierto = false;
        $this->dispatch('profesor-guardado', mensaje: $mensaje);
    }

    public function render()
    {
        return view('livewire.admin.profesores.formulario', [
            'sedes' => Sede::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }
}
