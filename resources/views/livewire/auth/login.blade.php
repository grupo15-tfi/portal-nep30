<div>
    <form wire:submit="ingresar">
        <div>
            <label for="email">Correo electrónico</label>
            <input type="email" id="email" wire:model="email" autocomplete="username" required autofocus>
            @error('email') <p role="alert">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password">Contraseña</label>
            <input type="password" id="password" wire:model="password" autocomplete="current-password" required>
            @error('password') <p role="alert">{{ $message }}</p> @enderror
        </div>

        <button type="submit">Ingresar</button>
    </form>
</div>
