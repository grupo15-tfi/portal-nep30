<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar · Campus N.E.P. y F.P. N.º 30</title>
    <x-favicon />
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-fondo-suave text-texto font-sans text-base flex flex-col items-center justify-center gap-4 px-4 py-10">
    <p class="m-0 w-full max-w-sm"><a href="{{ route('inicio') }}" class="boton boton-volver">Volver al sitio</a></p>
    {{-- #155: tarjeta de la base, centrada, con el sello arriba --}}
    <main class="tarjeta w-full max-w-sm rounded-3xl p-6 sm:p-8">
        <img src="{{ asset('img/sello.png') }}" alt="Sello del N.E.P. y F.P. N.º 30" width="64" height="64" class="mx-auto mb-3 size-16">
        <h1 class="mb-6 text-center font-titulos text-2xl font-bold tracking-[-0.01em] text-navy-900">Ingresar al campus</h1>
        <livewire:auth.login />
    </main>
</body>
</html>