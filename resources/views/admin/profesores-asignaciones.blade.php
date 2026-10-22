{{-- El componente ya trae su propio título (h1) --}}
<x-layouts.panel titulo="Materias del profesor" :encabezado="false">
    <livewire:admin.profesores.asignaciones :profesor="$profesor" />
</x-layouts.panel>
