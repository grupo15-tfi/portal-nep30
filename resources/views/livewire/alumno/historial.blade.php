@php
    use App\Livewire\Alumno\Historial;
    use App\Livewire\Profesor\Notas;
    use Illuminate\Support\Carbon;
@endphp
<div>
    <p class="text-texto-suave mb-4">
        Tus módulos por inscripción, con la nota final y la asistencia. Un módulo se acredita con nota 6 o más y 80 % de asistencia.
        El PDF es informativo: <strong>no reemplaza al certificado analítico oficial</strong>.
    </p>

    @if ($historiales->isEmpty())
        <p class="rounded-lg border border-borde bg-primario-claro px-4 py-3">
            Todavía no tenés inscripciones en ningún curso. Cuando la escuela te dé de alta, tu historial aparece acá.
        </p>
    @else
        <div class="flex flex-col gap-6">
            @foreach ($historiales as $h)
                @php
                    $estadoInsc = data_get($h, 'estado_inscripcion');
                    $modulos = collect(data_get($h, 'modulos', []));
                @endphp
                <section wire:key="historial-{{ data_get($h, 'inscripcion_id') }}" aria-labelledby="titulo-insc-{{ data_get($h, 'inscripcion_id') }}"
                         class="rounded-lg border border-borde bg-white p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 id="titulo-insc-{{ data_get($h, 'inscripcion_id') }}" class="m-0 text-lg">{{ data_get($h, 'plan') }}</h2>
                            <p class="m-0 text-sm text-texto-suave">
                                {{ data_get($h, 'sede') }}@if (data_get($h, 'anio')) · {{ data_get($h, 'anio') }}@endif
                                · Inscripción
                                <span @class([
                                    'inline-block rounded-full px-2 py-0.5 text-xs font-semibold',
                                    'bg-exito-claro text-exito' => $estadoInsc === 'activa',
                                    'bg-primario-claro text-primario' => $estadoInsc === 'finalizada',
                                    'bg-borde/40 text-texto' => ! in_array($estadoInsc, ['activa', 'finalizada']),
                                ])>{{ ucfirst((string) $estadoInsc) }}</span>
                            </p>
                        </div>
                        @if ($conPdf)
                            <a href="{{ route('alumno.historial.pdf', data_get($h, 'inscripcion_id')) }}"
                               class="boton inline-flex items-center rounded border border-primario px-3 py-1.5 font-semibold text-primario no-underline hover:bg-primario-claro">
                                Descargar PDF<span class="sr-only">: historial de {{ data_get($h, 'plan') }}</span>
                            </a>
                        @endif
                    </div>

                    {{-- Resumen al emitir --}}
                    <dl class="mt-4 grid grid-cols-3 gap-2 text-center">
                        <div class="rounded bg-exito-claro px-2 py-2">
                            <dt class="text-xs font-semibold text-exito">Acreditados</dt>
                            <dd class="m-0 text-xl font-bold">{{ (int) data_get($h, 'resumen.modulos_acreditados') }}</dd>
                        </div>
                        <div class="rounded bg-primario-claro px-2 py-2">
                            <dt class="text-xs font-semibold text-primario">Promedio</dt>
                            <dd class="m-0 text-xl font-bold">{{ Historial::numero(data_get($h, 'resumen.promedio')) }}</dd>
                        </div>
                        <div class="rounded bg-borde/30 px-2 py-2">
                            <dt class="text-xs font-semibold text-texto">Pendientes</dt>
                            <dd class="m-0 text-xl font-bold">{{ (int) data_get($h, 'resumen.modulos_pendientes') }}</dd>
                        </div>
                    </dl>

                    @if ($modulos->isEmpty())
                        <p class="mb-0 mt-4 text-texto-suave">Todavía no hay módulos cargados en esta inscripción.</p>
                    @else
                        <div class="mt-4 overflow-x-auto">
                            <table class="tabla-apilada">
                                <caption class="sr-only">Módulos de {{ data_get($h, 'plan') }}</caption>
                                <thead>
                                    <tr><th>Módulo</th><th>Ciclo</th><th>Estado</th><th class="md:text-right">Nota final</th><th class="md:text-right">Asistencia</th><th>Acreditado el</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($modulos as $m)
                                        @php $est = data_get($m, 'estado'); $fecha = data_get($m, 'fecha_acreditacion'); $asis = data_get($m, 'asistencia'); @endphp
                                        <tr>
                                            <td class="font-semibold">{{ data_get($m, 'materia') }}</td>
                                            <td data-etiqueta="Ciclo">{{ data_get($m, 'ciclo') ?? '—' }}</td>
                                            <td data-etiqueta="Estado">
                                                <span @class([
                                                    'inline-block rounded-full px-2.5 py-0.5 text-sm font-semibold whitespace-nowrap',
                                                    'bg-exito-claro text-exito' => $est === 'acreditada',
                                                    'bg-borde/40 text-texto' => $est === 'pendiente',
                                                    'bg-primario-claro text-primario' => $est === 'cursando',
                                                    'bg-error-claro text-error' => $est === 'libre',
                                                ])>{{ Notas::ESTADOS[$est] ?? $est }}</span>
                                            </td>
                                            <td data-etiqueta="Nota final" class="tabular-nums md:text-right">{{ Historial::numero(data_get($m, 'nota_final')) }}</td>
                                            <td data-etiqueta="Asistencia" class="tabular-nums md:text-right">{{ $asis === null ? '—' : Historial::numero($asis, 1)."\u{00A0}%" }}</td>
                                            <td data-etiqueta="Acreditado el">{{ $fecha ? Carbon::parse($fecha)->format('d/m/Y') : '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @endforeach
        </div>
    @endif
</div>
