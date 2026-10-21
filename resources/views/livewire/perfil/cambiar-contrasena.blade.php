<section aria-labelledby="titulo-contrasena" class="flex flex-col gap-4">
    <h2 id="titulo-contrasena" class="font-titulos text-2xl font-semibold text-primario">Cambiar contraseña</h2>

    @if (session('contrasena-ok'))
        <p role="status" class="rounded border border-exito px-3 py-2 text-exito">{{ session('contrasena-ok') }}</p>
    @endif

    <form wire:submit="guardar" class="flex flex-col gap-4 max-w-md" novalidate>
        @foreach ([['actual', 'Contraseña actual', 'current-password'], ['nueva', 'Contraseña nueva', 'new-password'], ['nueva_confirmation', 'Repetí la contraseña nueva', 'new-password']] as [$campo, $texto, $auto])
            <div class="flex flex-col gap-1">
                <label for="{{ $campo }}" class="font-semibold">{{ $texto }}</label>
                <input id="{{ $campo }}" type="password" wire:model="{{ $campo }}" autocomplete="{{ $auto }}"
                       class="rounded border border-texto-suave px-3 py-2" @error($campo) aria-invalid="true" aria-describedby="error-{{ $campo }}" @enderror>
                @error($campo) <p id="error-{{ $campo }}" class="text-error">{{ $message }}</p> @enderror
            </div>
        @endforeach
        <p class="text-texto-suave">Mínimo 8 caracteres.</p>
        <button type="submit" class="self-start rounded bg-primario px-5 py-3 font-semibold text-white">Cambiar contraseña</button>
    </form>
</section>
