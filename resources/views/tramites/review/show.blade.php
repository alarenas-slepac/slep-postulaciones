@extends('layouts.app')

@section('content')
    @php
        $documentsConfig = (array) ($tipoConfig['documentos'] ?? []);
        $isBienios = $tramite->tipo === 'reconocimiento_bienios';
        $isExternalBieniosFlow = $isBienios && (bool) $tramite->bienios_flujo_externo;
        $displayTimezone = config('app.display_timezone', 'America/Santiago');
        $solicitanteRole = $tramite->user?->roles?->pluck('name')->intersect(['postulante', 'funcionario'])->first();
        $approvedDocumentsCount = $tramite->documentos->where('estado_revision', 'aprobado')->count();
        $applicantNotificationEmail = trim((string) ($tramite->user?->email ?: $tramite->email_snapshot));
        $applicantUserEmail = trim((string) ($tramite->user?->email ?? ''));
        $hasCaptureDocuments = false;
        $hasApprovedDocuments = $tramite->documentos->contains(fn ($documento) => (string) $documento->estado_revision === 'aprobado');
        $hasCalculationPeriods = !$isExternalBieniosFlow && ($tramite->has_calculo_periodos || ($isBienios && $hasApprovedDocuments));
        $hasResolutionTab = $isExternalBieniosFlow || $tramite->has_resolucion_reconocimiento;
        $activeTab = 'documentos';
        if (request('tab') === 'captura' && $hasCaptureDocuments) {
            $activeTab = 'captura';
        } elseif (request('tab') === 'calculo' && $hasCalculationPeriods) {
            $activeTab = 'calculo';
        } elseif (request('tab') === 'resolucion' && $hasResolutionTab) {
            $activeTab = 'resolucion';
        }
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h4 mb-0">Revisión de trámite #{{ $tramite->id }}</h1>
            <div class="text-muted small">{{ $tramite->tipo_label }}</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ $approvedDocumentsCount > 0 ? route('tramites.documentos.downloadApproved', $tramite) : '#' }}"
               class="btn btn-outline-primary {{ $approvedDocumentsCount > 0 ? '' : 'disabled' }}"
               @if ($approvedDocumentsCount <= 0) aria-disabled="true" @endif
               title="{{ $approvedDocumentsCount > 0 ? 'Descargar documentos aprobados (ZIP)' : 'Sin documentos aprobados para descargar' }}">
                <i class="bi bi-file-earmark-zip"></i> Descargar aprobados
            </a>
            <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#notifyApplicantModal" {{ $applicantNotificationEmail === '' ? 'disabled' : '' }}>
                <i class="bi bi-envelope-paper"></i> Notificar solicitante
            </button>
            @if ($isBienios && !$isExternalBieniosFlow)
                <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#informarCierreBieniosModal" {{ $applicantUserEmail === '' ? 'disabled' : '' }}>
                    <i class="bi bi-check2-circle"></i> Informar cierre de trámite
                </button>
            @endif
            <a href="{{ route('tramites.index') }}" class="btn btn-outline-secondary">Volver</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (session('capture_status'))
        <div class="alert alert-info">{{ session('capture_status') }}</div>
    @endif

    @if (session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1">No fue posible guardar la revisión.</div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($applicantNotificationEmail === '')
        <div class="alert alert-warning">Este trámite no tiene correo del solicitante disponible; el botón <strong>Notificar solicitante</strong> quedará deshabilitado.</div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Estado trámite</div>
                    <div>
                        <span class="badge {{ $tramite->estado_badge_class }} fs-6">{{ $tramite->estado_label }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Enviado</div>
                    <div class="fw-semibold">{{ optional($tramite->enviado_at)->timezone($displayTimezone)->format('d-m-Y H:i') ?: '—' }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Solicitante</div>
                    <div class="fw-semibold">{{ $tramite->nombre_completo_snapshot ?: '—' }}</div>
                    <div class="small text-muted">{{ $solicitanteRole === 'funcionario' ? 'Funcionario' : 'Postulante' }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">RUT / Correo</div>
                    <div class="fw-semibold">{{ $tramite->rut_snapshot ?: '—' }}</div>
                    <div class="small text-muted">{{ $tramite->email_snapshot ?: '—' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header fw-semibold">Datos del solicitante</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label text-muted small mb-1">Nombre completo</label>
                    <div class="form-control bg-light">{{ $tramite->nombre_completo_snapshot ?: '—' }}</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted small mb-1">Establecimiento</label>
                    <div class="form-control bg-light">{{ $tramite->establecimiento_nombre_snapshot ?: '—' }}</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted small mb-1">Estatuto</label>
                    <div class="form-control bg-light">{{ $tramite->estatuto_snapshot ?: '—' }}</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted small mb-1">Escalafón</label>
                    <div class="form-control bg-light">{{ $tramite->escalafon_snapshot ?: '—' }}</div>
                </div>
            </div>
        </div>
    </div>

    @if ($isBienios)
        <div class="alert alert-info">
            <div class="fw-semibold">Reconocimiento de Bienios</div>
            @if ($isExternalBieniosFlow)
                <div>Revisa y resuelve los documentos del expediente. El cómputo se realiza fuera de la plataforma; una vez finalizado, carga conjuntamente la resolución firmada y el detalle del cálculo en PDF desde la pestaña Resultado.</div>
            @else
                <div>Desde esta vista puedes revisar cada PDF del expediente, aprobar o rechazar documentos y luego confirmar, modificar o agregar períodos manualmente desde la pestaña Cálculo de períodos. La captura automática/OCR ya no se ejecuta en este flujo.</div>
            @endif
        </div>
    @endif

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-header fw-semibold">Acciones del trámite</div>
        <div class="card-body">
            @if ($isBienios && !$isExternalBieniosFlow)
                <div class="border rounded p-3 mb-3 bg-light">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <div class="fw-semibold">Informar cierre de trámite</div>
                            <div class="small text-muted">Envía al correo del solicitante el aviso institucional de cierre del trámite de Reconocimiento de Bienios. No carga REX, no cambia períodos y no modifica documentos.</div>
                            <div class="small text-muted mt-1">Destinatario: {{ $applicantUserEmail ?: 'sin correo de usuario disponible' }}</div>
                        </div>
                        <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#informarCierreBieniosModal" {{ $applicantUserEmail === '' ? 'disabled' : '' }}>
                            <i class="bi bi-envelope-check"></i> Informar cierre de trámite
                        </button>
                    </div>
                </div>
            @endif

            <details>
                <summary class="fw-semibold text-danger" style="cursor:pointer;">Anular trámite</summary>
                <form method="POST" action="{{ route('tramites.anular.gestion', $tramite) }}" class="mt-3 row g-3">
                    @csrf
                    <div class="col-12">
                        <label for="motivo_anulacion" class="form-label">Motivo de anulación</label>
                        <textarea name="motivo_anulacion" id="motivo_anulacion" rows="3" class="form-control @error('motivo_anulacion') is-invalid @enderror" required>{{ old('motivo_anulacion') }}</textarea>
                        @error('motivo_anulacion')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button type="submit" class="btn btn-danger">Confirmar anulación</button>
                    </div>
                </form>
            </details>
        </div>
    </div>

    @include('tramites.partials.manual-notifications-history', [
        'manualApplicantNotifications' => $manualApplicantNotifications ?? collect(),
        'displayTimezone' => $displayTimezone,
    ])

    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $activeTab === 'documentos' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-documentos" type="button" role="tab" aria-selected="{{ $activeTab === 'documentos' ? 'true' : 'false' }}">
                Documentos del trámite
            </button>
        </li>
        @if ($hasCalculationPeriods)
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $activeTab === 'calculo' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-calculo" type="button" role="tab" aria-selected="{{ $activeTab === 'calculo' ? 'true' : 'false' }}">
                    Cálculo de períodos
                </button>
            </li>
        @endif
        @if ($hasResolutionTab)
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $activeTab === 'resolucion' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-resolucion" type="button" role="tab" aria-selected="{{ $activeTab === 'resolucion' ? 'true' : 'false' }}">
                    {{ $isExternalBieniosFlow ? 'Resultado del trámite' : 'Resolución de Reconocimiento de Bienios' }}
                </button>
            </li>
        @endif
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade {{ $activeTab === 'documentos' ? 'show active' : '' }}" id="tab-documentos" role="tabpanel">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">Documentos del trámite</div>
                <div class="card-body">
                    @if ($tramite->documentos->count())
                        <div class="accordion" id="tramiteDocsAccordion">
                            @foreach ($tramite->documentos as $documento)
                                @php
                                    $collapseId = 'doc-' . $documento->id;
                                @endphp
                                <div class="accordion-item mb-3 border rounded">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}">
                                            <div class="d-flex flex-column flex-md-row align-items-md-center gap-2 gap-md-3 w-100 pe-3">
                                                <span class="fw-semibold">{{ data_get($documentsConfig, $documento->tipo_documento . '.label', $documento->tipo_documento_label) }}</span>
                                                <span class="small text-muted">{{ $documento->original_name }}</span>
                                                <span class="badge {{ $documento->estado_revision_badge_class }} ms-md-auto">{{ $documento->estado_revision_label }}</span>
                                            </div>
                                        </button>
                                    </h2>
                                    <div id="{{ $collapseId }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#tramiteDocsAccordion">
                                        <div class="accordion-body">
                                            <div class="row g-3">
                                                <div class="col-lg-8">
                                                    <div class="border rounded overflow-hidden bg-light" style="min-height: 720px;">
                                                        <iframe src="{{ route('tramites.documentos.view', [$tramite, $documento]) }}" title="PDF {{ $documento->original_name }}" width="100%" height="720" style="border:0;"></iframe>
                                                    </div>
                                                </div>
                                                <div class="col-lg-4">
                                                    <div class="border rounded p-3 h-100 d-flex flex-column gap-3">
                                                        <div>
                                                            <div class="text-muted small">Archivo</div>
                                                            <div class="fw-semibold">{{ $documento->original_name }}</div>
                                                            <div class="small text-muted">{{ number_format(((int) $documento->size) / 1048576, 2, ',', '.') }} MB · {{ strtoupper($documento->formato ?: 'pdf') }}</div>
                                                        </div>

                                                        @unless ($isExternalBieniosFlow)
                                                            <div>
                                                                <div class="text-muted small">Período asociado</div>
                                                                <div>
                                                                    @if ($documento->fecha_inicio || $documento->fecha_termino)
                                                                        {{ optional($documento->fecha_inicio)->format('d-m-Y') ?: '—' }} al {{ optional($documento->fecha_termino)->format('d-m-Y') ?: '—' }}
                                                                    @else
                                                                        —
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        @endunless

                                                        @if ($documento->reviewedBy)
                                                            <div>
                                                                <div class="text-muted small">Última revisión</div>
                                                                <div class="small">
                                                                    {{ $documento->reviewedBy->nombre_completo ?: $documento->reviewedBy->email }}
                                                                    @if ($documento->reviewed_at)
                                                                        · {{ $documento->reviewed_at->timezone($displayTimezone)->format('d-m-Y H:i') }}
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        @endif

                                                        @if ($documento->revision_observacion)
                                                            <div>
                                                                <div class="text-muted small">Observación registrada</div>
                                                                <div class="small border rounded bg-light p-2">{{ $documento->revision_observacion }}</div>
                                                            </div>
                                                        @endif

                                                        <div class="d-flex gap-2 flex-wrap">
                                                            <a href="{{ route('tramites.documentos.download', [$tramite, $documento]) }}" class="btn btn-sm btn-outline-primary">
                                                                <i class="bi bi-download"></i> Descargar PDF
                                                            </a>
                                                            <a href="{{ route('tramites.documentos.view', [$tramite, $documento]) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                                                                <i class="bi bi-box-arrow-up-right"></i> Abrir aparte
                                                            </a>
                                                        </div>

                                                        @if ($canReviewDocuments)
                                                            <div class="border-top pt-3">
                                                                <div class="fw-semibold mb-2">Resolver documento</div>
                                                                <form method="POST" action="{{ route('tramites.documentos.review', [$tramite, $documento]) }}" class="d-flex flex-column gap-2">
                                                                    @csrf
                                                                    @method('PATCH')
                                                                    <textarea name="revision_observacion" rows="4" class="form-control" placeholder="Observación opcional para la revisión">{{ old('revision_observacion') }}</textarea>
                                                                    <div class="d-flex gap-2">
                                                                        <button type="submit" name="estado_revision" value="aprobado" class="btn btn-success btn-sm">
                                                                            <i class="bi bi-check-circle"></i> Aprobar
                                                                        </button>
                                                                        <button type="submit" name="estado_revision" value="rechazado" class="btn btn-danger btn-sm">
                                                                            <i class="bi bi-x-circle"></i> Rechazar
                                                                        </button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center text-muted py-4">No hay documentos adjuntos para este trámite.</div>
                    @endif
                </div>
            </div>
        </div>

        @if ($hasCalculationPeriods)
            <div class="tab-pane fade {{ $activeTab === 'calculo' ? 'show active' : '' }}" id="tab-calculo" role="tabpanel">
                @include('tramites.partials.calculo-periodos-tab', [
                    'tramite' => $tramite,
                    'displayTimezone' => $displayTimezone,
                    'canEditCalculoPeriodos' => $canReviewDocuments,
                    'canGenerateRex' => true,
                ])
            </div>
        @endif
        @if ($hasResolutionTab)
            <div class="tab-pane fade {{ $activeTab === 'resolucion' ? 'show active' : '' }}" id="tab-resolucion" role="tabpanel">
                @include('tramites.partials.resolucion-bienios-tab', [
                    'tramite' => $tramite,
                    'canManageResolution' => true,
                    'bieniosDocumentationStatus' => $bieniosDocumentationStatus ?? [],
                ])
            </div>
        @endif
    </div>


    @if ($isBienios && !$isExternalBieniosFlow)
        <div class="modal fade" id="informarCierreBieniosModal" tabindex="-1" aria-labelledby="informarCierreBieniosModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('tramites.bienios.informar-cierre', $tramite) }}">
                        @csrf
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="informarCierreBieniosModalLabel">Informar cierre de trámite</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-2">Se enviará un correo al solicitante:</p>
                            <div class="alert alert-light border">
                                <div class="fw-semibold">{{ $applicantUserEmail ?: 'Sin correo de usuario disponible' }}</div>
                                <div class="small text-muted">El correo se toma desde el usuario asociado al trámite.</div>
                            </div>
                            <div class="border rounded p-3 bg-light">
                                <div class="fw-semibold mb-2">Mensaje que recibirá el solicitante</div>
                                <p class="mb-2"><strong>Trámite de Reconocimiento de Bienios finalizado</strong></p>
                                <p class="mb-2">Posteriormente será cargada la resolución exenta respectiva y en el próximo pago de remuneraciones se verá reflejado el pago correspondiente de bienios.</p>
                                <p class="mb-0"><strong>Fecha de Reconocimiento:</strong> {{ optional($tramite->enviado_at)->timezone($displayTimezone)->format('d-m-Y') ?: '—' }}</p>
                            </div>
                            <div class="alert alert-info mt-3 mb-0">Esta acción sólo envía el correo informativo. No cambia documentos, períodos, estado del cálculo ni carga la REX firmada.</div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-success" {{ $applicantUserEmail === '' ? 'disabled' : '' }}>
                                <i class="bi bi-envelope-check"></i> Enviar informe de cierre
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <div class="modal fade" id="notifyApplicantModal" tabindex="-1" aria-labelledby="notifyApplicantModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('tramites.notify-applicant', $tramite) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="notifyApplicantModalLabel">Notificar solicitante</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <div class="small text-muted">Destinatario</div>
                            <div class="fw-semibold">{{ $tramite->nombre_completo_snapshot ?: ($tramite->user?->nombre_completo ?? 'Solicitante') }}</div>
                            <div class="text-muted">{{ $applicantNotificationEmail ?: 'Sin correo configurado' }}</div>
                        </div>
                        <div class="mb-0">
                            <label for="mensaje_notificacion" class="form-label">Mensaje</label>
                            <textarea
                                name="mensaje_notificacion"
                                id="mensaje_notificacion"
                                rows="8"
                                class="form-control @if($errors->notifyApplicant->has('mensaje_notificacion')) is-invalid @endif"
                                placeholder="Escribe aquí la indicación que debe revisar el solicitante."
                                required
                            >{{ old('mensaje_notificacion') }}</textarea>
                            @if($errors->notifyApplicant->has('mensaje_notificacion'))
                                <div class="invalid-feedback">{{ $errors->notifyApplicant->first('mensaje_notificacion') }}</div>
                            @endif
                            <div class="form-text">Se enviará este texto por correo al solicitante y quedará registrado en el historial del trámite.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" {{ $applicantNotificationEmail === '' ? 'disabled' : '' }}>Notificar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const shouldOpen = @json(session('open_notify_applicant_modal', false) || $errors->notifyApplicant->any());
        if (!shouldOpen) {
            return;
        }

        const modalElement = document.getElementById('notifyApplicantModal');
        if (!modalElement || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            return;
        }

        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    });
</script>
@endpush
