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
            ['—', 'Gestión académica'],                     // #156: los grupos se muestran como títulos
            ['Sedes', 'admin.sedes'],
            ['Planes de estudio', 'admin.planes'],
            ['Cursos', 'admin.cursos'],
            ['Profesores', 'admin.profesores.index'],       // HU08
            ['Preinscripciones', 'admin.preinscripciones'], // HU09
            ['Alumnos', 'admin.alumnos'],                   // HU09 (#88)
            ['—', 'Sitio web'],
            ['Noticias', 'admin.noticias'],                 // HU10 (#75)
            ['Contenidos', 'admin.contenidos'],             // HU10 (#75)
            ['Calendario', 'admin.calendario'],             // HU10 (#75)
            ['—', 'Control'],
            ['Auditoría', 'admin.auditoria'],               // HU16
        ],
        'profesor' => [
            ['Inicio', 'profesor.inicio'],
            ['Mis materias', 'profesor.materias'],          // HU08 (#54)
            // HU11 y HU13: notas y asistencia se abren desde cada fila de «Mis materias» (la ruta lleva la materia)
            ['Tareas', 'profesor.tareas'],                  // HU12
        ],
        'alumno' => [
            ['Inicio', 'alumno.inicio'],
            ['Mis notas', 'alumno.notas'],                  // HU11 (#91)
            ['Mi asistencia', 'alumno.asistencia'],         // HU13 (#94)
            ['Tareas', 'alumno.tareas'],                    // HU12
            ['Historial académico', 'alumno.historial'],    // HU17
        ],
        'auditor' => [
            ['Inicio', 'auditor.inicio'],
            ['Resumen por sede', 'auditor.sedes'],          // HU18
            ['Auditoría', 'auditor.auditoria'],             // HU18
        ],
    ];

    // Los títulos de grupo ('—') pasan siempre; las opciones, solo si su ruta existe.
    // Un título que se queda sin opciones debajo no se muestra.
    $items = collect($menus[$rol] ?? [])
        ->filter(fn ($item) => $item[0] === '—' || \Illuminate\Support\Facades\Route::has($item[1]))
        ->values();
    $items = $items->filter(fn ($item, $i) => $item[0] !== '—' || (isset($items[$i + 1]) && $items[$i + 1][0] !== '—'))->values();

    // #156: un ícono por opción (trazos de 24 px), por el final del nombre de la ruta
    $iconos = [
        'inicio'           => 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z',
        'sedes'            => 'M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0zM12 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
        'planes'           => 'M2 4h6a4 4 0 0 1 4 4v13a3 3 0 0 0-3-3H2zM22 4h-6a4 4 0 0 0-4 4v13a3 3 0 0 1 3-3h7z',
        'cursos'           => 'M3 7h18v13H3zM8 7V4h8v3',
        'index'            => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8',
        'preinscripciones' => 'M9 11l3 3 8-8M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9',
        'alumnos'          => 'M22 10 12 5 2 10l10 5zM6 12v5c3 2 9 2 12 0v-5',
        'noticias'         => 'M4 4h13a2 2 0 0 1 2 2v14H6a2 2 0 0 1-2-2zM19 8h1a1 1 0 0 1 1 1v9a2 2 0 0 1-2 2M8 8h7M8 12h7M8 16h4',
        'contenidos'       => 'M12 20h9M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4z',
        'calendario'       => 'M3 5h18v16H3zM3 10h18M8 3v4M16 3v4',
        'auditoria'        => 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z',
        'materias'         => 'M2 4h6a4 4 0 0 1 4 4v13a3 3 0 0 0-3-3H2zM22 4h-6a4 4 0 0 0-4 4v13a3 3 0 0 1 3-3h7z',
        'tareas'           => 'M9 11l3 3 8-8M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9',
        'notas'            => 'M12 2l3 6.3 7 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 7-1z',
        'asistencia'       => 'M3 5h18v16H3zM3 10h18M8 3v4M16 3v4M9 15l2 2 4-4',
        'historial'        => 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M8 13h8M8 17h5',
    ];
    $icono = fn (string $ruta) => $iconos[\Illuminate\Support\Str::afterLast($ruta, '.')] ?? $iconos['inicio'];
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }} · Campus N.E.P. y F.P. N.º 30</title>
    <x-favicon />
    {{-- Sin npm run build la página funciona igual, sin estilos (y las pruebas pasan) --}}
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-fondo-suave text-texto font-sans text-base leading-relaxed">
    <a href="#contenido" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:bg-white focus:px-3 focus:py-2 focus:z-50">
        Saltar al contenido
    </a>

    {{-- Barra superior --}}
    <header class="bg-primario text-white">
        <div class="mx-auto max-w-7xl px-4 py-3 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                {{-- #56: sello del proyecto (versión blanca, para el fondo azul) --}}
                <img src="{{ asset('img/sello-blanco.png') }}" alt="Sello del N.E.P. y F.P. N.º 30" width="48" height="48" class="size-10 shrink-0">
                <div class="min-w-0">
                    <p class="font-titulos text-lg font-semibold leading-tight">Campus N.E.P. y F.P. N.º 30</p>
                    {{-- #156: el rol como chip, separado del nombre --}}
                    <p class="m-0 flex min-w-0 items-center gap-2 text-sm">
                        <span class="truncate opacity-90">{{ $usuario->nombreCompleto() }}</span>
                        <span class="shrink-0 rounded-full bg-white/15 px-2 py-0.5 text-xs font-bold">{{ $nombresRol[$rol] ?? 'Sin rol' }}</span>
                    </p>
                </div>
            </div>

            <div class="hidden md:flex items-center gap-3">
                <a href="{{ route('perfil') }}" class="boton inline-flex min-h-11 items-center rounded-[10px] border border-white/60 px-4 font-bold text-white no-underline hover:bg-white/10 hover:text-white">Mi perfil</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="boton min-h-11 rounded-[10px] border-0 bg-white px-4 font-bold text-primario hover:bg-primario-claro">Salir</button>
                </form>
            </div>

            {{-- Celular: menú desplegable sin JavaScript --}}
            <details class="md:hidden relative">
                <summary class="flex min-h-11 cursor-pointer list-none items-center rounded-[10px] border border-white/60 px-4 font-bold">Menú</summary>
                <nav aria-label="Menú del panel" class="absolute right-0 z-40 mt-2 w-64 rounded-2xl bg-white py-2 text-texto shadow-lg">
                    @foreach ($items as [$texto, $ruta])
                        @if ($texto === '—')
                            <p class="mx-4 mt-3 mb-1 text-xs font-extrabold uppercase tracking-[0.12em] text-texto-suave">{{ $ruta }}</p>
                            @continue
                        @endif
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
            <ul class="m-0 flex list-none flex-col gap-1 p-0">
                @foreach ($items as [$texto, $ruta])
                    @if ($texto === '—')
                        {{-- #156: título de grupo --}}
                        <li class="mx-3 mt-4 mb-1 text-xs font-extrabold uppercase tracking-[0.12em] text-texto-suave">{{ $ruta }}</li>
                        @continue
                    @endif
                    <li>
                        <a href="{{ route($ruta) }}"
                           @if (request()->routeIs($ruta)) aria-current="page" @endif
                           class="flex min-h-11 items-center gap-3 rounded-[10px] px-3 py-2 no-underline {{ request()->routeIs($ruta) ? 'bg-primario text-white font-semibold' : 'text-texto hover:bg-white' }}">
                            <svg aria-hidden="true" class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icono($ruta) }}"/></svg>
                            {{ $texto }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <main id="contenido" class="tarjeta min-w-0 rounded-3xl p-4 md:p-8">
            @if ($encabezado)
                <h1 class="mt-0 mb-6 font-titulos text-2xl font-bold tracking-[-0.01em] text-navy-900 md:text-3xl">{{ $titulo }}</h1>
            @endif

            {{ $slot }}
        </main>
    </div>
</body>
</html>