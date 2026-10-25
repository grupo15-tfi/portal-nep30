@extends('layouts.publico')

@section('titulo', 'Calendario académico')

@section('contenido')
    <h1 class="font-titulos text-3xl md:text-4xl font-semibold text-primario mb-2">Calendario académico</h1>
    <p class="max-w-3xl text-lg mb-6">Fechas institucionales: inscripciones, inicio de clases, feriados y cierres. Las fechas de cada curso se ven en el campus.</p>

    <nav aria-label="Cambiar de mes" class="flex items-center justify-between gap-3 mb-4">
        <a href="{{ route('calendario', ['mes' => $anterior]) }}" class="whitespace-nowrap rounded border border-primario px-4 min-h-11 inline-flex items-center gap-1 font-semibold text-primario hover:bg-primario-claro" aria-label="Mes anterior"><span aria-hidden="true" class="text-2xl leading-none">‹</span> <span class="hidden sm:inline">Anterior</span></a>
        <h2 class="font-titulos text-2xl font-semibold text-primario text-center m-0">{{ ucfirst($inicio->translatedFormat('F Y')) }}</h2>
        <a href="{{ route('calendario', ['mes' => $siguiente]) }}" class="whitespace-nowrap rounded border border-primario px-4 min-h-11 inline-flex items-center gap-1 font-semibold text-primario hover:bg-primario-claro" aria-label="Mes siguiente"><span class="hidden sm:inline">Siguiente</span> <span aria-hidden="true" class="text-2xl leading-none">›</span></a>
    </nav>

    @if ($eventos->isEmpty())
        <p class="text-texto-suave py-6">No hay fechas cargadas para este mes.</p>
    @else
        <x-oferta.eventos :eventos="$eventos" />
    @endif

    <p class="mt-8"><a href="{{ route('oferta') }}" class="text-primario underline">Ver la oferta educativa</a></p>
@endsection
