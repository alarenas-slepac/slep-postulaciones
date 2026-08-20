<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Informe de descuento CGR</title>
    <style>
        @page { margin: 24px 28px 38px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #202938; font-family: DejaVu Sans, sans-serif; font-size: 9px; line-height: 1.35; }
        .header { width: 100%; border-bottom: 2px solid #184a78; margin-bottom: 12px; padding-bottom: 8px; }
        .header td { vertical-align: middle; }
        .logo { height: auto; width: 80px; }
        .header-title { text-align: right; }
        h1 { color: #184a78; font-size: 19px; margin: 0 0 3px; }
        h2 { color: #184a78; font-size: 11px; margin: 0 0 6px; text-transform: uppercase; }
        .muted { color: #667085; }
        .section { border: 1px solid #d8dee8; border-radius: 4px; margin-bottom: 9px; padding: 8px; }
        .summary { width: 100%; border-collapse: collapse; }
        .summary td { border-right: 1px solid #e3e7ed; padding: 2px 9px; vertical-align: top; width: 25%; }
        .summary td:first-child { padding-left: 0; }
        .summary td:last-child { border-right: 0; padding-right: 0; }
        .label { color: #667085; display: block; font-size: 7.5px; margin-bottom: 2px; text-transform: uppercase; }
        .value { color: #101828; font-size: 11px; font-weight: bold; }
        .data-table { border-collapse: collapse; width: 100%; }
        .data-table th, .data-table td { border: 1px solid #cfd6df; padding: 4px 3px; }
        .data-table thead { display: table-header-group; }
        .data-table th { background: #eaf0f6; color: #173d63; font-size: 7.3px; text-align: center; }
        .data-table td { font-size: 7.5px; }
        .data-table tr { page-break-inside: avoid; }
        .data-table tfoot td { background: #f1f4f8; font-weight: bold; }
        .center { text-align: center; }
        .right { text-align: right; }
        .pending { background: #fff5d6; color: #8a5a00; font-weight: bold; }
        .warning { background: #fff5d6; border: 1px solid #e8c75f; color: #6f4d00; margin-bottom: 9px; padding: 6px 8px; }
        .verification { width: 100%; border-collapse: collapse; }
        .verification td { vertical-align: middle; }
        .qr { height: 88px; width: 88px; }
        .hash { color: #344054; font-family: DejaVu Sans Mono, monospace; font-size: 7px; word-break: break-all; }
        .observations { white-space: pre-wrap; }
        .footer { bottom: -25px; color: #667085; font-size: 7px; left: 0; position: fixed; right: 0; text-align: center; }
    </style>
</head>
<body>
@php
    $pesos = fn ($valor) => $valor === null ? 'Pendiente UTM' : '$'.number_format((float) $valor, 0, ',', '.');
    $utm = fn ($valor) => number_format((float) $valor, 4, ',', '.');
@endphp

<table class="header">
    <tr>
        <td>
            @if($logoDataUri)
                <img src="{{ $logoDataUri }}" alt="Servicio Local de Educación Pública Andalién Costa" class="logo">
            @else
                <strong>Servicio Local de Educación Pública Andalién Costa</strong>
            @endif
        </td>
        <td class="header-title">
            <h1>Informe de descuento CGR</h1>
            <div>Resolución N° {{ $descuentoCgr->numero_resolucion }}</div>
            <div class="muted">Registro interno N° {{ $descuentoCgr->id }}</div>
        </td>
    </tr>
</table>

<div class="section">
    <h2>Identificación y resolución</h2>
    <table class="summary"><tr>
        <td><span class="label">Funcionario/a</span><span class="value">{{ $descuentoCgr->nombre }}</span></td>
        <td><span class="label">RUT</span><span class="value">{{ \App\Support\Rut::format($descuentoCgr->rut) }}</span></td>
        <td><span class="label">Resolución CGR</span><span class="value">{{ $descuentoCgr->numero_resolucion }}</span></td>
        <td><span class="label">Fecha resolución</span><span class="value">{{ $descuentoCgr->fecha_resolucion?->format('d-m-Y') ?? 'No informada' }}</span></td>
    </tr></table>
</div>

<div class="section">
    <h2>Valores dictaminados</h2>
    <table class="summary">
        <tr>
            <td><span class="label">Deuda definitiva</span><span class="value">{{ $pesos($descuentoCgr->deuda_definitiva_pesos) }}</span></td>
            <td><span class="label">Deuda equivalente</span><span class="value">{{ $utm($descuentoCgr->deuda_equivalente_utm) }} UTM</span></td>
            <td><span class="label">Cuota según resolución</span><span class="value">{{ $utm($descuentoCgr->cuota_utm) }} UTM</span></td>
            <td><span class="label">Número de cuotas</span><span class="value">{{ $descuentoCgr->numero_cuotas }}</span></td>
        </tr>
        <tr>
            <td><span class="label">Tasa interés anual</span><span class="value">{{ number_format((float) $descuentoCgr->tasa_interes_anual, 4, ',', '.') }}%</span></td>
            <td><span class="label">Tasa interés mensual</span><span class="value">{{ number_format((float) $descuentoCgr->tasa_interes_mensual, 4, ',', '.') }}%</span></td>
            <td><span class="label">Primer descuento</span><span class="value">{{ $descuentoCgr->fecha_primer_descuento->format('m-Y') }}</span></td>
            <td><span class="label">Resolución adjunta</span><span class="value">{{ $descuentoCgr->resolucion_pdf_nombre }}</span></td>
        </tr>
    </table>
</div>

@if($calculo['utm_faltantes'] !== [])
    <div class="warning"><strong>Cronograma con valores pendientes:</strong> faltan las UTM de {{ implode(', ', $calculo['utm_faltantes']) }}. Los montos en pesos de esos periodos no fueron estimados.</div>
@endif

<div class="section">
    <h2>Cronograma de descuentos</h2>
    <table class="data-table">
        <thead><tr><th>N°</th><th>Mes</th><th>Valor UTM</th><th>Saldo inicial UTM</th><th>Capital UTM</th><th>Saldo final UTM</th><th>Saldo inicial $</th><th>Capital $</th><th>Interés mes $</th><th>Descuento total $</th></tr></thead>
        <tbody>
            @foreach($calculo['filas'] as $fila)
                <tr>
                    <td class="center">{{ $fila['numero'] }}</td>
                    <td class="center">{{ $fila['periodo']->format('m-Y') }}</td>
                    <td class="right {{ $fila['pendiente_utm'] ? 'pending' : '' }}">{{ $fila['valor_utm'] === null ? 'Pendiente' : $pesos($fila['valor_utm']) }}</td>
                    <td class="right">{{ $utm($fila['saldo_inicial_utm']) }}</td>
                    <td class="right">{{ $utm($fila['capital_utm']) }}</td>
                    <td class="right">{{ $utm($fila['saldo_final_utm']) }}</td>
                    <td class="right">{{ $pesos($fila['saldo_inicial_pesos']) }}</td>
                    <td class="right">{{ $pesos($fila['capital_pesos']) }}</td>
                    <td class="right">{{ $pesos($fila['interes_pesos']) }}</td>
                    <td class="right"><strong>{{ $pesos($fila['descuento_pesos']) }}</strong></td>
                </tr>
            @endforeach
        </tbody>
        <tfoot><tr><td colspan="7" class="right">Totales calculados</td><td class="right">{{ $pesos($calculo['totales']['capital_pesos']) }}</td><td class="right">{{ $pesos($calculo['totales']['interes_pesos']) }}</td><td class="right">{{ $pesos($calculo['totales']['descuento_pesos']) }}</td></tr></tfoot>
    </table>
</div>

@if($descuentoCgr->observaciones)
    <div class="section"><h2>Observaciones</h2><div class="observations">{{ $descuentoCgr->observaciones }}</div></div>
@endif

<div class="section">
    <table class="verification"><tr>
        <td style="width: 105px;">@if($qrDataUri)<img src="{{ $qrDataUri }}" alt="Código QR de verificación" class="qr">@endif</td>
        <td>
            <h2>Verificación documental</h2>
            <div><strong>Código:</strong> {{ $descuentoCgr->codigo_verificacion }}</div>
            <div><strong>Emitido:</strong> {{ $descuentoCgr->documento_emitido_en?->format('d-m-Y H:i:s') }} hrs.</div>
            <div><strong>Verificación:</strong> {{ $validacionUrl }}</div>
            <div><strong>Huella SHA-256:</strong></div>
            <div class="hash">{{ $descuentoCgr->documento_hash }}</div>
            <div class="muted" style="margin-top: 4px;">El código QR permite comprobar que los datos del informe coinciden con el registro institucional vigente al momento de la consulta.</div>
        </td>
    </tr></table>
</div>

<div class="footer">Documento generado por el Sistema SGA · Servicio Local de Educación Pública Andalién Costa</div>
</body>
</html>
