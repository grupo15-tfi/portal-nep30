{{--
    Layout de los paneles (HU05 #8). Lo usan todas las pantallas internas.
    Uso:  <x-layouts.panel titulo="Sedes"> ...contenido... </x-layouts.panel>
    Si el contenido ya trae su propio <h1> (por ejemplo un componente Livewire), usar :encabezado="false".

    El menú depende del rol. Cada opción es [texto, nombre de ruta] y SOLO se muestra si la ruta existe
    (Route::has): las historias de los próximos sprints aparecen solas cuando registran su ruta con ese nombre.
--}}
@props(['titulo', 'encabezado' => true])

@php
    $usuario = auth()->user();
    $rol = $usuario->getRoleNames()->first();

    $nombresRol = [
        'administrador' => 'Administrador',
        'profesor'      => 'Profesor',
        'alumno'        => 'Alumno',
        'auditor'       => 'Auditor',
    ];

    $menus = [
        'administrador' => [
            ['Inicio', 'admin.inicio'],
            ['Sedes', 'admin.sedes'],
            ['Planes de estudio', 'admin.planes'],
            ['Cursos', 'admin.cursos'],
            ['Profesores', 'admin.profesores.index'],       // HU08
            ['Preinscripciones', 'admin.preinscripciones'], // HU09
            ['Noticias y contenidos', 'admin.noticias'],    // HU10
            ['Auditoría', 'admin.auditoria'],               // HU16
        ],
        'profesor' => [
            ['Inicio', 'profesor.inicio'],
            ['Mis cursos', 'profesor.cursos'],              // HU11
            ['Asistencia', 'profesor.asistencia'],          // HU13
            ['Tareas', 'profesor.tareas'],                  // HU12
        ],
        'alumno' => [
            ['Inicio', 'alumno.inicio'],
            ['Mis cursos y notas', 'alumno.cursos'],        // HU11
            ['Tareas', 'alumno.tareas'],                    // HU12
            ['Historial académico', 'alumno.historial'],    // HU17
        ],
        'auditor' => [
            ['Inicio', 'auditor.inicio'],
            ['Resumen por sede', 'auditor.sedes'],          // HU18
            ['Auditoría', 'auditor.auditoria'],             // HU18
        ],
    ];

    $items = collect($menus[$rol] ?? [])
        ->filter(fn ($item) => \Illuminate\Support\Facades\Route::has($item[1]))
        ->values();
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }} · Campus N.E.P. y F.P. N.º 30</title>
    {{-- Sin npm run build la página funciona igual, sin estilos (y las pruebas pasan) --}}
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-primario-claro/40 text-texto font-sans text-base leading-relaxed">
    <a href="#contenido" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:bg-white focus:px-3 focus:py-2 focus:z-50">
        Saltar al contenido
    </a>

    {{-- Barra superior --}}
    <header class="bg-primario text-white">
        <div class="mx-auto max-w-7xl px-4 py-3 flex items-center justify-between gap-4">
            <div class="min-w-0">
                <p class="font-titulos text-lg font-semibold leading-tight">Campus N.E.P. y F.P. N.º 30</p>
                <p class="text-sm opacity-90 truncate">
                    {{ $usuario->nombreCompleto() }} · {{ $nombresRol[$rol] ?? 'Sin rol' }}
                </p>
            </div>

            <div class="hidden md:flex items-center gap-3">
                <a href="{{ route('perfil') }}" class="underline">Mi perfil</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded bg-white text-primario px-3 py-1.5 font-semibold">Salir</button>
                </form>
            </div>

            {{-- Celular: menú desplegable sin JavaScript --}}
            <details class="md:hidden relative">
                <summary class="cursor-pointer list-none rounded border border-white/60 px-3 py-1.5">Menú</summary>
                <nav aria-label="Menú del panel" class="absolute right-0 mt-2 w-64 rounded bg-white text-texto shadow-lg py-2 z-40">
                    @foreach ($items as [$texto, $ruta])
                        <a href="{{ route($ruta) }}"
                           @if (request()->routeIs($ruta)) aria-current="page" @endif
                           class="block px-4 py-2 hover:bg-primario-claro {{ request()->routeIs($ruta) ? 'font-semibold text-primario' : '' }}">
                            {{ $texto }}
                        </a>
                    @endforeach
                    <a href="{{ route('perfil') }}" class="block px-4 py-2 hover:bg-primario-claro">Mi perfil</a>
                    <form method="POST" action="{{ route('logout') }}" class="px-4 py-2">
                        @csrf
                        <button type="submit" class="font-semibold text-error">Salir</button>
                    </form>
                </nav>
            </details>
        </div>
    </header>

    <div class="mx-auto max-w-7xl px-4 py-6 md:grid md:grid-cols-[14rem_1fr] md:gap-6">
        {{-- Menú lateral (escritorio) --}}
        <nav aria-label="Menú del panel" class="hidden md:block">
            <ul class="flex flex-col gap-1">
                @foreach ($items as [$texto, $ruta])
                    <li>
                        <a href="{{ route($ruta) }}"
                           @if (request()->routeIs($ruta)) aria-current="page" @endif
                           class="block rounded px-3 py-2 {{ request()->routeIs($ruta) ? 'bg-primario text-white font-semibold' : 'hover:bg-primario-claro' }}">
                            {{ $texto }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <main id="contenido" class="min-w-0 rounded bg-white p-4 md:p-6 shadow-sm">
            @if ($encabezado)
                <h1 class="font-titulos text-2xl md:text-3xl font-semibold text-primario mb-6">{{ $titulo }}</h1>
            @endif

            {{ $slot }}
        </main>
    </div>
</body>
</html>