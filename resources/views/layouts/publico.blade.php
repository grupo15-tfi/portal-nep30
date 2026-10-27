<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Inicio') · N.E.P. y F.P. N.º 30</title>
    <x-favicon />
    <meta name="description" content="Núcleo de Educación Permanente y Formación Profesional N.º 30 «Hna. María Delgado Valero» (Formosa).">

    {{-- Si todavía no se compiló con npm run build, la página se ve sin estilos pero funciona (y las pruebas pasan) --}}
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        {{-- Recursos de una sola página (ej.: Leaflet en el mapa de sedes, #49) --}}
        @stack('vite')
    @endif
</head>
<body class="min-h-screen flex flex-col bg-white text-texto font-sans text-base leading-relaxed">
    {{-- Accesibilidad: saltar el menú con el teclado --}}
    <a href="#contenido" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:bg-white focus:px-3 focus:py-2 focus:z-50">
        Saltar al contenido
    </a>

    <header class="bg-primario text-white">
        <div class="mx-auto max-w-6xl px-4 py-3 flex items-center justify-between gap-4">
            <a href="{{ route('inicio') }}" class="flex items-center gap-3 font-titulos text-lg font-semibold leading-tight min-w-0">
                {{-- #56: sello del proyecto (versión blanca, para el fondo azul) --}}
                <img src="{{ asset('img/sello-blanco.png') }}" alt="Sello del N.E.P. y F.P. N.º 30" width="48" height="48" class="size-10 md:size-12 shrink-0">
                <span class="min-w-0">N.E.P. y F.P. N.º 30</span>
            </a>

            @php
                // [texto, url, rutas en las que el enlace queda marcado como la sección actual]
                $menu = [
                    ['Inicio', route('inicio'), ['inicio']],
                    ['Historia', route('historia'), ['historia']],
                    ['Oferta educativa', route('oferta'), ['oferta', 'oferta.curso', 'calendario']], // HU02 (#44); el calendario se abre desde la oferta
                    ['Sedes', route('sedes.mapa'), ['sedes.mapa']], // HU03 (#49)
                    ['Noticias', route('noticias.index'), ['noticias.*']],
                    ['Contacto', route('contacto'), ['contacto']],
                ];

                // #32: con sesión iniciada se muestra «Mi panel» (al inicio del panel de su rol) en lugar de «Ingresar».
                // Un usuario sin rol no tiene panel: no se muestra ninguno de los dos.
                $panel = auth()->user()?->rutaPanel();
            @endphp

            {{-- Escritorio --}}
            <nav aria-label="Menú principal" class="hidden md:flex items-center gap-5">
                @foreach ($menu as [$texto, $url, $rutas])
                    @php $actual = $rutas && request()->routeIs(...$rutas); @endphp
                    <a href="{{ $url }}" @if ($actual) aria-current="page" @endif
                       @class(['hover:underline focus:underline', 'font-bold underline decoration-2 underline-offset-8' => $actual])>{{ $texto }}</a>
                @endforeach
                @auth
                    @if ($panel)
                        <a href="{{ $panel }}" class="rounded bg-white text-primario px-3 py-1.5 font-semibold">Mi panel</a>
                    @endif
                @endauth
                @guest
                    <a href="{{ route('login') }}" class="rounded bg-white text-primario px-3 py-1.5 font-semibold">Ingresar</a>
                @endguest
            </nav>

            {{-- Celular: menú desplegable sin JavaScript --}}
            <details class="md:hidden relative">
                <summary class="cursor-pointer list-none rounded border border-white/60 px-3 py-1.5">Menú</summary>
                <nav aria-label="Menú principal" class="absolute right-0 mt-2 w-56 rounded bg-white text-texto shadow-lg py-2 z-40">
                    @foreach ($menu as [$texto, $url, $rutas])
                        @php $actual = $rutas && request()->routeIs(...$rutas); @endphp
                        <a href="{{ $url }}" @if ($actual) aria-current="page" @endif
                           @class(['block px-4 py-2 hover:bg-primario-claro', 'border-l-4 border-primario bg-primario-claro font-bold text-primario' => $actual])>{{ $texto }}</a>
                    @endforeach
                    @auth
                        @if ($panel)
                            <a href="{{ $panel }}" class="block px-4 py-2 font-semibold text-primario hover:bg-primario-claro">Mi panel</a>
                        @endif
                    @endauth
                    @guest
                        <a href="{{ route('login') }}" class="block px-4 py-2 font-semibold text-primario hover:bg-primario-claro">Ingresar</a>
                    @endguest
                </nav>
            </details>
        </div>
    </header>

    <main id="contenido" class="flex-1 mx-auto w-full max-w-6xl px-4 py-8">
        @yield('contenido')
    </main>

    <footer class="bg-primario text-white">
        <div class="mx-auto max-w-6xl px-4 py-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div class="flex items-center gap-3">
                <img src="{{ asset('img/sello-blanco.png') }}" alt="Sello del N.E.P. y F.P. N.º 30" width="48" height="48" class="size-12 shrink-0">
                <p>Núcleo de Educación Permanente y Formación Profesional N.º 30 · Formosa</p>
            </div>
            <nav aria-label="Enlaces del pie" class="flex gap-4">
                <a href="{{ route('contacto') }}" class="underline">Contacto</a>
                <a href="{{ route('privacidad') }}" class="underline">Política de privacidad</a>
            </nav>
        </div>
    </footer>
</body>
</html>
