<div>
    @if ($abierto)
        {{-- Modal: fondo oscuro + cuadro centrado. Esc o «Cancelar» lo cierran. --}}
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
             wire:keydown.escape.window="cerrar">
            <div role="dialog" aria-modal="true" aria-labelledby="titulo-formulario"
                 class="w-full max-w-lg max-h-full overflow-y-auto rounded bg-white p-6 shadow-lg">
                <h2 id="titulo-formulario" class="mt-0">{{ $profesorId ? 'Editar profesor' : 'Nuevo profesor' }}</h2>

                <form wire:submit="guardar">
                    <div>
                        <label for="dni">DNI * (7 u 8 números, sin puntos)</label>
                        <input id="dni" type="text" inputmode="numeric" wire:model="dni" maxlength="8" autofocus>
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
            </div>
        </div>
    @endif
</div>
