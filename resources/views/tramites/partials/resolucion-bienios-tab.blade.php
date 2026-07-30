@php
    $canManageResolution = $canManageResolution ?? false;
    $isExternalBieniosFlow = (bool) $tramite->bienios_flujo_externo;
    $documentationStatus = $bieniosDocumentationStatus ?? [
        'ready' => false,
        'messages' => ['No fue posible determinar el estado de la revisión documental.'],
        'missing_required' => [],
        'approved_optional_count' => 0,
    ];

    if (!$isExternalBieniosFlow) {
        $summary = $tramite->calculo_periodos_resumen;
        $resolutionPreview = app(\App\Services\ResolucionReconocimientoBieniosService::class)->buildData($tramite);
        $resolutionDocuments = collect(data_get($resolutionPreview, 'documentos', []));
    }
@endphp

@if ($isExternalBieniosFlow)
    <div class="d-flex flex-column gap-3">
        <div class="card shadow-sm border-0">
            <div class="card-header fw-semibold">Resultado del Reconocimiento de Bienios</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-lg-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Modalidad de cómputo</div>
                            <div class="fw-semibold">Cálculo administrativo externo</div>
                            <div class="small text-muted mt-2">Los períodos y bienios son determinados por la unidad responsable mediante su planilla de trabajo. La plataforma no genera estimaciones ni cómputos preliminares.</div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Revisión documental</div>
                            @if ($documentationStatus['ready'] ?? false)
                                <div class="fw-semibold text-success"><i class="bi bi-check-circle"></i> Documentación aprobada</div>
                                <div class="small text-muted mt-2">Se encuentran aprobados los documentos obligatorios y al menos un antecedente complementario.</div>
                            @else
                                <div class="fw-semibold text-warning"><i class="bi bi-exclamation-triangle"></i> Revisión incompleta</div>
                                @foreach ((array) ($documentationStatus['messages'] ?? []) as $message)
                                    <div class="small text-muted mt-1">{{ $message }}</div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Estado del expediente</div>
                            <div class="fw-semibold">{{ $tramite->estado_label }}</div>
                            @if ($tramite->resolucion_pdf_uploaded_at)
                                <div class="small text-muted mt-1">Resolución cargada: {{ $tramite->resolucion_pdf_uploaded_at->format('d-m-Y H:i') }}</div>
                            @endif
                            @if ($tramite->detalle_calculo_pdf_uploaded_at)
                                <div class="small text-muted mt-1">Detalle cargado: {{ $tramite->detalle_calculo_pdf_uploaded_at->format('d-m-Y H:i') }}</div>
                            @endif
                            @if ($tramite->resultado_enviado_at)
                                <div class="small text-muted mt-1">Resultado notificado: {{ $tramite->resultado_enviado_at->format('d-m-Y H:i') }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if ($canManageResolution)
            <div class="card shadow-sm border-0">
                <div class="card-header fw-semibold">Cargar resolución y detalle del cómputo</div>
                <div class="card-body">
                    @if (!($documentationStatus['ready'] ?? false))
                        <div class="alert alert-warning mb-0">
                            Antes de cargar el resultado debes finalizar la revisión documental.
                            @foreach ((array) ($documentationStatus['messages'] ?? []) as $message)
                                <div class="small mt-1">{{ $message }}</div>
                            @endforeach
                        </div>
                    @else
                        <form method="POST" action="{{ route('tramites.resolucion.upload-pdf', $tramite) }}" enctype="multipart/form-data" class="row g-3">
                            @csrf
                            <div class="col-md-6">
                                <label for="resolucion_pdf" class="form-label">Resolución firmada en PDF <span class="text-danger">*</span></label>
                                <input type="file" name="resolucion_pdf" id="resolucion_pdf" class="form-control @error('resolucion_pdf') is-invalid @enderror" accept="application/pdf,.pdf" required>
                                <div class="form-text">Resolución exenta o acto administrativo firmado. Máximo 20 MB.</div>
                                @error('resolucion_pdf')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label for="detalle_calculo_pdf" class="form-label">Detalle del cómputo en PDF <span class="text-danger">*</span></label>
                                <input type="file" name="detalle_calculo_pdf" id="detalle_calculo_pdf" class="form-control @error('detalle_calculo_pdf') is-invalid @enderror" accept="application/pdf,.pdf" required>
                                <div class="form-text">PDF exportado desde la planilla de cálculo administrativa. Máximo 20 MB.</div>
                                @error('detalle_calculo_pdf')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-upload"></i> Cargar resultado definitivo
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        <div class="card shadow-sm border-0">
            <div class="card-header fw-semibold">Documentos del resultado</div>
            <div class="card-body">
                @if (!$tramite->resolucion_pdf_path && !$tramite->detalle_calculo_pdf_path)
                    <div class="text-muted">El resultado definitivo aún no ha sido cargado.</div>
                @else
                    <div class="d-flex flex-wrap gap-2">
                        @if ($tramite->resolucion_pdf_path)
                            <a href="{{ route('tramites.resolucion.download-pdf', ['tramite' => $tramite, 'tipo' => 'resolucion']) }}" class="btn btn-outline-primary">
                                <i class="bi bi-file-earmark-pdf"></i> Descargar resolución firmada
                            </a>
                        @endif
                        @if ($tramite->detalle_calculo_pdf_path)
                            <a href="{{ route('tramites.resolucion.download-pdf', ['tramite' => $tramite, 'tipo' => 'detalle']) }}" class="btn btn-outline-secondary">
                                <i class="bi bi-file-earmark-spreadsheet"></i> Descargar detalle del cómputo
                            </a>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        @if ($canManageResolution)
            <div class="card shadow-sm border-0">
                <div class="card-header fw-semibold">Notificar resultado</div>
                <div class="card-body d-flex flex-column gap-2">
                    <div class="small text-muted">La notificación se enviará únicamente al correo del usuario solicitante, con la resolución y el detalle del cómputo adjuntos.</div>
                    <form method="POST" action="{{ route('tramites.resolucion.enviar-resultado', $tramite) }}">
                        @csrf
                        <button type="submit" class="btn btn-success" {{ $tramite->resolucion_pdf_path && $tramite->detalle_calculo_pdf_path ? '' : 'disabled' }}>
                            <i class="bi bi-envelope-check"></i> {{ $tramite->resultado_enviado_at ? 'Reenviar resultado' : 'Enviar resultado y cerrar trámite' }}
                        </button>
                    </form>
                </div>
            </div>
        @endif
    </div>
@else
<div class="d-flex flex-column gap-3">
    <div class="card shadow-sm border-0">
        <div class="card-header fw-semibold">Resolución de Reconocimiento de Bienios</div>
        <div class="card-body d-flex flex-column gap-3">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">Resumen para resolución</div>
                        <div class="fw-semibold">{{ data_get($summary, 'duracion.years', 0) }} años, {{ data_get($summary, 'duracion.months', 0) }} meses y {{ data_get($summary, 'duracion.days', 0) }} días</div>
                        <div class="small text-muted mt-1">Equivalentes a {{ data_get($summary, 'bienios', 0) }} bienio(s).</div>
                        <div class="small text-muted mt-1">Fecha de reconocimiento: {{ optional($tramite->rex_fecha_reconocimiento)->format('d-m-Y') ?: optional($tramite->enviado_at)->format('d-m-Y') ?: '—' }}</div>
                        <div class="small text-muted mt-1">Fecha de antigüedad: {{ data_get($resolutionPreview, 'fecha_antiguedad_corta') ?: '—' }}</div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">REX Word</div>
                        @if ($tramite->rex_docx_path)
                            <div class="fw-semibold mb-2">Borrador generado</div>
                            <a href="{{ route('tramites.resolucion.download-docx', $tramite) }}" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-download"></i> Descargar Resolución Word
                            </a>
                            <div class="small text-muted mt-2">Generada {{ optional($tramite->rex_generado_at)->format('d-m-Y H:i') ?: '—' }}</div>
                        @else
                            <div class="small text-muted">La REX firmada se genera fuera del sistema y se carga directamente en PDF.</div>
                        @endif
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">Estado del expediente</div>
                        <div class="fw-semibold">{{ $tramite->estado_label }}</div>
                        @if ($tramite->resuelto_at)
                            <div class="small text-muted mt-1">Resuelto el {{ $tramite->resuelto_at->format('d-m-Y H:i') }}</div>
                        @endif
                        @if ($tramite->resultado_enviado_at)
                            <div class="small text-muted mt-1">Resultado enviado el {{ $tramite->resultado_enviado_at->format('d-m-Y H:i') }}</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card border-0 bg-light">
                <div class="card-body">
                    <div class="fw-semibold mb-2">Antecedentes que se incorporarán al numeral 13°</div>
                    @if ($resolutionDocuments->isEmpty())
                        <div class="small text-muted">Aún no existen documentos confirmados para construir la resolución.</div>
                    @else
                        <ol class="mb-0 small">
                            @foreach ($resolutionDocuments as $item)
                                <li>{{ $item['texto'] }}</li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header fw-semibold">Cargar REX Firmada</div>
        <div class="card-body">
            @if ($canManageResolution)
                <form method="POST" action="{{ route('tramites.resolucion.upload-pdf', $tramite) }}" enctype="multipart/form-data" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-md-8">
                        <label for="resolucion_pdf" class="form-label">REX firmada en PDF</label>
                        <input type="file" name="resolucion_pdf" id="resolucion_pdf" class="form-control @error('resolucion_pdf') is-invalid @enderror" accept="application/pdf,.pdf" required>
                        <div class="form-text">Archivo PDF obligatorio. Máximo 10 MB.</div>
                        @error('resolucion_pdf')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4 d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload"></i> Cargar REX Firmada
                        </button>
                    </div>
                </form>
            @endif

            @if ($tramite->resolucion_pdf_path)
                <div class="mt-3 d-flex flex-wrap gap-2 align-items-center">
                    <a href="{{ route('tramites.resolucion.download-pdf', $tramite) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-file-earmark-pdf"></i> Descargar REX firmada
                    </a>
                    <span class="small text-muted">Cargado {{ optional($tramite->resolucion_pdf_uploaded_at)->format('d-m-Y H:i') ?: '—' }}</span>
                </div>
            @endif
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header fw-semibold">Enviar resultado</div>
        <div class="card-body d-flex flex-column gap-2">
            <div class="small text-muted">Al enviar el resultado se notificará únicamente al correo del usuario solicitante, con la resolución PDF adjunta y el detalle consolidado de los períodos utilizados en el cálculo.</div>
            @if ($canManageResolution)
                <form method="POST" action="{{ route('tramites.resolucion.enviar-resultado', $tramite) }}">
                    @csrf
                    <button type="submit" class="btn btn-success" {{ $tramite->resolucion_pdf_path ? '' : 'disabled' }}>
                        <i class="bi bi-envelope-check"></i> Enviar Resultado
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>

@endif
