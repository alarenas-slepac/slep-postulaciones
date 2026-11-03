<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Nómina docente - RBD {{ $establecimiento->rbd }} - {{ $anio }}</title>
    <style>
        @page { margin: 86pt 28pt 34pt; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; line-height: 1.4; color: #0f172a; }
        .report-header { position: fixed; top: -64pt; left: 0; right: 0; border-bottom: 2pt solid #0b3d91; padding-bottom: 7pt; }
        h1 { margin: 0 0 3pt; font-size: 14pt; color: #0b3d91; }
        .meta { font-size: 8pt; color: #334155; }
        .block + .block, .definitions { page-break-before: always; }
        h2 { font-size: 12pt; color: #0b3d91; margin: 0 0 7pt; }
        h3 { font-size: 10pt; color: #0b3d91; margin: 12pt 0 6pt; page-break-after: avoid; }
        .notice { margin: 0 0 10pt; padding: 8pt; border: 0.7pt solid #c9dcf5; background: #eef6ff; color: #0d47a1; font-size: 8pt; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 8pt; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td { border: 0.5pt solid #dbe4f0; padding: 5pt 3pt; vertical-align: top; overflow-wrap: anywhere; word-wrap: break-word; }
        th { background: #eef6ff; color: #0b3d91; font-size: 7.5pt; text-align: left; }
        .block-name { background: #0b3d91; color: #fff; font-size: 11pt; padding: 7pt; margin-bottom: 0; }
        .num { text-align: right; }
        .total td { background: #eef6ff; font-weight: bold; }
        .pending { background: #fff8e1; color: #8a4b00; font-weight: bold; }
        .detail { margin: 0 0 10pt; }
        .detail-title { font-weight: bold; color: #0b3d91; margin: 0 0 3pt; page-break-after: avoid; }
        .detail-text { margin: 0; white-space: pre-wrap; word-wrap: break-word; }
        .definition { margin-bottom: 8pt; }
        .definition dt { font-weight: bold; color: #0b3d91; page-break-after: avoid; }
        .definition dd { margin: 2pt 0 0; }
    </style>
</head>
<body>
@php
    $horas = \App\Exports\DotacionNominaDocentesPdfExport::HORAS;
    $fmt = static fn ($value) => $value === null ? '-' : rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
    $fecha = static function ($value) {
        if (!$value) return '-';
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);
        return $date && $date->format('Y-m-d') === $value ? $date->format('d-m-Y') : (string) $value;
    };
@endphp
<header class="report-header">
    <h1>Nómina docente en orden de prelación - Dotación {{ $anio }}</h1>
    <div class="meta">RBD {{ $establecimiento->rbd }} - {{ $establecimiento->nombre_establecimiento }} - {{ $establecimiento->comuna }}</div>
    <div class="meta">Fuente contractual: {{ $informe['periodos_padron'] ?? 'Período no informado' }}</div>
</header>
@foreach($bloques as $bloque)
    <section class="block">
        <p class="notice">Nómina completa del bloque. Las horas son generales del docente y pueden repetirse entre bloques: no sumarlas entre secciones. Las horas de Etapa 7 son propuestas en revisión. El detalle de fundamentos se incluye después de la tabla.</p>
        <h2 class="block-name">{{ $bloque['titulo'] }} - {{ $bloque['filas']->count() }} docentes</h2>
        <table>
            <colgroup>
                <col style="width:2.5%"><col style="width:8%"><col style="width:14%"><col style="width:13%"><col style="width:10%"><col style="width:5.5%"><col style="width:7%">
                @foreach($horas as $campo)<col style="width:5%">@endforeach
            </colgroup>
            <thead>
                <tr>
                    <th style="width:2.5%">N°</th><th style="width:8%">RUT</th><th style="width:14%">Nombre completo<br>{{ $bloque['titulo'] }}</th><th style="width:13%">Título</th><th style="width:10%">Grupo de prelación</th><th style="width:5.5%">Tramo</th><th style="width:7%">Antigüedad</th>
                    <th style="width:5%">Contrato<br>original<br>(h)</th><th style="width:5%">Contrato<br>consid.<br>(h)</th><th style="width:5%">Aula<br>asignada<br>(h)</th><th style="width:5%">Contrato<br>asignado<br>(h)</th><th style="width:5%">Reserva<br>(h)</th><th style="width:5%">Sin<br>asignar<br>(h)</th><th style="width:5%">Etapa 7<br>con justif.<br>(h)</th><th style="width:5%">Etapa 7<br>sin justif.<br>(h)</th>
                </tr>
            </thead>
            <tbody>
            @forelse($bloque['filas'] as $fila)
                <tr>
                    <td>{{ $loop->iteration }}</td><td>{{ $fila['rut'] }}</td><td>{{ $fila['nombre'] }}</td><td>{{ $fila['titulo'] }}</td><td>{{ $fila['prelacion'] }}</td><td>{{ $fila['tramo'] ?: '-' }}</td><td>{{ $fecha($fila['fecha_antiguedad']) }}</td>
                    @foreach($horas as $campo)<td class="num {{ $campo === 'sin_justificacion' && ($fila[$campo] ?? 0) > 0.01 ? 'pending' : '' }}">{{ $fmt($fila[$campo] ?? null) }}</td>@endforeach
                </tr>
            @empty
                <tr><td colspan="15">No hay docentes en la nómina de este bloque.</td></tr>
            @endforelse
                <tr class="total"><td colspan="7">Total {{ $bloque['titulo'] }}</td>@foreach($horas as $campo)<td class="num">{{ $fmt($bloque['totales'][$campo]) }}</td>@endforeach</tr>
            </tbody>
        </table>
        @if($bloque['filas']->contains(fn ($fila) => trim($fila['detalle_justificaciones'] ?? '') !== ''))
            <h3>Detalle de justificaciones - {{ $bloque['titulo'] }}</h3>
            @foreach($bloque['filas'] as $fila)
                @if(trim($fila['detalle_justificaciones'] ?? '') !== '')
                    <div class="detail"><p class="detail-title">{{ $loop->iteration }}. {{ $fila['nombre'] }} - {{ $fila['rut'] }}</p><p class="detail-text">{{ $fila['detalle_justificaciones'] }}</p></div>
                @endif
            @endforeach
        @endif
    </section>
@endforeach
<section class="definitions">
    <h2>Definiciones de la nómina</h2>
    <p>Las horas conservan hasta dos decimales. El signo - identifica un dato no determinado. Los totales generales del docente no deben sumarse entre bloques.</p>
    <dl>
    @foreach(array_slice($definiciones, 1) as $definicion)
        <div class="definition"><dt>{{ $definicion[0] }}</dt><dd>{{ $definicion[1] }}</dd></div>
    @endforeach
    </dl>
</section>
</body>
</html>
