<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Informe global de avance - Dotacion Establecimiento</title>
    <style>
        @page { margin: 18px 18px 22px 18px; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 9px; color: #172033; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #d6deea; padding: 5px 6px; vertical-align: middle; }
        th { background: #eaf2ff; color: #173a69; font-weight: 700; }
        .header { border-bottom: 2px solid #0d6efd; padding-bottom: 8px; margin-bottom: 10px; }
        .header td { border: 0; padding: 0; }
        .logo { height: 42px; max-width: 190px; object-fit: contain; }
        .title { font-size: 17px; font-weight: 700; color: #0b3d91; margin-bottom: 2px; }
        .subtitle { font-size: 8px; color: #64748b; margin: 1px 0; }
        .section-title { color: #0b3d91; font-size: 11px; font-weight: 700; margin: 11px 0 5px; }
        .meta td { width: 20%; }
        .label { color: #64748b; font-size: 7px; font-weight: 700; text-transform: uppercase; }
        .value { margin-top: 2px; font-size: 10px; font-weight: 700; }
        .kpis td { width: 20%; text-align: center; background: #f8fbff; }
        .kpi-number { font-size: 17px; color: #0d6efd; font-weight: 700; margin: 2px 0; }
        .small { font-size: 7.5px; }
        .muted { color: #64748b; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .progress-wrap { background: #e8edf5; border-radius: 8px; height: 10px; overflow: hidden; margin-top: 4px; }
        .progress-fill { background: #0d6efd; height: 10px; }
        .progress-green { background: #198754; }
        .progress-purple { background: #6f42c1; }
        .badge { display: inline-block; padding: 2px 7px; border-radius: 10px; font-size: 7px; font-weight: 700; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-primary { background: #dbeafe; color: #1d4ed8; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-secondary { background: #e5e7eb; color: #374151; }
        .note { margin-top: 7px; padding: 6px 8px; border: 1px solid #d6deea; background: #f8fafc; }
        .avoid-break { page-break-inside: auto; }
        .footer { position: fixed; bottom: -12px; left: 0; right: 0; text-align: right; color: #64748b; font-size: 7px; }
    </style>
</head>
<body>
@php
    $fmt = fn ($value) => \App\Support\DotacionEstablecimientoCalculator::formatHoras($value);
    $logoData = null;
    foreach ([
        public_path(config('brand.logo_pdf', 'branding/01_logo_principal.png')),
        public_path(config('brand.logo_lockup_horizontal', 'branding/04_lockup_horizontal.png')),
    ] as $logoFile) {
        if (is_file($logoFile)) {
            $ext = strtolower(pathinfo($logoFile, PATHINFO_EXTENSION));
            $mime = in_array($ext, ['jpg', 'jpeg'], true) ? 'image/jpeg' : 'image/png';
            $logoData = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoFile));
            break;
        }
    }
    $platformName = config('brand.platform_name', 'Plataforma SLEP Andalien Costa');
    $generatedByName = $generatedBy ? trim(implode(' ', array_filter([
        (string) ($generatedBy->nombres ?? ''),
        (string) ($generatedBy->apellido_paterno ?? ''),
        (string) ($generatedBy->apellido_materno ?? ''),
    ]))) : '';
    $generatedByName = $generatedByName !== '' ? $generatedByName : (string) ($generatedBy->email ?? 'Sistema SLEP');
    $general = (float) data_get($resumenGlobal, 'porcentaje_general', 0);
    $planes = (float) data_get($resumenGlobal, 'planes.porcentaje', 0);
    $asignacion = (float) data_get($resumenGlobal, 'asignacion.porcentaje', 0);
@endphp

<div class="header">
    <table>
        <tr>
            <td style="width: 220px;">
                @if ($logoData)
                    <img class="logo" src="{{ $logoData }}" alt="{{ $platformName }}">
                @endif
            </td>
            <td style="text-align: right;">
                <div class="title">Informe global de avance de Dotacion Establecimiento</div>
                <div class="subtitle">{{ $platformName }}</div>
                <div class="subtitle">Generado: {{ $generatedAt->format('d-m-Y H:i') }} | Usuario: {{ $generatedByName }}</div>
            </td>
        </tr>
    </table>
</div>

<table class="meta avoid-break">
    <tr>
        <td><div class="label">Ano del proceso</div><div class="value">{{ $anio }}</div></td>
        <td><div class="label">Comuna</div><div class="value">{{ $comuna !== '' ? $comuna : 'Todas' }}</div></td>
        <td colspan="2"><div class="label">Establecimiento</div><div class="value">{{ $establecimientoSeleccionado?->nombre_establecimiento ?? 'Todos los establecimientos' }}</div></td>
        <td><div class="label">Universo</div><div class="value">{{ number_format((int) data_get($resumenGlobal, 'total', 0), 0, ',', '.') }} establecimiento(s)</div></td>
    </tr>
</table>

<div class="section-title">Avance general por etapas</div>
<table class="avoid-break">
    <thead>
        <tr>
            <th style="width: 24%;">Etapa</th>
            <th style="width: 12%;">Avance</th>
            <th style="width: 42%;">Barra global</th>
            <th>Resultado consolidado</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>1. Configuracion de planes</strong></td>
            <td class="text-center"><strong>{{ number_format($planes, 1, ',', '.') }}%</strong></td>
            <td><div class="progress-wrap"><div class="progress-fill" style="width: {{ min(100, max(0, $planes)) }}%;"></div></div></td>
            <td>{{ number_format((int) data_get($resumenGlobal, 'planes.configurados', 0), 0, ',', '.') }} de {{ number_format((int) data_get($resumenGlobal, 'planes.total', 0), 0, ',', '.') }} cursos configurados; {{ number_format((int) data_get($resumenGlobal, 'planes.pendientes', 0), 0, ',', '.') }} pendientes.</td>
        </tr>
        <tr>
            <td><strong>2. Asignacion de horas aula</strong></td>
            <td class="text-center"><strong>{{ number_format($asignacion, 1, ',', '.') }}%</strong></td>
            <td><div class="progress-wrap"><div class="progress-fill progress-green" style="width: {{ min(100, max(0, $asignacion)) }}%;"></div></div></td>
            <td>{{ $fmt(data_get($resumenGlobal, 'asignacion.horas_aula_asignadas', 0)) }} de {{ $fmt(data_get($resumenGlobal, 'asignacion.horas_aula_requeridas', 0)) }} horas aula asignadas; {{ $fmt(data_get($resumenGlobal, 'asignacion.horas_aula_pendientes', 0)) }} pendientes.</td>
        </tr>
        <tr>
            <td><strong>Avance general</strong></td>
            <td class="text-center"><strong>{{ number_format($general, 1, ',', '.') }}%</strong></td>
            <td><div class="progress-wrap"><div class="progress-fill progress-purple" style="width: {{ min(100, max(0, $general)) }}%;"></div></div></td>
            <td>Promedio de configuracion de planes y asignacion de horas aula.</td>
        </tr>
    </tbody>
</table>

<div class="section-title">Resumen territorial</div>
<table class="kpis avoid-break">
    <tr>
        <td><div class="label">Completos</div><div class="kpi-number">{{ number_format((int) data_get($resumenGlobal, 'estados.completos', 0), 0, ',', '.') }}</div><span class="badge badge-success">100% sin observaciones</span></td>
        <td><div class="label">Avanzados</div><div class="kpi-number">{{ number_format((int) data_get($resumenGlobal, 'estados.avanzados', 0), 0, ',', '.') }}</div><span class="badge badge-primary">80% a 99,9%</span></td>
        <td><div class="label">En proceso</div><div class="kpi-number">{{ number_format((int) data_get($resumenGlobal, 'estados.en_proceso', 0), 0, ',', '.') }}</div><span class="badge badge-warning">Avance parcial</span></td>
        <td><div class="label">Sin iniciar</div><div class="kpi-number">{{ number_format((int) data_get($resumenGlobal, 'estados.sin_iniciar', 0), 0, ',', '.') }}</div><span class="badge badge-secondary">0%</span></td>
        <td><div class="label">Docentes con sobrecarga</div><div class="kpi-number">{{ number_format((int) data_get($resumenGlobal, 'asignacion.docentes_sobrecarga', 0), 0, ',', '.') }}</div><span class="small muted">Requiere revision</span></td>
    </tr>
</table>

<div class="section-title">Control contractual complementario</div>
<table class="avoid-break">
    <thead>
        <tr>
            <th>Indicador</th>
            <th class="text-right">Horas contrato</th>
        </tr>
    </thead>
    <tbody>
        <tr><td>Horas contrato requeridas</td><td class="text-right">{{ $fmt(data_get($resumenGlobal, 'asignacion.horas_contrato_requeridas', 0)) }}</td></tr>
        <tr><td>Horas contrato asignadas</td><td class="text-right">{{ $fmt(data_get($resumenGlobal, 'asignacion.horas_contrato_asignadas', 0)) }}</td></tr>
        <tr><td>Horas contrato pendientes</td><td class="text-right">{{ $fmt(data_get($resumenGlobal, 'asignacion.horas_contrato_pendientes', 0)) }}</td></tr>
        <tr><td>Horas contrato excedidas</td><td class="text-right">{{ $fmt(data_get($resumenGlobal, 'asignacion.horas_contrato_excedidas', 0)) }}</td></tr>
    </tbody>
</table>

<div class="section-title">Avance de asignacion por bloque</div>
<table class="avoid-break">
    <thead>
        <tr>
            <th>Bloque</th>
            <th>Unidad</th>
            <th class="text-right">Requeridas</th>
            <th class="text-right">Asignadas</th>
            <th class="text-right">Pendientes</th>
            <th class="text-right">Excedidas</th>
            <th style="width: 26%;">Avance</th>
        </tr>
    </thead>
    <tbody>
        @forelse (collect(data_get($resumenGlobal, 'desglose', [])) as $grupo)
            <tr>
                <td><strong>{{ $grupo['label'] }}</strong></td>
                <td>{{ ucfirst($grupo['unidad'] ?? 'horas contrato') }}</td>
                <td class="text-right">{{ $fmt($grupo['horas_requeridas']) }}</td>
                <td class="text-right">{{ $fmt($grupo['horas_asignadas']) }}</td>
                <td class="text-right">{{ $fmt($grupo['horas_pendientes']) }}</td>
                <td class="text-right">{{ $fmt($grupo['horas_excedidas']) }}</td>
                <td>
                    <div><strong>{{ number_format((float) $grupo['porcentaje'], 1, ',', '.') }}%</strong></div>
                    <div class="progress-wrap"><div class="progress-fill" style="width: {{ min(100, max(0, (float) $grupo['porcentaje'])) }}%;"></div></div>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center muted">Sin datos de asignacion para los filtros seleccionados.</td></tr>
        @endforelse
    </tbody>
</table>

@if ((float) data_get($resumenGlobal, 'asignacion.horas_aula_excedidas', 0) > 0)
    <div class="note"><strong>Alerta global:</strong> existen {{ $fmt(data_get($resumenGlobal, 'asignacion.horas_aula_excedidas', 0)) }} horas aula asignadas por sobre el plan configurado.</div>
@endif

<div class="section-title">Establecimientos con menor avance</div>
<table>
    <thead>
        <tr>
            <th>RBD</th>
            <th>Establecimiento</th>
            <th>Comuna</th>
            <th>Estado</th>
            <th>Avance general</th>
            <th>Cursos pendientes</th>
            <th>Horas aula pendientes</th>
        </tr>
    </thead>
    <tbody>
        @forelse (collect(data_get($resumenGlobal, 'establecimientos_criticos', [])) as $item)
            <tr>
                <td>{{ $item['rbd'] }}</td>
                <td>{{ $item['nombre'] }}</td>
                <td>{{ $item['comuna'] ?: 'Sin comuna' }}</td>
                <td>{{ $item['estado'] }}</td>
                <td class="text-center"><strong>{{ number_format((float) $item['porcentaje_general'], 1, ',', '.') }}%</strong></td>
                <td class="text-right">{{ number_format((int) $item['planes_pendientes'], 0, ',', '.') }}</td>
                <td class="text-right">{{ $fmt($item['horas_aula_pendientes'] ?? $item['horas_pendientes']) }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center muted">No existen establecimientos para los filtros seleccionados.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="note small">
    El avance general pondera en partes iguales la configuracion de planes y la asignacion de horas aula. El bloque Plan de estudios se informa en horas aula; PIE, funciones y planes normativos se informan en horas de contrato para no mezclar unidades.
</div>

<div class="footer">Informe global de avance de Dotacion Establecimiento - {{ $anio }}</div>
</body>
</html>
