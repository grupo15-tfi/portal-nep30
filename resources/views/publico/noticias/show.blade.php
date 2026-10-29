@extends('layouts.publico')

@section('titulo', $noticia->titulo)

@section('contenido')
    <article class="max-w-3xl">
        <p class="mb-2"><a href="{{ route('noticias.index') }}" class="text-primario underline">« Volver a noticias</a></p>

        <h1 class="font-titulos text-3xl md:text-4xl font-semibold text-primario mb-2">{{ $noticia->titulo }}</h1>
        <p class="text-texto-suave mb-6">
            <time datetime="{{ $noticia->publicada_en->toDateString() }}">{{ $noticia->publicada_en->format('d/m/Y') }}</time>
        </p>

        @if ($noticia->imagen_ruta)
            <img src="{{ asset('storage/' . $noticia->imagen_ruta) }}" alt="Imagen de la noticia: {{ $noticia->titulo }}"
                 class="mb-6 w-full h-auto rounded">
        @endif

        {{-- HU10 (#75): video de YouTube de la noticia --}}
        <x-video-youtube :url="$noticia->video_url" :titulo="$noticia->titulo" />

        <div>{!! nl2br(e($noticia->cuerpo)) !!}</div>
    </article>
@endsection
