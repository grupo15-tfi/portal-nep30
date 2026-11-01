<?php

namespace App\Livewire\Admin\Noticias;

use App\Actions\Contenidos\CambiarEstadoNoticia;
use App\Actions\Contenidos\EliminarNoticia;
use App\Actions\Contenidos\GuardarNoticia;
use App\Models\Noticia;
use App\Support\YouTube;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * HU10 (#75): listado de noticias con buscador y filtro por estado.
 * Nueva y editar se abren en la ventana emergente (#73). No valida ni guarda por su cuenta:
 * llama a las acciones de #74, y sus errores (mismas claves) quedan debajo de cada campo.
 */
class Index extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $buscar = '';

    /** '' = todas, 'borrador' o 'publicada' */
    public string $estado = '';

    public ?string $aviso = null;

    /** Error de una acción de la lista (por ejemplo, publicar sin consentimiento, #96) */
    public ?string $errorLista = null;

    // ---------- Ventana ----------
    public bool $ventanaAbierta = false;

    public ?int $noticiaId = null;

    // Los nombres coinciden con las claves de error de GuardarNoticia
    public string $titulo = '';
    public string $resumen = '';
    public string $cuerpo = '';
    public string $video_url = '';
    public $imagen = null;          // archivo nuevo (WithFileUploads)
    public bool $quitar_imagen = false;
    public ?string $imagenActual = null;

    /** #96: las personas de la imagen o el video, o que se nombran, dieron su consentimiento */
    public bool $consentimiento = false;

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function updatedEstado(): void
    {
        $this->resetPage();
    }

    /** Al elegir una imagen nueva, ya no se quita la anterior: se reemplaza */
    public function updatedImagen(): void
    {
        $this->quitar_imagen = false;
        $this->resetErrorBag('imagen');
    }

    public function nueva(): void
    {
        $this->cerrar();
        $this->ventanaAbierta = true;
    }

    public function editar(int $id): void
    {
        $noticia = Noticia::findOrFail($id);

        $this->cerrar();
        $this->noticiaId    = $noticia->id;
        $this->titulo       = $noticia->titulo;
        $this->resumen      = $noticia->resumen ?? '';
        $this->cuerpo       = $noticia->cuerpo;
        $this->video_url    = $noticia->video_url ?? '';
        $this->imagenActual = $noticia->imagen_ruta;
        $this->consentimiento = (bool) $noticia->consentimiento_en;
        $this->ventanaAbierta = true;
    }

    /** Cierra la ventana y vacía el formulario (Cancelar, Esc o después de guardar) */
    public function cerrar(): void
    {
        $this->reset(['ventanaAbierta', 'noticiaId', 'titulo', 'resumen', 'cuerpo', 'video_url', 'imagen', 'quitar_imagen', 'imagenActual', 'consentimiento']);
        $this->resetErrorBag();
    }

    /** «Guardar borrador» ($publicar = false) o «Publicar» ($publicar = true) */
    public function guardar(bool $publicar): void
    {
        $existente = $this->noticiaId ? Noticia::findOrFail($this->noticiaId) : null;

        $noticia = GuardarNoticia::ejecutar([
            'titulo'        => trim($this->titulo),
            'resumen'       => trim($this->resumen) !== '' ? trim($this->resumen) : null,
            'cuerpo'        => trim($this->cuerpo),
            'video_url'     => trim($this->video_url) !== '' ? trim($this->video_url) : null,
            'publicar'      => $publicar,
            'quitar_imagen' => $this->quitar_imagen,
            'consentimiento' => $this->consentimiento,
        ], $this->imagen ?: null, $existente);

        $this->aviso = $noticia->estado === 'publicada'
            ? "Noticia publicada: «{$noticia->titulo}» ya se ve en Inicio y en Noticias."
            : "Borrador guardado: «{$noticia->titulo}». No se ve en el sitio hasta que la publiques.";

        $this->cerrar();
    }

    /** Publicar o despublicar desde la lista */
    public function cambiarEstado(int $id): void
    {
        $noticia = Noticia::findOrFail($id);
        $this->reset('aviso', 'errorLista');

        try {
            $noticia = CambiarEstadoNoticia::ejecutar($noticia, $noticia->estado !== 'publicada');
        } catch (ValidationException $e) {
            // #96: con imagen o video y sin consentimiento no se publica; se avisa arriba de la lista
            $this->errorLista = "«{$noticia->titulo}»: ".collect($e->errors())->flatten()->first();

            return;
        }

        $this->aviso = $noticia->estado === 'publicada'
            ? "Noticia publicada: «{$noticia->titulo}» ya se ve en Inicio y en Noticias."
            : "«{$noticia->titulo}» volvió a borrador: ya no se ve en el sitio.";
    }

    public function eliminar(int $id): void
    {
        $noticia = Noticia::findOrFail($id);
        $titulo = $noticia->titulo;

        EliminarNoticia::ejecutar($noticia);

        if ($this->noticiaId === $id) {
            $this->cerrar();
        }
        $this->aviso = "Se eliminó la noticia «{$titulo}».";
    }

    /** Vista previa de la imagen: la recién elegida o la que ya tiene la noticia */
    public function vistaPreviaImagen(): ?string
    {
        if ($this->imagen) {
            try {
                return $this->imagen->temporaryUrl();
            } catch (\Throwable) {
                return null;   // tipo de archivo sin vista previa: el error lo da la acción al guardar
            }
        }

        return ($this->imagenActual && ! $this->quitar_imagen) ? Storage::url($this->imagenActual) : null;
    }

    public function render()
    {
        $noticias = Noticia::query()
            ->when(trim($this->buscar) !== '', fn ($q) => $q->where('titulo', 'like', '%'.trim($this->buscar).'%'))
            ->when(in_array($this->estado, ['borrador', 'publicada'], true), fn ($q) => $q->where('estado', $this->estado))
            ->orderByRaw('COALESCE(publicada_en, updated_at) DESC')
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.admin.noticias.index', [
            'noticias'      => $noticias,
            'vistaImagen'   => $this->ventanaAbierta ? $this->vistaPreviaImagen() : null,
            'vistaVideo'    => $this->ventanaAbierta ? YouTube::embed(trim($this->video_url) ?: null) : null,
            // Editando una publicada, los botones son «Guardar cambios» y «Pasar a borrador»
            'publicada'     => $this->noticiaId && Noticia::whereKey($this->noticiaId)->where('estado', 'publicada')->exists(),
        ]);
    }
}
