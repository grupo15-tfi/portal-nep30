<div>
    <h1>Preinscripciones</h1>

    @if ($aviso)
        <p role="status">{{ $aviso }}</p>
    @endif

    {{-- Filtros --}}
    <div class="mb-4 grid gap-4 sm:grid-cols-3">
        <div>
            <label for="estado">Estado</label>
            <select id="estado" wire:model.live="estado">
                <option value="">Todos</option>
                @foreach (\App\Livewire\Admin\Preinscripciones\Index::ESTADOS as $valor => $texto)
                    <option value="{{ $valor }}">{{ $texto }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="curso">Curso</label>
            <select id="curso" wire:model.live="cursoId">
                <option value="">Todos los cursos</option>
                @foreach ($cursos as $id => $nombre)
                    <option value="{{ $id }}">{{ $nombre }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="buscar">Buscar por DNI o apellido</label>
            <input id="buscar" type="search" wire:model.live.debounce.300ms="buscar" placeholder="Ej.: 30000005 o Pérez">
        </div>
    </div>

    {{-- Listado (en el celular, tarjetas: tabla-apilada) --}}
    <div class="overflow-x-auto">
        <table class="tabla-apilada">
            <thead>
                <tr>
                    <th>Llegó</th>
                    <th>Apellido y nombre</th>
                    <th>DNI</th>
                    <th>Curso</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($preinscripciones as $p)
                    <tr wire:key="preinsc-{{ $p->id }}">
                        <td data-etiqueta="Llegó">{{ $p->created_at->format('d/m/Y H:i') }}</td>
                        <td data-etiqueta="Apellido y nombre">{{ $p->apellido }}, {{ $p->nombre }}</td>
                        <td data-etiqueta="DNI">{{ $p->dni }}</td>
                        <td data-etiqueta="Curso">{{ $this->nombreCurso($p->curso) }}</td>
                        <td data-etiqueta="Estado">
                            {{-- Color y texto: el color no es la única señal (#61) --}}
                            <span @class([
                                'estado inline-block rounded-full px-2.5 py-0.5 text-sm font-semibold whitespace-nowrap',
                                'bg-primario-claro text-primario' => $p->estado === 'pendiente',
                                'bg-gray-100 text-gray-700'       => $p->estado === 'en_espera',
                                'bg-exito-claro text-exito'       => $p->estado === 'aprobada',
                                'bg-error-claro text-error'       => $p->estado === 'rechazada',
                            ])>{{ \App\Livewire\Admin\Preinscripciones\Index::ESTADOS[$p->estado] }}</span>
                        </td>
                        <td data-etiqueta="Acciones">
                            <div class="flex flex-wrap items-center gap-x-1">
                                <button type="button" wire:click="ver({{ $p->id }})">Ver</button>
                                @if (in_array($p->estado, ['pendiente', 'en_espera']))
                                    <button type="button" wire:click="aprobar({{ $p->id }})"
                                            wire:confirm="¿Aprobar la preinscripción de {{ $p->apellido }}, {{ $p->nombre }}? Se le envía un correo.">Aprobar</button>
                                    <button type="button" wire:click="abrirRechazo({{ $p->id }})">Rechazar</button>
                                @endif
                                @if ($p->estado === 'aprobada' && ! $p->inscripcion_existe)
                                    <button type="button" wire:click="darDeAlta({{ $p->id }})"
                                            wire:confirm="¿Dar de alta a {{ $p->apellido }}, {{ $p->nombre }}? Se crea su usuario y ocupa un lugar del cupo.">Dar de alta</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">No hay preinscripciones{{ $buscar !== '' || $estado !== '' || $cursoId ? ' con esos filtros' : '' }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($preinscripciones->hasPages())
        <nav aria-label="Páginas de preinscripciones" class="flex items-center justify-between gap-4">
            <button type="button" wire:click="previousPage" @disabled($preinscripciones->onFirstPage())>« Anterior</button>
            <span>Página {{ $preinscripciones->currentPage() }} de {{ $preinscripciones->lastPage() }}</span>
            <button type="button" wire:click="nextPage" @disabled(! $preinscripciones->hasMorePages())>Siguiente »</button>
        </nav>
    @endif

    {{-- Lista de espera del curso elegido --}}
    @if ($cursoElegido)
        <section aria-labelledby="titulo-espera" class="mt-8">
            <h2 id="titulo-espera">Lista de espera · {{ $this->nombreCurso($cursoElegido) }}</h2>
            <p>Lugares disponibles: <strong>{{ $disponibles }}</strong>.
                @if ($disponibles > 0 && $espera->isNotEmpty())
                    Hay lugar: avisá por teléfono en orden de llegada.
                @endif
            </p>

            @if ($espera->isEmpty())
                <p>No hay nadie en lista de espera para este curso.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="tabla-apilada">
                        <thead>
                            <tr><th>N.º</th><th>Apellido y nombre</th><th>Teléfono</th><th>Anotado</th><th>Último aviso</th><th>Acciones</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($espera as $e)
                                <tr wire:key="espera-{{ $e->preinscripcion_id }}">
                                    <td data-etiqueta="N.º">{{ $e->posicion }}</td>
                                    <td data-etiqueta="Apellido y nombre">{{ $e->apellido }}, {{ $e->nombre }}</td>
                                    <td data-etiqueta="Teléfono"><a href="tel:{{ preg_replace('/[^0-9+]/', '', $e->telefono) }}">{{ $e->telefono }}</a></td>
                                    <td data-etiqueta="Anotado">{{ \Illuminate\Support\Carbon::parse($e->anotado_en)->format('d/m/Y') }}</td>
                                    <td data-etiqueta="Último aviso">{{ $e->avisado_en ? \Illuminate\Support\Carbon::parse($e->avisado_en)->format('d/m/Y H:i') : '—' }}</td>
                                    <td data-etiqueta="Acciones">
                                        <button type="button" wire:click="registrarAviso({{ $e->preinscripcion_id }})">Registrar aviso</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

    {{-- Detalle (ventana compartida de #73) --}}
    @if ($detalle)
        <x-ventana titulo="Preinscripción de {{ $detalle->apellido }}, {{ $detalle->nombre }}" id="titulo-detalle">
            <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1">
                <dt>DNI</dt><dd>{{ $detalle->dni }}</dd>
                <dt>Nacimiento</dt><dd>{{ $detalle->fecha_nacimiento->format('d/m/Y') }} ({{ $detalle->fecha_nacimiento->age }} años)</dd>
                <dt>Correo</dt><dd class="break-all">{{ $detalle->email }}</dd>
                <dt>Teléfono</dt><dd>{{ $detalle->telefono }}</dd>
                <dt>Domicilio</dt><dd>{{ $detalle->domicilio ?? '—' }}</dd>
                <dt>Nivel educativo</dt><dd>{{ \App\Livewire\Admin\Preinscripciones\Index::NIVELES[$detalle->nivel_educativo] ?? '—' }}</dd>
                <dt>Curso</dt><dd>{{ $this->nombreCurso($detalle->curso) }}</dd>
                <dt>Estado</dt><dd>{{ \App\Livewire\Admin\Preinscripciones\Index::ESTADOS[$detalle->estado] }}</dd>
                @if ($detalle->observacion)
                    <dt>Motivo</dt><dd>{{ $detalle->observacion }}</dd>
                @endif
            </dl>
            <div class="mt-4 flex justify-end">
                <button type="button" wire:click="cerrar">Cerrar</button>
            </div>
        </x-ventana>
    @endif

    {{-- Rechazo con motivo --}}
    @if ($aRechazar)
        <x-ventana titulo="Rechazar la preinscripción de {{ $aRechazar->apellido }}, {{ $aRechazar->nombre }}" id="titulo-rechazo">
            <form wire:submit="rechazar">
                <label for="motivo">Motivo (le llega por correo al aspirante)</label>
                <textarea id="motivo" wire:model="motivo" rows="3" maxlength="500"
                          aria-describedby="error-motivo"></textarea>
                @error('motivo') <p id="error-motivo" class="text-error">{{ $message }}</p> @enderror
                @error('estado') <p class="text-error">{{ $message }}</p> @enderror
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="cerrar">Cancelar</button>
                    <button type="submit" class="bg-error text-white">Rechazar</button>
                </div>
            </form>
        </x-ventana>
    @endif

    {{-- Errores de aprobar, alta o aviso (vienen de la #86) --}}
    @foreach (['estado', 'cupo', 'alta'] as $clave)
        @if (! $aRechazar)
            @error($clave) <p role="alert" class="text-error">{{ $message }}</p> @enderror
        @endif
    @endforeach
</div>