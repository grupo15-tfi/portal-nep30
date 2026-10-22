<div>
    <h1>Sedes y anexos</h1>

    @if (session('ok'))
        <p role="status">{{ session('ok') }}</p>
    @endif

    {{-- Listado --}}
    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Tipo</th>
                <th>Localidad</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sedes as $sede)
                <tr wire:key="sede-{{ $sede->id }}">
                    <td>{{ $sede->nombre }}</td>
                    <td>{{ ucfirst($sede->tipo) }}</td>
                    <td>{{ $sede->localidad }}</td>
                    <td>{{ $sede->activo ? 'Activa' : 'Inactiva' }}</td>
                    <td>
                        <button type="button" wire:click="editar({{ $sede->id }})">Editar</button>
                        <button type="button" wire:click="cambiarEstado({{ $sede->id }})"
                                wire:confirm="¿Seguro que querés {{ $sede->activo ? 'desactivar' : 'activar' }} esta sede?">
                            {{ $sede->activo ? 'Desactivar' : 'Activar' }}
                        </button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">Todavía no hay sedes cargadas.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- Formulario de alta y edición --}}
    <h2>{{ $sedeId ? 'Editar sede' : 'Nueva sede' }}</h2>

    <form wire:submit="guardar">
        <div>
            <label for="nombre">Nombre *</label>
            <input id="nombre" type="text" wire:model="nombre" maxlength="120">
            @error('nombre') <p role="alert">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="tipo">Tipo *</label>
            <select id="tipo" wire:model="tipo">
                <option value="central">Central</option>
                <option value="sede">Sede</option>
                <option value="anexo">Anexo</option>
            </select>
            @error('tipo') <p role="alert">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="localidad">Localidad *</label>
            <input id="localidad" type="text" wire:model="localidad" maxlength="100">
            @error('localidad') <p role="alert">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="direccion">Dirección</label>
            <input id="direccion" type="text" wire:model="direccion" maxlength="200">
            @error('direccion') <p role="alert">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="telefono">Teléfono</label>
            <input id="telefono" type="text" wire:model="telefono" maxlength="30">
            @error('telefono') <p role="alert">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email">Correo electrónico</label>
            <input id="email" type="email" wire:model="email" maxlength="150">
            @error('email') <p role="alert">{{ $message }}</p> @enderror
        </div>

        <fieldset>
            <legend>Ubicación *</legend>
            <p>Copiá las coordenadas desde Google Maps: clic derecho sobre el edificio → clic en los números.</p>

            <div>
                <label for="latitud">Latitud</label>
                <input id="latitud" type="text" inputmode="decimal" wire:model="latitud" placeholder="-26.1849000">
                @error('latitud') <p role="alert">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="longitud">Longitud</label>
                <input id="longitud" type="text" inputmode="decimal" wire:model="longitud" placeholder="-58.1731000">
                @error('longitud') <p role="alert">{{ $message }}</p> @enderror
            </div>
        </fieldset>

        <div>
            <label for="observaciones">Observaciones</label>
            <textarea id="observaciones" wire:model="observaciones" maxlength="300"></textarea>
            @error('observaciones') <p role="alert">{{ $message }}</p> @enderror
        </div>

        <button type="submit">{{ $sedeId ? 'Guardar cambios' : 'Crear sede' }}</button>
        @if ($sedeId)
            <button type="button" wire:click="limpiar">Cancelar</button>
        @endif
    </form>
</div>