<div>
    @if ($abierto)
        {{-- Ventana emergente compartida (#73): Esc o «Cancelar» la cierran. --}}
        <x-ventana id="titulo-formulario" :titulo="$profesorId ? 'Editar profesor' : 'Nuevo profesor'" cerrar="cerrar">
            <form wire:submit="guardar">
                <div>
                    <label for="dni">DNI * (7 u 8 números, sin puntos)</label>
                    <input id="dni" type="text" inputmode="numeric" wire:model="dni" maxlength="8">
                    @error('dni') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="apellido">Apellido *</label>
                    <input id="apellido" type="text" wire:model="apellido" maxlength="100">
                    @error('apellido') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="nombre">Nombre *</label>
                    <input id="nombre" type="text" wire:model="nombre" maxlength="100">
                    @error('nombre') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="email">Correo *</label>
                    <input id="email" type="email" wire:model="email" maxlength="150">
                    @error('email') <p role="alert">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="sede_id">Sede de referencia</label>
                    <select id="sede_id" wire:model="sede_id">
                        <option value="">Sin sede</option>
                        @foreach ($sedes as $sede)
                            <option value="{{ $sede->id }}">{{ $sede->nombre }}</option>
                        @endforeach
                    </select>
                    @error('sede_id') <p role="alert">{{ $message }}</p> @enderror
                </div>

                @unless ($profesorId)
                    <p class="text-texto-suave">Al guardar le llega un correo para que cree su contraseña. Nadie más la conoce.</p>
                @endunless

                <div class="flex gap-2">
                    <button type="submit" wire:loading.attr="disabled" wire:target="guardar"
                            class="disabled:opacity-60 disabled:cursor-wait">
                        <span wire:loading.remove wire:target="guardar">{{ $profesorId ? 'Guardar cambios' : 'Dar de alta' }}</span>
                        <span wire:loading wire:target="guardar">Guardando…</span>
                    </button>
                    <button type="button" wire:click="cerrar">Cancelar</button>
                </div>
            </form>
        </x-ventana>
    @endif
</div>
