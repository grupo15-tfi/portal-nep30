@php use App\Livewire\Profesor\Asistencia as A; @endphp
<div>
    <p class="text-texto-suave mb-4">Tu asistencia por materia. Cuentan las clases dictadas; las suspendidas no. Las faltas justificadas suman como asistencia.</p>

    @if (collect($materias)->isEmpty())
        <p class="rounded-lg border border-borde bg-primario-claro px-4 py-3">Todavía no estás inscripto en ninguna materia.</p>
    @else
        <ul class="m-0 grid list-none gap-4 p-0 md:grid-cols-2">
            @foreach ($materias as $i => $m)
                @php
                    $pct = data_get($m, 'porcentaje');
                    $min = data_get($m, 'asistencia_minima');
                    $debajo = $pct !== null && $min !== null && (float) $pct < (float) $min;
                    $faltas = collect(data_get($m, 'inasistencias', []));
                @endphp
                <li wire:key="asis-{{ $i }}" class="rounded-lg border border-borde bg-white p-4">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h2 class="m-0 text-lg">{{ data_get($m, 'materia') }}</h2>
                            <p class="m-0 text-sm text-texto-suave">{{ data_get($m, 'curso') }}</p>
                        </div>
                        <span @class([
                            'inline-block rounded-full px-2.5 py-0.5 text-sm font-semibold whitespace-nowrap',
                            'bg-error-claro text-error' => $debajo,
                            'bg-exito-claro text-exito' => ! $debajo && $pct !== null,
                            'bg-borde/40 text-texto' => $pct === null,
                        ])>{{ A::porcentaje($pct) }}</span>
                    </div>

                    <p class="mb-0 mt-3 text-sm">
                        {{ data_get($m, 'clases_dictadas', 0) }} clases dictadas · mínimo del plan: {{ A::porcentaje($min) }}
                    </p>
                    @if ($debajo)
                        <p role="note" class="mb-0 mt-2 rounded border-l-4 border-error bg-error-claro px-3 py-2 text-sm">
                            Estás debajo del mínimo. Hablá con tu profesor o en secretaría.
                        </p>
                    @endif

                    @if ($faltas->isEmpty())
                        <p class="mb-0 mt-3 text-texto-suave">{{ $pct === null ? 'Todavía no hay clases cargadas.' : 'Sin faltas.' }}</p>
                    @else
                        <p class="mb-1 mt-3 font-semibold">Faltas</p>
                        <ul class="m-0 list-none p-0 text-sm">
                            @foreach ($faltas as $f)
                                <li>{{ \Illuminate\Support\Carbon::parse(data_get($f, 'fecha'))->format('d/m/Y') }} · {{ data_get($f, 'estado') === 'justificado' ? 'Justificada' : 'Ausente' }}</li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
