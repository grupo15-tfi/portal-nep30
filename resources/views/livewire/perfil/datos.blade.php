<section aria-labelledby="titulo-datos" class="flex flex-col gap-4">
    <h2 id="titulo-datos" class="font-titulos text-2xl font-semibold text-primario">Mis datos</h2>

    @if (session('perfil-ok'))
        <p role="status" class="rounded border border-exito px-3 py-2 text-exito">{{ session('perfil-ok') }}</p>
    @endif

    {{-- DNI y correo: se muestran, no se editan --}}
    <dl class="grid gap-2 sm:grid-cols-2">
        <div>
            <dt class="font-semibold">DNI</dt>
            <dd>{{ $usuario->dni }}</dd>
        </div>
        <div>
            <dt class="font-semibold">Correo</dt>
            <dd class="break-all">{{ $usuario->email }}</dd>
        </div>
    </dl>
    <p class="text-texto-suave">Para cambiar el DNI o el correo, pedíselo a la administración.</p>

    <form wire:submit="guardar" class="flex flex-col gap-4" novalidate>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="flex flex-col gap-1">
                <label for="apellido" class="font-semibold">Apellido</label>
                <input id="apellido" type="text" wire:model="apellido" autocomplete="family-name"
                       class="rounded border border-texto-suave px-3 py-2" @error('apellido') aria-invalid="true" aria-describedby="error-apellido" @enderror>
                @error('apellido') <p id="error-apellido" class="text-error">{{ $message }}</p> @enderror
            </div>
            <div class="flex flex-col gap-1">
                <label for="nombre" class="font-semibold">Nombre</label>
                <input id="nombre" type="text" wire:model="nombre" autocomplete="given-name"
                       class="rounded border border-texto-suave px-3 py-2" @error('nombre') aria-invalid="true" aria-describedby="error-nombre" @enderror>
                @error('nombre') <p id="error-nombre" class="text-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <fieldset class="flex flex-col gap-3">
            <legend class="font-semibold mb-1">Teléfonos</legend>

            @forelse ($telefonos as $i => $telefono)
                <div wire:key="telefono-{{ $i }}" class="rounded border border-borde p-3 grid gap-3 sm:grid-cols-[1fr_10rem]">
                    <div class="flex flex-col gap-1">
                        <label for="tel-numero-{{ $i }}">Número</label>
                        <input id="tel-numero-{{ $i }}" type="tel" wire:model="telefonos.{{ $i }}.numero" inputmode="tel"
                               class="rounded border border-texto-suave px-3 py-2" @error("telefonos.$i.numero") aria-invalid="true" @enderror>
                        @error("telefonos.$i.numero") <p class="text-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex flex-col gap-1">
                        <label for="tel-tipo-{{ $i }}">Tipo</label>
                        <select id="tel-tipo-{{ $i }}" wire:model.live="telefonos.{{ $i }}.tipo" class="rounded border border-texto-suave px-3 py-2">
                            @foreach ($tipos as $tipo)
                                <option value="{{ $tipo }}">{{ $tipo === 'whatsapp' ? 'WhatsApp' : ucfirst($tipo) }}</option>
                            @endforeach
                        </select>
                        @error("telefonos.$i.tipo") <p class="text-error">{{ $message }}</p> @enderror
                    </div>

                    @if (($telefono['tipo'] ?? '') === 'emergencia')
                        <div class="flex flex-col gap-1 sm:col-span-2">
                            <label for="tel-contacto-{{ $i }}">Nombre del contacto de emergencia</label>
                            <input id="tel-contacto-{{ $i }}" type="text" wire:model="telefonos.{{ $i }}.contacto_nombre"
                                   class="rounded border border-texto-suave px-3 py-2" @error("telefonos.$i.contacto_nombre") aria-invalid="true" @enderror>
                            @error("telefonos.$i.contacto_nombre") <p class="text-error">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div class="flex flex-wrap items-center justify-between gap-3 sm:col-span-2">
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" wire:model="telefonos.{{ $i }}.es_principal"> Principal
                        </label>
                        <button type="button" wire:click="quitarTelefono({{ $i }})" class="text-error underline">Quitar</button>
                    </div>
                </div>
            @empty
                <p class="text-texto-suave">No cargaste teléfonos.</p>
            @endforelse

            <button type="button" wire:click="agregarTelefono" class="self-start rounded border-2 border-primario px-4 py-2 font-semibold text-primario">
                + Agregar teléfono
            </button>
        </fieldset>

        <button type="submit" class="self-start rounded bg-primario px-5 py-3 font-semibold text-white">Guardar mis datos</button>
    </form>
</section>
