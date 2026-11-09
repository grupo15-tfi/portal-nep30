@php use App\Livewire\Profesor\Notas; @endphp
<div>
    <p class="mb-2"><a href="{{ route('profesor.materias') }}" class="text-primario underline">« Mis materias</a></p>
    <h1>Notas · {{ $cursoProfesor->materia->nombre }}</h1>
    <p class="text-texto-suave">
        {{ $cursoProfesor->curso->planEstudio->nombre }} · {{ $cursoProfesor->curso->sede->nombre }} · turno {{ $cursoProfesor->curso->turno }}.
        Notas de 1 a 10, con hasta dos decimales. Cada corrección queda registrada.
    </p>

    @if ($aviso)
        <p role="status" class="rounded border-l-4 border-exito bg-exito-claro px-4 py-3">{{ $aviso }}</p>
    @endif

    @if ($filas->isEmpty())
        <p class="rounded-lg border border-borde bg-primario-claro px-4 py-3">Todavía no hay alumnos inscriptos en esta materia.</p>
    @else
        {{-- En el celular cada alumno se ve como tarjeta (tabla-apilada) --}}
        <div class="overflow-x-auto">
            <table class="tabla-apilada">
                <thead>
                    <tr><th>Alumno</th><th>Notas</th><th>Nota final</th><th>Estado</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                    @foreach ($filas as $im)
                        @php $activa = $im->inscripcion->estado === 'activa'; @endphp
                        <tr wire:key="im-{{ $im->id }}">
                            <td data-etiqueta="Alumno">
                                <div class="min-w-0">
                                    <span class="block font-semibold">{{ $im->inscripcion->alumno->nombreCompleto() }}</span>
                                    @unless ($activa)
                                        <span class="text-sm text-texto-suave">Inscripción: {{ $im->inscripcion->estado }}</span>
                                    @endunless
                                </div>
                            </td>
                            <td data-etiqueta="Notas">
                                @if (isset($evaluaciones[$im->id]))
                                    <ul class="m-0 flex list-none flex-col gap-1 p-0">
                                        @foreach ($evaluaciones[$im->id] as $ev)
                                            <li wire:key="ev-{{ $ev->id }}">
                                                <button type="button" wire:click="editar({{ $ev->id }})" class="text-sm"
                                                        title="Corregir">
                                                    {{ Notas::TIPOS[$ev->tipo] ?? $ev->tipo }}: <strong>{{ Notas::formatoNota($ev->nota) }}</strong>
                                                    <span class="font-normal text-texto-suave">· {{ $ev->fecha->format('d/m/Y') }}</span>
                                                    <span class="sr-only">(corregir)</span>
                                                </button>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <span class="text-texto-suave">Sin notas</span>
                                @endif
                            </td>
                            <td data-etiqueta="Nota final">{{ Notas::formatoNota($im->nota_final) }}</td>
                            <td data-etiqueta="Estado">
                                <span @class([
                                    'inline-block rounded-full px-2.5 py-0.5 text-sm font-semibold whitespace-nowrap',
                                    'bg-primario-claro text-primario' => $im->estado === 'cursando',
                                    'bg-exito-claro text-exito' => $im->estado === 'acreditada',
                                    'bg-amber-100 text-amber-700' => $im->estado === 'pendiente',
                                    'bg-error-claro text-error' => $im->estado === 'libre',
                                ])>{{ Notas::ESTADOS[$im->estado] ?? $im->estado }}</span>
                            </td>
                            <td data-etiqueta="Acciones">
                                @if ($activa)
                                    <button type="button" wire:click="nueva({{ $im->id }})">
                                        Cargar nota<span class="sr-only">: {{ $im->inscripcion->alumno->nombreCompleto() }}</span>
                                    </button>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-sm text-texto-suave">Para corregir una nota, tocala.</p>
    @endif

    {{-- Cargar y corregir en la ventana emergente compartida (#73) --}}
    @if ($ventanaAbierta && $elegida)
        <x-ventana id="titulo-nota" :titulo="$evaluacionId ? 'Corregir nota' : 'Cargar nota'" cerrar="cerrar">
            <p class="font-semibold">{{ $elegida->inscripcion->alumno->nombreCompleto() }}</p>

            <form wire:submit="guardar">
                @error('inscripcion_materia_id') <p role="alert" class="max-w-none">{{ $message }}</p> @enderror

                <div class="max-w-none">
                    <label for="tipo">Tipo *</label>
                    <select id="tipo" wire:model="tipo" @error('tipo') aria-invalid="true" @enderror>
                        @foreach (Notas::TIPOS as $valor => $texto)
                            <option value="{{ $valor }}">{{ $texto }}</option>
                        @endforeach
                    </select>
                    <p class="text-sm text-texto-suave mb-0">El recuperatorio solo se acepta si hay un parcial desaprobado.</p>
                    @error('tipo') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div class="max-w-none">
                    <label for="nota">Nota * (1 a 10)</label>
                    <input id="nota" type="text" inputmode="decimal" wire:model="nota" maxlength="5" placeholder="Ej.: 7,50"
                           @error('nota') aria-invalid="true" @enderror>
                    @error('nota') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div class="max-w-none">
                    <label for="fecha">Fecha *</label>
                    <input id="fecha" type="date" wire:model="fecha" max="{{ today()->format('Y-m-d') }}" @error('fecha') aria-invalid="true" @enderror>
                    @error('fecha') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-wrap gap-2 max-w-none">
                    <button type="submit" wire:loading.attr="disabled" wire:target="guardar">{{ $evaluacionId ? 'Guardar corrección' : 'Guardar nota' }}</button>
                    <button type="button" wire:click="cerrar">Cancelar</button>
                </div>
            </form>
        </x-ventana>
    @endif
</div>
