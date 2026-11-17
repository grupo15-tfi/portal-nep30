{{-- HU11 (#91): el componente ya trae su propio título (h1) --}}
<x-layouts.panel titulo="Notas" :encabezado="false">
    <livewire:profesor.notas :curso-profesor="$cursoProfesor" />
</x-layouts.panel>
