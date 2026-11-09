@php use App\Livewire\Profesor\Asistencia; @endphp
<div>
    <p class="mb-2"><a href="{{ route('profesor.materias') }}" class="text-primario underline">« Mis materias</a></p>
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="mb-0">Asistencia · {{ $cursoProfesor->materia->nombre }}</h1>
        <button type="button" wire:click="hoy" class="bg-primario text-white hover:bg-primario/90">Tomar asistencia de hoy</button>
    </div>
    <p class="text-texto-suave">
        {{ $cursoProfesor->curso->planEstudio->nombre }} · {{ $cursoProfesor->curso->sede->nombre }} · turno {{ $cursoProfesor->curso->turno }}.
        Todos empiezan como presentes: marcá solo a los que faltaron o llegaron tarde.
    </p>

    @if ($aviso)
        <p role="status" class="rounded border-l-4 border-exito bg-exito-claro px-4 py-3">{{ $aviso }}</p>
    @endif

    {{-- Porcentaje por alumno: en rojo si está por debajo del mínimo del plan --}}
    <h2 class="text-xl">Porcentaje por alumno</h2>
    @if ($resumen->isEmpty())
        <p class="rounded-lg border border-borde bg-primario-claro px-4 py-3">Todavía no hay alumnos inscriptos en este curso.</p>
    @else
        <div class="overflow-x-auto">
            <table class="tabla-apilada">
                <thead><tr><th>Alumno</th><th>Clases dictadas</th><th>Asistencia</th></tr></thead>
                <tbody>
                    @foreach ($resumen as $f)
                        @php
                            $pct = data_get($f, 'porcentaje');
                            $min = data_get($f, 'asistencia_minima');
                            $debajo = $pct !== null && $min !== null && (float) $pct < (float) $min;
                        @endphp
                        <tr wire:key="res-{{ data_get($f, 'inscripcion_id') }}">
                            <td data-etiqueta="Alumno" class="font-semibold">{{ data_get($f, 'apellido') }}, {{ data_get($f, 'nombre') }}</td>
                            <td data-etiqueta="Clases dictadas">{{ data_get($f, 'clases_dictadas', 0) }}</td>
                            <td data-etiqueta="Asistencia">
                                <div class="flex flex-col items-start gap-1">
                                <span @class([
                                    'inline-block rounded-full px-2.5 py-0.5 text-sm font-semibold whitespace-nowrap',
                                    'bg-error-claro text-error' => $debajo,
                                    'bg-exito-claro text-exito' => ! $debajo && $pct !== null,
                                    'bg-borde/40 text-texto' => $pct === null,
                                ])>{{ Asistencia::porcentaje($pct) }}</span>
                                @if ($debajo)
                                    <span class="text-sm text-error">Debajo del mínimo ({{ Asistencia::porcentaje($min) }})</span>
                                @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <h2 class="text-xl">Clases</h2>
    @if ($clases->isEmpty())
        <p class="rounded-lg border border-borde bg-primario-claro px-4 py-3">Todavía no hay clases cargadas. Empezá con «Tomar asistencia de hoy».</p>
    @else
        <ul class="m-0 flex list-none flex-col gap-2 p-0">
            @foreach ($clases as $c)
                <li wire:key="clase-{{ $c->id }}" class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-borde bg-white px-4 py-3">
                    <div class="min-w-0">
                        <span class="block font-semibold">{{ $c->fecha->format('d/m/Y') }}</span>
                        <span class="block text-sm text-texto-suave">
                            @if ($c->estado === 'suspendida')
                                Suspendida
                            @else
                                {{ $c->ausentes === 1 ? '1 ausente' : $c->ausentes.' ausentes' }}
                            @endif
                            @if ($c->tema) · {{ $c->tema }} @endif
                        </span>
                    </div>
                    <button type="button" wire:click="abrir({{ $c->id }})">
                        Ver o corregir<span class="sr-only"> la clase del {{ $c->fecha->format('d/m/Y') }}</span>
                    </button>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($ventanaAbierta)
        <x-ventana id="titulo-clase" :titulo="$claseId ? 'Clase del '.\Illuminate\Support\Carbon::parse($fecha)->format('d/m/Y') : 'Asistencia de hoy'" cerrar="cerrar" ancho="max-w-2xl">
            <form wire:submit="guardar">
                @error('clase') <p role="alert" class="max-w-none">{{ $message }}</p> @enderror

                <div class="flex flex-wrap gap-4 max-w-none">
                    <div class="mb-0 w-full max-w-48">
                        <label for="fecha">Fecha *</label>
                        <input id="fecha" type="date" wire:model="fecha" max="{{ today()->format('Y-m-d') }}" @error('fecha') aria-invalid="true" @enderror>
                        @error('fecha') <p role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="mb-0 min-w-0 flex-1">
                        <label for="tema">Tema (opcional)</label>
                        <input id="tema" type="text" wire:model="tema" maxlength="200" @error('tema') aria-invalid="true" @enderror>
                        @error('tema') <p role="alert">{{ $message }}</p> @enderror
                    </div>
                </div>

                <label class="mt-3 font-normal max-w-none">
                    <input type="checkbox" wire:model.live="suspendida">
                    La clase se suspendió (no cuenta para el porcentaje)
                </label>
                @error('estado') <p role="alert">{{ $message }}</p> @enderror

                @unless ($suspendida)
                    <div class="mt-3 flex flex-wrap items-center gap-2 max-w-none">
                        <span class="text-sm text-texto-suave">Marcar a todos:</span>
                        <button type="button" wire:click="todos('presente')" class="text-sm">Presentes</button>
                        <button type="button" wire:click="todos('ausente')" class="text-sm">Ausentes</button>
                    </div>

                    @forelse ($alumnos as $i)
                        <fieldset wire:key="asis-{{ $i->id }}" class="mt-3 max-w-none rounded-lg border border-borde px-3 py-2">
                            <legend class="px-1 font-semibold">{{ $i->alumno->nombreCompleto() }}</legend>
                            <div class="flex flex-wrap gap-x-4 gap-y-1">
                                @foreach (Asistencia::ESTADOS as $valor => $texto)
                                    <label class="mb-0 font-normal">
                                        <input type="radio" name="estado-{{ $i->id }}" value="{{ $valor }}" wire:model="estados.{{ $i->id }}">
                                        {{ $texto }}
                                    </label>
                                @endforeach
                            </div>
                            @error('estados.'.$i->id) <p role="alert" class="mb-0">{{ $message }}</p> @enderror
                        </fieldset>
                    @empty
                        <p class="mt-3">No hay alumnos activos en este curso.</p>
                    @endforelse
                @endunless

                <div class="mt-4 flex flex-wrap gap-2 max-w-none">
                    <button type="submit" wire:loading.attr="disabled" wire:target="guardar">Guardar</button>
                    <button type="button" wire:click="cerrar">Cancelar</button>
                </div>
            </form>
        </x-ventana>
    @endif
</div>
