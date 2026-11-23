@extends('layouts.publico')

@section('titulo', 'Calendario académico')

@section('contenido')
    <h1 class="titulo-pagina">Calendario académico</h1>
    <p class="subtitulo mb-8">Fechas institucionales: inscripciones, inicio de clases, feriados y cierres. Las fechas de cada curso se ven en el campus.</p>

    <nav aria-label="Cambiar de mes" class="flex items-center justify-between gap-3 mb-4">
        <a href="{{ route('calendario', ['mes' => $anterior]) }}" class="boton whitespace-nowrap rounded border border-primario px-4 min-h-11 inline-flex items-center gap-1 font-semibold text-primario hover:bg-primario-claro" aria-label="Mes anterior"><span aria-hidden="true" class="text-2xl leading-none">‹</span> <span class="hidden sm:inline">Anterior</span></a>
        <h2 class="font-titulos text-2xl font-semibold text-primario text-center m-0">{{ ucfirst($inicio->translatedFormat('F Y')) }}</h2>
        <a href="{{ route('calendario', ['mes' => $siguiente]) }}" class="boton whitespace-nowrap rounded border border-primario px-4 min-h-11 inline-flex items-center gap-1 font-semibold text-primario hover:bg-primario-claro" aria-label="Mes siguiente"><span class="hidden sm:inline">Siguiente</span> <span aria-hidden="true" class="text-2xl leading-none">›</span></a>
    </nav>

    @if ($eventos->isEmpty())
        <p class="text-texto-suave py-6">No hay fechas cargadas para este mes.</p>
    @else
        <x-oferta.eventos :eventos="$eventos" />
    @endif

    <p class="mt-8"><a href="{{ route('oferta') }}" class="boton boton-secundario">Ver la oferta educativa <svg aria-hidden="true" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a></p>
@endsection
