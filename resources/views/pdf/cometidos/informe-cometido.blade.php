<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Informe de Cometido Funcionario</title>
    <style>
        @page { margin: 28px 34px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        .header { width: 100%; border-bottom: 3px solid #0f5ea8; padding-bottom: 10px; margin-bottom: 14px; }
        .logo { width: 120px; }
        .title { font-size: 19px; font-weight: 700; color: #0f3760; margin: 0; text-transform: uppercase; }
        .subtitle { font-size: 12px; color: #475569; margin-top: 4px; }
        .meta { font-size: 10px; color: #475569; text-align: right; }
        .section-title { background: #0f5ea8; color: #fff; padding: 7px 9px; font-weight: 700; margin-top: 12px; margin-bottom: 0; border-radius: 4px 4px 0 0; }
        table { width: 100%; border-collapse: collapse; }
        .info td, .info th { border: 1px solid #d7e0ea; padding: 7px 8px; vertical-align: top; }
        .info th { width: 23%; background: #f1f5f9; color: #334155; text-align: left; font-weight: 700; }
        .muted { color: #64748b; }
        .box { border: 1px solid #d7e0ea; padding: 9px; min-height: 42px; white-space: pre-line; line-height: 1.42; }
        .warning { background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; padding: 8px; margin-top: 8px; }
        .signature { margin-top: 20px; border: 1px solid #cbd5e1; background: #ffffff; }
        .signature-main { padding: 0; }
        .signature-wrap { padding: 10px; }
        .signature-declaration { font-size: 10px; color: #334155; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; margin-bottom: 10px; line-height: 1.5; }
        .signature-cards { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin-left: -8px; margin-right: -8px; margin-bottom: 0; }
        .signature-cards td { width: 50%; vertical-align: top; padding: 0 8px; }
        .signature-card { border: 1px solid #dbe5ef; border-radius: 8px; background: #f8fafc; }
        .signature-card-head { background: #eaf2fb; color: #0f3760; font-weight: 700; padding: 7px 10px; border-bottom: 1px solid #dbe5ef; }
        .signature-card-body { padding: 10px; line-height: 1.55; }
        .signature-label { color: #64748b; font-size: 9px; text-transform: uppercase; letter-spacing: .03em; display: block; margin-bottom: 2px; }
        .signature-value { color: #1f2937; margin-bottom: 6px; }
        .signature-status { display: inline-block; padding: 2px 7px; border-radius: 999px; font-size: 9px; font-weight: 700; background: #dcfce7; color: #166534; }
        .signature-status.pending { background: #fef3c7; color: #92400e; }
        .signature-observation { margin-top: 10px; padding: 9px 10px; border: 1px solid #fde68a; background: #fffbeb; border-radius: 6px; }
        .validation-box { margin-top: 10px; border: 1px solid #dbe5ef; border-radius: 8px; background: #f8fafc; }
        .validation-head { background: #eaf2fb; color: #0f3760; font-weight: 700; padding: 7px 10px; border-bottom: 1px solid #dbe5ef; }
        .validation-body { padding: 10px; }
        .validation-layout { width: 100%; border-collapse: separate; border-spacing: 0; }
        .validation-layout td { vertical-align: top; }
        .validation-text { width: 67%; padding-right: 10px; line-height: 1.55; word-break: break-word; }
        .validation-qr { width: 33%; padding-left: 10px; border-left: 1px solid #dbe5ef; }
        .validation-row { margin-bottom: 5px; }
        .validation-row:last-child { margin-bottom: 0; }
        .qr-card { text-align: center; }
        .qr-card-head { color: #0f3760; font-weight: 700; margin-bottom: 8px; }
        .qr-card-body { padding: 0; }
        .qr-caption { margin-top: 8px; color: #475569; font-size: 9px; line-height: 1.4; }
        .small { font-size: 10px; }
        .footer-note { margin-top: 8px; color: #475569; font-size: 9px; text-align: center; }
    </style>
</head>
<body>
@php
    $funcionarioAc = $cometido->funcionarioAcAutorizado;
    $esAc = method_exists($cometido, 'esAdministracionCentral') && $cometido->esAdministracionCentral();
    $dependencia = $esAc ? ($cometido->subdireccion_dependencia_ac ?: ($funcionarioAc->subdireccion_dependencia ?? 'Administración Central')) : ($cometido->establecimiento->nombre ?? 'Establecimiento');
    $unidad = $esAc ? ($cometido->unidad_departamento_ac ?: ($funcionarioAc->unidad_departamento ?? '—')) : ($cometido->cargo_funcion ?? '—');
    $firmaFuncionario = $documento->firmas->where('tipo_firma', 'funcionario_informe')->sortByDesc('fecha_firma')->first();
    $firmaJefatura = $documento->firmas->where('tipo_firma', 'jefatura_informe')->sortByDesc('fecha_firma')->first();
    $financialPdfData = $financialPdfData ?? [
        'viatico' => [
            'mostrar' => (bool) $cometido->solicita_viatico,
            'anticipo' => (int) ($cometido->monto_anticipo_viatico ?? 0),
            'pendiente' => (int) ($cometido->monto_saldo_viatico ?? 0),
            'total' => (int) ($cometido->monto_viatico_solicitado_director ?? $cometido->cdp_viatico_total ?? 0),
        ],
        'reembolso' => [
            'mostrar' => (bool) $cometido->solicita_reembolso,
            'anticipo' => 0,
            'pendiente' => 0,
            'total' => 0,
        ],
    ];
    $jefaturaPdfData = $jefaturaPdfData ?? [
        'nombre' => $firmaJefatura?->nombre_firmante ?: 'Jefatura',
        'rut' => $firmaJefatura?->rut_firmante ?: '—',
        'subdireccion' => $firmaJefatura?->dependencia_firmante ?: ($cometido->subdireccion_dependencia_ac ?: '—'),
        'es_subrogante' => false,
    ];
    $formatoMonto = fn ($monto) => '$' . number_format((int) ($monto ?? 0), 0, ',', '.');
    $datoVisible = function ($valor): ?string {
        $valor = trim((string) ($valor ?? ''));
        return $valor !== '' && $valor !== '—' ? $valor : null;
    };
    $escalafonObservaciones = null;
    $observacionesAc = trim((string) ($funcionarioAc->observaciones ?? ''));
    if ($observacionesAc !== '' && preg_match('/Escalaf[oó]n:\s*(.*?)(?:\s+Unidad:|\s+Subdirecci[oó]n dependencia:|\s+Calidad jur[ií]dica:|$)/iu', $observacionesAc, $coincidenciasEscalafon)) {
        $escalafonObservaciones = trim(preg_replace('/\s+/', ' ', $coincidenciasEscalafon[1] ?? '')) ?: null;
    }
    $escalafonPdf = $datoVisible($cometido->estamento)
        ?: $datoVisible($funcionarioAc->escalafon ?? null)
        ?: $datoVisible($escalafonObservaciones)
        ?: '—';
@endphp
<table class="header">
    <tr>
        <td style="width: 135px;">
            @if(!empty($logoDataUri))
                <img src="{{ $logoDataUri }}" class="logo" alt="SLEP Andalién Costa">
            @endif
        </td>
        <td>
            <h1 class="title">Informe de Cometido Funcionario</h1>
            <div class="subtitle">Servicio Local de Educación Pública de Andalién Costa</div>
        </td>
        <td class="meta">
            <strong>N° Cometido:</strong> {{ $cometido->numero_cometido_interno ?: '#'.$cometido->id }}<br>
            <strong>N° Documento:</strong> {{ $documento->numero_documento }}<br>
            <strong>Fecha emisión:</strong> {{ optional($documento->emitido_at)->format('d-m-Y H:i') ?: now()->format('d-m-Y H:i') }}
        </td>
    </tr>
</table>

<div class="section-title">1. Identificación del funcionario</div>
<table class="info">
    <tr>
        <th>Nombre</th><td>{{ $cometido->funcionario_nombre ?: '—' }}</td>
        <th>RUN</th><td>{{ $cometido->funcionario_rut ?: '—' }}</td>
    </tr>
    <tr>
        <th>Dependencia</th><td>{{ $dependencia }}</td>
        <th>Unidad / cargo</th><td>{{ $unidad }}</td>
    </tr>
    <tr>
        <th>Estamento / escalafón</th><td>{{ $escalafonPdf }}</td>
        <th>Grado</th><td>{{ $funcionarioAc->grado ?? '—' }}</td>
    </tr>
    <tr>
        <th>Teléfono</th><td>{{ $funcionarioAc->telefono ?? '—' }}</td>
        <th>Correo electrónico</th><td>{{ $funcionarioAc->email ?? optional($cometido->solicitante)->email ?? '—' }}</td>
    </tr>
</table>

<div class="section-title">2. Detalle original del cometido</div>
<table class="info">
    <tr>
        <th>Origen</th><td>{{ $cometido->comuna_origen_nombre ?: '—' }}</td>
        <th>Destino</th><td>{{ $cometido->comuna_destino_nombre ?: $cometido->destino ?: '—' }}</td>
    </tr>
    <tr>
        <th>Institución / lugar</th><td colspan="3">{{ $cometido->institucion_destino ?: $cometido->destino ?: '—' }}</td>
    </tr>
    <tr>
        <th>Fecha desde</th><td>{{ optional($cometido->fecha_desde)->format('d-m-Y') ?: '—' }}</td>
        <th>Fecha hasta</th><td>{{ optional($cometido->fecha_hasta)->format('d-m-Y') ?: '—' }}</td>
    </tr>
    <tr>
        <th>Hora salida</th><td>{{ substr((string) $cometido->hora_salida, 0, 5) ?: '—' }}</td>
        <th>Hora regreso</th><td>{{ substr((string) $cometido->hora_regreso, 0, 5) ?: '—' }}</td>
    </tr>
    <tr>
        <th>Motivo</th><td colspan="3">{{ $cometido->motivo }}{{ $cometido->motivo_otro ? ' - '.$cometido->motivo_otro : '' }}</td>
    </tr>
    <tr>
        <th>Descripción original</th><td colspan="3">{{ $cometido->descripcion_actividades ?: '—' }}</td>
    </tr>
</table>

<div class="section-title">3. Fechas y horarios reales informados</div>
<table class="info">
    <tr>
        <th>Fecha real desde</th><td>{{ optional($informe->fecha_desde_real)->format('d-m-Y') ?: '—' }}</td>
        <th>Fecha real hasta</th><td>{{ optional($informe->fecha_hasta_real)->format('d-m-Y') ?: '—' }}</td>
    </tr>
    <tr>
        <th>Hora real salida</th><td>{{ substr((string) $informe->hora_salida_real, 0, 5) ?: '—' }}</td>
        <th>Hora real regreso</th><td>{{ substr((string) $informe->hora_regreso_real, 0, 5) ?: '—' }}</td>
    </tr>
    <tr>
        <th>Justificación por cambios</th><td colspan="3">{{ $informe->justificacion_cambio_fechas ?: 'No registra cambios respecto de la solicitud original.' }}</td>
    </tr>
</table>

@if(($financialPdfData['viatico']['mostrar'] ?? false))
    <div style="margin-top: 8px;"><strong>Información financiera - Viático</strong></div>
    <table class="info">
        <tr>
            <th>Anticipo viático</th><td>{{ $formatoMonto($financialPdfData['viatico']['anticipo'] ?? 0) }}</td>
            <th>Pendiente viático</th><td>{{ $formatoMonto($financialPdfData['viatico']['pendiente'] ?? 0) }}</td>
        </tr>
        <tr>
            <th>Total a pagar</th><td colspan="3">{{ $formatoMonto($financialPdfData['viatico']['total'] ?? 0) }}</td>
        </tr>
    </table>
@endif

@if(($financialPdfData['reembolso']['mostrar'] ?? false))
    <div style="margin-top: 8px;"><strong>Información financiera - Reembolso</strong></div>
    <table class="info">
        <tr>
            <th>Anticipo reembolso</th><td>{{ $formatoMonto($financialPdfData['reembolso']['anticipo'] ?? 0) }}</td>
            <th>Pendiente reembolso</th><td>{{ $formatoMonto($financialPdfData['reembolso']['pendiente'] ?? 0) }}</td>
        </tr>
        <tr>
            <th>Total a pagar</th><td colspan="3">{{ $formatoMonto($financialPdfData['reembolso']['total'] ?? 0) }}</td>
        </tr>
    </table>
@endif

@if($informe->requiere_nuevo_cometido_diferencia)
    <div class="warning"><strong>Alerta:</strong> el informe registra diferencias de fechas u horarios a favor del funcionario. Debe evaluarse la generación de un nuevo cometido por la diferencia.</div>
@endif

<div class="section-title">4. Desarrollo del cometido</div>
<table class="info">
    <tr><th>Organismos, autoridades o relatores</th><td>{{ $informe->organismos_autoridades_relatores ?: '—' }}</td></tr>
</table>
<div style="margin-top: 8px;"><strong>Descripción de actividades realizadas</strong></div>
<div class="box">{{ $informe->descripcion_actividades_realizadas ?: '—' }}</div>
<div style="margin-top: 8px;"><strong>Resultados obtenidos</strong></div>
<div class="box">{{ $informe->resultados_obtenidos ?: '—' }}</div>
<div style="margin-top: 8px;"><strong>Opiniones y propuestas</strong></div>
<div class="box">{{ $informe->opiniones_propuestas ?: '—' }}</div>

<div class="section-title">5. Declaración, revisión y firmas electrónicas</div>
<table class="signature">
    <tr>
        <td class="signature-main">
            <div class="signature-wrap">
                <div class="signature-declaration">
                    Declaro que el presente informe refleja las actividades realizadas durante el cometido funcionario y que la información proporcionada es fidedigna.
                </div>

                <table class="signature-cards">
                    <tr>
                        <td>
                            <div class="signature-card">
                                <div class="signature-card-head">Firma electrónica del funcionario</div>
                                <div class="signature-card-body">
                                    <span class="signature-label">Nombre</span>
                                    <div class="signature-value">{{ $firmaFuncionario?->nombre_firmante ?: $cometido->funcionario_nombre ?: 'Funcionario' }}</div>

                                    <span class="signature-label">RUN</span>
                                    <div class="signature-value">{{ $firmaFuncionario?->rut_firmante ?: $cometido->funcionario_rut ?: '—' }}</div>

                                    <span class="signature-label">Dependencia</span>
                                    <div class="signature-value">{{ $firmaFuncionario?->dependencia_firmante ?: $dependencia }}</div>

                                    <span class="signature-label">Fecha y hora de firma</span>
                                    <div class="signature-value">{{ optional($firmaFuncionario?->fecha_firma)->format('d-m-Y H:i') ?: optional($informe->fecha_envio)->format('d-m-Y H:i') ?: '—' }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="signature-card">
                                <div class="signature-card-head">Firma electrónica de jefatura</div>
                                <div class="signature-card-body">
                                    @if($firmaJefatura)
                                        <span class="signature-label">Estado de revisión</span>
                                        <div class="signature-value"><span class="signature-status">{{ $informe->decision_jefatura ? ucfirst($informe->decision_jefatura) : 'Aprobado' }}</span></div>

                                        <span class="signature-label">Nombre</span>
                                        <div class="signature-value">{{ $jefaturaPdfData['nombre'] ?? ($firmaJefatura?->nombre_firmante ?: 'Jefatura') }}</div>

                                        <span class="signature-label">RUN</span>
                                        <div class="signature-value">{{ $jefaturaPdfData['rut'] ?? ($firmaJefatura?->rut_firmante ?: '—') }}</div>

                                        <span class="signature-label">Subdirección</span>
                                        <div class="signature-value">{{ $jefaturaPdfData['subdireccion'] ?? '—' }}</div>

                                        <span class="signature-label">Fecha y hora de revisión</span>
                                        <div class="signature-value">{{ optional($firmaJefatura?->fecha_firma)->format('d-m-Y H:i') ?: optional($informe->fecha_revision_jefatura)->format('d-m-Y H:i') ?: '—' }}</div>
                                    @else
                                        <span class="signature-label">Estado de revisión</span>
                                        <div class="signature-value"><span class="signature-status pending">Pendiente</span></div>
                                        <div class="muted">El informe se encuentra pendiente de revisión y firma electrónica por jefatura.</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>

                @if($informe->observacion_jefatura)
                    <div class="signature-observation">
                        <strong>Observación de jefatura:</strong> {{ $informe->observacion_jefatura }}
                    </div>
                @endif

                <div class="validation-box">
                    <div class="validation-head">Validación del documento</div>
                    <div class="validation-body">
                        <table class="validation-layout">
                            <tr>
                                <td class="validation-text">
                                    <div class="validation-row"><strong>Código de validación:</strong> {{ $documento->codigo_validacion }}</div>
                                    <div class="validation-row"><strong>Validación documental:</strong> {{ $validacionUrl }}</div>
                                    <div class="validation-row"><strong>Hash documento:</strong> {{ $documento->documento_hash ?: 'se genera al emitir PDF' }}</div>
                                </td>
                                <td class="validation-qr">
                                    <div class="qr-card">
                                        <div class="qr-card-head">Código QR</div>
                                        <div class="qr-card-body">
                                            @if(!empty($qrDataUri))
                                                <img src="{{ $qrDataUri }}" width="96" height="96" alt="QR validación documental">
                                            @endif
                                            <div class="qr-caption">Escanee este código para comprobar autenticidad, vigencia y datos de validación del documento.</div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </td>
    </tr>
</table>
<div class="footer-note">Escanee el QR o ingrese el código de validación en la plataforma para comprobar autenticidad y vigencia del documento.</div>
</body>
</html>
