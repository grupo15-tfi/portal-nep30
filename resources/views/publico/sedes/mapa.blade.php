@extends('layouts.publico')

@section('titulo', 'Sedes y anexos')

{{-- Leaflet se carga solo en esta página (#49) --}}
@push('vite')
    @vite('resources/js/mapa-sedes.js')
@endpush

@php use App\Http\Controllers\SedesMapaController; @endphp

@section('contenido')
    <h1 class="font-titulos text-3xl md:text-4xl font-semibold text-primario mb-2">Sedes y anexos</h1>
    <p class="max-w-3xl text-lg mb-6">
        La sede central funciona en la E.P.E.P. N.º 57 del barrio Luján y hay anexos en distintos barrios de Formosa.
        Encontrá el que te quede más cerca.
    </p>

    <div id="sedes"
         data-url-datos="{{ route('sedes.datos') }}"
         data-url-cercanas="{{ route('sedes.cercanas') }}"
         @if ($urlLocalidades) data-url-localidades="{{ $urlLocalidades }}" @endif
         data-url-oferta="{{ route('oferta') }}"
         class="grid gap-6 lg:grid-cols-[minmax(0,24rem)_minmax(0,1fr)] lg:items-start">

        <div class="flex flex-col gap-4 min-w-0">
            {{-- Buscar la sede más cercana: primero la ubicación del celular, si no, el domicilio --}}
            <section aria-labelledby="titulo-buscar" class="rounded-lg border border-borde bg-white p-4 md:p-5">
                <h2 id="titulo-buscar" class="text-xl font-semibold mt-0 mb-1">¿Qué sede te queda más cerca?</h2>
                <p class="text-texto-suave text-sm mb-4">Usamos tu ubicación o tu dirección solo para calcular la distancia. No la guardamos.</p>

                <button type="button" id="usar-ubicacion" hidden
                        class="w-full min-h-11 rounded bg-primario px-4 py-2.5 font-semibold text-white hover:bg-primario/90">
                    Usar mi ubicación actual
                </button>

                <p id="separador-domicilio" hidden class="my-4 flex items-center gap-3 text-sm text-texto-suave before:h-px before:flex-1 before:bg-borde after:h-px after:flex-1 after:bg-borde">
                    o escribí tu dirección
                </p>

                <form id="buscar-domicilio" class="flex flex-col gap-3" novalidate>
                    <div class="flex flex-col gap-1 mb-0">
                        <label for="domicilio" class="font-semibold">Dirección</label>
                        <input id="domicilio" name="domicilio" type="text" maxlength="150" autocomplete="street-address"
                               placeholder="Ej.: Av. 25 de Mayo 1200" aria-describedby="sedes-estado">
                    </div>
                    <div id="campo-localidad" hidden class="flex flex-col gap-1 mb-0">
                        <label for="localidad" class="font-semibold">Localidad</label>
                        <select id="localidad" name="localidad"></select>
                    </div>
                    <button type="submit" class="min-h-11 w-full">Buscar</button>
                </form>

                {{-- Mensajes para lectores de pantalla y para todos: buscando, errores, resultado --}}
                <p id="sedes-estado" role="status" aria-live="polite" class="mt-3 mb-0 text-sm font-normal text-texto empty:hidden"></p>

                <noscript>
                    <p class="mt-3 mb-0 text-sm">Para ver el mapa y buscar la sede más cercana hace falta JavaScript. Abajo tenés la lista de sedes.</p>
                </noscript>
            </section>

            {{-- Lista de sedes: se ve aunque no cargue el mapa; con una búsqueda se ordena por distancia --}}
            <section aria-labelledby="titulo-lista">
                <h2 id="titulo-lista" class="sr-only">Lista de sedes</h2>
                <ol id="lista-sedes" class="flex flex-col gap-3 list-none p-0 m-0">
                    @foreach ($sedes as $sede)
                        <li data-sede-id="{{ $sede->id }}" class="sede rounded-lg border border-borde bg-white p-4">
                            <p class="sede-cercana-etiqueta hidden text-sm font-bold uppercase tracking-wide text-exito mb-1">Tu sede más cercana</p>
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="text-lg font-semibold leading-snug m-0">{{ $sede->nombre }}</h3>
                                <span class="shrink-0 rounded-full bg-primario-claro px-2.5 py-0.5 text-sm font-semibold text-primario">{{ SedesMapaController::tipo($sede->tipo) }}</span>
                            </div>
                            <p class="text-texto-suave m-0">{{ $sede->direccion }}@if ($sede->localidad), {{ $sede->localidad }}@endif</p>
                            @if ($sede->telefono)
                                <p class="text-texto-suave m-0">Teléfono: <a href="tel:{{ preg_replace('/\D/', '', $sede->telefono) }}" class="text-primario underline">{{ $sede->telefono }}</a></p>
                            @endif
                            <p class="m-0 text-sm text-texto-suave">
                                {{ count($sede->cursos) === 1 ? '1 curso este año' : count($sede->cursos).' cursos este año' }}
                            </p>
                            <p data-distancia class="m-0 font-semibold text-primario empty:hidden"></p>
                            <p class="mt-2 mb-0 flex flex-wrap gap-x-4 gap-y-1">
                                <a href="{{ route('oferta', ['sede' => $sede->id]) }}" class="font-semibold text-primario underline">Ver cursos de esta sede</a>
                                <a href="https://www.google.com/maps/dir/?api=1&amp;destination={{ $sede->latitud }},{{ $sede->longitud }}"
                                   target="_blank" rel="noopener" class="font-semibold text-primario underline">
                                    Cómo llegar<span class="sr-only"> (se abre en otra pestaña)</span>
                                </a>
                                <button type="button" data-ver-en-mapa hidden
                                        class="border-0 bg-transparent p-0 font-semibold text-primario underline hover:bg-transparent">Ver en el mapa</button>
                            </p>
                        </li>
                    @endforeach
                </ol>
                @if (count($sedes) === 0)
                    <p class="text-texto-suave">Todavía no hay sedes cargadas.</p>
                @endif
            </section>
        </div>

        {{-- Mapa (Leaflet + OpenStreetMap). En el celular va debajo de la lista. --}}
        <section aria-labelledby="titulo-mapa" class="min-w-0 lg:sticky lg:top-4">
            <h2 id="titulo-mapa" class="sr-only">Mapa de sedes</h2>
            <div id="mapa-sedes" class="h-80 md:h-[32rem] w-full rounded-lg border border-borde bg-primario-claro"
                 role="region" aria-label="Mapa de las sedes. Con el teclado: flechas para moverlo, + y − para el zoom, Tab para ir a cada sede."></div>
            <p class="mt-2 text-sm text-texto-suave">Las distancias son aproximadas, en línea recta.</p>
        </section>
    </div>
@endsection
