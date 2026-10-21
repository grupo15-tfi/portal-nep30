<div>
    @if ($enviado)
        <p role="status" class="mb-4">{{ \App\Livewire\Auth\OlvideContrasena::MENSAJE }}</p>
    @endif

    <form wire:submit="enviar" class="flex flex-col gap-4">
        <p>Escribí el correo con el que entrás al campus y te mandamos un enlace para crear una contraseña nueva.</p>

        <div class="flex flex-col gap-1">
            <label for="email" class="font-semibold">Correo electrónico</label>
            <input type="email" id="email" wire:model="email" autocomplete="username" required autofocus
                   class="rounded border border-gray-500 px-3 py-2">
            @error('email') <p role="alert" class="text-error">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="rounded bg-primario text-white px-4 py-2 font-semibold disabled:opacity-60 disabled:cursor-wait"
                wire:loading.attr="disabled" wire:target="enviar">
            <span wire:loading.remove wire:target="enviar">Enviar enlace</span>
            <span wire:loading wire:target="enviar">Enviando…</span>
        </button>

        <p class="text-center"><a href="{{ route('login') }}" class="text-primario underline">« Volver a ingresar</a></p>
    </form>
</div>