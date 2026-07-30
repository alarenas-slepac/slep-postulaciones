@extends('layouts.app')

@section('content')
    @php
        $documentsConfig = (array) ($tipoConfig['documentos'] ?? []);
        $isBienios = $tramite->tipo === 'reconocimiento_bienios';
        $isExternalBieniosFlow = $isBienios && (bool) $tramite->bienios_flujo_externo;
        $hasCaptureDocuments = false;
        $hasApprovedDocuments = $tramite->documentos->contains(fn ($documento) => (string) $documento->estado_revision === 'aprobado');
        $hasCalculationPeriods = !$isExternalBieniosFlow && ($tramite->has_calculo_periodos || ($isBienios && $hasApprovedDocuments));
        $hasResolutionTab = $tramite->has_resolucion_reconocimiento;
        $activeTab = 'documentos';
        if (request('tab') === 'captura' && $hasCaptureDocuments) {
            $activeTab = 'captura';
        } elseif (request('tab') === 'calculo' && $hasCalculationPeriods) {
            $activeTab = 'calculo';
        } elseif (request('tab') === 'resolucion' && $hasResolutionTab) {
            $activeTab = 'resolucion';
        }
        $displayTimezone = config('app.display_timezone', 'America/Santiago');
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h4 mb-0">Trámite #{{ $tramite->id }}</h1>
            <div class="text-muted small">{{ $tramite->tipo_label }}</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('tramites.index') }}" class="btn btn-outline-secondary">Volver</a>
            @if (in_array($tramite->estado, ['enviado', 'en_revision'], true))
                <a href="{{ route('tramites.edit', $tramite) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-pencil"></i> Editar
                </a>
                @if ($tramite->estado === 'enviado')
                    <form method="POST" action="{{ route('tramites.anular', $tramite) }}" onsubmit="return confirm('¿Seguro que deseas anular el envío de este trámite?');">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-outline-danger">
                            <i class="bi bi-x-circle"></i> Anular envío
                        </button>
                    </form>
                @endif
            @endif
            <a href="{{ route('tramites.create') }}" class="btn btn-primary">Nuevo trámite</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Estado</div>
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
                    <div class="text-muted small">RUT</div>
                    <div class="fw-semibold">{{ $tramite->rut_snapshot ?: '—' }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Correo</div>
                    <div class="fw-semibold">{{ $tramite->email_snapshot ?: '—' }}</div>
                </div>
            </div>
        </div>
    </div>

    @if ($tramite->estado === 'anulado')
        <div class="alert alert-warning mb-3">
            <strong>Trámite anulado por usuario.</strong>
            @if ($tramite->anulado_at)
                Anulado el {{ $tramite->anulado_at->timezone($displayTimezone)->format('d-m-Y H:i') }}
            @endif
            @if ($tramite->anuladoPor)
                por {{ trim(collect([$tramite->anuladoPor->nombres, $tramite->anuladoPor->apellido_paterno, $tramite->anuladoPor->apellido_materno])->filter()->implode(' ')) ?: $tramite->anuladoPor->email }}.
            @endif
        </div>
    @endif

    <div class="card shadow-sm mb-3">
        <div class="card-header fw-semibold">Datos del trámite</div>
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


    @if ($isExternalBieniosFlow)
        <div class="alert alert-info mb-3">
            <div class="fw-semibold">Cómputo administrativo externo</div>
            <div>La plataforma se utiliza para recibir y revisar tus documentos. No debes ingresar fechas ni se mostrará un cálculo preliminar. El resultado oficial estará disponible mediante la resolución y el detalle del cómputo que cargará la unidad responsable.</div>
        </div>
    @endif

    @include('tramites.partials.manual-notifications-history', [
        'manualApplicantNotifications' => $manualApplicantNotifications ?? collect(),
        'displayTimezone' => $displayTimezone,
    ])

    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $activeTab === 'documentos' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-documentos" type="button" role="tab" aria-selected="{{ $activeTab === 'documentos' ? 'true' : 'false' }}">
                Documentos adjuntos
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
                <div class="card-header fw-semibold">Documentos adjuntos</div>
                <div class="card-body p-0">
                    @if ($tramite->documentos->count())
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Tipo de documento</th>
                                        <th>Archivo</th>
                                        @unless ($isExternalBieniosFlow)<th>Período</th>@endunless
                                        <th>Formato</th>
                                        <th>Revisión</th>
                                        <th>Observación</th>
                                        <th class="text-end">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($tramite->documentos as $documento)
                                        <tr>
                                            <td>{{ data_get($documentsConfig, $documento->tipo_documento . '.label', $documento->tipo_documento_label) }}</td>
                                            <td>
                                                <div class="fw-semibold">{{ $documento->original_name }}</div>
                                                <div class="small text-muted">{{ number_format(((int) $documento->size) / 1048576, 2, ',', '.') }} MB</div>
                                            </td>
                                            @unless ($isExternalBieniosFlow)
                                                <td>
                                                    @if ($documento->fecha_inicio || $documento->fecha_termino)
                                                        {{ optional($documento->fecha_inicio)->format('d-m-Y') ?: '—' }} al {{ optional($documento->fecha_termino)->format('d-m-Y') ?: '—' }}
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                            @endunless
                                            <td>{{ strtoupper($documento->formato ?: 'pdf') }}</td>
                                            <td>
                                                <span class="badge {{ $documento->estado_revision_badge_class }}">{{ $documento->estado_revision_label }}</span>
                                            </td>
                                            <td>
                                                @if ($documento->revision_observacion)
                                                    <div class="small text-wrap">{{ $documento->revision_observacion }}</div>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <a href="{{ route('tramites.documentos.download', [$tramite, $documento]) }}" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-download"></i> Descargar
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-4 text-center text-muted">No hay documentos adjuntos para este trámite.</div>
                    @endif
                </div>
            </div>
        </div>

        @if ($hasCalculationPeriods)
            <div class="tab-pane fade {{ $activeTab === 'calculo' ? 'show active' : '' }}" id="tab-calculo" role="tabpanel">
                @include('tramites.partials.calculo-periodos-tab', [
                    'tramite' => $tramite,
                    'displayTimezone' => $displayTimezone,
                    'canEditCalculoPeriodos' => false,
                    'canGenerateRex' => false,
                ])
            </div>
        @endif
        @if ($hasResolutionTab)
            <div class="tab-pane fade {{ $activeTab === 'resolucion' ? 'show active' : '' }}" id="tab-resolucion" role="tabpanel">
                @include('tramites.partials.resolucion-bienios-tab', [
                    'tramite' => $tramite,
                    'canManageResolution' => false,
                    'bieniosDocumentationStatus' => $bieniosDocumentationStatus ?? [],
                ])
            </div>
        @endif
    </div>
@endsection
