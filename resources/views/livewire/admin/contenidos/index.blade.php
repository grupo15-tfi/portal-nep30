<div>
    <h1>Contenidos del sitio</h1>
    <p class="text-texto-suave">Textos fijos del sitio público. Lo que guardes acá se ve enseguida en el sitio.</p>

    @if ($aviso)
        <p role="status" class="rounded border-l-4 border-exito bg-exito-claro px-4 py-3">{{ $aviso }}</p>
    @endif

    <div class="grid gap-6 md:grid-cols-[16rem_minmax(0,1fr)]">
        {{-- Lista de secciones --}}
        <nav aria-label="Secciones del sitio">
            <ul class="m-0 flex list-none flex-col gap-2 p-0">
                @foreach ($secciones as $seccion)
                    <li wire:key="seccion-{{ $seccion->id }}">
                        <button type="button" wire:click="elegir({{ $seccion->id }})"
                                @if ($seccion->id === $seccionId) aria-current="true" @endif
                                @class([
                                    'w-full text-left font-normal text-texto',
                                    'border-borde bg-white' => $seccion->id !== $seccionId,
                                    'border-2 border-primario bg-primario-claro' => $seccion->id === $seccionId,
                                ])>
                            <span class="block font-semibold">{{ \App\Livewire\Admin\Contenidos\Index::NOMBRES[$seccion->clave] ?? $seccion->titulo }}</span>
                            <span class="block text-sm text-texto-suave">
                                Actualizada {{ $seccion->updated_at?->format('d/m/Y') ?? '—' }}@if ($seccion->actualizado_por && isset($editores[$seccion->actualizado_por])) por {{ $editores[$seccion->actualizado_por]->nombreCompleto() }}@endif
                            </span>
                        </button>
                    </li>
                @endforeach
            </ul>
        </nav>

        {{-- Edición de la sección elegida --}}
        @if ($elegida)
            <section aria-labelledby="titulo-seccion" class="min-w-0">
                <h2 id="titulo-seccion" class="mt-0">{{ \App\Livewire\Admin\Contenidos\Index::NOMBRES[$elegida->clave] ?? $elegida->titulo }}</h2>

                <form wire:submit="guardar">
                    <div class="max-w-none">
                        <label for="titulo">Título *</label>
                        <input id="titulo" type="text" wire:model="titulo" maxlength="150" @error('titulo') aria-invalid="true" @enderror>
                        @error('titulo') <p role="alert">{{ $message }}</p> @enderror
                    </div>

                    <div class="max-w-none">
                        <label for="contenido">Contenido *</label>
                        <textarea id="contenido" wire:model="contenido" rows="9" @error('contenido') aria-invalid="true" @enderror></textarea>
                        @error('contenido') <p role="alert">{{ $message }}</p> @enderror
                    </div>

                    <div class="max-w-none">
                        <label for="imagen">Imagen (opcional)</label>
                        @if ($vistaImagen)
                            <img src="{{ $vistaImagen }}" alt="Vista previa de la imagen" class="mb-2 max-h-48 w-auto rounded border border-borde">
                        @endif
                        <input id="imagen" type="file" wire:model="imagen" accept="image/jpeg,image/png,image/webp" aria-describedby="ayuda-imagen"
                               @error('imagen') aria-invalid="true" @enderror>
                        <p wire:loading wire:target="imagen" class="text-sm mb-0">Subiendo la imagen…</p>
                        <p id="ayuda-imagen" class="text-sm text-texto-suave mb-0">JPG, PNG o WEBP, hasta 2 MB. Solo fotos con autorización (Ley 25.326).</p>
                        @error('imagen') <p role="alert">{{ $message }}</p> @enderror
                        @if ($imagenActual && ! $imagen)
                            <label class="mt-2 font-normal"><input type="checkbox" wire:model.live="quitar_imagen"> Quitar la imagen actual</label>
                        @endif
                    </div>

                    <div class="max-w-none">
                        <label for="video_url">Video de YouTube (opcional)</label>
                        <input id="video_url" type="url" wire:model.blur="video_url" maxlength="255" placeholder="https://www.youtube.com/watch?v=…"
                               aria-describedby="ayuda-video" @error('video_url') aria-invalid="true" @enderror>
                        <p id="ayuda-video" class="text-sm text-texto-suave mb-0">Pegá el enlace del video (youtube.com o youtu.be).</p>
                        @error('video_url') <p role="alert">{{ $message }}</p> @enderror
                        @if ($vistaVideo)
                            <iframe src="{{ $vistaVideo }}" title="Vista previa del video" loading="lazy"
                                    class="mt-2 aspect-video w-full rounded border border-borde"
                                    allow="encrypted-media; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
                        @endif
                    </div>

                    <div class="flex flex-wrap gap-2 max-w-none">
                        <button type="submit" wire:loading.attr="disabled" wire:target="guardar, imagen">
                            <span wire:loading.remove wire:target="guardar">Guardar</span>
                            <span wire:loading wire:target="guardar">Guardando…</span>
                        </button>
                        <button type="button" wire:click="cancelar">Cancelar</button>
                    </div>
                </form>
            </section>
        @endif
    </div>
</div>
