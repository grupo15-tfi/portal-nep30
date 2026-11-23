@extends('layouts.publico')

@section('titulo', 'Inicio')

{{--
    #60: Inicio según «Visión a futuro» (#36), aprobado por Aguayo el 26/10 sin el lema.
    El título y el texto de la portada salen de la sección «inicio» (editable desde el panel, HU10).
--}}
@php
    // «Encontrar mi sede más cercana» lleva al mapa (HU03, #49); si todavía no está integrado, a la oferta
    $urlSedes = Route::has('sedes.mapa') ? route('sedes.mapa') : route('oferta');
@endphp

@section('contenido')
    {{-- Portada. #155: dos columnas; a la derecha la foto o el video (HU10) y, si no hay, el sello sobre azul oscuro. --}}
    <section aria-labelledby="titulo-inicio" class="mb-14 grid items-center gap-8 md:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)] md:gap-12">
        <div>
            <p class="etiqueta">Formación profesional · Educación permanente · Formosa</p>
            <h1 id="titulo-inicio" class="titulo-pagina">{{ $seccion->titulo }}</h1>
            <div class="subtitulo mb-7">{!! nl2br(e($seccion->contenido)) !!}</div>
            <p class="mb-0 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                <a href="{{ route('oferta') }}" class="boton boton-principal">
                    Ver oferta educativa
                    <svg aria-hidden="true" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
                <a href="{{ $urlSedes }}" class="boton boton-secundario">
                    <svg aria-hidden="true" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    Encontrar mi sede más cercana
                </a>
            </p>
        </div>
        {{-- Foto real de la sede, con autorización (Ley 25.326), y video de YouTube (HU10) --}}
        @if ($seccion->imagen_ruta || $seccion->video_url)
            <div class="flex flex-col gap-4">
                @if ($seccion->imagen_ruta)
                    <img src="{{ asset('storage/'.$seccion->imagen_ruta) }}" alt="Sede del N.E.P. y F.P. N.º 30"
                         width="640" height="400" decoding="async" class="aspect-[16/10] w-full rounded-3xl object-cover">
                @endif
                <x-video-youtube :url="$seccion->video_url" :titulo="$seccion->titulo" class="mb-0" />
            </div>
        @else
            <div class="relative flex min-h-72 flex-col items-center justify-center gap-4 overflow-hidden rounded-3xl bg-navy-900 p-8 md:min-h-96" aria-hidden="true">
                <span class="absolute -top-28 -right-36 size-[32rem] rounded-full border border-white/10"></span>
                <span class="absolute -bottom-40 -left-28 size-80 rounded-full border border-white/10"></span>
                <img src="{{ asset('img/sello-blanco.png') }}" alt="" width="144" height="144" class="size-36 rounded-full ring-8 ring-white/10">
            </div>
        @endif
    </section>

    {{-- Los 4 valores --}}
    <section aria-labelledby="titulo-valores" class="mb-14 rounded-3xl bg-fondo-suave p-5 md:p-8">
        <h2 id="titulo-valores" class="sr-only">Qué ofrecemos</h2>
        <ul class="m-0 grid list-none gap-4 p-0 sm:grid-cols-2 lg:grid-cols-4 md:gap-5">
            @foreach ([
                ['Formación para el trabajo', 'Cursos con salida laboral, dictados por instructores de la institución.', 'M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z', 'bg-primario-claro text-primario'],
                ['Terminá la primaria', 'Terminalidad primaria para adultos: alfabetización, formación integral y por proyectos.', 'M2 4h6a4 4 0 0 1 4 4v13a3 3 0 0 0-3-3H2zM22 4h-6a4 4 0 0 0-4 4v13a3 3 0 0 1 3-3h7z', 'bg-exito-claro text-exito'],
                ['Sedes cerca de tu barrio', 'Sede central en el barrio Luján y anexos en distintos barrios de Formosa.', 'M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0zM12 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6z', 'bg-amber-100 text-amber-700'],
                ['Preinscripción en línea', 'Elegí curso, sede y turno desde el celular. Si no hay cupo, quedás en lista de espera.', 'M7 2h10a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zM12 18h.01', 'bg-primario-claro text-primario'],
            ] as [$titulo, $texto, $icono, $colores])
                <li class="tarjeta flex flex-col gap-2 p-5 md:p-6">
                    <span class="inline-flex size-12 items-center justify-center rounded-xl {{ $colores }}" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icono }}"/></svg>
                    </span>
                    <h3 class="m-0 mt-1 font-titulos text-xl font-semibold text-navy-900">{{ $titulo }}</h3>
                    <p class="m-0 text-texto-suave">{{ $texto }}</p>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- ¿Qué querés estudiar? (cursos vigentes de HU02) --}}
    <section aria-labelledby="titulo-cursos" class="mb-14">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="etiqueta">Oferta educativa {{ $anio }}</p>
                <h2 id="titulo-cursos" class="m-0 font-titulos text-3xl font-bold tracking-[-0.02em] text-navy-900 md:text-4xl">¿Qué querés estudiar?</h2>
            </div>
            <a href="{{ route('oferta') }}" class="boton boton-secundario">
                Ver toda la oferta por sede
                <svg aria-hidden="true" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </a>
        </div>

        @if ($cursos->isEmpty())
            <p class="text-texto-suave">Todavía no hay cursos con inscripción abierta.</p>
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($cursos as $curso)
                    <x-oferta.tarjeta :curso="$curso" />
                @endforeach
            </div>
        @endif

        @if ($terminalidad->isNotEmpty())
            <div class="tarjeta mt-5 flex flex-col gap-3 p-5 md:flex-row md:items-center md:gap-5 md:p-6">
                <span class="chip chip-exito self-start md:self-center">Terminalidad primaria para adultos</span>
                <p class="m-0 text-texto-suave md:flex-1">{{ $terminalidad->implode(' · ') }}</p>
                <a href="{{ route('oferta') }}" class="boton boton-secundario">Ver sedes y horarios</a>
            </div>
        @endif
    </section>

    {{-- Últimas noticias --}}
    <section aria-labelledby="titulo-noticias" class="mb-14">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="etiqueta">Actualidad</p>
                <h2 id="titulo-noticias" class="m-0 font-titulos text-3xl font-bold tracking-[-0.02em] text-navy-900 md:text-4xl">Últimas noticias</h2>
            </div>
            <a href="{{ route('noticias.index') }}" class="boton boton-secundario">
                Ver todas las noticias
                <svg aria-hidden="true" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </a>
        </div>

        @if ($noticias->isEmpty())
            <p class="text-texto-suave">Todavía no hay noticias publicadas.</p>
        @else
            <div class="grid gap-5 md:grid-cols-3">
                @foreach ($noticias as $noticia)
                    <x-noticias.tarjeta :noticia="$noticia" />
                @endforeach
            </div>
        @endif
    </section>

    {{-- Franja «Preinscribite en línea»: lleva a la oferta, donde cada curso tiene su botón de preinscripción (HU04) --}}
    <section aria-labelledby="titulo-preinscripcion" class="relative overflow-hidden rounded-3xl bg-primario px-6 py-10 text-white md:flex md:items-center md:justify-between md:gap-8 md:px-12">
        <span class="absolute -top-32 -right-24 size-96 rounded-full border border-white/10" aria-hidden="true"></span>
        <div class="relative">
            <p class="etiqueta text-[#C9D6F2]">Preinscripción {{ $anio }}</p>
            <h2 id="titulo-preinscripcion" class="m-0 mb-3 font-titulos text-3xl font-bold tracking-[-0.02em] text-white md:text-4xl">Preinscribite en línea</h2>
            <p class="m-0 max-w-2xl text-lg text-[#DCE3F5]">Elegí curso, sede y turno. Te confirmamos por correo y, si no hay cupo, quedás en la lista de espera por orden de llegada.</p>
        </div>
        <a href="{{ route('oferta') }}" class="boton boton-principal relative mt-6 shrink-0 bg-white text-navy-900 hover:bg-primario-claro hover:text-navy-900 md:mt-0">
            Empezar preinscripción
            <svg aria-hidden="true" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
    </section>
@endsection
