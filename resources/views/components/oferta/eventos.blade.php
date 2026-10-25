{{--
    Lista de eventos del calendario (HU02). Uso: <x-oferta.eventos :eventos="$eventos" />
--}}
@props(['eventos'])

<ul class="flex flex-col">
    @foreach ($eventos as $evento)
        @php
            $inicio = $evento->fecha_inicio->locale('es');
            $varios = $evento->fecha_fin && ! $evento->fecha_fin->isSameDay($evento->fecha_inicio);
            $hora = $inicio->format('H:i') !== '00:00' ? $inicio->format('H:i') : null;
        @endphp
        <li class="flex gap-4 py-3 border-b border-borde">
            <time datetime="{{ $inicio->toDateString() }}" class="w-16 shrink-0 rounded bg-primario-claro text-primario text-center py-1.5">
                <span class="block font-titulos text-2xl font-semibold leading-none">{{ $inicio->format('d') }}</span>
                <span class="block text-sm font-semibold uppercase">{{ $inicio->translatedFormat('M') }}</span>
            </time>
            <div class="min-w-0">
                <p class="font-semibold text-lg leading-snug">{{ $evento->titulo }}</p>
                <p class="text-texto-suave">
                    {{ $evento->tipoTexto() }}
                    @if ($varios) · del {{ $inicio->format('d/m') }} al {{ $evento->fecha_fin->format('d/m') }} @endif
                    @if ($hora) · {{ $hora }} h @endif
                </p>
                @if ($evento->descripcion)
                    <p>{{ $evento->descripcion }}</p>
                @endif
            </div>
        </li>
    @endforeach
</ul>
