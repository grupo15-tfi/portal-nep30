@extends('layouts.publico')

@section('titulo', 'Noticias')

@section('contenido')
    <h1 class="font-titulos text-3xl md:text-4xl font-semibold text-primario mb-6">Noticias</h1>

    <x-noticias.listado :noticias="$noticias" />

    {{-- Paginación en español (10 por página) --}}
    @if ($noticias->hasPages())
        <nav aria-label="Páginas de noticias" class="mt-8 flex items-center justify-between gap-4">
            @if ($noticias->onFirstPage())
                <span class="text-texto-suave">« Más recientes</span>
            @else
                <a href="{{ $noticias->previousPageUrl() }}" class="text-primario underline">« Más recientes</a>
            @endif

            <span class="text-texto-suave">Página {{ $noticias->currentPage() }} de {{ $noticias->lastPage() }}</span>

            @if ($noticias->hasMorePages())
                <a href="{{ $noticias->nextPageUrl() }}" class="text-primario underline">Más antiguas »</a>
            @else
                <span class="text-texto-suave">Más antiguas »</span>
            @endif
        </nav>
    @endif
@endsection
