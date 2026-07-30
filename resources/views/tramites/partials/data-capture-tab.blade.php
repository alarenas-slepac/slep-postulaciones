@php
    $captureDocuments = $tramite->documentos->filter(fn ($documento) => $documento->can_run_data_capture);
    $displayTimezone = $displayTimezone ?? config('app.display_timezone', 'America/Santiago');
    $canRunCaptureButtons = $canRunCaptureButtons ?? false;
    $confirmedBlocksByDocument = collect($tramite->calculo_periodos_data ?? [])->keyBy(fn ($item) => (int) data_get($item, 'documento_id'));
@endphp

<div class="card shadow-sm border-0">
    <div class="card-header fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>Captura de datos de documentos aprobados</span>
        @if ($captureDocuments->count())
            <span class="badge text-bg-primary">{{ $captureDocuments->count() }} documento(s)</span>
        @endif
    </div>
    <div class="card-body">
        @if ($captureDocuments->isEmpty())
            <div class="text-center text-muted py-4">
                La pestaña de captura se habilita cuando exista al menos un documento aprobado y elegible para extracción automática de datos.
            </div>
        @else
            <div class="alert alert-info mb-3">
                <div class="fw-semibold">Lectura asistida del documento aprobado</div>
                <div class="small mb-0">La captura ahora intenta leer fecha del documento, emisor, número y períodos trabajados. Cuando el archivo tenga múltiples períodos, puedes seguir marcando los válidos y confirmarlos para trasladarlos a la pestaña <strong>Cálculo de períodos</strong>, agrupados por documento.</div>
            </div>

            <div class="accordion" id="capturaDatosAccordion">
                @foreach ($captureDocuments as $documento)
                    @php
                        $collapseId = 'captura-doc-' . $documento->id;
                        $payloadWarnings = collect(data_get($documento->captura_payload, 'warnings', []))->filter()->values();
                        $rutAnalysis = data_get($documento->captura_payload, 'rut_analysis', []);
                        $rutRoles = collect(data_get($rutAnalysis, 'roles', []))->filter();
                        $rutSelectedRole = data_get($rutAnalysis, 'selected_role');
                        $rutRoleContexts = collect(data_get($rutAnalysis, 'role_contexts', []));
                        $dateAnalysis = data_get($documento->captura_payload, 'date_analysis', []);
                        $documentMetadata = data_get($documento->captura_payload, 'document_metadata', []);
                        $manualDateInputs = data_get($documento->captura_payload, 'manual_date_inputs', []);
                        $rutRoleLabels = [
                            'trabajador' => 'Trabajador(a)',
                            'empleador' => 'Empleador(a)',
                            'representante' => 'Representante legal',
                        ];
                        $rutRoleSourceLabels = [
                            'encabezado_trabajador' => 'Encabezado trabajador',
                            'cedula_identidad' => 'Cédula de identidad',
                            'ex_trabajador' => 'Cláusula trabajador',
                            'encabezado_empleador' => 'Encabezado empleador',
                            'entre_empleador' => 'Bloque entre partes',
                            'ex_empleador' => 'Cláusula empleador',
                            'representante_legal' => 'Representación legal',
                            'contexto_trabajador' => 'Inferido por contexto trabajador',
                            'contexto_empleador' => 'Inferido por contexto empleador',
                            'contexto_representante' => 'Inferido por contexto representante',
                            'fallback_prioridad' => 'Selección por prioridad',
                        ];
                        $documentRequiresPeriods = (bool) ($documento->requires_period ?? false);
                        $confirmedBlock = $confirmedBlocksByDocument->get((int) $documento->id, []);
                        $confirmedIndexes = collect(data_get($confirmedBlock, 'periodos', []))
                            ->pluck('selected_index')
                            ->filter(fn ($value) => $value !== null && $value !== '')
                            ->map(fn ($value) => (int) $value)
                            ->values()
                            ->all();
                        $hasConfirmedPeriodsForDocument = !empty($confirmedIndexes);
                    @endphp
                    <div class="accordion-item mb-3 border rounded">
                        <h2 class="accordion-header">
                            <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}">
                                <div class="d-flex flex-column flex-md-row align-items-md-center gap-2 gap-md-3 w-100 pe-3">
                                    <span class="fw-semibold">{{ data_get($documentsConfig, $documento->tipo_documento . '.label', $documento->tipo_documento_label) }}</span>
                                    <span class="small text-muted">{{ $documento->original_name }}</span>
                                    <span class="badge {{ $documento->captura_estado_badge_class }} ms-md-auto">{{ $documento->captura_estado_label }}</span>
                                </div>
                            </button>
                        </h2>
                        <div id="{{ $collapseId }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#capturaDatosAccordion">
                            <div class="accordion-body">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                                    <div>
                                        <div class="fw-semibold">{{ $documento->original_name }}</div>
                                        <div class="small text-muted">
                                            Método: {{ $documento->captura_metodo_label }}
                                            @if ($documento->captura_ejecutada_at)
                                                · Ejecutado {{ $documento->captura_ejecutada_at->timezone($displayTimezone)->format('d-m-Y H:i') }}
                                            @endif
                                        </div>
                                        @if ($hasConfirmedPeriodsForDocument)
                                            <div class="small text-success mt-1">
                                                {{ count(data_get($confirmedBlock, 'periodos', [])) }} período(s) ya trasladado(s) a Cálculo de períodos.
                                            </div>
                                        @endif
                                    </div>
                                    @if ($canRunCaptureButtons)
                                        <form method="POST" action="{{ route('tramites.documentos.capture', [$tramite, $documento]) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-cpu"></i>
                                                {{ $documento->captura_ejecutada_at ? 'Recapturar datos' : 'Capturar datos' }}
                                            </button>
                                        </form>
                                    @endif
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6 col-xl-3">
                                        <div class="border rounded p-3 h-100">
                                            <div class="text-muted small">RUT detectado</div>
                                            <div class="fw-semibold">{{ $documento->captura_rut ?: '—' }}</div>
                                            <div class="small mt-1">
                                                @if ($documento->captura_rut_coincide_con_tramite === true)
                                                    <span class="badge text-bg-success">Coincide con solicitud</span>
                                                @elseif ($documento->captura_rut_coincide_con_tramite === false)
                                                    <span class="badge text-bg-danger">No coincide con solicitud</span>
                                                @else
                                                    <span class="text-muted">Sin validación disponible</span>
                                                @endif
                                            </div>
                                            @if ($rutSelectedRole)
                                                <div class="small text-muted mt-2">Clasificación principal: {{ $rutRoleLabels[$rutSelectedRole] ?? ucfirst($rutSelectedRole) }}</div>
                                            @endif
                                            @if ($rutRoles->isNotEmpty() || $rutRoleContexts->isNotEmpty())
                                                <div class="small mt-2 d-flex flex-column gap-2">
                                                    @foreach ($rutRoleLabels as $roleKey => $roleLabel)
                                                        @php
                                                            $roleRut = $rutRoles->get($roleKey);
                                                            $roleContext = $rutRoleContexts->get($roleKey, []);
                                                            $roleRawRut = data_get($roleContext, 'raw_rut');
                                                            $roleContextText = data_get($roleContext, 'context');
                                                            $roleSource = data_get($roleContext, 'source');
                                                        @endphp
                                                        @if ($roleRut || $roleRawRut || $roleContextText)
                                                            <div class="border rounded p-2 bg-light-subtle">
                                                                <div><span class="text-muted">{{ $roleLabel }}:</span> {{ $roleRut ?: 'Sin RUT normalizado' }}</div>
                                                                @if (!$roleRut && $roleRawRut)
                                                                    <div class="text-muted small">Texto OCR observado: {{ $roleRawRut }}</div>
                                                                @endif
                                                                @if ($roleSource)
                                                                    <div class="text-muted small">Fuente: {{ $rutRoleSourceLabels[$roleSource] ?? $roleSource }}</div>
                                                                @endif
                                                                @if ($roleContextText)
                                                                    <div class="text-muted small" style="white-space: pre-wrap;">{{ $roleContextText }}</div>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-xl-3">
                                        <div class="border rounded p-3 h-100">
                                            <div class="text-muted small">Período asociado</div>
                                            <div class="fw-semibold">
                                                @if ($documento->fecha_inicio || $documento->fecha_termino)
                                                    {{ optional($documento->fecha_inicio)->format('d-m-Y') ?: '—' }} al {{ optional($documento->fecha_termino)->format('d-m-Y') ?: '—' }}
                                                @else
                                                    —
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-xl-3">
                                        <div class="border rounded p-3 h-100">
                                            <div class="text-muted small">Rango detectado</div>
                                            <div class="fw-semibold">
                                                @if ($documento->captura_rango_inicio || $documento->captura_rango_termino)
                                                    {{ optional($documento->captura_rango_inicio)->format('d-m-Y') ?: '—' }} al {{ optional($documento->captura_rango_termino)->format('d-m-Y') ?: '—' }}
                                                @else
                                                    —
                                                @endif
                                            </div>
                                            <div class="small text-muted mt-1">{{ $documento->captura_total_periodos ?: 0 }} período(s) detectado(s)</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 col-xl-3">
                                        <div class="border rounded p-3 h-100">
                                            <div class="text-muted small">Comparación</div>
                                            <div class="fw-semibold">{{ $documento->captura_comparacion_label }}</div>
                                            <div class="small mt-1 d-flex flex-column gap-1">
                                                @if ($documento->captura_tiene_interrupciones)
                                                    <span class="badge text-bg-warning">Con interrupciones</span>
                                                @else
                                                    <span class="badge text-bg-secondary">Sin interrupciones</span>
                                                @endif
                                                @if (data_get($dateAnalysis, 'selected_period_source'))
                                                    <span class="text-muted">Fuente principal: {{ data_get($dateAnalysis, 'selected_period_source') }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-lg-7">
                                        <div class="border rounded p-3 h-100">
                                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                                <div class="fw-semibold">Períodos detectados</div>
                                                @if ($hasConfirmedPeriodsForDocument)
                                                    <span class="badge text-bg-success">Confirmados en cálculo</span>
                                                @endif
                                            </div>

                                            @if ($documento->captura_periodos_collection->count())
                                                @if ($canRunCaptureButtons)
                                                    <form method="POST" action="{{ route('tramites.documentos.capture.confirm-periods', [$tramite, $documento]) }}">
                                                        @csrf
                                                        <div class="table-responsive">
                                                            <table class="table table-sm align-middle mb-0">
                                                                <thead>
                                                                    <tr>
                                                                        <th style="width: 48px;">Sel.</th>
                                                                        <th>#</th>
                                                                        <th>Inicio</th>
                                                                        <th>Término</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach ($documento->captura_periodos_collection as $periodo)
                                                                        @php
                                                                            $index = $loop->index;
                                                                            $checked = $hasConfirmedPeriodsForDocument ? in_array($index, $confirmedIndexes, true) : true;
                                                                        @endphp
                                                                        <tr>
                                                                            <td>
                                                                                <input class="form-check-input" type="checkbox" name="periodos[]" value="{{ $index }}" id="periodo-{{ $documento->id }}-{{ $index }}" {{ $checked ? 'checked' : '' }}>
                                                                            </td>
                                                                            <td><label for="periodo-{{ $documento->id }}-{{ $index }}" class="mb-0">{{ $loop->iteration }}</label></td>
                                                                            <td>{{ \Illuminate\Support\Carbon::parse($periodo['inicio'])->format('d-m-Y') }}</td>
                                                                            <td>{{ \Illuminate\Support\Carbon::parse($periodo['termino'])->format('d-m-Y') }}</td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3 pt-2 border-top">
                                                            <div class="small text-muted">Marca los períodos válidos y usa el botón para trasladarlos a la pestaña Cálculo de períodos.</div>
                                                            <button type="submit" class="btn btn-sm btn-success">
                                                                <i class="bi bi-check2-square"></i>
                                                                {{ $hasConfirmedPeriodsForDocument ? 'Actualizar períodos confirmados' : 'Confirmar período(s)' }}
                                                            </button>
                                                        </div>
                                                    </form>
                                                @else
                                                    <div class="table-responsive">
                                                        <table class="table table-sm align-middle mb-0">
                                                            <thead>
                                                                <tr>
                                                                    <th>#</th>
                                                                    <th>Inicio</th>
                                                                    <th>Término</th>
                                                                    <th>Estado</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach ($documento->captura_periodos_collection as $periodo)
                                                                    @php
                                                                        $index = $loop->index;
                                                                        $isConfirmed = $hasConfirmedPeriodsForDocument && in_array($index, $confirmedIndexes, true);
                                                                    @endphp
                                                                    <tr>
                                                                        <td>{{ $loop->iteration }}</td>
                                                                        <td>{{ \Illuminate\Support\Carbon::parse($periodo['inicio'])->format('d-m-Y') }}</td>
                                                                        <td>{{ \Illuminate\Support\Carbon::parse($periodo['termino'])->format('d-m-Y') }}</td>
                                                                        <td>
                                                                            @if ($isConfirmed)
                                                                                <span class="badge text-bg-success">Confirmado</span>
                                                                            @else
                                                                                <span class="badge text-bg-light text-dark border">Detectado</span>
                                                                            @endif
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                @endif
                                            @else
                                                <div class="text-muted small">No se detectaron períodos en la última captura. Esto es esperable en cartas dirigidas al Director Ejecutivo y en certificados de cotizaciones; en el resto de documentos revisa la lectura antes de confirmar.</div>

                                                @php
                                                    $showManualCapturePeriodForm = $documentRequiresPeriods
                                                        && $canRunCaptureButtons
                                                        && (string) $documento->captura_estado === 'requiere_revision'
                                                        && !$documento->captura_rango_inicio
                                                        && !$documento->captura_rango_termino;
                                                @endphp

                                                @if ($showManualCapturePeriodForm)
                                                    <form method="POST" action="{{ route('tramites.documentos.capture.manual-period', [$tramite, $documento]) }}" class="mt-3 border rounded p-3 bg-warning-subtle">
                                                        @csrf
                                                        <div class="fw-semibold small mb-2">Agregar período manual para este documento</div>
                                                        <div class="small text-muted mb-3">Usa esta opción cuando el documento aprobado queda en Requiere revisión porque no se detectó rango de fechas, pero el período está validado visualmente en el archivo.</div>
                                                        <div class="row g-2">
                                                            <div class="col-md-6">
                                                                <label class="form-label form-label-sm mb-1">Fecha inicio</label>
                                                                <input type="date" name="manual_fecha_inicio" value="{{ old('manual_fecha_inicio') }}" class="form-control form-control-sm" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label form-label-sm mb-1">Fecha término</label>
                                                                <input type="date" name="manual_fecha_termino" value="{{ old('manual_fecha_termino') }}" class="form-control form-control-sm" required>
                                                            </div>
                                                            <div class="col-12">
                                                                <label class="form-label form-label-sm mb-1">Referencia u observación</label>
                                                                <input type="text" name="manual_referencia" value="{{ old('manual_referencia') }}" class="form-control form-control-sm" maxlength="160" placeholder="Ej.: Rango ingresado manualmente desde finiquito revisado">
                                                            </div>
                                                        </div>
                                                        <div class="d-flex justify-content-end mt-3">
                                                            <button type="submit" class="btn btn-sm btn-warning">
                                                                <i class="bi bi-plus-circle"></i> Agregar período a cálculo
                                                            </button>
                                                        </div>
                                                    </form>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-lg-5">
                                        <div class="border rounded p-3 h-100 d-flex flex-column gap-3">
                                            <div>
                                                <div class="fw-semibold mb-2">Datos estructurados detectados</div>
                                                <div class="small d-flex flex-column gap-1">
                                                    <div><span class="text-muted">Tipo detectado:</span> {{ data_get($documentMetadata, 'detected_label') ?: '—' }}</div>
                                                    <div><span class="text-muted">Emisor:</span> {{ data_get($documentMetadata, 'issuer_name') ?: '—' }}</div>
                                                    <div><span class="text-muted">Número/Folio:</span> {{ data_get($documentMetadata, 'document_number') ?: '—' }}</div>
                                                    <div><span class="text-muted">Fecha documento:</span> {{ data_get($dateAnalysis, 'document_date') ?: '—' }}</div>
                                                    <div><span class="text-muted">Fecha certificación:</span> {{ data_get($dateAnalysis, 'certification_date') ?: '—' }}</div>
                                                    @if ($documentRequiresPeriods)
                                                        <div><span class="text-muted">Inicio laboral:</span> {{ data_get($dateAnalysis, 'labor_start') ?: (data_get($dateAnalysis, 'labor_start_partial_text') ? 'Parcial: ' . data_get($dateAnalysis, 'labor_start_partial_text') : '—') }}</div>
                                                        <div><span class="text-muted">Término laboral:</span> {{ data_get($dateAnalysis, 'labor_end') ?: (data_get($dateAnalysis, 'labor_end_partial_text') ? 'Parcial: ' . data_get($dateAnalysis, 'labor_end_partial_text') : '—') }}</div>
                                                    @endif
                                                </div>

                                                @php
                                                    $showManualDateForm = $documentRequiresPeriods && $canRunCaptureButtons && (
                                                        data_get($dateAnalysis, 'labor_start_partial_text') ||
                                                        data_get($dateAnalysis, 'labor_end_partial_text') ||
                                                        data_get($manualDateInputs, 'labor_start') ||
                                                        data_get($manualDateInputs, 'labor_end')
                                                    );
                                                    $startFieldValue = old('labor_start_text', data_get($manualDateInputs, 'labor_start') ?: data_get($dateAnalysis, 'labor_start_partial_text') ?: '');
                                                    $endFieldValue = old('labor_end_text', data_get($manualDateInputs, 'labor_end') ?: data_get($dateAnalysis, 'labor_end_partial_text') ?: '');
                                                @endphp

                                                @if ($showManualDateForm)
                                                    <form method="POST" action="{{ route('tramites.documentos.capture.manual-dates', [$tramite, $documento]) }}" class="mt-3 border rounded p-3 bg-light-subtle">
                                                        @csrf
                                                        <div class="fw-semibold small mb-2">Completar fechas parciales detectadas</div>
                                                        <div class="small text-muted mb-2">Cuando el OCR sólo detecte parte de la fecha, puedes completar manualmente el texto. Acepta formatos como 01-04-2025, 01/04/2025 o 01 de abril de 2025.</div>
                                                        <div class="mb-2">
                                                            <label class="form-label form-label-sm mb-1">Inicio laboral manual</label>
                                                            <input type="text" name="labor_start_text" value="{{ $startFieldValue }}" class="form-control form-control-sm" placeholder="Ej.: 01 de abril de 2025">
                                                        </div>
                                                        <div class="mb-2">
                                                            <label class="form-label form-label-sm mb-1">Término laboral manual</label>
                                                            <input type="text" name="labor_end_text" value="{{ $endFieldValue }}" class="form-control form-control-sm" placeholder="Ej.: 28 de febrero de 2026">
                                                        </div>
                                                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                            <i class="bi bi-calendar-check"></i> Guardar fechas manuales
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>

                                            <div>
                                                <div class="fw-semibold mb-2">Observación de captura</div>
                                                <div class="small">{{ $documento->captura_observaciones ?: '—' }}</div>
                                            </div>

                                            @if ($payloadWarnings->isNotEmpty())
                                                <div>
                                                    <div class="fw-semibold mb-2">Advertencias</div>
                                                    <ul class="small mb-0 ps-3">
                                                        @foreach ($payloadWarnings as $warning)
                                                            <li>{{ $warning }}</li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif

                                            @if (data_get($documento->captura_payload, 'snippet'))
                                                <div>
                                                    <div class="fw-semibold mb-2">Texto detectado (resumen)</div>
                                                    <div class="small border rounded bg-light p-2" style="max-height: 180px; overflow:auto; white-space: pre-wrap;">{{ data_get($documento->captura_payload, 'snippet') }}</div>
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
        @endif
    </div>
</div>
