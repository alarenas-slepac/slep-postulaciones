@extends('layouts.app')

@section('content')
@php
    $estado = $cometido->estado ?? 'sin_estado';
    $nombreFuncionario = $cometido->funcionario_nombre ?? $cometido->nombre_funcionario ?? $cometido->nombre ?? 'Funcionario/a';
    $rutFuncionario = $cometido->funcionario_rut ?? $cometido->rut_funcionario ?? $cometido->rut ?? null;
    $montoRendido = (int) ($rendicion?->monto_rendido ?? 0);
    $montoAutorizado = $rendicion?->monto_autorizado_daf;
    $montoCdpRendicion = $rendicion?->monto_cdp_reembolso;
    $montoResolucion = $resolucion?->monto_resolucion;
    $montoPago = $resolucion?->monto_pagado_reembolso;
    $referenciaReembolso = $referenciaReembolso ?? [];
    $referenciaReembolsoTotal = $referenciaReembolso['total_referencial'] ?? null;
    $referenciaReembolsoRows = collect($referenciaReembolso['rows'] ?? []);
    $estadoRendicion = $rendicion?->estado;
    $estadoResolucion = $resolucion?->estado;
    $informeCometido = $informeCometido ?? null;
    $estadoInformeCometido = $informeCometido?->estado_informe;
    $informeCometidoAprobado = (bool) ($informeCometidoAprobado ?? false);
    $puedeCompletarInformeCometido = (bool) ($puedeCompletarInformeCometido ?? false);
    $bloqueaDafPorInforme = (bool) ($bloqueaDafPorInforme ?? false);
    $documentoInformeCometido = $cometido->documentosGenerados->where('tipo_documento', 'informe_cometido')->sortByDesc('id')->first();
    $documentosRespaldoRaw = $rendicion?->documentos_respaldo;
    if (is_string($documentosRespaldoRaw)) {
        $documentosRespaldoDecoded = json_decode($documentosRespaldoRaw, true);
        $documentosRespaldoRaw = is_array($documentosRespaldoDecoded) ? $documentosRespaldoDecoded : [];
    }
    $documentosRespaldo = isset($documentosRendicionVisibles) && is_array($documentosRendicionVisibles)
        ? array_values($documentosRendicionVisibles)
        : (is_array($documentosRespaldoRaw) ? array_values($documentosRespaldoRaw) : []);
    $activeRole = auth()->user() && method_exists(auth()->user(), 'activeRoleName') ? auth()->user()->activeRoleName() : null;
    $esCometidoAcRendicion = method_exists($cometido, 'esAdministracionCentral') && $cometido->esAdministracionCentral();
    $esFuncionarioEstab = $activeRole === 'funcionario_estab';
    $esFuncionarioAcSolicitante = $activeRole === 'funcionario_ac' && $esCometidoAcRendicion && (int) $cometido->user_id === (int) auth()->id();
    $actorSolicitanteRendicion = $esCometidoAcRendicion ? 'funcionario AC' : 'establecimiento';
    $mostrarFormularioRendicion = $puedeRendir && ($esFuncionarioEstab || $esFuncionarioAcSolicitante || $activeRole === 'admin') && (! $rendicion);
    $puedeRectificarRendicion = $rendicion
        && $estadoRendicion === 'rendicion_observada_daf'
        && ($esFuncionarioEstab || $esFuncionarioAcSolicitante || $activeRole === 'admin');
    $puedeVerValoresReferenciaDaf = ! ($esFuncionarioEstab || $esFuncionarioAcSolicitante);
    $puedeActuarDaf = $puedeRevisarDaf && in_array($activeRole, ['admin', 'funcionario_daf'], true);
    $puedeActuarCdpRendicion = $puedeRevisarCdp && in_array($activeRole, ['admin', 'supervisor_plani', 'coordinador_plani'], true);
    $puedeGestionarPagoCierre = $puedeRegistrarPago && in_array($activeRole, ['admin', 'funcionario_daf'], true);
    $puedeRegistrarContabilidad = (bool) ($puedeRegistrarContabilidad ?? false) && in_array($activeRole, ['admin', 'funcionario_daf'], true);
    $documentoDafPath = $rendicion?->documento_daf_path;
    $documentoCdpRendicionPath = $rendicion?->documento_cdp_reembolso_path;
    $documentoResolucionPath = $resolucion?->documento_resolucion_path;
    $documentoPagoPath = $resolucion?->documento_pago_path;
    $pagoReembolsoRegistrado = (bool) ($resolucion && (
        $resolucion->estado === 'reembolso_pagado'
        || $resolucion->monto_pagado_reembolso !== null
        || $resolucion->fecha_pago_reembolso
        || $documentoPagoPath
    ));
    $resolucionPagoEmitida = (bool) ($resolucion && (
        in_array($resolucion->estado, ['resolucion_reembolso_emitida', 'reembolso_pagado'], true)
        || $resolucion->numero_resolucion
        || $resolucion->fecha_resolucion
        || $documentoResolucionPath
    ));
    $resolucionPendienteJuridica = (bool) ($resolucion && ! $resolucionPagoEmitida);
    $cdpRendicionGenerado = (bool) ($rendicion?->referencia_cdp_reembolso || $rendicion?->monto_cdp_reembolso !== null || $documentoCdpRendicionPath);
    $rendicionPendienteRevisionDaf = $rendicion
        && $rendicion->monto_autorizado_daf === null
        && ! in_array($rendicion->estado, [
            'rendicion_observada_daf',
            'rendicion_rechazada_daf',
            'en_revision_cdp_rendicion',
            'cdp_observado_rendicion',
            'cdp_rechazado_rendicion',
            'cdp_reembolso_aprobado',
            'cerrado_sin_pago_reembolso',
        ], true);

    $fmtMoney = fn ($value) => '$' . number_format((int) $value, 0, ',', '.');

    if ($rendicion) {
        if ($estadoRendicion === 'rendicion_rechazada_daf') {
            $statusRendicion = 'rejected';
        } elseif ($estadoRendicion === 'rendicion_observada_daf') {
            $statusRendicion = 'observed';
        } else {
            $statusRendicion = 'completed';
        }
    } else {
        $statusRendicion = $puedeRendir ? 'current' : 'pending';
    }

    if ($informeCometidoAprobado) {
        $statusInforme = 'completed';
    } elseif($estadoInformeCometido === 'enviado_pendiente_jefatura') {
        $statusInforme = 'current';
    } elseif(in_array($estadoInformeCometido, ['observado_jefatura', 'informe_observado'], true)) {
        $statusInforme = 'observed';
    } elseif($puedeCompletarInformeCometido) {
        $statusInforme = 'current';
    } else {
        $statusInforme = 'pending';
    }

    if ($bloqueaDafPorInforme) {
        $statusDaf = 'pending';
    } elseif (! $rendicion) {
        $statusDaf = 'pending';
    } elseif ($estadoRendicion === 'rendicion_observada_daf') {
        $statusDaf = 'observed';
    } elseif ($estadoRendicion === 'rendicion_rechazada_daf') {
        $statusDaf = 'rejected';
    } elseif ($montoAutorizado !== null || in_array($estadoRendicion, ['en_revision_cdp_rendicion', 'cdp_observado_rendicion', 'cdp_rechazado_rendicion', 'cdp_reembolso_aprobado', 'cerrado_sin_pago_reembolso'], true)) {
        $statusDaf = 'completed';
    } else {
        $statusDaf = 'current';
    }

    if (! $rendicion || $montoAutorizado === null || (int) $montoAutorizado <= 0) {
        $statusCdp = 'pending';
    } elseif ($estadoRendicion === 'cdp_observado_rendicion') {
        $statusCdp = 'observed';
    } elseif ($estadoRendicion === 'cdp_rechazado_rendicion') {
        $statusCdp = 'rejected';
    } elseif ($montoCdpRendicion !== null || in_array($estadoRendicion, ['cdp_reembolso_aprobado'], true)) {
        $statusCdp = 'completed';
    } elseif (in_array($estadoRendicion, ['en_revision_cdp_rendicion'], true)) {
        $statusCdp = 'current';
    } else {
        $statusCdp = 'pending';
    }

    if (! $resolucion) {
        $statusJuridica = 'pending';
    } elseif (in_array($estadoResolucion, ['resolucion_reembolso_emitida', 'contabilidad_reembolso_registrada', 'reembolso_pagado'], true)) {
        $statusJuridica = 'completed';
    } else {
        $statusJuridica = 'current';
    }

    $statusContable = $estadoResolucion === 'reembolso_pagado' || $estadoResolucion === 'contabilidad_reembolso_registrada' ? 'completed' : ($estadoResolucion === 'resolucion_reembolso_emitida' ? 'current' : 'pending');
    $statusPago = $estadoResolucion === 'reembolso_pagado'
        ? 'completed'
        : ($estadoResolucion === 'contabilidad_reembolso_registrada' ? 'current' : 'pending');
    $statusCierre = ($cometido->estado ?? null) === 'cerrado' ? 'completed' : 'pending';

    $statusMeta = [
        'completed' => ['class' => 'is-completed', 'badge' => 'Completado', 'icon' => 'bi-check2'],
        'current' => ['class' => 'is-current', 'badge' => 'Actual', 'icon' => 'bi-hourglass-split'],
        'pending' => ['class' => 'is-pending', 'badge' => 'Pendiente', 'icon' => 'bi-clock'],
        'observed' => ['class' => 'is-observed', 'badge' => 'Observado', 'icon' => 'bi-exclamation-circle'],
        'rejected' => ['class' => 'is-rejected', 'badge' => 'Rechazado', 'icon' => 'bi-x-circle'],
    ];

    $timelineSteps = [
        ['label' => 'Rendición', 'description' => ($esCometidoAcRendicion ? 'El funcionario AC carga documentos fiscales y declara el monto rendido.' : 'El establecimiento carga documentos fiscales y declara el monto rendido.'), 'status' => $statusRendicion],
        ['label' => 'Informe cometido', 'description' => 'El funcionario envía el informe del cometido y la jefatura debe aprobarlo antes de DAF.', 'status' => $statusInforme],
        ['label' => 'DAF', 'description' => 'DAF revisa el respaldo sólo cuando rendición e informe aprobado estén completos.', 'status' => $statusDaf],
        ['label' => 'CDP rendición', 'description' => 'Planificación aprueba el CDP de la rendición antes de Jurídica.', 'status' => $statusCdp],
        ['label' => 'Jurídica', 'description' => 'Jurídica emite la resolución de pago correspondiente.', 'status' => $statusJuridica],
        ['label' => 'DAF contable', 'description' => 'DAF registra compromiso y devengo antes del pago.', 'status' => $statusContable],
        ['label' => 'Pago', 'description' => 'DAF / Finanzas registra el pago efectivo del reembolso.', 'status' => $statusPago],
        ['label' => 'Cierre', 'description' => 'Se cierra el trámite cuando el pago de reembolso queda finalizado.', 'status' => $statusCierre],
    ];
