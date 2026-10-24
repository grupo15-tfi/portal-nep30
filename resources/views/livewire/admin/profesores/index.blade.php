<div>
    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
        <h1>Profesores</h1>
        <button type="button" wire:click="$dispatch('abrir-formulario')" class="bg-primario text-white hover:bg-primario/90">+ Nuevo profesor</button>
    </div>

    @if ($aviso)
        <p role="status">{{ $aviso }}</p>
    @endif

    <div class="mb-4 max-w-md">
        <label for="buscar">Buscar por DNI, apellido o correo</label>
        <input id="buscar" type="search" wire:model.live.debounce.300ms="buscar" placeholder="Ej.: DNI, apellido o @gmail">
    </div>

    {{-- En el celular cada fila se ve como tarjeta (tabla-apilada en app.css): data-etiqueta = nombre de la columna --}}
    <div class="overflow-x-auto">
        <table class="tabla-apilada">
            <thead>
                <tr>
                    <th>Apellido y nombre</th>
                    <th>DNI</th>
                    <th>Correo</th>
                    <th>Sede</th>
                    <th>Materias</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($profesores as $profesor)
                    <tr wire:key="profesor-{{ $profesor->id }}">
                        <td data-etiqueta="Apellido y nombre">{{ $profesor->nombreCompleto() }}</td>
                        <td data-etiqueta="DNI">{{ $profesor->dni }}</td>
                        <td data-etiqueta="Correo" class="break-all">{{ $profesor->email }}</td>
                        <td data-etiqueta="Sede">{{ $sedes[$profesor->sede_id] ?? '—' }}</td>
                        <td data-etiqueta="Materias">{{ $profesor->asignaciones_count }}</td>
                        <td data-etiqueta="Estado">{{ $profesor->activo ? 'Activo' : 'De baja' }}</td>
                        <td data-etiqueta="Acciones">
                            <div class="flex flex-wrap items-center gap-x-1">
                            <button type="button" wire:click="$dispatch('abrir-formulario', { id: {{ $profesor->id }} })">Editar</button>
                            <a href="{{ route('admin.profesores.asignaciones', $profesor) }}" class="inline-block mb-1 mr-1 text-sm font-semibold text-primario underline">Materias</a>
                            <button type="button" wire:click="cambiarEstado({{ $profesor->id }})"
                                    wire:confirm="¿Seguro que querés {{ $profesor->activo ? 'dar de baja' : 'dar de alta' }} a {{ $profesor->nombreCompleto() }}?">
                                {{ $profesor->activo ? 'Dar de baja' : 'Dar de alta' }}
                            </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">No hay profesores{{ $buscar !== '' ? ' que coincidan con la búsqueda' : '' }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginación en español (15 por página) --}}
    @if ($profesores->hasPages())
        <nav aria-label="Páginas de profesores" class="flex items-center justify-between gap-4">
            <button type="button" wire:click="previousPage" @disabled($profesores->onFirstPage())>« Anterior</button>
            <span>Página {{ $profesores->currentPage() }} de {{ $profesores->lastPage() }}</span>
            <button type="button" wire:click="nextPage" @disabled(! $profesores->hasMorePages())>Siguiente »</button>
        </nav>
    @endif

    <livewire:admin.profesores.formulario />
</div>
