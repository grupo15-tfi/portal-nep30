@extends('layouts.publico')

@section('titulo', $seccion->titulo)

@section('contenido')
    <article class="max-w-3xl">
        <h1 class="font-titulos text-3xl md:text-4xl font-semibold text-primario mb-4">{{ $seccion->titulo }}</h1>
        <div>{!! nl2br(e($seccion->contenido)) !!}</div>
    </article>
@endsection
