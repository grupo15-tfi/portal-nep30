@extends('layouts.publico')

@section('titulo', $curso->plan)

@php
    use App\Support\OfertaTexto;
    $preinscripcion = OfertaTexto::urlPreinscripcion($curso);
@endphp

@section('contenido')
    <p class="mb-3"><a href="{{ route('oferta', ['sede' => $curso->sede_id]) }}" class="text-primario underline">« Volver a la oferta</a></p>

    <div class="grid gap-8 lg:grid-cols-[1fr_20rem]">
        <article class="min-w-0">
            <p><span class="inline-block rounded-full bg-primario-claro text-primario text-sm font-semibold px-2.5 py-0.5">{{ OfertaTexto::tipo($curso) }}</span></p>
            <h1 class="font-titulos text-3xl md:text-4xl font-semibold text-primario mt-2 mb-4">{{ $curso->plan }}</h1>

            @if ($descripcion)
                <p class="max-w-3xl text-lg mb-6">{{ $descripcion }}</p>
            @endif

            <h2 class="font-titulos text-2xl font-semibold text-primario mt-0 mb-3">Qué vas a estudiar</h2>
            @if ($materias->isEmpty())
                <p class="text-texto-suave">Las materias de este plan todavía no están cargadas.</p>
            @else
                <ul class="flex flex-col gap-2 max-w-2xl">
                    @foreach ($materias as $materia)
                        <li class="rounded border border-borde px-4 py-2">
                            {{ $materia->nombre }}
                            @if ($materia->ciclo) <span class="text-texto-suave">· Ciclo {{ $materia->ciclo }}</span> @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </article>

        {{-- Datos de cursada y preinscripción --}}
        <aside class="rounded-lg border border-borde p-5 flex flex-col gap-2 h-fit">
            <h2 class="font-semibold text-lg text-texto font-sans m-0">{{ $curso->sede }}</h2>
            <p class="text-texto-suave">{{ $curso->localidad }}</p>
            <p>{{ OfertaTexto::cursada($curso) }}</p>
            <p>Desde los {{ $curso->edad_minima }} años · Período {{ $curso->anio }}</p>

            @if ($curso->con_lista_espera)
                <p class="font-semibold text-amber-700">{{ OfertaTexto::cupos($curso) }}</p>
                <p class="text-texto-suave">Si te anotás, quedás en la lista por orden de llegada y te llaman cuando se libere un lugar.</p>
            @else
                <p class="font-semibold text-exito">{{ OfertaTexto::cupos($curso) }}</p>
            @endif

            @if ($preinscripcion)
                <a href="{{ $preinscripcion }}" class="mt-2 rounded bg-primario text-white text-center font-semibold px-4 py-2.5 hover:bg-primario/90">
                    {{ $curso->con_lista_espera ? 'Anotarme en la lista de espera' : 'Preinscribirme' }}
                </a>
            @else
                <p class="mt-2 rounded bg-primario-claro px-3 py-2">La preinscripción en línea va a estar disponible pronto. Mientras tanto, acercate a la sede.</p>
            @endif

            <p class="text-texto-suave text-sm mt-1">Para inscribirte vas a necesitar fotocopia del DNI.</p>
        </aside>
    </div>
@endsection
