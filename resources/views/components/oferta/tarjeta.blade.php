{{--
    Tarjeta de un curso de la oferta pública (HU02). $curso es una fila de v_oferta_publica.
    Uso: <x-oferta.tarjeta :curso="$curso" />
--}}
@props(['curso'])

@php
    use App\Support\OfertaTexto;
    $preinscripcion = OfertaTexto::urlPreinscripcion($curso);
@endphp

<article data-curso="{{ $curso->curso_id }}" class="rounded-lg border border-borde bg-white p-4 flex flex-col gap-1.5">
    <p>
        <span class="inline-block rounded-full bg-primario-claro text-primario text-sm font-semibold px-2.5 py-0.5">{{ OfertaTexto::tipo($curso) }}</span>
    </p>
    <h3 class="font-semibold text-lg leading-snug m-0">
        <a href="{{ route('oferta.curso', $curso->curso_id) }}" class="hover:underline focus:underline">{{ $curso->plan }}</a>
    </h3>
    <p class="text-texto-suave">{{ OfertaTexto::cursada($curso) }}</p>

    @if ($curso->con_lista_espera)
        <p class="font-semibold text-amber-700">{{ OfertaTexto::cupos($curso) }}</p>
    @else
        <p class="font-semibold text-exito">{{ OfertaTexto::cupos($curso) }}</p>
    @endif

    <p class="mt-auto pt-1 flex flex-wrap gap-x-4 gap-y-1">
        <a href="{{ route('oferta.curso', $curso->curso_id) }}" class="font-semibold text-primario underline">Ver detalle</a>
        @if ($preinscripcion)
            <a href="{{ $preinscripcion }}" class="font-semibold text-primario underline">
                {{ $curso->con_lista_espera ? 'Anotarme en la lista de espera' : 'Preinscribirme' }} →
            </a>
        @endif
    </p>
</article>
