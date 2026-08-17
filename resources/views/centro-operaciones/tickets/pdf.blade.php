<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 34px 38px 46px; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #233143; font-size: 10px; line-height: 1.35; }
        h1, h2, p { margin: 0; }
        .header { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .header td { vertical-align: middle; }
        .logo { width: 150px; }
        .logo img { width: 132px; max-height: 62px; object-fit: contain; }
        .institution { text-align: right; color: #52657a; font-size: 8.5px; text-transform: uppercase; letter-spacing: .6px; }
        .document-title { border-top: 4px solid #174a7e; background: #eef5fb; padding: 13px 15px; margin-bottom: 12px; }
        .document-title table { width: 100%; border-collapse: collapse; }
        .document-title td { vertical-align: middle; }
        .eyebrow { color: #3172ad; font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: .7px; margin-bottom: 3px; }
        .document-title h1 { color: #123f6c; font-size: 19px; line-height: 1.15; }
        .subtitle { color: #617286; font-size: 9px; margin-top: 4px; }
        .status-cell { width: 120px; text-align: right; }
        .status { display: inline-block; padding: 6px 11px; border-radius: 12px; font-size: 8px; font-weight: bold; text-transform: uppercase; }
        .status-resuelto { background: #dcf4e5; color: #17613a; }
        .status-vencido, .status-escalado { background: #fde5e5; color: #9d2424; }
        .status-asignado { background: #e5f0fb; color: #174a7e; }
        .status-pendiente_asignacion { background: #fff0ce; color: #805a00; }
        .summary { width: 100%; border-collapse: separate; border-spacing: 5px 0; margin: 0 -5px 12px; }
        .summary td { width: 25%; border: 1px solid #d6e0ea; padding: 8px; vertical-align: top; }
        .label { display: block; color: #6b7c8e; font-size: 7.5px; text-transform: uppercase; letter-spacing: .35px; margin-bottom: 3px; }
        .value { display: block; color: #203247; font-size: 9px; font-weight: bold; }
        .section { border: 1px solid #d6e0ea; margin-bottom: 10px; page-break-inside: avoid; }
        .section-head { background: #f5f8fb; border-bottom: 1px solid #d6e0ea; padding: 7px 10px; }
        .section-head h2 { color: #174a7e; font-size: 11px; }
        .section-body { padding: 9px 10px; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid td { width: 50%; vertical-align: top; padding: 5px 8px 5px 0; }
        .grid td + td { border-left: 1px solid #e1e8ef; padding-left: 10px; }
        .detail { background: #f8fafc; border-left: 3px solid #3172ad; padding: 8px 10px; margin-top: 7px; white-space: pre-wrap; }
        .severity { color: #9d2424; font-weight: bold; text-transform: uppercase; }
        .resolution { background: #eff9f3; border-left: 3px solid #268455; padding: 9px 10px; white-space: pre-wrap; }
        .pending { background: #fff8e7; border-left: 3px solid #d89a19; padding: 9px 10px; color: #6c541b; }
        .photo-section { page-break-inside: auto; }
        .photo-grid { margin: -4px; font-size: 0; }
        .photo { width: 50%; display: inline-block; padding: 4px; vertical-align: top; page-break-inside: avoid; }
        .photo-box { border: 1px solid #d6e0ea; padding: 5px; }
        .photo img { width: 100%; max-height: 220px; display: block; object-fit: contain; }
        .photo-caption { margin-top: 4px; color: #617286; font-size: 8px; text-align: center; }
        .signature { width: 100%; border-collapse: collapse; }
        .signature td { vertical-align: top; }
        .signature-mark { width: 54px; height: 54px; border: 2px solid #268455; color: #268455; text-align: center; font-size: 25px; font-weight: bold; line-height: 50px; }
        .signature-data { padding-left: 12px; }
        .signature-title { color: #17613a; font-size: 10px; font-weight: bold; margin-bottom: 3px; }
        .signature-line { margin: 2px 0; }
        .hash { font-family: DejaVu Sans Mono, monospace; font-size: 7px; word-break: break-all; color: #43556a; }
        .final-grid { width: 100%; border-collapse: collapse; }
        .final-grid > tbody > tr > td { vertical-align: top; }
        .signature-cell { width: 57%; padding-right: 10px; }
        .verification-cell { width: 43%; border-left: 1px solid #d6e0ea; padding-left: 10px; }
        .verification { width: 100%; border-collapse: collapse; }
        .verification td { vertical-align: middle; }
        .verification-data { padding-right: 5px; }
        .qr { width: 76px; text-align: center; }
        .qr img { width: 70px; height: 70px; }
        .validation-url { color: #174a7e; font-size: 7px; word-break: break-all; }
        .legal { margin-top: 7px; color: #6d7d8d; font-size: 7.5px; }
        .footer { position: fixed; left: 0; right: 0; bottom: -29px; border-top: 1px solid #d6e0ea; padding-top: 6px; color: #768697; font-size: 7px; text-align: center; }
    </style>
</head>
<body>
@php
    $estado = strtolower($ticket->estado);
    $estadoLabel = match ($estado) {
        'pendiente_asignacion' => 'Pendiente de asignación',
        'asignado' => 'Asignado',
        'vencido' => 'Vencido',
        'escalado' => 'Escalado',
        'resuelto' => 'Resuelto',
        default => ucfirst(str_replace('_', ' ', $estado)),
    };
    $reporte = $ticket->incidencia->reporte;
    $firma = $ticket->firmaResolucion;
@endphp

<table class="header">
    <tr>
        <td class="logo">@if($logoDataUri)<img src="{{ $logoDataUri }}" alt="SLEP Andalién Costa">@endif</td>
        <td class="institution">Servicio Local de Educación Pública Andalién Costa<br>Centro de Operaciones</td>
    </tr>
</table>

<div class="document-title">
    <table>
        <tr>
            <td>
                <div class="eyebrow">Informe de gestión de incidencia</div>
                <h1>{{ $ticket->numero }}</h1>
                <div class="subtitle">Documento de seguimiento, resolución y trazabilidad institucional</div>
            </td>
            <td class="status-cell"><span class="status status-{{ $estado }}">{{ $estadoLabel }}</span></td>
        </tr>
    </table>
</div>

<table class="summary">
    <tr>
        <td><span class="label">Fecha de creación</span><span class="value">{{ $ticket->created_at?->format('d/m/Y H:i') }} hrs.</span></td>
        <td><span class="label">Fecha límite</span><span class="value">{{ $ticket->vence_en?->format('d/m/Y H:i') ?? 'Por asignar' }}</span></td>
        <td><span class="label">Reporte de origen</span><span class="value">{{ $reporte ? 'N° '.$reporte->id.' - v'.$reporte->version : 'Sin reporte' }}</span></td>
        <td><span class="label">Severidad</span><span class="value severity">{{ ucfirst($ticket->incidencia->severidad ?: 'alerta') }}</span></td>
    </tr>
</table>

<div class="section">
    <div class="section-head"><h2>1. Identificación de la incidencia</h2></div>
    <div class="section-body">
        <table class="grid">
            <tr>
                <td><span class="label">Tipo de incidencia</span><span class="value">{{ $ticket->incidencia->tipo_label }}</span></td>
                <td><span class="label">Modalidad</span><span class="value">{{ $ticket->incidencia->modalidad ? ucfirst(str_replace('_', ' ', $ticket->incidencia->modalidad)) : 'No aplica' }}</span></td>
            </tr>
            <tr>
                <td><span class="label">Establecimiento</span><span class="value">{{ $ticket->incidencia->establecimiento?->nombre_establecimiento ?? 'Sin establecimiento' }}</span></td>
                <td><span class="label">RBD / Comuna</span><span class="value">{{ $ticket->incidencia->establecimiento?->rbd ?? 'Sin RBD' }} / {{ $reporte?->establecimiento_comuna ?: 'Sin comuna' }}</span></td>
            </tr>
            <tr>
                <td><span class="label">Enviado por</span><span class="value">{{ $reporte?->reportado_por_nombre_visible ?? 'Usuario registrado sin nombre disponible' }}</span></td>
                <td><span class="label">Fecha del reporte</span><span class="value">{{ $reporte?->reportado_en?->format('d/m/Y H:i') ?? 'Sin fecha' }} hrs.</span></td>
            </tr>
        </table>
        <div class="detail"><span class="label">Detalle informado</span>{{ $ticket->incidencia->descripcion ?: 'Sin detalle informado.' }}</div>
    </div>
</div>

<div class="section">
    <div class="section-head"><h2>2. Asignación y responsabilidad</h2></div>
    <div class="section-body">
        <table class="grid">
            <tr>
                <td><span class="label">Responsable principal</span><span class="value">{{ $ticket->responsable?->nombre_completo ?? 'Pendiente de asignación' }}</span></td>
                <td><span class="label">Segundo responsable</span><span class="value">{{ $ticket->segundoResponsable?->nombre_completo ?? 'No asignado' }}</span></td>
            </tr>
            <tr>
                <td><span class="label">Unidad / Departamento</span><span class="value">{{ $ticket->unidad_departamento ?: 'Pendiente de asignación' }}</span></td>
                <td><span class="label">Subdirección responsable</span><span class="value">{{ $ticket->subdireccion_dependencia ?: 'Pendiente de asignación' }}</span></td>
            </tr>
            @if($ticket->segunda_subdireccion_responsable)
                <tr><td colspan="2"><span class="label">Segunda subdirección responsable</span><span class="value">{{ $ticket->segunda_subdireccion_responsable }}</span></td></tr>
            @endif
        </table>
    </div>
</div>

<div class="section">
    <div class="section-head"><h2>3. Resolución</h2></div>
    <div class="section-body">
        @if($estado === 'resuelto')
            <div class="resolution"><span class="label">Acciones y resultado</span>{{ $ticket->resolucion ?: 'Sin detalle de resolución.' }}</div>
        @else
            <div class="pending">El ticket permanece {{ strtolower($estadoLabel) }}. La firma electrónica de cierre se incorporará cuando el responsable registre la resolución.</div>
        @endif
    </div>
</div>

@if($imagenesPdf->isNotEmpty())
    <div class="section photo-section">
        <div class="section-head"><h2>4. Registro fotográfico del establecimiento</h2></div>
        <div class="section-body">
            <div class="photo-grid">
                @foreach($imagenesPdf as $imagen)
                    <div class="photo">
                        <div class="photo-box">
                            <img src="{{ $imagen }}" alt="Fotografía {{ $loop->iteration }}">
                            <div class="photo-caption">Fotografía {{ $loop->iteration }} de {{ $imagenesPdf->count() }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif

<div class="section">
    <div class="section-head"><h2>{{ $imagenesPdf->isNotEmpty() ? '5' : '4' }}. Firma electrónica y verificación documental</h2></div>
    <div class="section-body">
        <table class="final-grid">
            <tr>
                <td class="signature-cell">
                    @if($firma)
                        <table class="signature">
                            <tr>
                                <td class="signature-mark">FE</td>
                                <td class="signature-data">
                                    <div class="signature-title">Firmado electrónicamente</div>
                                    <div class="signature-line"><strong>{{ $firma->nombre_firmante }}</strong>@if($firma->rut_firmante) - RUT {{ $firma->rut_firmante }}@endif</div>
                                    <div class="signature-line">{{ $firma->cargo_firmante ?: $firma->rol_firmante ?: 'Funcionario responsable' }}</div>
                                    @if($firma->dependencia_firmante)<div class="signature-line">{{ $firma->dependencia_firmante }}</div>@endif
                                    <div class="signature-line">Fecha y hora: {{ $firma->fecha_firma?->format('d/m/Y H:i:s') }} hrs.</div>
                                    <div class="signature-line hash">Huella de firma: {{ $firma->hash_firma }}</div>
                                </td>
                            </tr>
                        </table>
                    @else
                        <div class="pending">El ticket aún no registra firma electrónica de cierre. Esta se incorporará automáticamente cuando el responsable informe la resolución.</div>
                    @endif
                </td>
                <td class="verification-cell">
                    <table class="verification">
                        <tr>
                            <td class="verification-data">
                                <span class="label">Código</span>
                                <span class="value">{{ $ticket->codigo_validacion }}</span>
                                <div style="height:4px"></div>
                                <span class="label">Huella de datos SHA-256</span>
                                <div class="hash">{{ $ticket->documento_hash }}</div>
                            </td>
                            <td class="qr">@if($qrDataUri)<img src="{{ $qrDataUri }}" alt="QR de validación documental">@endif</td>
                        </tr>
                    </table>
                    <div class="validation-url">{{ $validacionUrl }}</div>
                    <p class="legal">Escanee el QR para comprobar la existencia, estado, integridad de los datos y firma registrada.</p>
                </td>
            </tr>
        </table>
    </div>
</div>

<div class="footer">Documento generado por el Sistema SGA - SLEP Andalién Costa - {{ $ticket->documento_emitido_en?->format('d/m/Y H:i:s') }} hrs.</div>
</body>
</html>
