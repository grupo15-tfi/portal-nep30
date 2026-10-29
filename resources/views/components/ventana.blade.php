@props([
    'titulo',
    'id' => 'titulo-ventana',
    'cerrar' => 'cerrar',
    'ancho' => 'max-w-lg',
])

{{--
    Ventana emergente compartida del panel (#73), la misma de «Editar profesor».
    Uso: @if ($abierta) <x-ventana titulo="Nueva sede" id="titulo-sede" cerrar="limpiar"> … </x-ventana> @endif
    - Esc o el método de «cerrar» la cierran.
    - Al abrir, el foco va al primer campo; al cerrar, vuelve al botón que la abrió.
    - En el celular ocupa el ancho y se desplaza por dentro si el formulario es largo.
--}}
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
     wire:keydown.escape.window="{{ $cerrar }}"
     x-data="{
         previo: document.activeElement,
         destroy() { if (this.previo && document.body.contains(this.previo)) this.previo.focus() },
     }"
     x-init="$nextTick(() => $el.querySelector('[role=dialog]').querySelector('input:not([type=hidden]), select, textarea, button')?.focus())">
    <div role="dialog" aria-modal="true" aria-labelledby="{{ $id }}"
         {{ $attributes->class(['w-full max-h-full overflow-y-auto rounded bg-white p-6 shadow-lg', $ancho]) }}>
        <h2 id="{{ $id }}" class="mt-0">{{ $titulo }}</h2>

        {{ $slot }}
    </div>
</div>
