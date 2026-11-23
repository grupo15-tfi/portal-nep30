{{--
    #156: Inicio del panel con tarjetas según el rol (antes: «Elegí una opción del menú»).
    Los números salen de App\Queries\InicioDelPanel y cada tarjeta lleva a su pantalla.
--}}
@php
    $inicio = \App\Queries\InicioDelPanel::para(auth()->user());
    $tonos = [
        'primario' => 'bg-primario',
        'exito'    => 'bg-exito',
        'aviso'    => 'bg-amber-700',
    ];
@endphp

<x-layouts.panel titulo="Inicio" :encabezado="false">
    <p class="etiqueta">Inicio</p>
    <h1 class="mt-0 mb-2 font-titulos text-3xl font-bold tracking-[-0.02em] text-navy-900">Hola, {{ auth()->user()->nombre }}</h1>
    <p class="mb-8 text-texto-suave">Esto es lo más importante de hoy. Tocá una tarjeta para ir a esa pantalla.</p>

    @if (count($inicio['tarjetas']) > 0)
        <ul class="m-0 grid list-none gap-4 p-0 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($inicio['tarjetas'] as $t)
                <li>
                    <a href="{{ route($t['ruta']) }}" class="tarjeta tarjeta-enlace flex h-full flex-col gap-1 p-5 text-texto no-underline hover:bg-fondo-suave md:p-6">
                        <span class="text-sm font-bold text-texto-suave">{{ $t['titulo'] }}</span>
                        <span class="flex items-center gap-3">
                            <span class="font-titulos text-4xl font-bold text-navy-900">{{ $t['valor'] }}</span>
                            <span class="inline-flex size-2.5 rounded-full {{ $tonos[$t['tono']] }}" aria-hidden="true"></span>
                        </span>
                        <span class="text-texto-suave">{{ $t['detalle'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @else
        <p class="text-texto-suave">Elegí una opción del menú para empezar.</p>
    @endif

    @if (count($inicio['proximas']) > 0)
        <section aria-labelledby="titulo-proximas" class="mt-10">
            <h2 id="titulo-proximas" class="mt-0 mb-4 font-titulos text-xl font-bold text-navy-900">Próximas entregas</h2>
            <ul class="m-0 flex list-none flex-col gap-3 p-0">
                @foreach ($inicio['proximas'] as $tarea)
                    <li class="tarjeta flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                        <span>
                            <span class="block font-semibold">{{ $tarea['titulo'] }}</span>
                            <span class="text-sm text-texto-suave">{{ $tarea['materia'] }}</span>
                        </span>
                        <span class="chip chip-aviso">Vence el {{ $tarea['fecha_entrega']->format('d/m') }} a las {{ $tarea['fecha_entrega']->format('H:i') }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts.panel>
