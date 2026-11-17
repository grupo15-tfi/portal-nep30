@php use App\Livewire\Profesor\Notas as N; @endphp
<div>
    <p class="text-texto-suave mb-4">Tus notas por materia. Las carga cada profesor; si ves un error, consultalo con él o en secretaría.</p>

    @if (collect($materias)->isEmpty())
        <p class="rounded-lg border border-borde bg-primario-claro px-4 py-3">Todavía no estás inscripto en ninguna materia.</p>
    @else
        <ul class="m-0 grid list-none gap-4 p-0 md:grid-cols-2">
            @foreach ($materias as $i => $m)
                @php $estado = data_get($m, 'estado'); $evals = collect(data_get($m, 'evaluaciones', [])); @endphp
                <li wire:key="materia-{{ $i }}" class="rounded-lg border border-borde bg-white p-4">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h2 class="m-0 text-lg">{{ data_get($m, 'materia') }}</h2>
                            <p class="m-0 text-sm text-texto-suave">{{ data_get($m, 'curso') }}</p>
                        </div>
                        <span @class([
                            'inline-block rounded-full px-2.5 py-0.5 text-sm font-semibold whitespace-nowrap',
                            'bg-primario-claro text-primario' => $estado === 'cursando',
                            'bg-exito-claro text-exito' => $estado === 'acreditada',
                            'bg-amber-100 text-amber-700' => $estado === 'pendiente',
                            'bg-error-claro text-error' => $estado === 'libre',
                        ])>{{ N::ESTADOS[$estado] ?? $estado }}</span>
                    </div>

                    @if ($evals->isEmpty())
                        <p class="mb-0 mt-3 text-texto-suave">Sin notas cargadas todavía.</p>
                    @else
                        <table class="mt-3 w-full text-sm">
                            <caption class="sr-only">Notas de {{ data_get($m, 'materia') }}</caption>
                            <thead><tr><th class="text-left">Evaluación</th><th class="text-left">Fecha</th><th class="text-right">Nota</th></tr></thead>
                            <tbody>
                                @foreach ($evals as $e)
                                    @php $fecha = data_get($e, 'fecha'); @endphp
                                    <tr>
                                        <td>{{ N::TIPOS[data_get($e, 'tipo')] ?? data_get($e, 'tipo') }}</td>
                                        <td>{{ $fecha ? \Illuminate\Support\Carbon::parse($fecha)->format('d/m/Y') : '—' }}</td>
                                        <td class="text-right font-semibold">{{ N::formatoNota(data_get($e, 'nota')) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif

                    <p class="mb-0 mt-3">Nota final: <strong>{{ N::formatoNota(data_get($m, 'nota_final')) }}</strong></p>
                </li>
            @endforeach
        </ul>
    @endif
</div>
