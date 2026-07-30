<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; }
        .header { width: 100%; border-bottom: 2px solid #0f4c81; padding-bottom: 8px; margin-bottom: 10px; }
        .logo-cell { width: 190px; border: 0; padding: 0; vertical-align: middle; }
        .title-cell { border: 0; padding: 0; vertical-align: middle; text-align: right; }
        .logo { width: 168px; height: auto; }
        .title { font-size: 16px; color: #0f4c81; font-weight: 700; }
        .subtitle { font-size: 10px; color: #4b5563; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #d1d5db; padding: 5px; vertical-align: top; }
        th { background: #eff6ff; text-align: left; }
        .section { background: #0f4c81; color: white; font-weight: 700; padding: 5px; margin-top: 8px; }
        .signature { height: 58px; border: 1px solid #d1d5db; padding: 6px; }
        .small { font-size: 9px; }
        .validation { border: 1px dashed #6b7280; padding: 6px; font-size: 9px; }
        .validation-table { width: 100%; border-collapse: collapse; margin: 0; }
        .validation-table td { border: 0; padding: 0; vertical-align: middle; }
        .qr { width: 96px; height: 96px; text-align: right; }
    </style>
</head>
<body>
<div class="header">
    <table style="margin:0; border:0;">
        <tr>
            <td class="logo-cell">
                @if(!empty($logoDataUri))
                    <img class="logo" src="{{ $logoDataUri }}" alt="SLEP Andalién Costa">
                @endif
            </td>
            <td class="title-cell">
                <div class="title">SOLICITUD DE PEDIDO - COMPRA DE PASAJE AÉREO</div>
                <div class="subtitle">Servicio Local de Educación Pública de Andalién Costa</div>
            </td>
        </tr>
    </table>
</div>
<table>
    <tr><th>N° Solicitud de Pedido</th><td>{{ $pasaje->numero_solicitud_pedido }}</td><th>Cometido asociado</th><td>{{ $cometido->numero_cometido_interno }}</td></tr>
    <tr><th>Fecha emisión</th><td>{{ now()->format('d-m-Y') }}</td><th>Estado</th><td>{{ $pasaje->estado_pasaje }}</td></tr>
</table>
@php
    $funcionarioAcPedido = $cometido->funcionarioAcAutorizado;
    $telefonoPedido = $funcionarioAcPedido?->telefono ?: '—';
    $emailPedido = $funcionarioAcPedido?->email ?: ($cometido->solicitante?->email ?: '—');
    $fechaNacimientoPedido = ! empty($funcionarioAcPedido?->fecha_nacimiento)
        ? \Illuminate\Support\Carbon::parse($funcionarioAcPedido->fecha_nacimiento)->format('d-m-Y')
        : '—';
    $tiposPasajePedidoCabecera = [
        'solo_ida' => 'Solo ida',
        'solo_regreso' => 'Solo regreso',
        'ida_y_regreso' => 'Ida y regreso',
    ];
    $tipoPasajePedido = $tiposPasajePedidoCabecera[$cometido->tipo_pasaje_aereo] ?? 'No informado';
@endphp
<div class="section">Funcionario solicitante</div>
<table>
    <tr><th>Nombre</th><td>{{ $cometido->funcionario_nombre }}</td><th>RUN</th><td>{{ $cometido->funcionario_rut }}</td></tr>
    <tr><th>Teléfono</th><td>{{ $telefonoPedido }}</td><th>Correo electrónico</th><td>{{ $emailPedido }}</td></tr>
    <tr><th>Fecha de nacimiento</th><td>{{ $fechaNacimientoPedido }}</td><th>Tipo de pasaje</th><td>{{ $tipoPasajePedido ?? 'No informado' }}</td></tr>
    <tr><th>Unidad</th><td>{{ $cometido->unidad_departamento_ac }}</td><th>Subdirección dependencia</th><td>{{ $cometido->subdireccion_dependencia_ac }}</td></tr>
</table>
<div class="section">Origen y destino</div>
<table>
    <tr><th>Origen</th><td>{{ $cometido->comuna_origen_nombre }}</td><th>Destino</th><td>{{ $cometido->es_destino_extranjero ? (($cometido->pais_destino ?: '') . ' - ' . ($cometido->ciudad_destino_extranjero ?: '')) : ($cometido->comuna_destino_nombre ?: $cometido->destino) }}</td></tr>
    <tr><th>Fecha desde</th><td>{{ optional($cometido->fecha_desde)->format('d-m-Y') }}</td><th>Fecha hasta</th><td>{{ optional($cometido->fecha_hasta)->format('d-m-Y') }}</td></tr>
    <tr><th>Hora salida</th><td>{{ $cometido->hora_salida }}</td><th>Hora regreso</th><td>{{ $cometido->hora_regreso }}</td></tr>
    @php
        $tiposPasajePedido = [
            'solo_ida' => 'Solo ida',
            'solo_regreso' => 'Solo regreso',
            'ida_y_regreso' => 'Ida y regreso',
        ];
        $tipoPasajePedido = $tiposPasajePedido[$cometido->tipo_pasaje_aereo] ?? 'No informado';
    @endphp
    <tr><th>Tipo de pasaje aéreo requerido</th><td colspan="3">{{ $tipoPasajePedido }}</td></tr>
    <tr><th>Institución / lugar</th><td colspan="3">{{ $cometido->institucion_destino }} - {{ $cometido->destino }}</td></tr>
</table>
<div class="section">Fundamento</div>
<table>
    <tr><th>Motivo</th><td>{{ $cometido->motivo }}</td></tr>
    <tr><th>Actividad</th><td>{{ $cometido->descripcion_actividades }}</td></tr>
    <tr><th>Días hábiles anticipación</th><td>{{ $cometido->dias_habiles_anticipacion ?? '—' }}</td></tr>
    <tr><th>Justificación menor a 7 días hábiles</th><td>{{ $cometido->justificacion_menor_7_dias ?: 'No aplica' }}</td></tr>
</table>
<div class="section">Firmas electrónicas internas</div>
<p class="small">La presente Solicitud de Pedido de Pasaje Aéreo se emite asociada al cometido funcionario autorizado y queda respaldada por la firma electrónica interna del funcionario solicitante y de la jefatura o subrogante autorizador.</p>
<table>
    <tr>
        @foreach($documento->firmas as $firma)
            <td class="signature">
                <strong>{{ $firma->tipo_firma === 'solicitante' ? 'Firmado electrónicamente por' : 'Autorizado electrónicamente por' }}:</strong><br>
                {{ $firma->nombre_firmante }}<br>
                RUN: {{ $firma->rut_firmante ?: '—' }}<br>
                Dependencia: {{ $firma->dependencia_firmante ?: '—' }}<br>
                {{ $firma->es_subrogante ? 'Autoriza en calidad de subrogante activo' : '' }}<br>
                Fecha: {{ optional($firma->fecha_firma)->format('d-m-Y H:i') }}
            </td>
        @endforeach
        @if($documento->firmas->isEmpty())
            <td class="signature">Documento generado sin firmas registradas.</td>
        @endif
    </tr>
</table>
<div class="validation">
    <table class="validation-table">
        <tr>
            <td>
                <strong>Código de validación:</strong> {{ $documento->codigo_validacion }}<br>
                <strong>Validación documental:</strong> {{ $validacionUrl }}<br>
                <strong>Hash documento:</strong> {{ $documento->documento_hash ?: 'se genera al emitir PDF' }}
            </td>
            <td class="qr">
                @if(!empty($qrDataUri))
                    <img src="{{ $qrDataUri }}" width="96" height="96" alt="QR validación documental">
                @else
                    <strong>QR no disponible</strong><br>Use el código de validación.
                @endif
            </td>
        </tr>
    </table>
</div>
</body>
</html>
