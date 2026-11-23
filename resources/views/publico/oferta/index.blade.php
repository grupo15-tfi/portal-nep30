@extends('layouts.publico')

@section('titulo', 'Oferta educativa')

@section('contenido')
    <p class="etiqueta">Formación profesional · Terminalidad primaria</p>
    <h1 class="titulo-pagina">Oferta educativa</h1>
    <p class="subtitulo mb-8">
        Formación profesional, capacitación laboral y terminalidad primaria para jóvenes y adultos. Elegí la sede que te quede más cerca y el turno que te sirva.
    </p>

    {{-- #155: filtro por sede con chips. Son enlaces GET (?sede=ID): funcionan sin JavaScript y sin botón «Filtrar». --}}
    <nav aria-label="Filtrar por sede" class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('oferta') }}" @if (! $sedeId) aria-current="true" @endif
           @class(['boton inline-flex min-h-11 items-center rounded-full border-2 px-4 font-bold no-underline',
                   'border-primario bg-primario text-white hover:text-white' => ! $sedeId,
                   'border-borde bg-white text-texto hover:border-primario hover:text-primario' => $sedeId])>Todas las sedes</a>
        @foreach ($sedes as $sede)
            <a href="{{ route('oferta', ['sede' => $sede->sede_id]) }}" @if ($sedeId == $sede->sede_id) aria-current="true" @endif
               @class(['boton inline-flex min-h-11 items-center rounded-full border-2 px-4 font-bold no-underline',
                       'border-primario bg-primario text-white hover:text-white' => $sedeId == $sede->sede_id,
                       'border-borde bg-white text-texto hover:border-primario hover:text-primario' => $sedeId != $sede->sede_id])>{{ $sede->sede }}<span class="sr-only"> ({{ $sede->localidad }})</span></a>
        @endforeach
    </nav>

    <p role="status" class="text-texto-suave mb-8">
        @if ($total === 0)
            Todavía no hay cursos con inscripción abierta.
        @else
            {{ $total === 1 ? 'Hay 1 curso' : "Hay {$total} cursos" }}
            {{ $sedeId ? 'en esta sede' : 'en '.($porSede->count() === 1 ? '1 sede' : $porSede->count().' sedes') }}.
        @endif
    </p>

    @foreach ($porSede as $cursos)
        <section aria-labelledby="sede-{{ $cursos->first()->sede_id }}" class="mb-10">
            <h2 id="sede-{{ $cursos->first()->sede_id }}" class="mt-0 mb-0 font-titulos text-2xl font-bold tracking-[-0.01em] text-navy-900 md:text-3xl">{{ $cursos->first()->sede }}</h2>
            <p class="mb-5 text-texto-suave">{{ $cursos->first()->localidad }}</p>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($cursos as $curso)
                    <x-oferta.tarjeta :curso="$curso" />
                @endforeach
            </div>
        </section>
    @endforeach

    {{-- Próximas fechas del calendario académico --}}
    <section aria-labelledby="titulo-fechas" class="rounded-3xl bg-fondo-suave p-5 md:p-8">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <h2 id="titulo-fechas" class="m-0 font-titulos text-2xl font-bold text-navy-900 md:text-3xl">Próximas fechas</h2>
            <a href="{{ route('calendario') }}" class="boton boton-secundario bg-white">Ver el calendario académico <svg aria-hidden="true" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
        </div>
        @if ($proximos->isEmpty())
            <p class="text-texto-suave">No hay fechas próximas cargadas.</p>
        @else
            <x-oferta.eventos :eventos="$proximos" />
        @endif
    </section>
@endsection
