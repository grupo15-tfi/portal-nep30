{{-- HU13 (#94): el componente ya trae su propio título (h1) --}}
<x-layouts.panel titulo="Asistencia" :encabezado="false">
    <livewire:profesor.asistencia :curso-profesor="$cursoProfesor" />
</x-layouts.panel>
