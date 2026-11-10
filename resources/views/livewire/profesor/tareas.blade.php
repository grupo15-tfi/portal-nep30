@php use Illuminate\Support\Carbon; @endphp
<div>
    <div class="flex flex-wrap items-center justify-between gap-4">
        <p class="m-0 text-texto-suave">Tus tareas por materia. Los alumnos ven la consigna y entregan desde «Tareas».</p>
        <button type="button" wire:click="nueva" class="bg-primario text-white hover:bg-primario/90">+ Nueva tarea</button>
    </div>

    @if ($aviso)
        <p role="status" class="mt-4 rounded border-l-4 border-exito bg-exito-claro px-4 py-3">{{ $aviso }}</p>
    @endif

    @if ($tareas->isEmpty())
        <p class="mt-4 rounded-lg border border-borde bg-primario-claro px-4 py-3">Todavía no publicaste tareas. Empezá con «+ Nueva tarea».</p>
    @else
        <div class="mt-4 overflow-x-auto">
            <table class="tabla-apilada">
                <caption class="sr-only">Tus tareas</caption>
                <thead><tr><th>Tarea</th><th>Entrega hasta</th><th>Entregadas</th><th>Acciones</th></tr></thead>
                <tbody>
                    @foreach ($tareas as $t)
                        @php $vence = Carbon::parse(data_get($t, 'fecha_entrega')); @endphp
                        <tr wire:key="tarea-{{ data_get($t, 'tarea_id') }}" @class(['bg-primario-claro/40' => $verTarea && $verTarea->id === (int) data_get($t, 'tarea_id')])>
                            <td>
                                <span class="block font-semibold">{{ data_get($t, 'titulo') }}</span>
                                <span class="block text-sm text-texto-suave">{{ data_get($t, 'materia') }} · {{ data_get($t, 'curso') }}</span>
                            </td>
                            <td data-etiqueta="Entrega hasta">
                                <time datetime="{{ $vence->toIso8601String() }}">{{ $vence->format('d/m/Y H:i') }}</time>
                                @if ($vence->isPast()) <span class="block text-sm text-texto-suave">Vencida</span> @endif
                            </td>
                            <td data-etiqueta="Entregadas" class="tabular-nums">{{ (int) data_get($t, 'entregadas') }} / {{ (int) data_get($t, 'alumnos') }}</td>
                            <td data-etiqueta="Acciones">
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" wire:click="verEntregas({{ data_get($t, 'tarea_id') }})">
                                        Ver entregas<span class="sr-only">: {{ data_get($t, 'titulo') }}</span>
                                    </button>
                                    <button type="button" wire:click="editar({{ data_get($t, 'tarea_id') }})">
                                        Editar<span class="sr-only">: {{ data_get($t, 'titulo') }}</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Entregas de la tarea elegida --}}
    @if ($verTarea)
        <section aria-labelledby="titulo-entregas" class="mt-6 rounded-lg border border-borde bg-white p-4">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="titulo-entregas" class="m-0 text-xl">Entregas · {{ $verTarea->titulo }}</h2>
                <span class="text-sm text-texto-suave">Hasta el {{ $verTarea->fecha_entrega->format('d/m/Y H:i') }}</span>
            </div>
            @if ($entregas->isEmpty())
                <p class="mb-0 mt-3">No hay alumnos activos en este curso.</p>
            @else
                <div class="mt-3 overflow-x-auto">
                    <table class="tabla-apilada">
                        <caption class="sr-only">Entregas de {{ $verTarea->titulo }}</caption>
                        <thead><tr><th>Alumno</th><th>Estado</th><th>Entregó el</th><th>Acciones</th></tr></thead>
                        <tbody>
                            @foreach ($entregas as $e)
                                @php $entregoId = data_get($e, 'entrega_id'); $fuera = (bool) data_get($e, 'fuera_de_termino'); $f = data_get($e, 'fecha_entrega'); @endphp
                                <tr wire:key="entrega-{{ data_get($e, 'inscripcion_id') }}">
                                    <td class="font-semibold">{{ data_get($e, 'apellido') }}, {{ data_get($e, 'nombre') }}</td>
                                    <td data-etiqueta="Estado">
                                        <span class="flex flex-wrap gap-1">
                                            @if ($entregoId)
                                                <span class="inline-block rounded-full bg-exito-claro px-2.5 py-0.5 text-sm font-semibold text-exito">Entregó</span>
                                                @if ($fuera)
                                                    <span class="inline-block rounded-full bg-error-claro px-2.5 py-0.5 text-sm font-semibold text-error">Fuera de término</span>
                                                @endif
                                                @if (data_get($e, 'devuelta'))
                                                    <span class="inline-block rounded-full bg-primario-claro px-2.5 py-0.5 text-sm font-semibold text-primario">Devuelta</span>
                                                @endif
                                            @else
                                                <span class="inline-block rounded-full bg-borde/40 px-2.5 py-0.5 text-sm font-semibold text-texto">No entregó</span>
                                            @endif
                                        </span>
                                    </td>
                                    <td data-etiqueta="Entregó el">{{ $f ? Carbon::parse($f)->format('d/m/Y H:i') : '—' }}</td>
                                    <td data-etiqueta="Acciones">
                                        @if ($entregoId)
                                            <div class="flex flex-wrap items-center gap-2">
                                                @if ($conDescargas)
                                                    <a href="{{ route('entregas.archivo', $entregoId) }}"
                                                       class="boton inline-flex items-center rounded border border-primario px-3 py-1.5 font-semibold text-primario no-underline hover:bg-primario-claro">
                                                        Descargar<span class="sr-only"> la entrega de {{ data_get($e, 'apellido') }}</span>
                                                    </a>
                                                @endif
                                                <button type="button" wire:click="devolver({{ $entregoId }})">
                                                    {{ data_get($e, 'devuelta') ? 'Editar devolución' : 'Devolver' }}<span class="sr-only">: {{ data_get($e, 'apellido') }}</span>
                                                </button>
                                            </div>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

    {{-- Nueva o editar tarea --}}
    @if ($ventanaTarea)
        <x-ventana id="titulo-tarea" :titulo="$tareaId ? 'Editar tarea' : 'Nueva tarea'" cerrar="cerrar" ancho="max-w-2xl">
            <form wire:submit="guardar">
                <div class="max-w-none">
                    <label for="curso_profesor_id">Materia *</label>
                    <select id="curso_profesor_id" wire:model="curso_profesor_id" @error('curso_profesor_id') aria-invalid="true" @enderror>
                        @foreach ($asignaciones as $a)
                            <option value="{{ $a->id }}">{{ $a->materia->nombre }} · {{ $a->curso->planEstudio->nombre }} · {{ $a->curso->sede->nombre }}</option>
                        @endforeach
                    </select>
                    @error('curso_profesor_id') <p role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="max-w-none">
                    <label for="titulo">Título *</label>
                    <input id="titulo" type="text" wire:model="titulo" maxlength="150" @error('titulo') aria-invalid="true" @enderror>
                    @error('titulo') <p role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="max-w-none">
                    <label for="consigna">Consigna</label>
                    <textarea id="consigna" wire:model="consigna" rows="5" @error('consigna') aria-invalid="true" @enderror></textarea>
                    @error('consigna') <p role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="max-w-none">
                    <label for="fecha_entrega">Fecha y hora de entrega *</label>
                    <input id="fecha_entrega" type="datetime-local" wire:model="fecha_entrega" @error('fecha_entrega') aria-invalid="true" @enderror>
                    @error('fecha_entrega') <p role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="max-w-none">
                    <label for="archivo">Archivo de la consigna (opcional)</label>
                    <input id="archivo" type="file" wire:model="archivo" accept=".pdf,.docx,.jpg,.png,.zip" aria-describedby="ayuda-archivo" @error('archivo') aria-invalid="true" @enderror>
                    <p wire:loading wire:target="archivo" class="text-sm mb-0">Subiendo el archivo…</p>
                    <p id="ayuda-archivo" class="text-sm text-texto-suave mb-0">PDF, DOCX, JPG, PNG o ZIP, hasta 10 MB.@if ($tareaId) Si no elegís otro, queda el que ya tiene.@endif</p>
                    @error('archivo') <p role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-wrap gap-2 max-w-none">
                    <button type="submit" wire:loading.attr="disabled" wire:target="guardar, archivo">
                        <span wire:loading.remove wire:target="guardar">{{ $tareaId ? 'Guardar cambios' : 'Publicar tarea' }}</span>
                        <span wire:loading wire:target="guardar">Guardando…</span>
                    </button>
                    <button type="button" wire:click="cerrar">Cancelar</button>
                </div>
            </form>
        </x-ventana>
    @endif

    {{-- Devolución --}}
    @if ($entregaElegida)
        <x-ventana id="titulo-devolucion" titulo="Devolver la entrega" cerrar="cerrar">
            <p class="font-semibold">{{ $entregaElegida->inscripcion->alumno->apellido }}, {{ $entregaElegida->inscripcion->alumno->nombre }}</p>
            <form wire:submit="guardarDevolucion">
                <div class="max-w-none">
                    <label for="devolucion">Comentario para el alumno *</label>
                    <textarea id="devolucion" wire:model="devolucion" rows="5" @error('devolucion') aria-invalid="true" @enderror></textarea>
                    @error('devolucion') <p role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-wrap gap-2 max-w-none">
                    <button type="submit" wire:loading.attr="disabled" wire:target="guardarDevolucion">
                        <span wire:loading.remove wire:target="guardarDevolucion">Guardar devolución</span>
                        <span wire:loading wire:target="guardarDevolucion">Guardando…</span>
                    </button>
                    <button type="button" wire:click="cerrar">Cancelar</button>
                </div>
            </form>
        </x-ventana>
    @endif
</div>
