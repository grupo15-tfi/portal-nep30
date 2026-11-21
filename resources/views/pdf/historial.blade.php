{{-- HU17 (#126): historial académico en PDF. Lo arma dompdf: CSS simple, tablas y la fuente DejaVu Sans (tildes y ñ). --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial académico · {{ $h['alumno']['apellido'] }} {{ $h['alumno']['nombre'] }}</title>
    <style>
        @page { margin: 18mm 16mm 20mm 16mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #1f2937; }
        .encabezado { width: 100%; border-bottom: 2px solid #1e3a5f; padding-bottom: 6pt; margin-bottom: 12pt; }
        .encabezado td { vertical-align: middle; }
        .sello { width: 56px; }
        .institucion { font-size: 12pt; font-weight: bold; color: #1e3a5f; }
        .lema { font-size: 9pt; color: #6b7280; }
        h1 { font-size: 14pt; margin: 0 0 8pt 0; color: #1e3a5f; }
        .datos { width: 100%; margin-bottom: 12pt; }
        .datos td { padding: 2pt 0; }
        .etiqueta { color: #6b7280; width: 28%; }
        table.modulos { width: 100%; border-collapse: collapse; margin-bottom: 12pt; }
        .modulos th { background: #1e3a5f; color: #fff; font-size: 9pt; text-align: left; padding: 5pt; }
        .modulos td { border-bottom: 1px solid #d1d5db; padding: 5pt; font-size: 9pt; }
        .centro { text-align: center; }
        .resumen td { padding: 2pt 10pt 2pt 0; }
        .pie { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 8pt; color: #6b7280; border-top: 1px solid #d1d5db; padding-top: 4pt; }
    </style>
</head>
<body>
    <table class="encabezado">
        <tr>
            <td class="sello"><img src="{{ public_path('img/sello.png') }}" width="52" height="52" alt="Sello"></td>
            <td>
                <div class="institucion">Núcleo de Educación Permanente y Formación Profesional N.º 30</div>
                <div class="lema">«Hna. María Delgado Valero» · Formosa</div>
            </td>
        </tr>
    </table>

    <h1>Historial académico</h1>

    <table class="datos">
        <tr><td class="etiqueta">Alumno/a</td><td>{{ $h['alumno']['apellido'] }}, {{ $h['alumno']['nombre'] }}</td></tr>
        <tr><td class="etiqueta">DNI</td><td>{{ $h['alumno']['dni'] }}</td></tr>
        <tr><td class="etiqueta">Plan de estudio</td><td>{{ $h['plan'] }}</td></tr>
        <tr><td class="etiqueta">Sede</td><td>{{ $h['sede'] }}</td></tr>
        <tr><td class="etiqueta">Año</td><td>{{ $h['anio'] }}</td></tr>
        <tr><td class="etiqueta">Estado de la inscripción</td><td>{{ ucfirst($h['estado_inscripcion']) }}</td></tr>
    </table>

    <table class="modulos">
        <thead>
            <tr>
                <th>Módulo</th>
                <th>Estado</th>
                <th class="centro">Nota final</th>
                <th class="centro">Fecha de acreditación</th>
                <th class="centro">Asistencia</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($h['modulos'] as $m)
                <tr>
                    <td>{{ $m['materia'] }}</td>
                    <td>{{ ucfirst($m['estado']) }}</td>
                    <td class="centro">{{ $m['nota_final'] === null ? '—' : number_format($m['nota_final'], 2, ',', '') }}</td>
                    <td class="centro">{{ $m['fecha_acreditacion'] ? \Illuminate\Support\Carbon::parse($m['fecha_acreditacion'])->format('d/m/Y') : '—' }}</td>
                    <td class="centro">{{ $m['asistencia'] === null ? '—' : number_format($m['asistencia'], 0, ',', '').' %' }}</td>
                </tr>
            @empty
                <tr><td colspan="5">La inscripción no tiene módulos cargados.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="resumen">
        <tr>
            <td><strong>Módulos acreditados:</strong> {{ $h['resumen']['modulos_acreditados'] }}</td>
            <td><strong>Pendientes:</strong> {{ $h['resumen']['modulos_pendientes'] }}</td>
            <td><strong>Promedio:</strong> {{ $h['resumen']['promedio'] === null ? '—' : number_format($h['resumen']['promedio'], 2, ',', '') }}</td>
        </tr>
    </table>

    <div class="pie">
        Emitido el {{ $h['emitido_en']->format('d/m/Y') }} a las {{ $h['emitido_en']->format('H:i') }} h.
        Constancia informativa. No reemplaza al certificado analítico oficial.
    </div>
</body>
</html>