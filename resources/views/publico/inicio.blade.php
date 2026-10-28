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
    {{-- Portada --}}
    <section aria-labelledby="titulo-inicio" class="mb-12 rounded-xl bg-primario-claro px-5 py-8 md:px-10 md:py-12">
        <div @class(['grid gap-8 items-center', 'md:grid-cols-2' => $seccion->imagen_ruta || $seccion->video_url])>
            <div>
                <p class="mb-3 text-sm font-bold uppercase tracking-wider text-primario">Formación profesional · Educación permanente · Formosa</p>
                <h1 id="titulo-inicio" class="font-titulos text-3xl md:text-5xl font-semibold leading-tight text-primario mb-4">
                    {{ $seccion->titulo }}
                </h1>
                <div class="max-w-2xl text-lg mb-6">{!! nl2br(e($seccion->contenido)) !!}</div>
                <p class="flex flex-col gap-3 sm:flex-row sm:flex-wrap mb-0">
                    <a href="{{ route('oferta') }}" class="inline-flex min-h-11 items-center justify-center rounded bg-primario px-5 py-2.5 font-semibold text-white hover:bg-primario/90">Ver oferta educativa</a>
                    <a href="{{ $urlSedes }}" class="inline-flex min-h-11 items-center justify-center rounded border-2 border-primario bg-white px-5 py-2 font-semibold text-primario hover:bg-primario-claro">Encontrar mi sede más cercana</a>
                </p>
            </div>
            {{-- Foto real de la sede, con autorización (Ley 25.326), y video de YouTube (HU10). Sin ninguno, no se muestra nada. --}}
            @if ($seccion->imagen_ruta || $seccion->video_url)
                <div class="flex flex-col gap-4">
                    @if ($seccion->imagen_ruta)
                        <img src="{{ asset('storage/'.$seccion->imagen_ruta) }}" alt="Sede del N.E.P. y F.P. N.º 30"
                             width="640" height="400" decoding="async" class="w-full rounded-lg object-cover aspect-[16/10]">
                    @endif
                    <x-video-youtube :url="$seccion->video_url" :titulo="$seccion->titulo" class="mb-0" />
                </div>
            @endif
        </div>
    </section>

    {{-- Los 4 valores --}}
    <section aria-labelledby="titulo-valores" class="mb-12">
        <h2 id="titulo-valores" class="sr-only">Qué ofrecemos</h2>
        <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4 list-none p-0 m-0">
            @foreach ([
                ['Formación para el trabajo', 'Cursos con salida laboral, dictados por instructores de la institución.', 'M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z'],
                ['Terminá la primaria', 'Terminalidad primaria para adultos: alfabetización, formación integral y por proyectos.', 'M2 4h6a4 4 0 0 1 4 4v13a3 3 0 0 0-3-3H2zM22 4h-6a4 4 0 0 0-4 4v13a3 3 0 0 1 3-3h7z'],
                ['Sedes cerca de tu barrio', 'Sede central en el barrio Luján y anexos en distintos barrios de Formosa.', 'M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0zM12 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6z'],
                ['Preinscripción en línea', 'Elegí curso, sede y turno desde el celular. Si no hay cupo, quedás en lista de espera.', 'M7 2h10a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zM12 18h.01'],
            ] as [$titulo, $texto, $icono])
                <li class="flex flex-col gap-2">
                    <span class="inline-flex size-12 items-center justify-center rounded-full bg-primario-claro text-primario" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icono }}"/></svg>
                    </span>
                    <h3 class="m-0 text-lg font-semibold text-primario">{{ $titulo }}</h3>
                    <p class="m-0 text-texto-suave">{{ $texto }}</p>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- ¿Qué querés estudiar? (cursos vigentes de HU02) --}}
    <section aria-labelledby="titulo-cursos" class="mb-12">
        <p class="mb-1 text-sm font-bold uppercase tracking-wider text-primario">Oferta educativa {{ $anio }}</p>
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <h2 id="titulo-cursos" class="m-0 font-titulos text-2xl md:text-3xl font-semibold text-primario">¿Qué querés estudiar?</h2>
            <a href="{{ route('oferta') }}" class="inline-flex min-h-11 items-center rounded border-2 border-primario px-4 py-1.5 font-semibold text-primario hover:bg-primario-claro">Ver toda la oferta por sede</a>
        </div>

        @if ($cursos->isEmpty())
            <p class="text-texto-suave">Todavía no hay cursos con inscripción abierta.</p>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($cursos as $curso)
                    <x-oferta.tarjeta :curso="$curso" />
                @endforeach
            </div>
        @endif

        @if ($terminalidad->isNotEmpty())
            <a href="{{ route('oferta') }}" class="mt-4 flex flex-col gap-1 rounded-lg border-2 border-primario bg-white p-4 hover:bg-primario-claro sm:flex-row sm:items-center sm:gap-4">
                <span class="font-semibold text-primario">Terminalidad primaria para adultos</span>
                <span class="text-texto-suave sm:flex-1">{{ $terminalidad->implode(' · ') }}</span>
                <span class="font-semibold text-primario">Ver sedes y horarios →</span>
            </a>
        @endif
    </section>

    {{-- Últimas noticias --}}
    <section aria-labelledby="titulo-noticias" class="mb-12">
        <p class="mb-1 text-sm font-bold uppercase tracking-wider text-primario">Actualidad</p>
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <h2 id="titulo-noticias" class="m-0 font-titulos text-2xl md:text-3xl font-semibold text-primario">Últimas noticias</h2>
            <a href="{{ route('noticias.index') }}" class="inline-flex min-h-11 items-center rounded border-2 border-primario px-4 py-1.5 font-semibold text-primario hover:bg-primario-claro">Ver todas las noticias</a>
        </div>

        @if ($noticias->isEmpty())
            <p class="text-texto-suave">Todavía no hay noticias publicadas.</p>
        @else
            <div class="grid gap-4 md:grid-cols-3">
                @foreach ($noticias as $noticia)
                    <x-noticias.tarjeta :noticia="$noticia" />
                @endforeach
            </div>
        @endif
    </section>

    {{-- Franja «Preinscribite en línea»: lleva a la oferta, donde cada curso tiene su botón de preinscripción (HU04) --}}
    <section aria-labelledby="titulo-preinscripcion" class="rounded-xl bg-primario px-5 py-8 text-white md:flex md:items-center md:justify-between md:gap-8 md:px-10">
        <div>
            <h2 id="titulo-preinscripcion" class="m-0 mb-2 text-white font-titulos text-2xl md:text-3xl font-semibold">Preinscribite en línea</h2>
            <p class="m-0 max-w-2xl">Elegí curso, sede y turno. Te confirmamos por correo y, si no hay cupo, quedás en la lista de espera por orden de llegada.</p>
        </div>
        <a href="{{ route('oferta') }}" class="mt-5 inline-flex min-h-11 shrink-0 items-center justify-center rounded bg-white px-5 py-2.5 font-semibold text-primario hover:bg-primario-claro md:mt-0">Empezar preinscripción</a>
    </section>
@endsection
