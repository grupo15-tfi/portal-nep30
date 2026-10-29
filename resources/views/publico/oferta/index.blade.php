@extends('layouts.publico')

@section('titulo', 'Oferta educativa')

@section('contenido')
    <h1 class="font-titulos text-3xl md:text-4xl font-semibold text-primario mb-2">Oferta educativa</h1>
    <p class="max-w-3xl text-lg mb-6">
        Formación profesional y terminalidad primaria para jóvenes y adultos. Elegí la sede que te quede más cerca y el turno que te sirva.
    </p>

    {{-- Filtro por sede: formulario GET, funciona sin JavaScript --}}
    <form method="GET" action="{{ route('oferta') }}" class="rounded-lg border border-borde p-4 mb-4 flex flex-col sm:flex-row sm:items-end gap-3">
        <div class="flex flex-col gap-1 flex-1 max-w-md mb-0">
            <label for="sede" class="font-semibold">Sede</label>
            <select id="sede" name="sede" class="rounded border border-gray-500 bg-white px-3 py-2 w-full">
                <option value="">Todas las sedes</option>
                @foreach ($sedes as $sede)
                    <option value="{{ $sede->sede_id }}" @selected($sedeId == $sede->sede_id)>{{ $sede->sede }} ({{ $sede->localidad }})</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="px-5 py-2">Filtrar</button>
    </form>

    <p role="status" class="text-texto-suave mb-6">
        @if ($total === 0)
            Todavía no hay cursos con inscripción abierta.
        @else
            {{ $total === 1 ? 'Hay 1 curso' : "Hay {$total} cursos" }}
            {{ $sedeId ? 'en esta sede' : 'en '.($porSede->count() === 1 ? '1 sede' : $porSede->count().' sedes') }}.
        @endif
    </p>

    @foreach ($porSede as $cursos)
        <section aria-labelledby="sede-{{ $cursos->first()->sede_id }}" class="mb-10">
            <h2 id="sede-{{ $cursos->first()->sede_id }}" class="font-titulos text-2xl font-semibold text-primario mt-0 mb-0">{{ $cursos->first()->sede }}</h2>
            <p class="text-texto-suave mb-4">{{ $cursos->first()->localidad }}</p>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($cursos as $curso)
                    <x-oferta.tarjeta :curso="$curso" />
                @endforeach
            </div>
        </section>
    @endforeach

    {{-- Próximas fechas del calendario académico --}}
    <section aria-labelledby="titulo-fechas" class="border-t border-borde pt-8">
        <div class="flex flex-wrap items-baseline justify-between gap-3 mb-4">
            <h2 id="titulo-fechas" class="font-titulos text-2xl font-semibold text-primario m-0">Próximas fechas</h2>
            <a href="{{ route('calendario') }}" class="text-primario underline">Ver el calendario académico</a>
        </div>
        @if ($proximos->isEmpty())
            <p class="text-texto-suave">No hay fechas próximas cargadas.</p>
        @else
            <x-oferta.eventos :eventos="$proximos" />
        @endif
    </section>
@endsection
