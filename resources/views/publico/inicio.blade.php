@extends('layouts.publico')

@section('titulo', 'Inicio')

@section('contenido')
    <section aria-labelledby="titulo-inicio" class="mb-10">
        <h1 id="titulo-inicio" class="font-titulos text-3xl md:text-4xl font-semibold text-primario mb-4">
            {{ $seccion->titulo }}
        </h1>
        <div class="max-w-3xl text-lg">{!! nl2br(e($seccion->contenido)) !!}</div>
    </section>

    <section aria-labelledby="titulo-noticias">
        <div class="flex items-baseline justify-between gap-4 mb-4">
            <h2 id="titulo-noticias" class="font-titulos text-2xl font-semibold text-primario">Últimas noticias</h2>
            <a href="{{ route('noticias.index') }}" class="text-primario underline">Ver todas</a>
        </div>
        <x-noticias.listado :noticias="$noticias" />
    </section>
@endsection
