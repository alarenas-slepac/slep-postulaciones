<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Detalle de cálculo de endeudamiento</title>
    <style>
        @page { margin: 22px 18px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; color: #1f2937; }
        .header { border-bottom: 2px solid #0d6efd; padding-bottom: 8px; margin-bottom: 10px; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: middle; }
        .logo { height: 42px; max-width: 190px; object-fit: contain; }
        .title { font-size: 16px; font-weight: 700; color: #0d47a1; margin: 0 0 2px 0; }
        .subtitle { font-size: 10px; color: #6b7280; margin: 0; }
        .meta-table, .summary-table, .detail-table { width: 100%; border-collapse: collapse; }
        .meta-table td { padding: 4px 6px; border: 1px solid #d1d5db; vertical-align: top; }
        .meta-label { color: #6b7280; font-size: 8px; text-transform: uppercase; }
        .meta-value { font-size: 10px; font-weight: 700; margin-top: 2px; }
        .section-title { font-size: 12px; font-weight: 700; color: #0d47a1; margin: 12px 0 6px 0; }
        .summary-table th, .summary-table td, .detail-table th, .detail-table td { border: 1px solid #d1d5db; padding: 4px 5px; }
        .summary-table th, .detail-table th { background: #eef2ff; font-weight: 700; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 10px; font-size: 8px; font-weight: 700; color: #fff; }
        .badge-success { background: #146c43; }
        .badge-danger { background: #b02a37; }
        .badge-warning { background: #946200; }
        .badge-secondary { background: #495057; }
        .row-danger td { background: #f8d7da; }
        .row-warning td { background: #fff3cd; }
        .row-secondary td { background: #e9ecef; }
        .obs { margin-top: 8px; padding: 7px 8px; background: #f3f4f6; border: 1px solid #d1d5db; }
        .footer { margin-top: 10px; font-size: 8px; color: #6b7280; text-align: right; }
    </style>
</head>
<body>
    @php
        $registro = $analysis['registro'];
        $logoData = null;
        foreach ([
            public_path(config('brand.logo_pdf', 'branding/01_logo_principal.png')),
            public_path(config('brand.logo_lockup_horizontal', 'branding/04_lockup_horizontal.png')),
        ] as $logoFile) {
            if (is_file($logoFile)) {
                $ext = strtolower(pathinfo($logoFile, PATHINFO_EXTENSION));
                $mime = $ext === 'svg' ? 'image/svg+xml' : 'image/png';
                $logoData = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoFile));
                break;
            }
        }
        $platformName = config('brand.platform_name', 'Plataforma SLEP Andalién Costa');
        $estadoLabel = $analysis['estado'] === 'cumple' ? 'Dentro de tope' : ($analysis['estado'] === 'excede_tope' ? 'Con exceso' : 'Revisión');
        $estadoClass = $analysis['estado'] === 'cumple' ? 'badge-success' : ($analysis['estado'] === 'excede_tope' ? 'badge-danger' : 'badge-warning');
    @endphp

    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width: 210px;">
                    @if ($logoData)
                        <img class="logo" src="{{ $logoData }}" alt="{{ $platformName }}">
                    @endif
                </td>
                <td style="text-align: right;">
                    <div class="title">Detalle de cálculo de endeudamiento</div>
                    <p class="subtitle">{{ $platformName }}</p>
                    <p class="subtitle">Trabajador: {{ $registro->nombre_completo }} | RUT-DV: {{ $registro->rut_dv }} | Generado: {{ $generatedAt->format('d-m-Y H:i') }}</p>
                </td>
            </tr>
        </table>
    </div>

    <table class="meta-table">
        <tr>
            <td width="16.6%"><div class="meta-label">Período</div><div class="meta-value">{{ sprintf('%02d/%04d', $registro->mes, $registro->anio) }}</div></td>
            <td width="16.6%"><div class="meta-label">Dominio</div><div class="meta-value">{{ $registro->dominio }}</div></td>
            <td width="16.6%"><div class="meta-label">Versión MAE</div><div class="meta-value">v{{ $registro->carga?->version }}{{ $registro->carga?->es_vigente ? ' vigente' : '' }}</div></td>
            <td width="16.6%"><div class="meta-label">Total haberes</div><div class="meta-value">${{ number_format($analysis['base_calculo'], 0, ',', '.') }}</div></td>
            <td width="16.6%"><div class="meta-label">Máx. 45%</div><div class="meta-value">${{ number_format($analysis['monto_maximo_endeudamiento'], 0, ',', '.') }}</div></td>
            <td width="16.6%"><div class="meta-label">Estado</div><div class="meta-value"><span class="badge {{ $estadoClass }}">{{ $estadoLabel }}</span></div></td>
        </tr>
    </table>

    @if (!empty($analysis['observaciones']))
        <div class="obs"><strong>Observaciones:</strong> {{ implode(' | ', $analysis['observaciones']) }}</div>
    @endif

    <div class="section-title">Resumen del cálculo</div>
    <table class="summary-table">
        <thead>
            <tr>
                <th>Total descuentos</th>
                <th>% descuento</th>
                <th>Exceso</th>
                <th>Aplicable</th>
                <th>No aplicable</th>
                <th>Patronal excluido</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="text-right">${{ number_format($analysis['total_descuentos'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($analysis['porcentaje_total_descuento'], 2, ',', '.') }}%</td>
                <td class="text-right">${{ number_format($analysis['monto_excedido'], 0, ',', '.') }}</td>
                <td class="text-right">${{ number_format($analysis['totales']['aplicable_total'], 0, ',', '.') }}</td>
                <td class="text-right">${{ number_format($analysis['totales']['no_aplicable_total'], 0, ',', '.') }}</td>
                <td class="text-right">${{ number_format($analysis['totales']['patronal'], 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">Descuentos legales base MAE</div>
    <table class="summary-table">
        <thead>
            <tr>
                <th>Imposiciones</th>
                <th>Salud</th>
                <th>Impuesto</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="text-right">${{ number_format($analysis['descuentos_legales']['imposiciones'] ?? 0, 0, ',', '.') }}</td>
                <td class="text-right">${{ number_format($analysis['descuentos_legales']['salud'] ?? 0, 0, ',', '.') }}</td>
                <td class="text-right">${{ number_format($analysis['descuentos_legales']['impuesto'] ?? 0, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">Detalle de descuentos</div>
    <table class="detail-table">
        <thead>
            <tr>
                <th width="4%">#</th>
                <th width="17%">Descuento / columna</th>
                <th width="10%">Grupo</th>
                <th width="10%">Subgrupo</th>
                <th width="9%">Prioridad</th>
                <th width="8%">Monto</th>
                <th width="7%">Cuota</th>
                <th width="7%">Aplicable</th>
                <th width="7%">No aplicable</th>
                <th width="7%">Estado</th>
                <th width="14%">Motivo</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($analysis['detalles'] as $detalle)
                @php
                    $rowClass = $detalle['estado_aplicacion'] === 'eliminar'
                        ? ($detalle['primero_sobre_tope'] ? 'row-warning' : 'row-danger')
                        : ($detalle['estado_aplicacion'] === 'revision'
                            ? 'row-warning'
                            : ($detalle['estado_aplicacion'] === 'patronal_excluido' ? 'row-secondary' : ''));
                    $badgeClass = $detalle['estado_aplicacion'] === 'aplicar'
                        ? 'badge-success'
                        : ($detalle['estado_aplicacion'] === 'patronal_excluido'
                            ? 'badge-secondary'
                            : ($detalle['estado_aplicacion'] === 'revision' ? 'badge-warning' : 'badge-danger'));
                    $estadoTexto = $detalle['estado_aplicacion'] === 'aplicar'
                        ? 'Aplicar'
                        : ($detalle['estado_aplicacion'] === 'patronal_excluido'
                            ? 'Patronal'
                            : ($detalle['estado_aplicacion'] === 'revision' ? 'Revisión' : 'Eliminar'));
                @endphp
                <tr class="{{ $rowClass }}">
                    <td class="text-center">{{ $detalle['orden_resolucion'] }}</td>
                    <td>{{ $detalle['columna_origen'] }}</td>
                    <td>{{ $detalle['grupo'] ?: '—' }}</td>
                    <td>{{ $detalle['subgrupo'] ?: '—' }}</td>
                    <td>{{ $detalle['prioridad_label'] }}</td>
                    <td class="text-right">${{ number_format($detalle['valor_original'], 0, ',', '.') }}</td>
                    <td class="text-center">
                        @if ($detalle['cuota_label'])
                            {{ $detalle['cuota_label'] }}
                            @if ($detalle['mes_inicio_cuota_label'])<br><small>Inicio calculado {{ $detalle['mes_inicio_cuota_label'] }}</small>@elseif (($detalle['cuota_actual'] ?? null) === 0)<br><small>Sin inicio calculable</small>@endif
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-right">${{ number_format($detalle['valor_aplicable'], 0, ',', '.') }}</td>
                    <td class="text-right">${{ number_format($detalle['valor_no_aplicable'], 0, ',', '.') }}</td>
                    <td class="text-center"><span class="badge {{ $badgeClass }}">{{ $estadoTexto }}</span></td>
                    <td>{{ $detalle['motivo'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">{{ $platformName }} · Endeudamiento</div>
</body>
</html>
