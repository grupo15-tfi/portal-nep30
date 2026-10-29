<div>
    <h1>Calendario académico</h1>

    @if ($aviso)
        <p role="status" class="rounded border-l-4 border-exito bg-exito-claro px-4 py-3">{{ $aviso }}</p>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start">
        {{-- Eventos del mes --}}
        <section aria-labelledby="titulo-mes" class="min-w-0">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="mesAnterior" aria-label="Mes anterior">‹</button>
                    <h2 id="titulo-mes" class="m-0" aria-live="polite">{{ $nombreMes }}</h2>
                    <button type="button" wire:click="mesSiguiente" aria-label="Mes siguiente">›</button>
                </div>
                <div class="mb-0 w-full max-w-48">
                    <label for="filtroTipo">Tipo</label>
                    <select id="filtroTipo" wire:model.live="filtroTipo">
                        <option value="">Todos</option>
                        @foreach (\App\Livewire\Admin\Calendario\Index::tipos() as $valor => $nombre)
                            <option value="{{ $valor }}">{{ $nombre }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <ul class="m-0 list-none p-0">
                @forelse ($eventos as $evento)
                    <li wire:key="evento-{{ $evento->id }}" class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-borde py-3">
                        <span class="w-24 shrink-0 font-semibold">
                            <time datetime="{{ $evento->fecha_inicio->toDateString() }}">{{ $evento->fecha_inicio->format('d/m') }}</time>@if ($evento->fecha_fin && ! $evento->fecha_fin->isSameDay($evento->fecha_inicio)) a <time datetime="{{ $evento->fecha_fin->toDateString() }}">{{ $evento->fecha_fin->format('d/m') }}</time>@endif
                        </span>
                        <span class="min-w-0 flex-1">{{ $evento->titulo }}</span>
                        <span class="flex flex-wrap gap-1">
                            <span @class([
                                'rounded-full px-2.5 py-0.5 text-sm font-semibold',
                                'bg-primario-claro text-primario' => $evento->tipo === 'institucional',
                                'bg-amber-100 text-amber-700' => $evento->tipo === 'feriado',
                                'bg-borde/40 text-texto' => ! in_array($evento->tipo, ['institucional', 'feriado']),
                            ])>{{ $evento->tipoTexto() }}</span>
                            <span @class([
                                'rounded-full px-2.5 py-0.5 text-sm font-semibold',
                                'bg-exito-claro text-exito' => $evento->publico,
                                'bg-borde/40 text-texto' => ! $evento->publico,
                            ])>{{ $evento->publico ? 'Público' : 'Solo campus' }}</span>
                        </span>
                        <span class="flex gap-1">
                            <button type="button" wire:click="editar({{ $evento->id }})" class="text-sm">Editar</button>
                            <button type="button" wire:click="eliminar({{ $evento->id }})"
                                    wire:confirm="¿Seguro que querés eliminar «{{ $evento->titulo }}» del calendario?"
                                    class="text-sm border-error text-error hover:bg-error-claro">Eliminar</button>
                        </span>
                    </li>
                @empty
                    <li class="py-3 text-texto-suave">No hay eventos institucionales en {{ $nombreMes }}.</li>
                @endforelse
            </ul>

            <p class="mt-3 text-sm text-texto-suave">Las evaluaciones y entregas de cada curso las cargan los profesores y nunca son públicas.</p>
        </section>

        {{-- Alta y edición --}}
        <section aria-labelledby="titulo-evento" class="rounded border border-borde p-4">
            <h2 id="titulo-evento" class="mt-0 text-xl">{{ $eventoId ? 'Editar evento' : 'Nuevo evento' }}</h2>

            <form wire:submit="guardar">
                <div class="max-w-none">
                    <label for="titulo">Título *</label>
                    <input id="titulo" type="text" wire:model="titulo" maxlength="150" placeholder="Ej.: Inicio de clases" @error('titulo') aria-invalid="true" @enderror>
                    @error('titulo') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div class="max-w-none">
                    <label for="tipo">Tipo *</label>
                    <select id="tipo" wire:model="tipo" @error('tipo') aria-invalid="true" @enderror>
                        @foreach (\App\Livewire\Admin\Calendario\Index::tipos() as $valor => $nombre)
                            <option value="{{ $valor }}">{{ $nombre }}</option>
                        @endforeach
                    </select>
                    @error('tipo') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div class="max-w-none">
                    <label for="fecha_inicio">Desde *</label>
                    <input id="fecha_inicio" type="date" wire:model="fecha_inicio" @error('fecha_inicio') aria-invalid="true" @enderror>
                    @error('fecha_inicio') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div class="max-w-none">
                    <label for="fecha_fin">Hasta (opcional)</label>
                    <input id="fecha_fin" type="date" wire:model="fecha_fin" @error('fecha_fin') aria-invalid="true" @enderror>
                    @error('fecha_fin') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div class="max-w-none">
                    <label for="descripcion">Descripción (opcional)</label>
                    <textarea id="descripcion" wire:model="descripcion" rows="3" placeholder="Detalle que ve la gente"></textarea>
                    @error('descripcion') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div class="max-w-none">
                    <label class="font-normal"><input type="checkbox" wire:model="publico"> Mostrar en el calendario del sitio público</label>
                    @error('publico') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-wrap gap-2 max-w-none">
                    <button type="submit" wire:loading.attr="disabled" wire:target="guardar">{{ $eventoId ? 'Guardar cambios' : 'Guardar evento' }}</button>
                    @if ($eventoId)
                        <button type="button" wire:click="limpiar">Cancelar</button>
                    @endif
                </div>
            </form>
        </section>
    </div>
</div>
