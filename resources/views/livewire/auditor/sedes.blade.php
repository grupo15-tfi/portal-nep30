@php use App\Livewire\Auditor\Sedes; @endphp
<div>
    <p class="text-texto-suave mb-4">
        Números de cada sede al momento de abrir la página. Esta vista es de solo lectura.
    </p>

    @if ($sedes->isEmpty())
        <p class="rounded-lg border border-borde bg-primario-claro px-4 py-3">Todavía no hay sedes cargadas.</p>
    @else
        {{-- En el celular cada sede se ve como tarjeta (tabla-apilada) --}}
        <div class="overflow-x-auto">
            <table class="tabla-apilada">
                <caption class="sr-only">Resumen por sede: cursos, alumnos y preinscripciones</caption>
                <thead>
                    <tr>
                        <th scope="col">Sede</th>
                        @foreach (Sedes::COLUMNAS as $texto)
                            <th scope="col" class="md:text-right">{{ $texto }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sedes as $s)
                        @php $activa = (bool) data_get($s, 'activo'); @endphp
                        <tr wire:key="sede-{{ data_get($s, 'sede_id') }}" @class(['text-texto-suave' => ! $activa])>
                            <td class="font-semibold">
                                <span class="flex flex-wrap items-center gap-2">
                                    {{ data_get($s, 'sede') }}
                                    @unless ($activa)
                                        <span class="inline-block rounded-full bg-borde/40 px-2.5 py-0.5 text-sm font-semibold text-texto">Inactiva</span>
                                    @endunless
                                </span>
                            </td>
                            @foreach (Sedes::COLUMNAS as $clave => $texto)
                                <td data-etiqueta="{{ $texto }}" class="tabular-nums md:text-right">{{ (int) data_get($s, $clave) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-primario font-bold">
                        <td>Total ({{ $sedes->count() }} {{ $sedes->count() === 1 ? 'sede' : 'sedes' }})</td>
                        @foreach (Sedes::COLUMNAS as $clave => $texto)
                            <td data-etiqueta="{{ $texto }}" class="tabular-nums md:text-right">{{ $totales[$clave] }}</td>
                        @endforeach
                    </tr>
                </tfoot>
            </table>
        </div>
        <p class="text-sm text-texto-suave">Las sedes inactivas se muestran en gris y suman en los totales con sus números actuales.</p>
    @endif
</div>
