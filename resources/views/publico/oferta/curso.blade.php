@extends('layouts.publico')

@section('titulo', $curso->plan)

@php
    use App\Support\OfertaTexto;
    $preinscripcion = OfertaTexto::urlPreinscripcion($curso);
@endphp

@section('contenido')
    <p class="mb-5"><a href="{{ route('oferta', ['sede' => $curso->sede_id]) }}" class="boton boton-volver">Volver a la oferta</a></p>

    <div class="grid gap-8 lg:grid-cols-[1fr_20rem]">
        <article class="min-w-0">
            <p><span class="chip chip-primario">{{ OfertaTexto::tipo($curso) }}</span></p>
            <h1 class="titulo-pagina mt-3">{{ $curso->plan }}</h1>

            @if ($descripcion)
                <p class="subtitulo mb-8">{{ $descripcion }}</p>
            @endif

            <h2 class="mt-0 mb-4 font-titulos text-2xl font-bold text-navy-900">Qué vas a estudiar</h2>
            @if ($materias->isEmpty())
                <p class="text-texto-suave">Las materias de este plan todavía no están cargadas.</p>
            @else
                <ul class="flex flex-col gap-2 max-w-2xl">
                    @foreach ($materias as $materia)
                        <li class="tarjeta px-5 py-3">
                            {{ $materia->nombre }}
                            @if ($materia->ciclo) <span class="text-texto-suave">· Ciclo {{ $materia->ciclo }}</span> @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </article>

        {{-- Datos de cursada y preinscripción --}}
        <aside class="tarjeta flex h-fit flex-col gap-2 p-6">
            <h2 class="font-semibold text-lg text-texto font-sans m-0">{{ $curso->sede }}</h2>
            <p class="text-texto-suave">{{ $curso->localidad }}</p>
            <p>{{ OfertaTexto::cursada($curso) }}</p>
            <p>Desde los {{ $curso->edad_minima }} años · Período {{ $curso->anio }}</p>

            @if ($curso->con_lista_espera)
                <p><span class="chip chip-aviso chip-punto">{{ OfertaTexto::cupos($curso) }}</span></p>
                <p class="text-texto-suave">Si te anotás, quedás en la lista por orden de llegada y te llaman cuando se libere un lugar.</p>
            @else
                <p><span class="chip chip-exito chip-punto">{{ OfertaTexto::cupos($curso) }}</span></p>
            @endif

            @if ($preinscripcion)
                <a href="{{ $preinscripcion }}" class="boton boton-principal mt-2 w-full">
                    {{ $curso->con_lista_espera ? 'Anotarme en la lista de espera' : 'Preinscribirme' }}
                </a>
            @else
                <p class="mt-2 rounded-xl bg-primario-claro px-4 py-3">La preinscripción en línea va a estar disponible pronto. Mientras tanto, acercate a la sede.</p>
            @endif

            <p class="text-texto-suave text-sm mt-1">Para inscribirte vas a necesitar fotocopia del DNI.</p>
        </aside>
    </div>
@endsection
