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

    {{-- #59: encabezado blanco con el sello (aprobado por Aguayo el 26/10). En el celular, nombre corto y botón «Menú». --}}
    <header class="bg-white text-texto border-b border-borde">
        <div class="mx-auto max-w-6xl px-4 py-3 flex items-center justify-between gap-4">
            <a href="{{ route('inicio') }}" class="flex items-center gap-3 min-w-0 text-primario">
                <img src="{{ asset('img/sello.png') }}" alt="Sello del N.E.P. y F.P. N.º 30" width="48" height="48" class="size-11 lg:size-12 shrink-0">
                <span class="min-w-0 leading-tight">
                    <span class="font-titulos text-lg font-semibold lg:hidden">N.E.P. y F.P. N.º 30</span>
                    <span class="hidden lg:block font-titulos text-base font-semibold">Núcleo de Educación Permanente<br>y Formación Profesional N.º 30</span>
                    <span class="block text-xs lg:text-sm text-texto-suave">«Hna. María Delgado Valero»</span>
                </span>
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
            <nav aria-label="Menú principal" class="hidden lg:flex items-center gap-5">
                @foreach ($menu as [$texto, $url, $rutas])
                    @php $actual = $rutas && request()->routeIs(...$rutas); @endphp
                    <a href="{{ $url }}" @if ($actual) aria-current="page" @endif
                       @class(['py-1 hover:text-primario hover:underline focus:underline', 'font-bold text-primario underline decoration-2 underline-offset-8' => $actual])>{{ $texto }}</a>
                @endforeach
                @auth
                    @if ($panel)
                        <a href="{{ $panel }}" class="boton rounded border-2 border-primario px-4 py-1.5 font-semibold text-primario hover:bg-primario-claro">Mi panel</a>
                    @endif
                @endauth
                @guest
                    <a href="{{ route('login') }}" class="boton rounded border-2 border-primario px-4 py-1.5 font-semibold text-primario hover:bg-primario-claro">Ingresar</a>
                @endguest
            </nav>

            {{-- Celular: menú desplegable sin JavaScript. <details> se abre y se cierra con Enter o la barra espaciadora. --}}
            <details id="menu-celular" class="lg:hidden relative">
                <summary class="cursor-pointer list-none rounded border-2 border-primario px-4 py-1.5 font-semibold text-primario hover:bg-primario-claro">Menú</summary>
                <nav aria-label="Menú principal" class="absolute right-0 mt-2 w-60 rounded border border-borde bg-white text-texto shadow-lg py-2 z-40">
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

    {{-- #59: pie en azul oscuro (navy-900), como en el v4,1 --}}
        <footer class="bg-navy-900 text-white">
        <div class="mx-auto max-w-6xl px-4 pt-10 pb-6">
            <div class="flex flex-col gap-8 md:flex-row md:justify-between">
                <div class="flex items-start gap-4">
                    <img src="{{ asset('img/sello-blanco.png') }}" alt="Sello del N.E.P. y F.P. N.º 30" width="48" height="48" class="size-12 shrink-0">
                    <div class="space-y-1 text-sm">
                        <p class="text-base font-bold">Núcleo de Educación Permanente y<br class="hidden md:inline"> Formación Profesional N.º 30</p>
                        <p>«Hna. María Delgado Valero»</p>
                        <p>Funciona en la E.P.E.P. N.º 57 «Gendarme Argentino»</p>
                        <p>Av. de los Constituyentes S/N, B.º Luján, Formosa</p>
                    </div>
                </div>

                <div class="flex gap-16 text-sm">
                    <nav aria-label="El sitio" class="hidden md:block">
                        <p class="mb-3 text-base font-bold">El sitio</p>
                        <ul class="space-y-2">
                            <li><a href="{{ route('inicio') }}" class="hover:underline">Inicio</a></li>
                            <li><a href="{{ route('historia') }}" class="hover:underline">Historia</a></li>
                            <li><a href="{{ route('oferta') }}" class="hover:underline">Oferta educativa</a></li>
                            <li><a href="{{ route('sedes.mapa') }}" class="hover:underline">Sedes</a></li>
                            <li><a href="{{ route('noticias.index') }}" class="hover:underline">Noticias</a></li>
                        </ul>
                    </nav>
                    <nav aria-label="Contacto">
                        <p class="mb-3 text-base font-bold">Contacto</p>
                        <ul class="space-y-2">
                            <li><a href="{{ route('contacto') }}" class="hover:underline">Teléfono y correo</a></li>
                            <li><a href="{{ route('privacidad') }}" class="hover:underline">Política de privacidad</a></li>
                        </ul>
                    </nav>
                </div>
            </div>

            <div class="mt-8 border-t border-white/20 pt-5 text-sm">
                <p>© {{ date('Y') }} N.E.P. y F.P. N.º 30 · Formosa</p>
            </div>
        </div>
    </footer>
</body>
</html>
