<?php

namespace App\Livewire\Admin\Contenidos;

use App\Actions\Contenidos\GuardarSeccion;
use App\Models\Seccion;
use App\Models\User;
use App\Support\YouTube;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * HU10 (#75): textos fijos del sitio público (Inicio, Historia, Contacto, Servicios, Privacidad).
 * A la izquierda la lista; a la derecha la edición de la sección elegida.
 * Guarda con GuardarSeccion (#74), que también anota quién la actualizó.
 */
class Index extends Component
{
    use WithFileUploads;

    /** Orden y nombre con que se muestran las secciones */
    public const NOMBRES = [
        'inicio'     => 'Texto de la portada (Inicio)',
        'historia'   => 'Historia',
        'contacto'   => 'Contacto',
        'servicios'  => 'Servicios',
        'privacidad' => 'Política de privacidad',
    ];

    public ?int $seccionId = null;

    public ?string $aviso = null;

    // Los nombres coinciden con las claves de error de GuardarSeccion
    public string $titulo = '';
    public string $contenido = '';
    public string $video_url = '';
    public $imagen = null;
    public bool $quitar_imagen = false;
    public ?string $imagenActual = null;

    public function mount(): void
    {
        // Se abre con la primera sección elegida, como en el diseño (pantalla 07)
        $primera = $this->secciones()->first();
        if ($primera) {
            $this->elegir($primera->id);
        }
    }

    public function updatedImagen(): void
    {
        $this->quitar_imagen = false;
        $this->resetErrorBag('imagen');
    }

    public function elegir(int $id): void
    {
        $seccion = Seccion::findOrFail($id);

        $this->resetErrorBag();
        $this->reset(['imagen', 'quitar_imagen']);
        $this->seccionId    = $seccion->id;
        $this->titulo       = $seccion->titulo;
        $this->contenido    = $seccion->contenido;
        $this->video_url    = $seccion->video_url ?? '';
        $this->imagenActual = $seccion->imagen_ruta;
    }

    /** Cancelar: vuelve a lo que está guardado */
    public function cancelar(): void
    {
        if ($this->seccionId) {
            $this->elegir($this->seccionId);
        }
    }

    public function guardar(): void
    {
        $seccion = Seccion::findOrFail($this->seccionId);

        $seccion = GuardarSeccion::ejecutar($seccion, [
            'titulo'        => trim($this->titulo),
            'contenido'     => trim($this->contenido),
            'video_url'     => trim($this->video_url) !== '' ? trim($this->video_url) : null,
            'quitar_imagen' => $this->quitar_imagen,
        ], $this->imagen ?: null);

        $this->aviso = 'Se guardó «'.(self::NOMBRES[$seccion->clave] ?? $seccion->titulo).'». Ya se ve en el sitio.';
        $this->elegir($seccion->id);
    }

    private function secciones()
    {
        $orden = array_keys(self::NOMBRES);

        return Seccion::query()->get()
            ->sortBy(fn (Seccion $s) => ($i = array_search($s->clave, $orden, true)) === false ? 99 : $i)
            ->values();
    }

    public function render()
    {
        $secciones = $this->secciones();
        $editores = User::whereIn('id', $secciones->pluck('actualizado_por')->filter()->unique())->get()->keyBy('id');

        $vistaImagen = null;
        if ($this->imagen) {
            try {
                $vistaImagen = $this->imagen->temporaryUrl();
            } catch (\Throwable) {
                $vistaImagen = null;
            }
        } elseif ($this->imagenActual && ! $this->quitar_imagen) {
            $vistaImagen = Storage::url($this->imagenActual);
        }

        return view('livewire.admin.contenidos.index', [
            'secciones'   => $secciones,
            'editores'    => $editores,
            'elegida'     => $secciones->firstWhere('id', $this->seccionId),
            'vistaImagen' => $vistaImagen,
            'vistaVideo'  => YouTube::embed(trim($this->video_url) ?: null),
        ]);
    }
}
