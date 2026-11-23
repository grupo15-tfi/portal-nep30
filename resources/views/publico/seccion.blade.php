@extends('layouts.publico')

@section('titulo', $seccion->titulo)

@section('contenido')
    <article class="max-w-3xl">
        <h1 class="titulo-pagina mb-6">{{ $seccion->titulo }}</h1>
        {{-- HU10 (#75): imagen y video de la sección, si los tiene --}}
        @if ($seccion->imagen_ruta)
            <img src="{{ asset('storage/'.$seccion->imagen_ruta) }}" alt="Imagen de {{ $seccion->titulo }}"
                 decoding="async" class="mb-6 h-auto w-full rounded-3xl">
        @endif
        <x-video-youtube :url="$seccion->video_url" :titulo="$seccion->titulo" />

        <div>{!! nl2br(e($seccion->contenido)) !!}</div>
    </article>
@endsection
