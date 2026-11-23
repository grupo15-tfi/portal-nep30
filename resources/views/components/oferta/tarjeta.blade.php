{{--
    Tarjeta de un curso de la oferta pública (HU02). $curso es una fila de v_oferta_publica.
    #155: tarjeta de la base (#154), cupos como chip de color y la preinscripción como botón principal.
    Uso: <x-oferta.tarjeta :curso="$curso" />
--}}
@props(['curso'])

@php
    use App\Support\OfertaTexto;
    $preinscripcion = OfertaTexto::urlPreinscripcion($curso);
@endphp

<article data-curso="{{ $curso->curso_id }}" class="tarjeta flex flex-col gap-3 p-5 md:p-6">
    <p class="m-0">
        <span class="chip chip-primario">{{ OfertaTexto::tipo($curso) }}</span>
    </p>
    <h3 class="m-0 font-titulos text-xl font-semibold leading-snug text-navy-900">
        <a href="{{ route('oferta.curso', $curso->curso_id) }}" class="text-inherit no-underline hover:underline focus:underline">{{ $curso->plan }}</a>
    </h3>
    <p class="m-0 flex items-start gap-2 text-texto-suave">
        <svg aria-hidden="true" class="mt-1 size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
        <span>{{ OfertaTexto::cursada($curso) }}</span>
    </p>
    <p class="m-0">
        <span @class(['chip chip-punto', 'chip-aviso' => $curso->con_lista_espera, 'chip-exito' => ! $curso->con_lista_espera])>{{ OfertaTexto::cupos($curso) }}</span>
    </p>

    <p class="mt-auto mb-0 flex flex-col gap-2 pt-2">
        @if ($preinscripcion)
            <a href="{{ $preinscripcion }}" class="boton boton-principal w-full">
                {{ $curso->con_lista_espera ? 'Anotarme en la lista de espera' : 'Preinscribirme' }}<span class="sr-only">: {{ $curso->plan }}</span>
            </a>
        @endif
        <a href="{{ route('oferta.curso', $curso->curso_id) }}" class="boton boton-secundario w-full">Ver detalle<span class="sr-only">: {{ $curso->plan }}</span></a>
    </p>
</article>
