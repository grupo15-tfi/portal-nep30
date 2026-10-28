{{--
    HU08 (#54): materias asignadas al profesor que inició sesión. Diseño: «Mis materias» de las pantallas faltantes (#36).
    En el celular cada fila se ve como tarjeta (.tabla-apilada, #43).
--}}
@php use App\Http\Controllers\Profesor\MisMateriasController; @endphp

<x-layouts.panel titulo="Mis materias">
    <p class="text-texto-suave mb-4">Las materias que te asignó la administración. Si falta alguna o hay un dato mal, avisá en secretaría.</p>

    @if ($asignaciones->isEmpty())
        <p class="rounded-lg border border-borde bg-primario-claro px-4 py-3 text-texto">
            Todavía no tenés materias asignadas. Cuando la administración te asigne una, la vas a ver acá.
        </p>
    @else
        <p class="text-sm text-texto-suave">{{ $asignaciones->count() === 1 ? '1 materia' : $asignaciones->count().' materias' }}</p>

        <table class="tabla-apilada">
            <thead>
                <tr><th>Materia</th><th>Curso</th><th>Sede</th><th>Turno</th><th>Horario</th></tr>
            </thead>
            <tbody>
                @foreach ($asignaciones as $a)
                    <tr data-asignacion="{{ $a->id }}">
                        <td data-etiqueta="Materia" class="font-semibold">{{ $a->materia->nombre }}</td>
                        <td data-etiqueta="Curso">
                            {{ $a->curso->planEstudio->nombre }}
                            @if ($a->curso->estado !== 'activo')
                                <span class="ml-1 inline-block rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">{{ ucfirst($a->curso->estado) }}</span>
                            @endif
                        </td>
                        <td data-etiqueta="Sede">{{ $a->curso->sede->nombre }}</td>
                        <td data-etiqueta="Turno">{{ ucfirst($a->curso->turno) }}</td>
                        <td data-etiqueta="Horario" class="md:whitespace-nowrap">{{ MisMateriasController::horario($a->curso) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-layouts.panel>
