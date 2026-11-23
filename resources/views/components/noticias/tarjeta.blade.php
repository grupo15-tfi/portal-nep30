{{--
    Tarjeta de noticia con foto para el Inicio (#60).
    Sin foto se muestra el sello sobre un fondo liso: no se inventan imágenes (solo fotos reales con autorización, Ley 25.326).
    HU10 (#75): si tiene video de YouTube, la tarjeta lo avisa; el video se reproduce en el detalle.
    #155: tarjeta de la base (#154) y «Leer la noticia» como enlace con flecha, sin subrayado.
    Uso: <x-noticias.tarjeta :noticia="$noticia" />
--}}
@props(['noticia'])

<article class="tarjeta flex flex-col overflow-hidden">
    <div class="relative">
        @if ($noticia->imagen_ruta)
            <img src="{{ asset('storage/'.$noticia->imagen_ruta) }}" alt="" loading="lazy" decoding="async"
                 width="640" height="360" class="aspect-video w-full object-cover">
        @else
            <div class="flex aspect-video w-full items-center justify-center bg-primario-claro" aria-hidden="true">
                <img src="{{ asset('img/sello.png') }}" alt="" width="72" height="72" class="size-18 opacity-40">
            </div>
        @endif
        @if ($noticia->video_url)
            <span class="absolute bottom-3 left-3 inline-flex items-center gap-1 rounded-full bg-primario px-3 py-1 text-sm font-bold text-white">
                <svg aria-hidden="true" viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                Con video
            </span>
        @endif
    </div>
    <div class="flex flex-1 flex-col gap-2 p-5 md:p-6">
        <p class="m-0">
            <time datetime="{{ $noticia->publicada_en->toDateString() }}" class="chip bg-fondo-suave font-semibold text-texto-suave">{{ $noticia->publicada_en->format('d/m/Y') }}</time>
        </p>
        <h3 class="m-0 font-titulos text-xl font-semibold leading-snug text-navy-900">
            <a href="{{ route('noticias.show', $noticia->slug) }}" class="text-inherit no-underline hover:underline focus:underline">{{ $noticia->titulo }}</a>
        </h3>
        @if ($noticia->resumen)
            <p class="m-0 text-texto-suave">{{ $noticia->resumen }}</p>
        @endif
        <p class="mt-auto mb-0 pt-1">
            <a href="{{ route('noticias.show', $noticia->slug) }}" class="boton inline-flex min-h-11 items-center gap-1.5 font-bold text-primario no-underline hover:underline">
                Leer la noticia<span class="sr-only">: {{ $noticia->titulo }}</span>
                <svg aria-hidden="true" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </a>
        </p>
    </div>
</article>
