<div>
    <p class="text-texto-suave">Quién cambió qué y cuándo, en usuarios, notas, asistencias, preinscripciones e inscripciones. Los registros no se pueden editar ni borrar.</p>

    <div class="mb-4 flex flex-wrap items-end gap-4">
        <div class="mb-0 w-full max-w-56">
            <label for="filtro-tabla">Tabla</label>
            <select id="filtro-tabla" wire:model.live="tabla">
                <option value="">Todas</option>
                @foreach (\App\Queries\RegistroDeAuditoria::TABLAS as $valor => $texto)
                    <option value="{{ $valor }}">{{ $texto }}</option>
                @endforeach
            </select>
        </div>
        <div class="mb-0 w-full max-w-56">
            <label for="filtro-accion">Acción</label>
            <select id="filtro-accion" wire:model.live="accion">
                <option value="">Todas</option>
                @foreach (\App\Queries\RegistroDeAuditoria::ACCIONES as $valor => $texto)
                    <option value="{{ $valor }}">{{ $texto }}</option>
                @endforeach
            </select>
        </div>
        <div class="mb-0 w-full max-w-xs">
            <label for="filtro-usuario">Usuario (DNI o apellido)</label>
            <input id="filtro-usuario" type="search" wire:model.live.debounce.300ms="usuario" placeholder="Ej.: 25123456 o Gómez">
        </div>
        <div class="mb-0 w-full max-w-44">
            <label for="filtro-desde">Desde</label>
            <input id="filtro-desde" type="date" wire:model.live="desde">
        </div>
        <div class="mb-0 w-full max-w-44">
            <label for="filtro-hasta">Hasta</label>
            <input id="filtro-hasta" type="date" wire:model.live="hasta">
        </div>
        @if ($this->hayFiltros())
            <button type="button" wire:click="limpiar">Limpiar filtros</button>
        @endif
    </div>

    <p class="text-sm text-texto-suave" role="status">
        {{ $registros->total() }} {{ $registros->total() === 1 ? 'registro' : 'registros' }}{{ $this->hayFiltros() ? ' con esos filtros' : '' }}.
    </p>

    {{-- En el celular cada fila se ve como tarjeta (tabla-apilada en app.css) --}}
    <div class="overflow-x-auto">
        <table class="tabla-apilada">
            <thead>
                <tr>
                    <th>Fecha y hora</th>
                    <th>Usuario</th>
                    <th>Acción</th>
                    <th>Tabla</th>
                    <th>Sobre</th>
                    <th>Detalle</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($registros as $r)
                    <tr wire:key="auditoria-{{ $r['id'] }}">
                        <td data-etiqueta="Fecha y hora">{{ $r['fecha']->format('d/m/Y H:i') }}</td>
                        <td data-etiqueta="Usuario">{{ $r['usuario'] }}</td>
                        {{-- Acción en color; el texto se mantiene: el color no es la única señal --}}
                        <td data-etiqueta="Acción">
                            <span @class([
                                'accion inline-block rounded-full px-2.5 py-0.5 text-sm font-semibold whitespace-nowrap',
                                'accion-'.$r['accion'],
                                'bg-exito-claro text-exito' => $r['accion'] === 'alta',
                                'bg-primario-claro text-primario' => in_array($r['accion'], ['modificacion', 'login', 'descarga'], true),
                                'bg-error-claro text-error' => in_array($r['accion'], ['baja', 'login_fallido'], true),
                            ])>{{ \App\Queries\RegistroDeAuditoria::ACCIONES[$r['accion']] ?? $r['accion'] }}</span>
                        </td>
                        <td data-etiqueta="Tabla">{{ $r['tabla_legible'] }}</td>
                        <td data-etiqueta="Sobre">{{ $sobre[$r['id']] ?? '—' }}</td>
                        <td data-etiqueta="Detalle">
                            @if ($r['cambios'])
                                <button type="button" wire:click="verDetalle({{ $r['id'] }})">
                                    Ver detalle<span class="sr-only">: {{ $r['tabla_legible'] }}, {{ $sobre[$r['id']] ?? '' }}, {{ $r['fecha']->format('d/m/Y H:i') }}</span>
                                </button>
                            @else
                                <span class="text-texto-suave">Sin cambios de datos</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">No hay registros{{ $this->hayFiltros() ? ' con esos filtros' : ' todavía' }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($registros->hasPages())
        <nav aria-label="Páginas de la auditoría" class="flex items-center justify-between gap-4">
            <button type="button" wire:click="previousPage" @disabled($registros->onFirstPage())>« Anterior</button>
            <span>Página {{ $registros->currentPage() }} de {{ $registros->lastPage() }}</span>
            <button type="button" wire:click="nextPage" @disabled(! $registros->hasMorePages())>Siguiente »</button>
        </nav>
    @endif

    {{-- Detalle en la ventana emergente compartida (#73): solo lectura --}}
    @if ($detalle)
        <x-ventana id="titulo-detalle" titulo="Detalle del cambio" cerrar="cerrar" ancho="max-w-2xl">
            <p>
                <strong>{{ \App\Queries\RegistroDeAuditoria::ACCIONES[$detalle['accion']] ?? $detalle['accion'] }}</strong>
                en {{ $detalle['tabla_legible'] }}{{ ($sobre[$detalle['id']] ?? '—') !== '—' ? ': '.$sobre[$detalle['id']] : '' }}<br>
                {{ $detalle['fecha']->format('d/m/Y H:i') }} · {{ $detalle['usuario'] }}@if ($detalle['ip']) · IP {{ $detalle['ip'] }}@endif
            </p>

            {{-- Los números internos ya vienen como nombres (NombresDeAuditoria); en un alta, «Antes» queda vacío (—) --}}
            <div class="overflow-x-auto">
                <table class="tabla-apilada">
                    <thead>
                        <tr><th>Campo</th><th>Antes</th><th>Después</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($detalle['cambios'] as $c)
                            <tr wire:key="cambio-{{ $detalle['id'] }}-{{ $c['campo'] }}">
                                <td data-etiqueta="Campo">{{ \App\Livewire\Auditoria\Registro::campo($c['campo']) }}</td>
                                <td data-etiqueta="Antes">{{ \App\Livewire\Auditoria\Registro::valor($c['antes'], $c['campo']) }}</td>
                                <td data-etiqueta="Después">{{ \App\Livewire\Auditoria\Registro::valor($c['despues'], $c['campo']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <button type="button" wire:click="cerrar">Cerrar</button>
        </x-ventana>
    @endif
</div>