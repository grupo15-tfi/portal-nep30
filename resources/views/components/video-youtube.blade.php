{{--
    HU10 (#75): video de YouTube dentro del sitio, alojado en YouTube.
    Usa youtube-nocookie (App\Support\YouTube::embed, #74). El reproductor (un iframe con title)
    recién se carga al tocar «Reproducir»: así la página no descarga YouTube si nadie mira el video
    (celulares básicos y datos móviles). Sin JavaScript el botón es un enlace que abre el video en YouTube.
    Uso: <x-video-youtube :url="$noticia->video_url" :titulo="$noticia->titulo" />
--}}
@props(['url' => null, 'titulo' => 'Video'])

@php
    $embed = \App\Support\YouTube::embed($url);
    $id = $embed ? basename(parse_url($embed, PHP_URL_PATH)) : null;
@endphp

@if ($embed)
    <figure {{ $attributes->class(['m-0 mb-6']) }}>
        <div class="relative aspect-video w-full overflow-hidden rounded bg-primario">
            <a href="https://www.youtube.com/watch?v={{ $id }}" target="_blank" rel="noopener"
               data-video-embed="{{ $embed }}" data-video-titulo="Video: {{ $titulo }}"
               class="absolute inset-0 flex flex-col items-center justify-center gap-2 font-semibold text-white no-underline hover:bg-primario/90">
                <svg aria-hidden="true" viewBox="0 0 24 24" class="h-14 w-14" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                <span>Reproducir video<span class="sr-only">: {{ $titulo }}</span></span>
            </a>
        </div>
        <figcaption class="mt-1 text-sm text-texto-suave">El video se carga desde YouTube al tocar «Reproducir».</figcaption>
    </figure>

    @once
        <script>
            // Al tocar «Reproducir», el enlace se reemplaza por el reproductor dentro del sitio
            document.addEventListener('click', function (e) {
                var enlace = e.target.closest('[data-video-embed]');
                if (!enlace) return;
                e.preventDefault();
                var marco = document.createElement('iframe');
                marco.src = enlace.dataset.videoEmbed + '?autoplay=1';
                marco.title = enlace.dataset.videoTitulo;
                marco.className = 'absolute inset-0 h-full w-full';
                marco.allow = 'autoplay; encrypted-media; picture-in-picture';
                marco.allowFullscreen = true;
                marco.referrerPolicy = 'strict-origin-when-cross-origin';
                enlace.replaceWith(marco);
                marco.focus();
            });
        </script>
    @endonce
@endif
