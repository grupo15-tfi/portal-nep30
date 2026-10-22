<div>
    <form wire:submit="ingresar" class="flex flex-col gap-4">
        <div class="flex flex-col gap-1">
            <label for="email" class="font-semibold">Correo electrónico</label>
            <input type="email" id="email" wire:model="email" autocomplete="username" required autofocus
                   class="rounded border border-gray-500 px-3 py-2 focus:outline-2 focus:outline-primario">
            @error('email') <p role="alert" class="text-error">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-col gap-1">
            <label for="password" class="font-semibold">Contraseña</label>
            <input type="password" id="password" wire:model="password" autocomplete="current-password" required
                   class="rounded border border-gray-500 px-3 py-2 focus:outline-2 focus:outline-primario">
            @error('password') <p role="alert" class="text-error">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="rounded bg-primario text-white px-4 py-2 font-semibold"
                wire:loading.attr="disabled">Ingresar</button>

        {{-- Aparece solo cuando exista la ruta de recuperación (#15) --}}
        @if (Route::has('password.request'))
            <p class="text-center"><a href="{{ route('password.request') }}" class="text-primario underline">¿Olvidaste tu contraseña?</a></p>
        @endif
    </form>
</div>