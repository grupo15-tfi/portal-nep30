<div>
    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
        <h1>Noticias</h1>
        <button type="button" wire:click="nueva" class="bg-primario text-white hover:bg-primario/90">+ Nueva noticia</button>
    </div>

    @if ($aviso)
        <p role="status" class="rounded border-l-4 border-exito bg-exito-claro px-4 py-3">{{ $aviso }}</p>
    @endif
    @if ($errorLista)
        <p role="alert" class="rounded border-l-4 border-error bg-error-claro px-4 py-3">⚠ {{ $errorLista }}</p>
    @endif

    <div class="mb-4 flex flex-wrap items-end gap-4">
        <div class="mb-0 w-full max-w-md">
            <label for="buscar">Buscar</label>
            <input id="buscar" type="search" wire:model.live.debounce.300ms="buscar" placeholder="Título de la noticia">
        </div>
        <div class="mb-0 w-full max-w-48">
            <label for="estado">Estado</label>
            <select id="estado" wire:model.live="estado">
                <option value="">Todas</option>
                <option value="publicada">Publicadas</option>
                <option value="borrador">Borradores</option>
            </select>
        </div>
    </div>

    {{-- En el celular cada fila se ve como tarjeta (tabla-apilada) --}}
    <div class="overflow-x-auto">
        <table class="tabla-apilada">
            <thead>
                <tr>
                    <th>Noticia</th>
                    <th>Estado</th>
                    <th>Publicada</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($noticias as $noticia)
                    <tr wire:key="noticia-{{ $noticia->id }}">
                        <td data-etiqueta="Noticia">
                            <div class="min-w-0">
                                <span class="block font-semibold">{{ $noticia->titulo }}</span>
                                <span class="block text-sm text-texto-suave">
                                    {{ collect([$noticia->imagen_ruta ? 'Con imagen' : null, $noticia->video_url ? 'Con video de YouTube' : null])->filter()->join(' · ') ?: 'Sin imagen ni video' }}
                                </span>
                            </div>
                        </td>
                        <td data-etiqueta="Estado">
                            <span @class([
                                'inline-block rounded-full px-2.5 py-0.5 text-sm font-semibold whitespace-nowrap',
                                'bg-exito-claro text-exito' => $noticia->estado === 'publicada',
                                'bg-borde/40 text-texto' => $noticia->estado !== 'publicada',
                            ])>{{ $noticia->estado === 'publicada' ? 'Publicada' : 'Borrador' }}</span>
                        </td>
                        <td data-etiqueta="Publicada">
                            @if ($noticia->estado === 'publicada' && $noticia->publicada_en)
                                <time datetime="{{ $noticia->publicada_en->toIso8601String() }}">{{ $noticia->publicada_en->format('d/m/Y H:i') }}</time>
                            @else
                                —
                            @endif
                        </td>
                        <td data-etiqueta="Acciones">
                            <div class="flex flex-wrap items-center gap-x-1">
                                <button type="button" wire:click="editar({{ $noticia->id }})">Editar</button>
                                <button type="button" wire:click="cambiarEstado({{ $noticia->id }})">
                                    {{ $noticia->estado === 'publicada' ? 'Despublicar' : 'Publicar' }}
                                </button>
                                <button type="button" wire:click="eliminar({{ $noticia->id }})"
                                        wire:confirm="¿Seguro que querés eliminar «{{ $noticia->titulo }}»? No se puede deshacer."
                                        class="border-error text-error hover:bg-error-claro">Eliminar</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4">No hay noticias{{ $buscar !== '' || $estado !== '' ? ' que coincidan con la búsqueda' : ' cargadas todavía' }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="text-sm text-texto-suave">10 por página, de la más reciente a la más antigua.</p>

    @if ($noticias->hasPages())
        <nav aria-label="Páginas de noticias" class="flex items-center justify-between gap-4">
            <button type="button" wire:click="previousPage" @disabled($noticias->onFirstPage())>« Anterior</button>
            <span>Página {{ $noticias->currentPage() }} de {{ $noticias->lastPage() }}</span>
            <button type="button" wire:click="nextPage" @disabled(! $noticias->hasMorePages())>Siguiente »</button>
        </nav>
    @endif

    {{-- Nueva y editar en la ventana emergente (#73) --}}
    @if ($ventanaAbierta)
        <x-ventana id="titulo-noticia" :titulo="$noticiaId ? 'Editar noticia' : 'Nueva noticia'" cerrar="cerrar" ancho="max-w-2xl">
            <form wire:submit="guardar(true)">
                <div class="max-w-none">
                    <label for="titulo">Título *</label>
                    <input id="titulo" type="text" wire:model="titulo" maxlength="200" aria-describedby="ayuda-titulo"
                           @error('titulo') aria-invalid="true" @enderror>
                    <p id="ayuda-titulo" class="text-sm text-texto-suave mb-0">Hasta 200 caracteres.</p>
                    @error('titulo') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div class="max-w-none">
                    <label for="resumen">Resumen (opcional)</label>
                    <input id="resumen" type="text" wire:model="resumen" maxlength="400" aria-describedby="ayuda-resumen"
                           @error('resumen') aria-invalid="true" @enderror>
                    <p id="ayuda-resumen" class="text-sm text-texto-suave mb-0">Hasta 400 caracteres. Se ve en la tarjeta de la noticia.</p>
                    @error('resumen') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div class="max-w-none">
                    <label for="cuerpo">Texto de la noticia *</label>
                    <textarea id="cuerpo" wire:model="cuerpo" rows="7" @error('cuerpo') aria-invalid="true" @enderror></textarea>
                    @error('cuerpo') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div class="max-w-none">
                    <label for="imagen">Imagen (opcional)</label>
                    @if ($vistaImagen)
                        <img src="{{ $vistaImagen }}" alt="Vista previa de la imagen" class="mb-2 max-h-48 w-auto rounded border border-borde">
                    @endif
                    <input id="imagen" type="file" wire:model="imagen" accept="image/jpeg,image/png,image/webp" aria-describedby="ayuda-imagen"
                           @error('imagen') aria-invalid="true" @enderror>
                    <p wire:loading wire:target="imagen" class="text-sm mb-0">Subiendo la imagen…</p>
                    <p id="ayuda-imagen" class="text-sm text-texto-suave mb-0">JPG, PNG o WEBP, hasta 2 MB. Solo fotos con autorización de las personas que aparecen (Ley 25.326).</p>
                    @error('imagen') <p role="alert">{{ $message }}</p> @enderror
                    @if ($imagenActual && ! $imagen)
                        <label class="mt-2 font-normal"><input type="checkbox" wire:model.live="quitar_imagen"> Quitar la imagen actual</label>
                    @endif
                </div>

                <div class="max-w-none">
                    <label for="video_url">Video de YouTube (opcional)</label>
                    <input id="video_url" type="url" wire:model.blur="video_url" maxlength="255" placeholder="https://www.youtube.com/watch?v=…"
                           aria-describedby="ayuda-video" @error('video_url') aria-invalid="true" @enderror>
                    <p id="ayuda-video" class="text-sm text-texto-suave mb-0">Pegá el enlace del video (youtube.com o youtu.be). Queda alojado en YouTube.</p>
                    @error('video_url') <p role="alert">{{ $message }}</p> @enderror
                    @if ($vistaVideo)
                        <iframe src="{{ $vistaVideo }}" title="Vista previa del video" loading="lazy"
                                class="mt-2 aspect-video w-full rounded border border-borde"
                                allow="encrypted-media; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
                    @endif
                </div>

                {{-- #96: consentimiento de las personas (acta del Sprint 2, punto 4.2; Ley 25.326) --}}
                <div class="max-w-none rounded border border-borde bg-primario-claro/40 p-3">
                    <label class="flex items-start gap-2 font-normal">
                        <input type="checkbox" wire:model="consentimiento" class="mt-1" aria-describedby="ayuda-consentimiento"
                               @error('consentimiento') aria-invalid="true" @enderror>
                        <span>Las personas que aparecen en la imagen o el video, o que se nombran, dieron su consentimiento.</span>
                    </label>
                    <p id="ayuda-consentimiento" class="text-sm text-texto-suave mb-0">Obligatorio para publicar con imagen o video. Si no hay consentimiento, usá fotos sin personas identificables. Un borrador se puede guardar sin marcarlo.</p>
                    @error('consentimiento') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-wrap gap-2 max-w-none">
                    <button type="submit" wire:loading.attr="disabled" wire:target="guardar, imagen">
                        {{ $publicada ? 'Guardar cambios' : 'Publicar' }}
                    </button>
                    <button type="button" wire:click="guardar(false)" wire:loading.attr="disabled" wire:target="guardar, imagen">
                        {{ $publicada ? 'Pasar a borrador' : 'Guardar borrador' }}
                    </button>
                    <button type="button" wire:click="cerrar">Cancelar</button>
                </div>
                <p class="text-sm text-texto-suave mb-0">Un borrador no se ve en el sitio. Al publicar, la noticia aparece en el Inicio y en Noticias.</p>
            </form>
        </x-ventana>
    @endif
</div>
