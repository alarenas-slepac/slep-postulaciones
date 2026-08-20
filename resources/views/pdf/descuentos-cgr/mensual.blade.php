<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle mensual de descuento CGR</title>
    <style>
        @page { margin: 30px 36px 40px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #202938; font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.45; }
        .header { border-bottom: 2px solid #184a78; margin-bottom: 18px; padding-bottom: 10px; width: 100%; }
        .header td { vertical-align: middle; }
        .logo { max-height: 72px; width: 215px; }
        .header-title { text-align: right; }
        h1 { color: #184a78; font-size: 19px; margin: 0 0 4px; }
        h2 { color: #184a78; font-size: 11px; margin: 0 0 8px; text-transform: uppercase; }
        .muted { color: #667085; }
        .section { border: 1px solid #d8dee8; border-radius: 4px; margin-bottom: 12px; padding: 10px; }
        .summary { border-collapse: collapse; width: 100%; }
        .summary td { border-right: 1px solid #e3e7ed; padding: 3px 10px; vertical-align: top; width: 33.333%; }
        .summary td:first-child { padding-left: 0; }
        .summary td:last-child { border-right: 0; padding-right: 0; }
        .label { color: #667085; display: block; font-size: 8px; margin-bottom: 2px; text-transform: uppercase; }
        .value { color: #101828; font-size: 12px; font-weight: bold; }
        .amount-table { border-collapse: collapse; width: 100%; }
        .amount-table th, .amount-table td { border: 1px solid #cfd6df; padding: 7px 8px; }
        .amount-table th { background: #eaf0f6; color: #173d63; text-align: left; width: 46%; }
        .amount-table td { text-align: right; }
        .total th, .total td { background: #184a78; color: #fff; font-size: 13px; }
        .warning { background: #fff5d6; border: 1px solid #e8c75f; color: #6f4d00; margin-bottom: 12px; padding: 8px 10px; }
        .formula { background: #f7f9fc; color: #475467; font-size: 9px; margin-top: 7px; padding: 6px 8px; text-align: center; }
        .verification { border-collapse: collapse; width: 100%; }
        .verification td { vertical-align: middle; }
        .qr { height: 98px; width: 98px; }
        .hash { color: #344054; font-family: DejaVu Sans Mono, monospace; font-size: 7px; word-break: break-all; }
        .footer { bottom: -27px; color: #667085; font-size: 7px; left: 0; position: fixed; right: 0; text-align: center; }
    </style>
</head>
<body>
@php
    $pesos = fn ($valor) => $valor === null ? 'Pendiente de UTM' : '$'.number_format((float) $valor, 0, ',', '.');
    $utm = fn ($valor) => number_format((float) $valor, 4, ',', '.');
@endphp

<table class="header"><tr>
    <td>
        @if($logoDataUri)
            <img src="{{ $logoDataUri }}" alt="Servicio Local de Educación Pública Andalién Costa" class="logo">
        @else
            <strong>Servicio Local de Educación Pública Andalién Costa</strong>
        @endif
    </td>
    <td class="header-title">
        <h1>Detalle mensual de descuento CGR</h1>
        <div>Período {{ $fila['periodo']->format('m-Y') }}</div>
        <div class="muted">Cuota N° {{ $fila['numero'] }} de {{ $descuentoCgr->numero_cuotas }}</div>
    </td>
</tr></table>

<div class="section">
    <h2>Identificación</h2>
    <table class="summary"><tr>
        <td><span class="label">Funcionario/a</span><span class="value">{{ $descuentoCgr->nombre }}</span></td>
        <td><span class="label">RUT</span><span class="value">{{ \App\Support\Rut::format($descuentoCgr->rut) }}</span></td>
        <td><span class="label">Resolución CGR</span><span class="value">{{ $descuentoCgr->numero_resolucion }}</span></td>
    </tr></table>
</div>

<div class="section">
    <h2>Antecedentes del descuento</h2>
    <table class="summary"><tr>
        <td><span class="label">Deuda definitiva</span><span class="value">{{ $pesos($descuentoCgr->deuda_definitiva_pesos) }}</span></td>
        <td><span class="label">Deuda equivalente</span><span class="value">{{ $utm($descuentoCgr->deuda_equivalente_utm) }} UTM</span></td>
        <td><span class="label">Tasa mensual</span><span class="value">{{ number_format((float) $descuentoCgr->tasa_interes_mensual, 4, ',', '.') }}%</span></td>
    </tr></table>
</div>

@if($fila['pendiente_utm'])
    <div class="warning"><strong>Valor pendiente:</strong> aún no existe una UTM registrada para {{ $fila['periodo']->format('m-Y') }}. Por ello, los montos en pesos no fueron estimados.</div>
@endif

<div class="section">
    <h2>Valores de la cuota del mes</h2>
    <table class="amount-table">
        <tr><th>Valor UTM del período</th><td>{{ $fila['valor_utm'] === null ? 'Pendiente' : $pesos($fila['valor_utm']) }}</td></tr>
        <tr><th>Saldo inicial</th><td>{{ $utm($fila['saldo_inicial_utm']) }} UTM&nbsp;&nbsp;|&nbsp;&nbsp;{{ $pesos($fila['saldo_inicial_pesos']) }}</td></tr>
        <tr><th>Capital de la cuota</th><td>{{ $utm($fila['capital_utm']) }} UTM&nbsp;&nbsp;|&nbsp;&nbsp;{{ $pesos($fila['capital_pesos']) }}</td></tr>
        <tr><th>Interés del mes ({{ number_format((float) $descuentoCgr->tasa_interes_mensual, 4, ',', '.') }}%)</th><td>{{ $pesos($fila['interes_pesos']) }}</td></tr>
        <tr class="total"><th>Descuento total del mes</th><td>{{ $pesos($fila['descuento_pesos']) }}</td></tr>
        <tr><th>Saldo final</th><td>{{ $utm($fila['saldo_final_utm']) }} UTM</td></tr>
    </table>
    <div class="formula">Descuento total del mes = capital de la cuota en pesos + interés mensual sobre el saldo inicial en pesos.</div>
</div>

<div class="section">
    <table class="verification"><tr>
        <td style="width: 115px;">@if($qrDataUri)<img src="{{ $qrDataUri }}" alt="Código QR de verificación" class="qr">@endif</td>
        <td>
            <h2>Verificación documental</h2>
            <div><strong>Código:</strong> {{ $documento->codigo_verificacion }}</div>
            <div><strong>Emisión:</strong> {{ $documento->documento_emitido_en?->format('d-m-Y H:i:s') }} hrs.</div>
            <div><strong>Verificación:</strong> {{ $validacionUrl }}</div>
            <div><strong>Huella SHA-256:</strong></div>
            <div class="hash">{{ $documento->documento_hash }}</div>
            <div class="muted" style="margin-top: 5px;">La verificación considera la identificación, resolución adjunta, UTM del período y valores calculados de esta cuota.</div>
        </td>
    </tr></table>
</div>

<div class="footer">Documento generado por el Sistema SGA · Servicio Local de Educación Pública Andalién Costa</div>
</body>
</html>
