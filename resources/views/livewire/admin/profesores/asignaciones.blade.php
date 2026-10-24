<div>
    <p class="mb-2"><a href="{{ route('admin.profesores.index') }}" class="text-primario underline">« Volver a profesores</a></p>
    <h1>Materias de {{ $profesor->nombreCompleto() }}</h1>

    @if ($aviso)
        <p role="status">{{ $aviso }}</p>
    @endif
    @error('materia_id') <p role="alert">{{ $message }}</p> @enderror

    {{-- Lo que dicta hoy (en el celular, una tarjeta por materia: tabla-apilada en app.css) --}}
    <table class="tabla-apilada">
        <thead>
            <tr><th>Materia</th><th>Curso</th><th>Sede</th><th>Turno</th><th>Acción</th></tr>
        </thead>
        <tbody>
            @forelse ($asignaciones as $a)
                <tr wire:key="asignacion-{{ $a->id }}">
                    <td data-etiqueta="Materia">{{ $a->materia->nombre }}</td>
                    <td data-etiqueta="Curso">{{ $a->curso->planEstudio->nombre }}</td>
                    <td data-etiqueta="Sede">{{ $a->curso->sede->nombre }}</td>
                    <td data-etiqueta="Turno">{{ ucfirst($a->curso->turno) }}</td>
                    <td data-etiqueta="Acción">
                        <button type="button" wire:click="quitar({{ $a->curso_id }}, {{ $a->materia_id }})"
                                wire:confirm="¿Quitar «{{ $a->materia->nombre }}» a este profesor?">Quitar</button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">Todavía no tiene materias asignadas.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- Asignar una nueva --}}
    <h2>Asignar una materia</h2>

    <form wire:submit="asignar">
        <div>
            <label for="curso_id">Curso *</label>
            <select id="curso_id" wire:model.live="curso_id">
                <option value="">Elegí un curso</option>
                @foreach ($cursos as $curso)
                    <option value="{{ $curso->id }}">
                        {{ $curso->planEstudio->nombre }} · {{ $curso->sede->nombre }} · {{ ucfirst($curso->turno) }} · {{ $curso->periodoLectivo->anio }}
                    </option>
                @endforeach
            </select>
            @error('curso_id') <p role="alert">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="materia_id">Materia * (solo las del plan del curso)</label>
            <select id="materia_id" wire:model="materia_id" @disabled($curso_id === '')>
                <option value="">{{ $curso_id === '' ? 'Primero elegí un curso' : 'Elegí una materia' }}</option>
                @foreach ($materias as $materia)
                    <option value="{{ $materia->id }}">{{ $materia->nombre }}</option>
                @endforeach
            </select>
        </div>

        <button type="submit">Asignar</button>
    </form>
</div>
