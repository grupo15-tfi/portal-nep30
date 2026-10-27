{{--
    Tarjeta de noticia con foto para el Inicio (#60).
    Sin foto se muestra un fondo liso: no se inventan imágenes (solo fotos reales con autorización, Ley 25.326).
    El video de YouTube se suma cuando exista HU10 (#47).
    Uso: <x-noticias.tarjeta :noticia="$noticia" />
--}}
@props(['noticia'])

<article class="flex flex-col overflow-hidden rounded-lg border border-borde bg-white">
    @if ($noticia->imagen_ruta)
        <img src="{{ asset('storage/'.$noticia->imagen_ruta) }}" alt="" loading="lazy" decoding="async"
             width="640" height="360" class="aspect-video w-full object-cover">
    @else
        <div class="aspect-video w-full bg-primario-claro" aria-hidden="true"></div>
    @endif
    <div class="flex flex-1 flex-col gap-1.5 p-4">
        <p class="m-0 text-sm text-texto-suave">
            <time datetime="{{ $noticia->publicada_en->toDateString() }}">{{ $noticia->publicada_en->format('d/m/Y') }}</time>
        </p>
        <h3 class="m-0 text-lg font-semibold leading-snug">
            <a href="{{ route('noticias.show', $noticia->slug) }}" class="hover:underline focus:underline">{{ $noticia->titulo }}</a>
        </h3>
        @if ($noticia->resumen)
            <p class="m-0 text-texto-suave">{{ $noticia->resumen }}</p>
        @endif
        <p class="mt-auto pt-1 mb-0">
            <a href="{{ route('noticias.show', $noticia->slug) }}" class="font-semibold text-primario underline">
                Leer más<span class="sr-only">: {{ $noticia->titulo }}</span>
            </a>
        </p>
    </div>
</article>
