<div>
    <h1>Alumnos</h1>
    <p class="text-texto-suave">Alumnos inscriptos en cada curso. Las altas se hacen desde <a href="{{ route('admin.preinscripciones') }}" class="text-primario underline">Preinscripciones</a>.</p>

    @if ($aviso)
        <p role="status" class="rounded border-l-4 border-exito bg-exito-claro px-4 py-3">{{ $aviso }}</p>
    @endif

    <div class="mb-4 flex flex-wrap items-end gap-4">
        <div class="mb-0 w-full max-w-sm">
            <label for="buscar">Buscar por DNI o apellido</label>
            <input id="buscar" type="search" wire:model.live.debounce.300ms="buscar" placeholder="Ej.: 25123456 o Gómez">
        </div>
        <div class="mb-0 w-full max-w-md">
            <label for="curso">Curso</label>
            <select id="curso" wire:model.live="cursoId">
                <option value="">Todos los cursos</option>
                @foreach ($cursos as $id => $nombre)
                    <option value="{{ $id }}">{{ $nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="mb-0 w-full max-w-48">
            <label for="estado">Estado</label>
            <select id="estado" wire:model.live="estado">
                <option value="">Todos</option>
                @foreach (\App\Livewire\Admin\Alumnos\Index::ESTADOS as $valor => $texto)
                    <option value="{{ $valor }}">{{ $texto }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- En el celular cada fila se ve como tarjeta (tabla-apilada en app.css) --}}
    <div class="overflow-x-auto">
        <table class="tabla-apilada">
            <thead>
                <tr>
                    <th>Alumno</th>
                    <th>DNI</th>
                    <th>Curso</th>
                    <th>Inscripción</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($inscripciones as $inscripcion)
                    <tr wire:key="inscripcion-{{ $inscripcion->id }}">
                        <td data-etiqueta="Alumno">{{ $inscripcion->alumno->nombreCompleto() }}</td>
                        <td data-etiqueta="DNI">{{ $inscripcion->alumno->dni }}</td>
                        <td data-etiqueta="Curso"><span class="min-w-0">{{ $this->nombreCurso($inscripcion->curso) }}</span></td>
                        <td data-etiqueta="Inscripción">{{ $inscripcion->fecha_inscripcion?->format('d/m/Y') ?? '—' }}</td>
                        {{-- Estado en color, como en Profesores (#61); el texto se mantiene: el color no es la única señal --}}
                        <td data-etiqueta="Estado">
                            <div class="min-w-0">
                                <span @class([
                                    'estado inline-block rounded-full px-2.5 py-0.5 text-sm font-semibold whitespace-nowrap',
                                    'estado-activa bg-exito-claro text-exito' => $inscripcion->estado === 'activa',
                                    'estado-baja bg-error-claro text-error' => $inscripcion->estado === 'baja',
                                    'estado-finalizada bg-primario-claro text-primario' => $inscripcion->estado === 'finalizada',
                                ])>{{ \App\Livewire\Admin\Alumnos\Index::ESTADOS[$inscripcion->estado] ?? ucfirst($inscripcion->estado) }}</span>
                                @if ($inscripcion->estado === 'baja')
                                    <span class="block text-sm text-texto-suave">
                                        {{ $inscripcion->fecha_baja?->format('d/m/Y') }}@if ($inscripcion->motivo_baja) · {{ $inscripcion->motivo_baja }}@endif
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td data-etiqueta="Acciones">
                            @if ($inscripcion->estado === 'activa')
                                <button type="button" wire:click="abrirBaja({{ $inscripcion->id }})"
                                        class="border-error text-error hover:bg-error-claro">
                                    Dar de baja<span class="sr-only">: {{ $inscripcion->alumno->nombreCompleto() }}</span>
                                </button>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">No hay alumnos{{ trim($buscar) !== '' || $cursoId || $estado !== '' ? ' con esos filtros' : ' inscriptos todavía' }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($inscripciones->hasPages())
        <nav aria-label="Páginas de alumnos" class="flex items-center justify-between gap-4">
            <button type="button" wire:click="previousPage" @disabled($inscripciones->onFirstPage())>« Anterior</button>
            <span>Página {{ $inscripciones->currentPage() }} de {{ $inscripciones->lastPage() }}</span>
            <button type="button" wire:click="nextPage" @disabled(! $inscripciones->hasMorePages())>Siguiente »</button>
        </nav>
    @endif

    {{-- Baja con motivo en la ventana emergente compartida (#73) --}}
    @if ($elegida)
        <x-ventana id="titulo-baja" titulo="Dar de baja" cerrar="cerrar">
            <p>
                <strong>{{ $elegida->alumno->nombreCompleto() }}</strong> (DNI {{ $elegida->alumno->dni }})<br>
                {{ $this->nombreCurso($elegida->curso) }}
            </p>
            <p class="text-texto-suave">Se libera su lugar en el curso. Si no le queda otra inscripción activa, no va a poder ingresar al Campus.</p>

            <form wire:submit="darDeBaja">
                <div class="max-w-none">
                    <label for="motivo">Motivo de la baja *</label>
                    <textarea id="motivo" wire:model="motivo" rows="3" maxlength="300" aria-describedby="ayuda-motivo"
                              @error('motivo') aria-invalid="true" @enderror></textarea>
                    <p id="ayuda-motivo" class="text-sm text-texto-suave mb-0">Hasta 300 caracteres. Queda registrado en la inscripción.</p>
                    @error('motivo') <p role="alert">{{ $message }}</p> @enderror
                    @error('estado') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-wrap gap-2 max-w-none">
                    <button type="submit" wire:loading.attr="disabled" wire:target="darDeBaja" class="border-error bg-error text-white hover:bg-error/90">Dar de baja</button>
                    <button type="button" wire:click="cerrar">Cancelar</button>
                </div>
            </form>
        </x-ventana>
    @endif
</div>
