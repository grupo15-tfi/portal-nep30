<x-layouts.panel titulo="Inicio">
    <p class="text-lg">Bienvenido/a, {{ auth()->user()->nombreCompleto() }}.</p>
    <p class="mt-2 text-texto-suave">Elegí una opción del menú para empezar.</p>
</x-layouts.panel>