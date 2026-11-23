@extends('layouts.publico')

@section('titulo', 'Noticias')

@section('contenido')
    <p class="etiqueta">Actualidad</p>
    <h1 class="titulo-pagina mb-8">Noticias</h1>

    <x-noticias.listado :noticias="$noticias" />

    {{-- Paginación en español (10 por página) --}}
    @if ($noticias->hasPages())
        <nav aria-label="Páginas de noticias" class="mt-8 flex items-center justify-between gap-4">
            @if ($noticias->onFirstPage())
                <span class="boton boton-volver pointer-events-none opacity-50" aria-disabled="true">Más recientes</span>
            @else
                <a href="{{ $noticias->previousPageUrl() }}" class="boton boton-volver">Más recientes</a>
            @endif

            <span class="text-texto-suave">Página {{ $noticias->currentPage() }} de {{ $noticias->lastPage() }}</span>

            @if ($noticias->hasMorePages())
                <a href="{{ $noticias->nextPageUrl() }}" class="boton boton-secundario">Más antiguas <svg aria-hidden="true" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
            @else
                <span class="boton boton-secundario pointer-events-none opacity-50" aria-disabled="true">Más antiguas</span>
            @endif
        </nav>
    @endif
@endsection
