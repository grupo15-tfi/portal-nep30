@extends('layouts.publico')

@section('titulo', 'Preinscripción · '.$curso->plan)

@section('contenido')
    <p class="mb-3 text-sm text-texto-suave">
        <a href="{{ route('inicio') }}" class="underline">Inicio</a> ›
        <a href="{{ route('oferta') }}" class="underline">Oferta educativa</a> ›
        <a href="{{ route('oferta.curso', $curso->curso_id) }}" class="underline">{{ $curso->plan }}</a> › Preinscripción
    </p>
    <h1 class="font-titulos text-3xl md:text-4xl font-semibold text-primario mb-2">Preinscripción</h1>
    <p class="max-w-3xl text-lg mb-6">Completá tus datos. Lleva unos 5 minutos y te confirmamos por correo.</p>

    <livewire:publico.preinscripcion :curso="$curso" />
@endsection