@endphp

<style>
    .rendicion-page { --brand: #2563eb; --ink: #0f172a; --muted: #64748b; --line: #dbe3ef; }
    .rendicion-page .page-title { font-weight: 800; color: var(--ink); letter-spacing: -.03em; }
    .rendicion-page .page-kicker { color: #64748b; font-size: .78rem; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; }
    .rendicion-shell { border: 1px solid var(--line); border-radius: 1.25rem; background: #fff; box-shadow: 0 12px 28px rgba(15, 23, 42, .08); overflow: hidden; }
    .rendicion-shell-header { padding: 1.35rem 1.5rem; border-bottom: 1px solid var(--line); background: linear-gradient(135deg, #fff 0%, #f8fbff 100%); display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
    .rendicion-title-wrap { display: flex; align-items: flex-start; gap: 1rem; }
    .rendicion-main-icon { width: 3rem; height: 3rem; border-radius: 1rem; display: inline-flex; align-items: center; justify-content: center; background: #dbeafe; color: #1d4ed8; font-size: 1.35rem; flex: 0 0 auto; }
    .rendicion-status-pill { border: 1px solid #bfdbfe; background: #eff6ff; color: #1d4ed8; border-radius: 999px; padding: .45rem .8rem; font-weight: 800; font-size: .8rem; white-space: nowrap; }
    .rendicion-summary { padding: 1.25rem 1.5rem; background: #f8fafc; border-bottom: 1px solid var(--line); }
    .summary-card { border: 1px solid var(--line); border-radius: 1rem; background: #fff; padding: 1rem; height: 100%; box-shadow: 0 6px 16px rgba(15, 23, 42, .04); }
    .summary-card .label { color: #64748b; font-size: .75rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; margin-bottom: .35rem; }
    .summary-card .value { color: var(--ink); font-size: 1.7rem; line-height: 1.1; font-weight: 800; letter-spacing: -.03em; }
    .summary-card .hint { color: #64748b; font-size: .83rem; margin-top: .45rem; }
    .summary-card.is-green .value { color: #047857; }
    .summary-card.is-indigo .value { color: #4338ca; }
    .summary-card.is-purple .value { color: #7e22ce; }
    .rendicion-timeline { padding: 1.5rem; }
    .timeline-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; }
    .timeline-step { border: 1px solid var(--line); border-radius: 1rem; background: #f8fafc; padding: 1rem; min-height: 12rem; position: relative; }
    .timeline-step-head { display: flex; align-items: flex-start; justify-content: space-between; gap: .75rem; margin-bottom: 1rem; }
    .timeline-dot { width: 2.75rem; height: 2.75rem; border-radius: .9rem; display: inline-flex; align-items: center; justify-content: center; color: #0f172a; background: #e2e8f0; font-size: 1.1rem; }
    .timeline-badge { border-radius: 999px; padding: .35rem .65rem; font-size: .75rem; font-weight: 800; background: #e2e8f0; color: #475569; }
    .timeline-kicker { color: #64748b; text-transform: uppercase; font-size: .72rem; letter-spacing: .05em; font-weight: 800; }
    .timeline-title { color: var(--ink); font-size: 1.05rem; font-weight: 800; margin-top: .25rem; }
    .timeline-desc { color: #475569; font-size: .88rem; margin-top: .65rem; line-height: 1.45; }
    .timeline-step.is-completed { background: #f0fdf4; border-color: #bbf7d0; }
    .timeline-step.is-completed .timeline-dot, .timeline-step.is-completed .timeline-badge { background: #dcfce7; color: #047857; }
    .timeline-step.is-current { background: #eff6ff; border-color: #93c5fd; box-shadow: 0 8px 24px rgba(37, 99, 235, .12); }
    .timeline-step.is-current .timeline-dot, .timeline-step.is-current .timeline-badge { background: #dbeafe; color: #1d4ed8; }
    .timeline-step.is-observed { background: #fffbeb; border-color: #fde68a; }
    .timeline-step.is-observed .timeline-dot, .timeline-step.is-observed .timeline-badge { background: #fef3c7; color: #b45309; }
    .timeline-step.is-rejected { background: #fef2f2; border-color: #fecaca; }
    .timeline-step.is-rejected .timeline-dot, .timeline-step.is-rejected .timeline-badge { background: #fee2e2; color: #b91c1c; }
    .stage-card { border: 1px solid var(--line); border-radius: 1.25rem; background: #fff; box-shadow: 0 8px 22px rgba(15, 23, 42, .06); overflow: hidden; height: 100%; }
    .stage-card-header { padding: 1.15rem 1.25rem; border-bottom: 1px solid var(--line); background: linear-gradient(135deg, #fff 0%, #f8fafc 100%); }
    .stage-card-header .stage-number { color: #64748b; font-size: .72rem; text-transform: uppercase; font-weight: 800; letter-spacing: .06em; }
    .stage-card-header h3 { color: var(--ink); font-size: 1.15rem; font-weight: 800; margin: .2rem 0 .25rem; }
    .stage-card-header p { color: #64748b; font-size: .87rem; margin: 0; }
    .stage-card-body { padding: 1.25rem; }
    .fiscal-note { border: 1px solid #fde68a; background: #fffbeb; color: #92400e; border-radius: 1rem; padding: 1rem; font-size: .88rem; }
    .fiscal-note strong { display: block; margin-bottom: .35rem; }
    .soft-box { border: 1px solid var(--line); background: #f8fafc; border-radius: 1rem; padding: 1rem; }
    .doc-row { border: 1px solid var(--line); border-radius: .85rem; background: #fff; padding: .75rem; display: flex; justify-content: space-between; gap: .75rem; align-items: center; }
    .doc-name { font-weight: 700; color: var(--ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .rendicion-page .form-control, .rendicion-page .form-select { border-radius: .85rem; }
    .rendicion-page .btn { border-radius: .85rem; font-weight: 800; }
    @media (max-width: 991.98px) { .timeline-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .rendicion-shell-header { flex-direction: column; } }
    @media (max-width: 575.98px) { .timeline-grid { grid-template-columns: 1fr; } .summary-card .value { font-size: 1.35rem; } }
</style>

<div class="container-fluid px-4 py-4 rendicion-page">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <div class="page-kicker">Trámites · Cometidos funcionarios</div>
            <h1 class="page-title h2 mb-1">Rendición y pago de reembolso</h1>
            <p class="text-muted mb-0">Cometido funcionario #{{ $cometido->id }} · {{ $nombreFuncionario }} @if($rutFuncionario) · {{ $rutFuncionario }} @endif</p>
        </div>
        <a href="{{ route('tramites.cometidos-funcionarios.show', $cometido) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Volver al detalle
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success border-0 rounded-4 shadow-sm">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger rounded-4 shadow-sm">
            <strong>No fue posible completar la acción.</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="rendicion-shell mb-4">
        <div class="rendicion-shell-header">
            <div class="rendicion-title-wrap">
                <span class="rendicion-main-icon"><i class="bi bi-cash-coin"></i></span>
                <div>
                    <div class="page-kicker">Seguimiento del proceso</div>
                    <h2 class="h4 fw-bold mb-1">Flujo de reembolso</h2>
                    <p class="text-muted mb-0">Rendición de gastos, revisión DAF, CDP, resolución de pago y cierre.</p>
                </div>
            </div>
            <span class="rendicion-status-pill">{{ $cometido->etiquetaEstado() }}</span>
        </div>

        <div class="rendicion-summary">
            <div class="row g-3">
                <div class="col-md-6 col-xl-3">
                    <div class="summary-card">
                        <div class="label">Monto rendido</div>
                        <div class="value">{{ $fmtMoney($montoRendido) }}</div>
                        <div class="hint">Total declarado en la rendición.</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="summary-card is-green">
                        <div class="label">Monto aprobado DAF</div>
                        <div class="value">{{ $montoAutorizado !== null ? $fmtMoney($montoAutorizado) : 'Pendiente' }}</div>
                        <div class="hint">Monto final validado para continuar a CDP.</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="summary-card is-indigo">
                        <div class="label">CDP rendición</div>
                        <div class="value">{{ $montoCdpRendicion !== null ? $fmtMoney($montoCdpRendicion) : 'Pendiente' }}</div>
                        <div class="hint">Monto CDP aprobado por Planificación.</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="summary-card is-purple">
                        <div class="label">Resolución / pago</div>
                        <div class="value">{{ $montoPago !== null ? $fmtMoney($montoPago) : ($montoResolucion !== null ? $fmtMoney($montoResolucion) : 'Pendiente') }}</div>
                        <div class="hint">Estado financiero final del reembolso.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="rendicion-timeline">
            <div class="timeline-grid">
                @foreach($timelineSteps as $index => $step)
                    @php $visual = $statusMeta[$step['status']] ?? $statusMeta['pending']; @endphp
                    <div class="timeline-step {{ $visual['class'] }}">
                        <div class="timeline-step-head">
                            <span class="timeline-dot"><i class="bi {{ $visual['icon'] }}"></i></span>
                            <span class="timeline-badge">{{ $visual['badge'] }}</span>
                        </div>
                        <div class="timeline-kicker">Etapa {{ $index + 1 }}</div>
                        <div class="timeline-title">{{ $step['label'] }}</div>
                        <div class="timeline-desc">{{ $step['description'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="stage-card mb-4">
        <div class="stage-card-header">
            <div class="stage-number">Control previo a DAF</div>
            <h3>Informe de Cometido</h3>
            <p>Para reembolso, el informe se realiza en paralelo con la rendición y debe estar aprobado por jefatura antes de pasar a revisión DAF.</p>
        </div>
        <div class="stage-card-body">
            <div class="row g-3 align-items-stretch">
                <div class="col-lg-8">
                    <div class="soft-box h-100">
                        <div><strong>Estado informe:</strong> {{ $estadoInformeCometido ? str_replace('_', ' ', $estadoInformeCometido) : 'No enviado' }}</div>
                        @if($informeCometido?->fecha_envio)
                            <div class="mt-1"><strong>Fecha envío:</strong> {{ optional($informeCometido->fecha_envio)->format('d-m-Y H:i') }}</div>
                        @endif
                        @if($documentoInformeCometido)
                            <div class="d-flex flex-wrap gap-2 mt-3">
                                <a target="_blank" class="btn btn-outline-primary btn-sm" href="{{ route('tramites.cometidos-funcionarios.documentos-generados.ver', [$cometido, $documentoInformeCometido]) }}">
                                    <i class="bi bi-file-earmark-pdf"></i> Ver PDF informe
                                </a>
                                <a class="btn btn-outline-secondary btn-sm" href="{{ route('tramites.cometidos-funcionarios.documentos-generados.ver', [$cometido, $documentoInformeCometido, 'download' => 1]) }}">
                                    <i class="bi bi-download"></i> Descargar informe
                                </a>
                            </div>
                        @endif
                        @if($informeCometidoAprobado)
                            <div class="alert alert-success mt-3 mb-0 small"><i class="bi bi-check-circle"></i> Informe aprobado por jefatura. La rendición puede avanzar a revisión DAF cuando también esté enviada.</div>
                        @elseif($estadoInformeCometido === 'enviado_pendiente_jefatura')
                            <div class="alert alert-warning mt-3 mb-0 small"><i class="bi bi-hourglass-split"></i> Informe enviado y pendiente de aprobación de jefatura. DAF permanecerá bloqueado.</div>
                        @else
                            <div class="alert alert-info mt-3 mb-0 small"><i class="bi bi-info-circle"></i> Informe pendiente. El funcionario puede completarlo en paralelo a la rendición.</div>
                        @endif
                    </div>
                </div>
                <div class="col-lg-4 d-grid align-items-stretch">
                    @if($puedeCompletarInformeCometido)
                        <a class="btn btn-primary btn-lg d-flex align-items-center justify-content-center" href="{{ route('tramites.cometidos-funcionarios.informe.create', $cometido) }}">
                            <i class="bi bi-journal-text me-2"></i> Completar informe
                        </a>
                    @elseif($informeCometido && $activeRole === 'admin')
                        <a class="btn btn-outline-secondary btn-lg d-flex align-items-center justify-content-center" href="{{ route('tramites.cometidos-funcionarios.informe.create', $cometido) }}">
                            <i class="bi bi-eye me-2"></i> Ver informe
                        </a>
                    @else
                        <div class="soft-box text-muted small d-flex align-items-center">El botón se habilitará al funcionario solicitante cuando el flujo de reembolso esté disponible.</div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <section class="stage-card">
                <div class="stage-card-header">
                    <div class="stage-number">Etapa 1 · Establecimiento</div>
                    <h3>Rendición de reembolso</h3>
                    <p>El solicitante carga uno o más documentos fiscales válidos.</p>
                </div>
                <div class="stage-card-body">
                    <div class="fiscal-note mb-3">
                        <strong><i class="bi bi-exclamation-triangle me-1"></i> Requisito documental</strong>
                        El comprobante debe ser un documento fiscal emitido correctamente y debe contener el detalle del gasto. Se pueden adjuntar uno o más documentos fiscales complementarios.
                    </div>

                    @if($rendicion)
                        <div class="soft-box mb-3 small">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <strong>Rendición enviada</strong>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">{{ str_replace('_', ' ', $rendicion->estado) }}</span>
                            </div>
                            <div><strong>Monto rendido:</strong> {{ $fmtMoney($rendicion->monto_rendido) }}</div>
                            @if($rendicion->observacion_establecimiento)
                                <div class="mt-2"><strong>Observación:</strong> {{ $rendicion->observacion_establecimiento }}</div>
                            @endif
                        </div>

                        <div class="mb-3">
                            <div class="small fw-bold text-muted text-uppercase mb-2">Documentos fiscales cargados</div>
                            @if(!empty($documentosRespaldo))
                                <div class="d-grid gap-2">
                                    @foreach($documentosRespaldo as $docIndex => $doc)
                                        @php
                                            $docPath = is_array($doc) ? ($doc['path'] ?? null) : $doc;
                                            $docName = is_array($doc) ? ($doc['original_name'] ?? basename((string) $docPath)) : basename((string) $docPath);
                                            $docFecha = is_array($doc) ? ($doc['fecha_documento'] ?? null) : null;
                                            $docMonto = is_array($doc) ? ($doc['monto_documento'] ?? null) : null;
                                            $docDetalle = is_array($doc) ? ($doc['detalle_gasto'] ?? null) : null;
                                        @endphp
                                        <div class="doc-row align-items-start">
                                            <div class="min-width-0">
                                                <div class="doc-name">{{ $docName }}</div>
                                                <div class="text-muted small">
                                                    Documento fiscal de respaldo
                                                    @if($docFecha) · Fecha: {{ \Illuminate\Support\Carbon::parse($docFecha)->format('d-m-Y') }} @endif
                                                    @if($docMonto !== null) · Monto: {{ $fmtMoney($docMonto) }} @endif
                                                </div>
                                                @if($docDetalle)
                                                    <div class="small mt-1">{{ $docDetalle }}</div>
                                                @endif
                                            </div>
                                            @if($docPath)
                                                <div class="d-flex flex-wrap gap-2 justify-content-end">
                                                    <a href="{{ route('tramites.cometidos-funcionarios.rendicion.documentos.ver', [$cometido, $rendicion, $docIndex]) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-eye"></i> Ver documento
                                                    </a>
                                                    <a href="{{ route('tramites.cometidos-funcionarios.rendicion.documentos.descargar', [$cometido, $rendicion, $docIndex]) }}" class="btn btn-sm btn-outline-secondary">
                                                        <i class="bi bi-download"></i> Descargar
                                                    </a>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="alert alert-warning small mb-0">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    No existen documentos fiscales asociados a esta rendición en el registro actual. Si el comprobante fue cargado y no aparece, debe reenviarse la rendición observada o volver a cargar el respaldo desde el formulario habilitado.
                                </div>
                            @endif
                        </div>
                    @endif

                    @if($mostrarFormularioRendicion)
                        <form method="POST" action="{{ route('tramites.cometidos-funcionarios.rendicion.enviar', $cometido) }}" enctype="multipart/form-data" class="d-grid gap-3" id="rendicionReembolsoForm">
                            @csrf

                            <div class="alert alert-info mb-0">
                                <div class="fw-bold mb-1"><i class="bi bi-receipt"></i> Documentos fiscales requeridos</div>
                                <div class="small">Cada respaldo debe ser un documento fiscal emitido correctamente, con detalle del gasto, fecha del documento y monto. Puede agregar uno o más comprobantes complementarios.</div>
                            </div>

                            <div id="comprobantesContainer" class="d-grid gap-3">
                                <div class="comprobante-item border rounded-4 p-3 bg-light-subtle">
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                                        <div>
                                            <div class="fw-bold text-dark">Documento fiscal #1</div>
                                            <div class="text-muted small">Complete los datos del comprobante y adjunte el archivo PDF.</div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-danger d-none js-remove-comprobante">Quitar</button>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">Archivo del comprobante fiscal</label>
                                            <input type="file" name="comprobantes[0][archivo]" class="form-control" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-semibold">Fecha documento</label>
                                            <input type="date" name="comprobantes[0][fecha_documento]" class="form-control" value="{{ old('comprobantes.0.fecha_documento') }}" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-semibold">Monto documento</label>
                                            <input type="number" name="comprobantes[0][monto_documento]" min="1" step="1" class="form-control js-monto-comprobante" value="{{ old('comprobantes.0.monto_documento') }}" required>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label fw-semibold">Detalle del gasto</label>
                                            <textarea name="comprobantes[0][detalle_gasto]" rows="2" class="form-control" placeholder="Ej.: Peaje ruta Concepción / Estacionamiento Universidad de Concepción" required>{{ old('comprobantes.0.detalle_gasto') }}</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                                <button type="button" class="btn btn-outline-primary" id="agregarComprobanteBtn">
                                    <i class="bi bi-plus-circle"></i> Agregar otro documento fiscal
                                </button>
                                <div class="text-end">
                                    <div class="small text-muted">Monto rendido total calculado</div>
                                    <div class="fs-4 fw-bold text-primary" id="montoRendidoPreview">$0</div>
                                </div>
                            </div>

                            <input type="hidden" name="monto_rendido" id="montoRendidoInput" value="{{ old('monto_rendido', 0) }}">

                            <div>
                                <label class="form-label fw-semibold">Observación del solicitante</label>
                                <textarea name="observacion_establecimiento" rows="3" class="form-control">{{ old('observacion_establecimiento', $rendicion?->observacion_establecimiento) }}</textarea>
                            </div>
                            <button class="btn btn-primary btn-lg"><i class="bi bi-send"></i> Enviar rendición</button>
                        </form>
                    @else
                        @if($rendicion && $activeRole === 'funcionario_estab' && $estadoRendicion !== 'rendicion_observada_daf')
                            <div class="alert alert-success mb-0">
                                <div class="fw-bold mb-1"><i class="bi bi-check-circle"></i> Rendición enviada</div>
                                <div class="small">La rendición ya fue enviada y quedó disponible para revisión interna. No se muestra nuevamente el formulario de carga al solicitante.</div>
                            </div>
                        @elseif($rendicion && $estadoRendicion !== 'rendicion_observada_daf')
                            <div class="text-muted small">La rendición ya fue enviada. Las acciones disponibles dependen de la etapa y del rol activo.</div>
                        @else
                            <div class="text-muted small">No tienes permisos para enviar rendición en este cometido.</div>
                        @endif
                    @endif
                </div>
            </section>
        </div>

        <div class="col-xl-6">
            <section class="stage-card">
                <div class="stage-card-header">
                    <div class="stage-number">Etapa 2 · DAF</div>
                    <h3>Revisión y autorización</h3>
                    <p>DAF valida el monto aprobado para avanzar al CDP de rendición.</p>
                </div>
                <div class="stage-card-body">
                    @if($rendicion)
                        <div class="soft-box mb-3 small">
                            <div class="d-flex align-items-start gap-3 mb-3">
                                <span class="timeline-dot" style="width:2.5rem;height:2.5rem;"><i class="bi bi-clipboard-check"></i></span>
                                <div>
                                    <div class="fw-bold text-dark">Resultado revisión DAF</div>
                                    @if($rendicion->monto_autorizado_daf !== null)
                                        <div class="text-muted">DAF ya revisó la rendición y registró el monto aprobado para continuar al CDP de rendición.</div>
                                    @elseif($rendicion->estado === 'rendicion_observada_daf')
                                        <div class="text-muted">DAF observó la rendición. Revise la observación para subsanar los antecedentes requeridos.</div>
                                    @elseif($rendicion->estado === 'rendicion_rechazada_daf')
                                        <div class="text-muted">DAF rechazó la rendición. Revise el motivo informado en la observación.</div>
                                    @else
                                        <div class="text-muted">La rendición fue enviada y se encuentra pendiente de revisión DAF.</div>
                                    @endif
                                </div>
                            </div>

                            <div><strong>Estado revisión DAF:</strong> {{ str_replace('_', ' ', $rendicion->estado) }}</div>

                            @if($puedeVerValoresReferenciaDaf)
                                <div class="alert alert-info mt-3 mb-3 small">
                                    <div class="fw-bold mb-1"><i class="bi bi-info-circle"></i> Valor referencial para revisión de reembolso</div>
                                    <div>Este valor proviene de la misma categoría/cálculo automático detectado para el viático y se muestra sólo como antecedente para DAF. No corresponde a un tope aprobado ni a un CDP inicial de reembolso.</div>
                                    <div class="mt-2">
                                        <strong>Categoría:</strong>
                                        {{ trim(($referenciaReembolso['estamento'] ?? '') . ' / ' . ($referenciaReembolso['cargo_funcion'] ?? ''), ' /') ?: 'No determinada' }}
                                        @if(($referenciaReembolso['valor_100'] ?? null) !== null)
                                            · <strong>100%:</strong> {{ $fmtMoney($referenciaReembolso['valor_100']) }}
                                        @endif
                                        @if(($referenciaReembolso['valor_40'] ?? null) !== null)
                                            · <strong>40%:</strong> {{ $fmtMoney($referenciaReembolso['valor_40']) }}
                                        @endif
                                        @if($referenciaReembolsoTotal !== null)
                                            · <strong>Total cálculo:</strong> {{ $fmtMoney($referenciaReembolsoTotal) }}
                                        @endif
                                    </div>
                                </div>
                            @endif

                            @if($rendicion->monto_autorizado_daf !== null)
                                <div><strong>Monto aprobado DAF:</strong> {{ $fmtMoney($rendicion->monto_autorizado_daf) }}</div>
                            @else
                                <div><strong>Monto aprobado DAF:</strong> Pendiente</div>
                            @endif

                            @if($rendicion->observacion_daf)
                                <div class="mt-2"><strong>Observación DAF:</strong> {{ $rendicion->observacion_daf }}</div>
                            @endif


                    @if($puedeRectificarRendicion)
                        <div class="alert alert-warning mt-4">
                            <div class="fw-bold"><i class="bi bi-pencil-square"></i> Rendición observada: rectificación requerida</div>
                            <div class="small">DAF observó los documentos fiscales. Adjunte nuevamente los comprobantes corregidos para que la rendición vuelva a revisión DAF.</div>
                            @if($rendicion?->observacion_daf)
                                <div class="small mt-2"><strong>Observación DAF:</strong> {{ $rendicion->observacion_daf }}</div>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('tramites.cometidos-funcionarios.rendicion.rectificar', $cometido) }}" enctype="multipart/form-data" class="d-grid gap-3 mt-3">
                            @csrf
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Fecha documento</label>
                                    <input type="date" name="comprobantes[0][fecha_documento]" class="form-control" required>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label fw-semibold">Documento fiscal rectificado</label>
                                    <input type="file" name="comprobantes[0][archivo]" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Monto documento</label>
                                    <input type="number" name="comprobantes[0][monto_documento]" min="1" step="1" class="form-control js-monto-comprobante-rect" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Detalle del gasto rectificado</label>
                                    <textarea name="comprobantes[0][detalle_gasto]" rows="2" class="form-control" required></textarea>
                                </div>
                            </div>
                            <input type="hidden" name="monto_rendido" id="montoRendidoRectInput" value="0">
                            <div>
                                <label class="form-label fw-semibold">Fundamento de la rectificación</label>
                                <textarea name="observacion_rectificacion" rows="3" class="form-control" placeholder="Indique qué documentos se corrigen o reemplazan según la observación DAF" required></textarea>
                            </div>
                            <button class="btn btn-warning btn-lg"><i class="bi bi-arrow-repeat"></i> Enviar rectificación a DAF</button>
                        </form>
                    @endif

                            @if($documentoDafPath)
                                <div class="mt-3 d-flex flex-wrap gap-2">
                                    <a href="{{ \Illuminate\Support\Facades\Storage::url($documentoDafPath) }}" target="_blank" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-eye"></i> Ver documento DAF
                                    </a>
                                    <a href="{{ \Illuminate\Support\Facades\Storage::url($documentoDafPath) }}?download=1" target="_blank" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-download"></i> Descargar documento DAF
                                    </a>
                                </div>
                            @elseif($rendicion->monto_autorizado_daf !== null)
                                <div class="alert alert-warning mt-3 mb-0 small">
                                    DAF registró la aprobación, pero no hay documento DAF adjunto para consulta.
                                </div>
                            @endif
                        </div>

                        @if($puedeActuarDaf && $rendicionPendienteRevisionDaf && $bloqueaDafPorInforme)
                            <div class="alert alert-warning mb-0">
                                <div class="fw-bold"><i class="bi bi-lock"></i> Revisión DAF bloqueada por informe pendiente</div>
                                <div>DAF podrá revisar la rendición cuando el funcionario haya enviado el Informe de Cometido y la jefatura lo apruebe.</div>
                            </div>
                        @elseif($puedeActuarDaf && $rendicionPendienteRevisionDaf)
                            <form method="POST" action="{{ route('tramites.cometidos-funcionarios.rendicion.daf.autorizar', $cometido) }}" enctype="multipart/form-data" class="d-grid gap-3 mb-3">
                                @csrf
                                @method('PATCH')
                                <div>
                                    <label class="form-label fw-semibold">Monto aprobado por DAF</label>
                                    <input type="number" name="monto_autorizado_daf" min="0" class="form-control" value="{{ $rendicion->monto_autorizado_daf ?? old('monto_autorizado_daf') }}" required>
                                    <div class="form-text">No puede superar el monto rendido por el solicitante. La referencia de viático se muestra sólo como antecedente de revisión.</div>
                                </div>
                                <div>
                                    <label class="form-label fw-semibold">Documento DAF</label>
                                    <input type="file" name="documento_daf" class="form-control">
                                </div>
                                <div>
                                    <label class="form-label fw-semibold">Observación DAF</label>
                                    <textarea name="observacion_daf" rows="3" class="form-control">{{ $rendicion->observacion_daf }}</textarea>
                                </div>
                                <button class="btn btn-success btn-lg"><i class="bi bi-check-circle"></i> Autorizar y derivar a Planificación</button>
                            </form>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <form method="POST" action="{{ route('tramites.cometidos-funcionarios.rendicion.daf.observar', $cometido) }}" class="d-grid gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <textarea name="observacion_daf" rows="3" class="form-control" placeholder="Observación DAF" required></textarea>
                                        <button class="btn btn-outline-warning">Observar</button>
                                    </form>
                                </div>
                                <div class="col-md-6">
                                    <form method="POST" action="{{ route('tramites.cometidos-funcionarios.rendicion.daf.rechazar', $cometido) }}" class="d-grid gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <textarea name="observacion_daf" rows="3" class="form-control" placeholder="Motivo de rechazo" required></textarea>
                                        <button class="btn btn-outline-danger">Rechazar rendición</button>
                                    </form>
                                </div>
                            </div>
                        @elseif($puedeActuarDaf && $rendicion->monto_autorizado_daf !== null)
                            <div class="alert alert-success mb-0">
                                <div class="fw-bold"><i class="bi bi-check-circle"></i> Rendición aprobada por DAF</div>
                                <div>Esta etapa ya fue revisada y autorizada. El formulario de aprobación se oculta para evitar una nueva revisión sobre el mismo registro.</div>
                            </div>
                        @elseif($puedeActuarDaf && in_array($rendicion->estado, ['rendicion_observada_daf', 'rendicion_rechazada_daf'], true))
                            <div class="alert alert-warning mb-0">
                                <div class="fw-bold"><i class="bi bi-exclamation-triangle"></i> Revisión DAF ya registrada</div>
                                <div>La rendición fue {{ $rendicion->estado === 'rendicion_observada_daf' ? 'observada' : 'rechazada' }} por DAF. Revise el resultado y la observación informada.</div>
                            </div>
                        @endif
                    @else
                        <div class="text-muted small">Aún no existe rendición enviada por el solicitante.</div>
                    @endif
                </div>
            </section>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <section class="stage-card">
                <div class="stage-card-header">
                    <div class="stage-number">Etapa 3 · Planificación</div>
                    <h3>CDP de rendición</h3>
                    <p>Planificación aprueba el monto CDP antes de derivar a Jurídica.</p>
                </div>
                <div class="stage-card-body">
                    @if($rendicion && $cdpRendicionGenerado)
                        <div class="soft-box small">
                            <div class="d-flex align-items-start gap-3 mb-3">
                                <span class="timeline-dot" style="width:2.5rem;height:2.5rem;"><i class="bi bi-file-earmark-check"></i></span>
                                <div>
                                    <div class="fw-bold text-dark">CDP de rendición generado por Planificación</div>
                                    <div class="text-muted">El CDP asociado al reembolso ya fue registrado. La etapa queda disponible sólo para consulta y no permite una nueva aprobación sobre el mismo registro.</div>
                                </div>
                            </div>
                            <div><strong>Estado rendición:</strong> {{ str_replace('_', ' ', $rendicion->estado) }}</div>
                            <div><strong>Monto aprobado DAF:</strong> {{ $fmtMoney($rendicion->monto_autorizado_daf) }}</div>
                            @if($rendicion->referencia_cdp_reembolso)
                                <div><strong>Referencia / N° CDP:</strong> {{ $rendicion->referencia_cdp_reembolso }}</div>
                            @endif
                            @if($rendicion->monto_cdp_reembolso !== null)
                                <div><strong>Monto CDP aprobado:</strong> {{ $fmtMoney($rendicion->monto_cdp_reembolso) }}</div>
                            @endif
                            @if($rendicion->observacion_cdp)
                                <div class="mt-2"><strong>Observación CDP:</strong> {{ $rendicion->observacion_cdp }}</div>
                            @endif
                            @if($documentoCdpRendicionPath)
                                <div class="mt-3 d-flex flex-wrap gap-2">
                                    <a href="{{ \Illuminate\Support\Facades\Storage::url($documentoCdpRendicionPath) }}" target="_blank" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-eye"></i> Ver documento CDP
                                    </a>
                                    <a href="{{ \Illuminate\Support\Facades\Storage::url($documentoCdpRendicionPath) }}" download class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-download"></i> Descargar documento CDP
                                    </a>
                                </div>
                            @else
                                <div class="alert alert-warning mt-3 mb-0 small">
                                    <i class="bi bi-exclamation-triangle"></i> CDP registrado sin documento adjunto disponible para descarga.
                                </div>
                            @endif
                        </div>
                    @elseif($rendicion && $rendicion->monto_autorizado_daf > 0 && $puedeActuarCdpRendicion && ! $cdpRendicionGenerado)
                        <div class="soft-box mb-3 small">
                            <div><strong>Estado rendición:</strong> {{ str_replace('_', ' ', $rendicion->estado) }}</div>
                            <div><strong>Monto aprobado DAF:</strong> {{ $fmtMoney($rendicion->monto_autorizado_daf) }}</div>
                        </div>

                        <form method="POST" action="{{ route('tramites.cometidos-funcionarios.rendicion.cdp.autorizar', $cometido) }}" enctype="multipart/form-data" class="d-grid gap-3 mb-3">
                            @csrf
                            @method('PATCH')
                            <div>
                                <label class="form-label fw-semibold">Referencia / número CDP</label>
                                <input name="referencia_cdp_reembolso" class="form-control" value="{{ old('referencia_cdp_reembolso') }}" required>
                            </div>
                            <div>
                                <label class="form-label fw-semibold">Monto CDP aprobado</label>
                                <input type="number" name="monto_cdp_reembolso" min="1" class="form-control" value="{{ old('monto_cdp_reembolso', $rendicion->monto_autorizado_daf) }}" required>
                                <div class="form-text">No puede superar el monto aprobado por DAF: {{ $fmtMoney($rendicion->monto_autorizado_daf) }}.</div>
                            </div>
                            <div>
                                <label class="form-label fw-semibold">Archivo CDP</label>
                                <input type="file" name="documento_cdp_reembolso" class="form-control" required>
                            </div>
                            <div>
                                <label class="form-label fw-semibold">Observación CDP</label>
                                <textarea name="observacion_cdp" rows="3" class="form-control">{{ old('observacion_cdp') }}</textarea>
                            </div>
                            <button class="btn btn-primary btn-lg"><i class="bi bi-shield-check"></i> Aprobar CDP y derivar a Jurídica</button>
                        </form>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <form method="POST" action="{{ route('tramites.cometidos-funcionarios.rendicion.cdp.observar', $cometido) }}" class="d-grid gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <textarea name="observacion_cdp" rows="3" class="form-control" placeholder="Observación CDP" required></textarea>
                                    <button class="btn btn-outline-warning">Observar CDP</button>
                                </form>
                            </div>
                            <div class="col-md-6">
                                <form method="POST" action="{{ route('tramites.cometidos-funcionarios.rendicion.cdp.rechazar', $cometido) }}" class="d-grid gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <textarea name="observacion_cdp" rows="3" class="form-control" placeholder="Motivo de rechazo CDP" required></textarea>
                                    <button class="btn btn-outline-danger">Rechazar CDP</button>
                                </form>
                            </div>
                        </div>
                    @elseif($rendicion && $rendicion->monto_autorizado_daf > 0)
                        <div class="soft-box small text-muted">
                            <div class="fw-bold text-dark mb-1">CDP de rendición pendiente</div>
                            <div>La rendición ya fue autorizada por DAF y se encuentra pendiente de generación del CDP por parte de Planificación.</div>
                        </div>
                    @elseif(! $rendicion || ! $rendicion->monto_autorizado_daf)
                        <div class="text-muted small">La etapa CDP se habilita cuando DAF autoriza un monto de rendición mayor a $0.</div>
                    @else
                        <div class="text-muted small">La información del CDP de rendición estará disponible cuando Planificación genere el documento correspondiente.</div>
                    @endif
                </div>
            </section>
        </div>

        <div class="col-xl-6">
            <section class="stage-card">
                <div class="stage-card-header">
                    <div class="stage-number">Etapa 4 · Jurídica</div>
                    <h3>Resolución de pago</h3>
                    <p>Jurídica emite la resolución que respalda el pago del reembolso.</p>
                </div>
                <div class="stage-card-body">
                    @if($resolucion)
                        <div class="soft-box mb-3 small">
                            <div><strong>Estado:</strong> {{ str_replace('_', ' ', $resolucion->estado) }}</div>
                            @if($resolucion->numero_resolucion)
                                <div><strong>Resolución:</strong> {{ $resolucion->numero_resolucion }} · {{ optional($resolucion->fecha_resolucion)->format('d-m-Y') }}</div>
                            @endif
                            @if($resolucion->monto_resolucion !== null)
                                <div><strong>Monto resolución:</strong> {{ $fmtMoney($resolucion->monto_resolucion) }}</div>
                            @endif
                        </div>
                    @endif

                    @if($resolucionPagoEmitida)
                        <div class="alert alert-success d-flex align-items-start gap-2 mb-3">
                            <i class="bi bi-check-circle-fill mt-1"></i>
                            <div>
                                <strong>REX de pago registrada por Jurídica.</strong>
                                <div class="small">La resolución ya fue emitida y el flujo queda derivado a la etapa de pago DAF / Finanzas.</div>
                            </div>
                        </div>

                        <div class="soft-box mb-3 small">
                            <div><strong>Estado resolución:</strong> {{ str_replace('_', ' ', $resolucion->estado ?? 'resolucion_reembolso_emitida') }}</div>
                            @if($resolucion->numero_resolucion)
                                <div><strong>N° resolución:</strong> {{ $resolucion->numero_resolucion }}</div>
                            @endif
                            @if($resolucion->fecha_resolucion)
                                <div><strong>Fecha resolución:</strong> {{ optional($resolucion->fecha_resolucion)->format('d-m-Y') }}</div>
                            @endif
                            @if($resolucion->monto_resolucion !== null)
                                <div><strong>Monto autorizado por resolución:</strong> {{ $fmtMoney($resolucion->monto_resolucion) }}</div>
                            @endif
                            @if($resolucion->observacion_juridica)
                                <div><strong>Observación Jurídica:</strong> {{ $resolucion->observacion_juridica }}</div>
                            @endif
                        </div>

                        @if($documentoResolucionPath)
                            <div class="d-flex flex-wrap gap-2">
                                <a href="{{ \Illuminate\Support\Facades\Storage::url($documentoResolucionPath) }}" target="_blank" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-eye"></i> Ver REX de pago
                                </a>
                                <a href="{{ \Illuminate\Support\Facades\Storage::url($documentoResolucionPath) }}" download class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-download"></i> Descargar REX de pago
                                </a>
                            </div>
                        @else
                            <div class="text-muted small">La resolución está registrada, pero no hay documento REX adjunto disponible.</div>
                        @endif
                    @elseif($resolucion && $puedeJuridica)
                        <form method="POST" action="{{ route('tramites.cometidos-funcionarios.juridica.emitir-resolucion', $cometido) }}" enctype="multipart/form-data" class="d-grid gap-3 mb-3">
                            @csrf
                            @method('PATCH')
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">N° resolución</label>
                                    <input name="numero_resolucion" class="form-control" value="{{ $resolucion->numero_resolucion }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Fecha resolución</label>
                                    <input type="date" name="fecha_resolucion" class="form-control" value="{{ optional($resolucion->fecha_resolucion)->format('Y-m-d') }}" required>
                                </div>
                            </div>
                            <div>
                                <label class="form-label fw-semibold">Monto resolución</label>
                                <input type="number" name="monto_resolucion" min="0" class="form-control" value="{{ $resolucion->monto_resolucion ?? $rendicion?->monto_cdp_reembolso }}" required>
                            </div>
                            <div>
                                <label class="form-label fw-semibold">Documento resolución</label>
                                <input type="file" name="documento_resolucion" class="form-control" required>
                            </div>
                            <textarea name="observacion_juridica" rows="3" class="form-control" placeholder="Observación jurídica opcional">{{ $resolucion->observacion_juridica }}</textarea>
                            <button class="btn btn-primary btn-lg"><i class="bi bi-file-earmark-check"></i> Registrar resolución y derivar a pago</button>
                        </form>

                        <form method="POST" action="{{ route('tramites.cometidos-funcionarios.juridica.observar', $cometido) }}" class="d-grid gap-2">
                            @csrf
                            @method('PATCH')
                            <textarea name="observacion_juridica" rows="3" class="form-control" placeholder="Observación jurídica" required></textarea>
                            <button class="btn btn-outline-warning">Observar antecedentes</button>
                        </form>
                    @elseif(! $resolucion)
                        <div class="text-muted small">La solicitud aún no ha sido derivada a Jurídica. Primero DAF debe autorizar la rendición y Planificación debe aprobar el CDP.</div>
                    @else
                        <div class="text-muted small">La información de la resolución de pago estará disponible cuando Jurídica registre la REX correspondiente.</div>
                    @endif
                </div>
            </section>
        </div>
    </div>

    <section class="stage-card mb-4">
        <div class="stage-card-header">
            <div class="stage-number">Etapas 5 y 6 · DAF / Finanzas</div>
            <h3>Registro de pago y cierre</h3>
            <p>Una vez emitida la resolución de pago, DAF registra el pago efectivo y luego se habilita el cierre.</p>
        </div>
        <div class="stage-card-body">
            @if($resolucion && $resolucionPagoEmitida && ! $pagoReembolsoRegistrado && $estadoResolucion === 'resolucion_reembolso_emitida')
                <div class="card border-info-subtle bg-info bg-opacity-10 mb-4">
                    <div class="card-body">
                        <div class="d-flex align-items-start gap-3 mb-3">
                            <div class="rounded-circle bg-info text-white d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;"><i class="bi bi-journal-check fs-5"></i></div>
                            <div>
                                <h5 class="mb-1">Registrar compromiso y devengo del reembolso</h5>
                                <p class="text-muted mb-0">Antes del pago, DAF debe registrar los folios contables.</p>
                            </div>
                        </div>
                        @if($puedeRegistrarContabilidad)
                            <form method="POST" action="{{ route('tramites.cometidos-funcionarios.rendicion.daf.contabilidad', $cometido) }}" enctype="multipart/form-data" class="row g-3">
                                @csrf
                                @method('PATCH')
                                <div class="col-md-6"><label class="form-label fw-semibold">Folio compromiso</label><input type="text" name="folio_compromiso_contable" class="form-control" required></div>
                                <div class="col-md-6"><label class="form-label fw-semibold">Fecha compromiso</label><input type="date" name="fecha_compromiso_contable" class="form-control" required></div>
                                <div class="col-md-6"><label class="form-label fw-semibold">Folio devengo</label><input type="text" name="folio_devengo_contable" class="form-control" required></div>
                                <div class="col-md-6"><label class="form-label fw-semibold">Fecha devengo</label><input type="date" name="fecha_devengo_contable" class="form-control" required></div>
                                <div class="col-12"><label class="form-label fw-semibold">Documento contable</label><input type="file" name="documento_contable" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"></div>
                                <div class="col-12"><label class="form-label fw-semibold">Observación</label><textarea name="observacion_contable" rows="3" class="form-control"></textarea></div>
                                <div class="col-12"><button class="btn btn-info btn-lg"><i class="bi bi-save"></i> Guardar registro contable</button></div>
                            </form>
                        @else
                            <div class="alert alert-light border mb-0">Pendiente de registro contable por DAF.</div>
                        @endif
                    </div>
                </div>
            @endif

            @if($resolucion && $pagoReembolsoRegistrado)
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
                            <div class="d-flex align-items-start gap-3">
                                <div class="rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                    <i class="bi bi-check2-circle fs-4"></i>
                                </div>
                                <div>
                                    <div class="text-uppercase text-muted fw-bold small">Resultado del pago</div>
                                    <h4 class="mb-1">Reembolso pagado por DAF</h4>
                                    <p class="text-muted mb-0">DAF / Finanzas registró el pago efectivo del reembolso y el trámite quedó disponible para cierre.</p>
                                </div>
                            </div>
                            <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-3 py-2">
                                <i class="bi bi-check-circle me-1"></i> Pago registrado
                            </span>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-md-6 col-xl-3">
                                <div class="border rounded-4 p-3 h-100 bg-light">
                                    <div class="text-uppercase text-muted fw-bold small mb-2">Estado pago</div>
                                    <div class="fw-bold text-success fs-5">{{ $estadoResolucion === 'reembolso_pagado' ? 'Pago registrado' : 'Pagado' }}</div>
                                    <div class="text-muted small mt-1">Pago efectivo informado por DAF / Finanzas.</div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-xl-3">
                                <div class="border rounded-4 p-3 h-100 bg-light">
                                    <div class="text-uppercase text-muted fw-bold small mb-2">Monto pagado</div>
                                    <div class="fw-bold text-dark fs-5">{{ $fmtMoney($resolucion->monto_pagado_reembolso ?? 0) }}</div>
                                    <div class="text-muted small mt-1">Monto final pagado al funcionario.</div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-xl-3">
                                <div class="border rounded-4 p-3 h-100 bg-light">
                                    <div class="text-uppercase text-muted fw-bold small mb-2">Fecha de pago</div>
                                    <div class="fw-bold text-dark fs-5">{{ optional($resolucion->fecha_pago_reembolso)->format('d-m-Y') ?? 'No informada' }}</div>
                                    <div class="text-muted small mt-1">Fecha registrada para el pago del reembolso.</div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-xl-3">
                                <div class="border rounded-4 p-3 h-100 {{ ($cometido->estado ?? null) === 'cerrado' ? 'bg-success-subtle border-success-subtle' : 'bg-warning-subtle border-warning-subtle' }}">
                                    <div class="text-uppercase text-muted fw-bold small mb-2">Cierre del trámite</div>
                                    <div class="fw-bold fs-5 {{ ($cometido->estado ?? null) === 'cerrado' ? 'text-success' : 'text-warning-emphasis' }}">
                                        {{ ($cometido->estado ?? null) === 'cerrado' ? 'Cerrado' : 'Pendiente' }}
                                    </div>
                                    <div class="text-muted small mt-1">Estado final posterior al pago del reembolso.</div>
                                </div>
                            </div>
                        </div>

                        @if($resolucion->observacion_pago)
                            <div class="alert alert-light border mt-3 mb-0">
                                <div class="fw-semibold mb-1"><i class="bi bi-chat-left-text me-1"></i> Observación de pago</div>
                                <div>{{ $resolucion->observacion_pago }}</div>
                            </div>
                        @endif

                        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mt-4 pt-3 border-top">
                            <div>
                                <div class="fw-semibold">Comprobante de pago</div>
                                <div class="text-muted small">
                                    @if($documentoPagoPath)
                                        Documento cargado por DAF / Finanzas como respaldo del pago.
                                    @else
                                        El pago fue registrado sin documento de respaldo adjunto.
                                    @endif
                                </div>
                            </div>
                            @if($documentoPagoPath)
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="{{ \Illuminate\Support\Facades\Storage::url($documentoPagoPath) }}" target="_blank" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-eye"></i> Ver comprobante
                                    </a>
                                    <a href="{{ \Illuminate\Support\Facades\Storage::url($documentoPagoPath) }}?download=1" target="_blank" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-download"></i> Descargar comprobante
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                @if(($cometido->estado ?? null) !== 'cerrado' && $puedeGestionarPagoCierre)
                    <div class="card border-primary-subtle bg-primary bg-opacity-10">
                        <div class="card-body">
                            <div class="d-flex align-items-start gap-3 mb-3">
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                    <i class="bi bi-check2-square fs-5"></i>
                                </div>
                                <div>
                                    <h5 class="mb-1">Cerrar trámite</h5>
                                    <p class="text-muted mb-0">El pago ya fue registrado. Puedes cerrar el trámite dejando una observación opcional.</p>
                                </div>
                            </div>
                            <form method="POST" action="{{ route('tramites.cometidos-funcionarios.cierre.cerrar', $cometido) }}" class="d-grid gap-3">
                                @csrf
                                @method('PATCH')
                                <textarea name="observacion_cierre" rows="3" class="form-control" placeholder="Observación de cierre opcional"></textarea>
                                <button class="btn btn-primary btn-lg">
                                    <i class="bi bi-check2-square"></i> Cerrar trámite
                                </button>
                            </form>
                        </div>
                    </div>
                @elseif(($cometido->estado ?? null) === 'cerrado')
                    <div class="alert alert-success mb-0">
                        <i class="bi bi-check-circle"></i> El trámite ya se encuentra cerrado.
                    </div>
                @else
                    <div class="alert alert-light border mb-0">
                        El pago ya fue registrado. El cierre del trámite debe ser realizado por DAF/Admin.
                    </div>
                @endif
            @elseif($resolucion && $puedeGestionarPagoCierre && $estadoResolucion === 'contabilidad_reembolso_registrada')
                <form method="POST" action="{{ route('tramites.cometidos-funcionarios.pago.registrar', $cometido) }}" enctype="multipart/form-data" class="d-grid gap-3 mb-3">
                    @csrf
                    @method('PATCH')
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Monto pagado</label>
                            <input type="number" name="monto_pagado_reembolso" min="0" class="form-control" value="{{ $resolucion->monto_resolucion }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Fecha de pago</label>
                            <input type="date" name="fecha_pago_reembolso" class="form-control" required>
                        </div>
                    </div>
                    <div>
                        <label class="form-label fw-semibold">Documento de pago</label>
                        <input type="file" name="documento_pago" class="form-control">
                    </div>
                    <textarea name="observacion_pago" rows="3" class="form-control" placeholder="Observación de pago"></textarea>
                    <button class="btn btn-dark btn-lg"><i class="bi bi-credit-card"></i> Registrar pago</button>
                </form>
            @else
                <div class="text-muted small">La etapa de pago se habilita cuando Jurídica emite la resolución y DAF registra compromiso y devengo.</div>
            @endif
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('comprobantesContainer');
    const addButton = document.getElementById('agregarComprobanteBtn');
    const totalInput = document.getElementById('montoRendidoInput');
    const totalPreview = document.getElementById('montoRendidoPreview');

    if (!container || !addButton || !totalInput || !totalPreview) {
        return;
    }

    const formatMoney = function (value) {
        const number = Number(value || 0);
        return '$' + number.toLocaleString('es-CL');
    };

    const updateIndexes = function () {
        container.querySelectorAll('.comprobante-item').forEach(function (item, index) {
            const title = item.querySelector('.fw-bold.text-dark');
            if (title) {
                title.textContent = 'Documento fiscal #' + (index + 1);
            }

            item.querySelectorAll('input, textarea').forEach(function (field) {
                field.name = field.name.replace(/comprobantes\[\d+\]/, 'comprobantes[' + index + ']');
            });

            const removeButton = item.querySelector('.js-remove-comprobante');
            if (removeButton) {
                removeButton.classList.toggle('d-none', container.querySelectorAll('.comprobante-item').length === 1);
            }
        });
    };

    const updateTotal = function () {
        let total = 0;
        container.querySelectorAll('.js-monto-comprobante').forEach(function (input) {
            total += Number(input.value || 0);
        });
        totalInput.value = total;
        totalPreview.textContent = formatMoney(total);
    };

    addButton.addEventListener('click', function () {
        const first = container.querySelector('.comprobante-item');
        if (!first) {
            return;
        }

        const clone = first.cloneNode(true);
        clone.querySelectorAll('input, textarea').forEach(function (field) {
            field.value = '';
        });
        container.appendChild(clone);
        updateIndexes();
        updateTotal();
    });

    container.addEventListener('click', function (event) {
        const button = event.target.closest('.js-remove-comprobante');
        if (!button) {
            return;
        }

        const items = container.querySelectorAll('.comprobante-item');
        if (items.length <= 1) {
            return;
        }

        button.closest('.comprobante-item').remove();
        updateIndexes();
        updateTotal();
    });

    container.addEventListener('input', function (event) {
        if (event.target.classList.contains('js-monto-comprobante')) {
            updateTotal();
        }
    });

    updateIndexes();
    updateTotal();
});

    function syncRectificacionMonto() {
        const monto = document.querySelector('.js-monto-comprobante-rect');
        const target = document.getElementById('montoRendidoRectInput');
        if (monto && target) { target.value = monto.value || 0; }
    }
    document.querySelectorAll('.js-monto-comprobante-rect').forEach((input) => {
        input.addEventListener('input', syncRectificacionMonto);
        input.addEventListener('change', syncRectificacionMonto);
    });
    syncRectificacionMonto();
</script>

@endsection
