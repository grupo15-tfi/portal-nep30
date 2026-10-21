{{--
    Listado de noticias reutilizable (HU01 y la vista previa de HU10).
    Uso: <x-noticias.listado :noticias="$noticias" />
    $noticias puede ser una colección o un paginador.
--}}
@props(['noticias'])

@if ($noticias->isEmpty())
    <p class="text-texto-suave">Todavía no hay noticias publicadas.</p>
@else
    <ul class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($noticias as $noticia)
            <li class="rounded border border-borde p-4 flex flex-col gap-2">
                <h3 class="font-titulos text-xl font-semibold leading-snug">
                    <a href="{{ route('noticias.show', $noticia->slug) }}" class="text-primario hover:underline">
                        {{ $noticia->titulo }}
                    </a>
                </h3>
                <p class="text-texto-suave">
                    <time datetime="{{ $noticia->publicada_en->toDateString() }}">{{ $noticia->publicada_en->format('d/m/Y') }}</time>
                </p>
                @if ($noticia->resumen)
                    <p>{{ $noticia->resumen }}</p>
                @endif
            </li>
        @endforeach
    </ul>
@endif
