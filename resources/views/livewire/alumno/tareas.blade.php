@php
    use App\Livewire\Alumno\Tareas;
    use Illuminate\Support\Carbon;
    $etiquetas = ['pendiente' => 'Pendiente', 'entregada' => 'Entregada', 'fuera_de_termino' => 'Fuera de término', 'vencida' => 'Vencida'];
@endphp
<div>
    <p class="text-texto-suave mb-4">Las tareas de tus materias. Podés reemplazar tu entrega hasta la fecha límite; después, la entrega queda marcada fuera de término.</p>

    @if ($aviso)
        <p role="status" class="rounded border-l-4 border-exito bg-exito-claro px-4 py-3">{{ $aviso }}</p>
    @endif

    @if ($tareas->isEmpty())
        <p class="rounded-lg border border-borde bg-primario-claro px-4 py-3">Todavía no hay tareas publicadas en tus materias.</p>
    @else
        <ul class="m-0 flex list-none flex-col gap-4 p-0">
            @foreach ($tareas as $t)
                @php
                    $estado = Tareas::estado($t);
                    $vence = Carbon::parse(data_get($t, 'fecha_entrega'));
                    $entrega = data_get($t, 'entrega');
                    $vencida = (bool) data_get($t, 'vencida');
                @endphp
                <li wire:key="tarea-{{ data_get($t, 'tarea_id') }}" class="rounded-lg border border-borde bg-white p-4">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h2 class="m-0 text-lg">{{ data_get($t, 'titulo') }}</h2>
                            <p class="m-0 text-sm text-texto-suave">{{ data_get($t, 'materia') }} · {{ data_get($t, 'curso') }}</p>
                        </div>
                        <span @class([
                            'inline-block rounded-full px-2.5 py-0.5 text-sm font-semibold whitespace-nowrap',
                            'bg-primario-claro text-primario' => $estado === 'pendiente',
                            'bg-exito-claro text-exito' => $estado === 'entregada',
                            'bg-amber-100 text-amber-700' => $estado === 'fuera_de_termino',
                            'bg-error-claro text-error' => $estado === 'vencida',
                        ])>{{ $etiquetas[$estado] }}</span>
                    </div>

                    <p class="mb-0 mt-2 text-sm">
                        Entrega hasta el <strong><time datetime="{{ $vence->toIso8601String() }}">{{ $vence->format('d/m/Y H:i') }}</time></strong>
                        @if ($entrega)
                            · Entregaste el {{ Carbon::parse(data_get($entrega, 'fecha_entrega'))->format('d/m/Y H:i') }}
                        @endif
                    </p>

                    @if (data_get($t, 'consigna'))
                        <p class="mb-0 mt-3 whitespace-pre-line">{{ data_get($t, 'consigna') }}</p>
                    @endif

                    @if (data_get($entrega, 'devolucion'))
                        <div class="mt-3 rounded border-l-4 border-primario bg-primario-claro px-3 py-2">
                            <p class="m-0 text-sm font-semibold">Devolución del profesor
                                @if (data_get($entrega, 'devuelta_en')) · {{ Carbon::parse(data_get($entrega, 'devuelta_en'))->format('d/m/Y') }} @endif
                            </p>
                            <p class="m-0 whitespace-pre-line">{{ data_get($entrega, 'devolucion') }}</p>
                        </div>
                    @endif

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        @if ($conConsigna && data_get($t, 'tiene_archivo'))
                            <a href="{{ route('tareas.archivo', data_get($t, 'tarea_id')) }}"
                               class="boton inline-flex items-center rounded border border-primario px-3 py-1.5 font-semibold text-primario no-underline hover:bg-primario-claro">
                                Descargar consigna<span class="sr-only">: {{ data_get($t, 'titulo') }}</span>
                            </a>
                        @endif
                        @if ($entrega && $conDescargas)
                            <a href="{{ route('entregas.archivo', data_get($entrega, 'id')) }}"
                               class="boton inline-flex items-center rounded border border-primario px-3 py-1.5 font-semibold text-primario no-underline hover:bg-primario-claro">
                                Ver mi entrega<span class="sr-only">: {{ data_get($t, 'titulo') }}</span>
                            </a>
                        @endif
                        {{-- Antes del vencimiento: entregar o reemplazar. Después: solo la primera entrega (queda fuera de término) --}}
                        @if (! $entrega || ! $vencida)
                            <button type="button" wire:click="entregar({{ data_get($t, 'tarea_id') }})" class="bg-primario text-white hover:bg-primario/90">
                                {{ $entrega ? 'Reemplazar' : 'Entregar' }}<span class="sr-only">: {{ data_get($t, 'titulo') }}</span>
                            </button>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($elegida)
        <x-ventana id="titulo-entrega" :titulo="data_get($elegida, 'entrega') ? 'Reemplazar la entrega' : 'Entregar la tarea'" cerrar="cerrar">
            <p class="font-semibold">{{ data_get($elegida, 'titulo') }}</p>
            @if (data_get($elegida, 'vencida'))
                <p role="note" class="rounded border-l-4 border-amber-500 bg-amber-100 px-3 py-2 text-sm">La fecha límite ya pasó: tu entrega va a quedar marcada <strong>fuera de término</strong> y después no la vas a poder reemplazar.</p>
            @elseif (data_get($elegida, 'entrega'))
                <p class="text-sm">El archivo nuevo reemplaza al que entregaste.</p>
            @endif
            <form wire:submit="guardar">
                <div class="max-w-none">
                    <label for="archivo">Archivo *</label>
                    <input id="archivo" type="file" wire:model="archivo" accept="{{ collect($tipos)->map(fn ($t) => '.'.$t)->join(',') }}"
                           aria-describedby="ayuda-entrega" @error('archivo') aria-invalid="true" @enderror>
                    <p wire:loading wire:target="archivo" class="text-sm mb-0">Subiendo el archivo…</p>
                    <p id="ayuda-entrega" class="text-sm text-texto-suave mb-0">{{ collect($tipos)->map(fn ($t) => strtoupper($t))->join(', ', ' o ') }}, hasta {{ $maximoMb }} MB.</p>
                    @error('archivo') <p role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-wrap gap-2 max-w-none">
                    <button type="submit" wire:loading.attr="disabled" wire:target="guardar, archivo">
                        <span wire:loading.remove wire:target="guardar">{{ data_get($elegida, 'entrega') ? 'Reemplazar entrega' : 'Entregar' }}</span>
                        <span wire:loading wire:target="guardar">Guardando…</span>
                    </button>
                    <button type="button" wire:click="cerrar">Cancelar</button>
                </div>
            </form>
        </x-ventana>
    @endif
</div>
