<div>
    @if ($listo)
        <p role="status" class="mb-4">Tu contraseña se cambió. Ya podés ingresar con la nueva.</p>
        <p class="text-center"><a href="{{ route('login') }}" class="rounded bg-primario text-white px-4 py-2 font-semibold inline-block">Ir a ingresar</a></p>
    @else
        <form wire:submit="guardar" class="flex flex-col gap-4">
            <div class="flex flex-col gap-1">
                <label for="email" class="font-semibold">Correo electrónico</label>
                <input type="email" id="email" wire:model="email" autocomplete="username" required
                       class="rounded border border-gray-500 px-3 py-2">
                @error('email') <p role="alert" class="text-error">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col gap-1">
                <label for="password" class="font-semibold">Contraseña nueva</label>
                <input type="password" id="password" wire:model="password" autocomplete="new-password" required
                       class="rounded border border-gray-500 px-3 py-2">
                @error('password') <p role="alert" class="text-error">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col gap-1">
                <label for="password_confirmation" class="font-semibold">Repetí la contraseña nueva</label>
                <input type="password" id="password_confirmation" wire:model="password_confirmation" autocomplete="new-password" required
                       class="rounded border border-gray-500 px-3 py-2">
            </div>

            <p class="text-texto-suave text-sm">Mínimo 8 caracteres, con letras y números.</p>

            <button type="submit" class="rounded bg-primario text-white px-4 py-2 font-semibold disabled:opacity-60 disabled:cursor-wait"
                    wire:loading.attr="disabled" wire:target="guardar">
                <span wire:loading.remove wire:target="guardar">Guardar contraseña</span>
                <span wire:loading wire:target="guardar">Guardando…</span>
            </button>
        </form>
    @endif
</div>