<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mi perfil · N.E.P. y F.P. N.º 30</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="bg-white text-texto font-sans text-base">
    {{-- Provisorio: cuando esté el layout de los paneles (#8) se reemplaza por <x-layouts.panel titulo="Mi perfil"> --}}
    <main id="contenido" class="mx-auto max-w-3xl px-4 py-8 flex flex-col gap-10">
        <h1 class="font-titulos text-3xl font-semibold text-primario">Mi perfil</h1>
        <livewire:perfil.datos />
        <livewire:perfil.cambiar-contrasena />
    </main>
</body>
</html>
