<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar · Campus N.E.P. y F.P. N.º 30</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-primario-claro/60 text-texto font-sans text-base flex items-center justify-center px-4 py-10">
    <main class="w-full max-w-sm rounded bg-white p-6 shadow">
        <p class="text-center mb-1"><a href="{{ route('inicio') }}" class="text-primario underline">« Volver al sitio</a></p>
        <h1 class="font-titulos text-2xl font-semibold text-primario text-center mb-6">Ingresar al campus</h1>
        <livewire:auth.login />
    </main>
</body>
</html>