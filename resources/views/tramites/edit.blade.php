@extends('layouts.app')

@section('content')
    @php
        $tipos = (array) $tipos;
        $selectedType = old('tipo', $tipoSeleccionado ?: $tramite->tipo);
        $selectedConfig = (array) data_get($tipos, $selectedType, []);
        $isExternalBieniosFlow = $selectedType === 'reconocimiento_bienios' && (bool) $tramite->bienios_flujo_externo;
        $documentsConfig = (array) data_get($selectedConfig, 'documentos', []);
        $oldDocs = old('documentos');
        $removedExisting = collect(old('existing_documentos_remove', []))->map(fn ($id) => (int) $id)->all();
        $existingDocs = $tramite->documentos->reject(fn ($doc) => in_array((int) $doc->id, $removedExisting, true))->values();
        $rows = [];

        if (is_array($oldDocs) && count($oldDocs)) {
            $rows = array_values($oldDocs);
        } else {
            foreach ($existingDocs as $documento) {
                if ((string) $documento->estado_revision !== 'rechazado') {
                    continue;
                }

                $rows[] = [
                    'tipo_documento' => $documento->tipo_documento,
                    'formato' => 'pdf',
                    'fecha_inicio' => optional($documento->fecha_inicio)->format('Y-m-d'),
                    'fecha_termino' => optional($documento->fecha_termino)->format('Y-m-d'),
                    'locked_type' => true,
                    'replacement_label' => 'Documento rechazado. Debes adjuntar aquí el reemplazo corregido.',
                ];
            }
        }

        $approvedByType = $existingDocs->where('estado_revision', 'aprobado')->groupBy('tipo_documento');
        $pendingByType = $existingDocs->filter(fn ($documento) => !in_array((string) $documento->estado_revision, ['aprobado', 'rechazado'], true))->groupBy('tipo_documento');
        $rejectedByType = $existingDocs->where('estado_revision', 'rechazado')->groupBy('tipo_documento');
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Editar trámite #{{ $tramite->id }}</h1>
            <div class="text-muted small">Puedes editar este trámite mientras permanezca en estado enviado o en revisión.</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('tramites.show', $tramite) }}" class="btn btn-outline-secondary">Volver</a>
            @if ($tramite->estado === 'enviado')
                <form method="POST" action="{{ route('tramites.anular', $tramite) }}" onsubmit="return confirm('¿Seguro que deseas anular el envío de este trámite?');">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="bi bi-x-circle"></i> Anular envío
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('tramites.update', $tramite) }}" enctype="multipart/form-data" class="js-validate" novalidate>
        @csrf
        @method('PUT')
        <input type="hidden" name="tipo" value="{{ $tramite->tipo }}">

        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold">Datos base del solicitante</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">RUT</label>
                        <input type="text" class="form-control" value="{{ $tramite->rut_snapshot }}" readonly>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Nombre completo</label>
                        <input type="text" class="form-control" value="{{ $tramite->nombre_completo_snapshot }}" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Correo</label>
                        <input type="text" class="form-control" value="{{ $tramite->email_snapshot }}" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Establecimiento</label>
                        <input type="text" class="form-control" value="{{ $tramite->establecimiento_nombre_snapshot }}" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Estatuto</label>
                        <input type="text" class="form-control" value="{{ $tramite->estatuto_snapshot }}" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Escalafón</label>
                        <input type="text" class="form-control" value="{{ $tramite->escalafon_snapshot }}" readonly>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold">Tipo de trámite</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Tipo</label>
                        <input type="text" class="form-control" value="{{ data_get($selectedConfig, 'label', $tramite->tipo_label) }}" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Estado del trámite</label>
                        <input type="text" class="form-control" value="{{ $tramite->estado_label }}" readonly>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold">Documentos actualmente adjuntos</div>
            <div class="card-body p-0">
                @if ($existingDocs->count())
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Tipo</th>
                                    <th>Archivo</th>
                                    @unless ($isExternalBieniosFlow)<th>Período</th>@endunless
                                    <th>Estado</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($existingDocs as $documento)
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
                                        <td>
                                            <span class="badge {{ $documento->estado_revision_badge_class }}">{{ $documento->estado_revision_label }}</span>
                                            @if ($documento->revision_observacion)
                                                <div class="small text-muted mt-1">{{ $documento->revision_observacion }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column align-items-start gap-2">
                                                <a href="{{ route('tramites.documentos.view', [$tramite, $documento]) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i> Ver documento
                                                </a>

                                                @if ($documento->estado_revision === 'rechazado')
                                                    <span class="text-danger small">Documento rechazado. Debes reemplazarlo abajo.</span>
                                                @elseif ($documento->estado_revision === 'aprobado')
                                                    <span class="text-success small">Documento aprobado. No se puede reemplazar.</span>
                                                @else
                                                    <span class="small text-warning-emphasis">Documento pendiente de revisión.</span>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" value="{{ $documento->id }}" id="remove-doc-{{ $documento->id }}" name="existing_documentos_remove[]">
                                                        <label class="form-check-label" for="remove-doc-{{ $documento->id }}">
                                                            Quitar en esta edición
                                                        </label>
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-4 text-center text-muted">Este trámite no tiene documentos vigentes. Debes volver a adjuntar los obligatorios para guardar la edición.</div>
                @endif
            </div>
        </div>

        <div class="card shadow-sm mb-3" id="bienios-card">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                <span>Agregar nuevos documentos o reemplazos</span>
                <a href="{{ route('tramites.template.download', 'reconocimiento_bienios') }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-download"></i> Descargar plantilla carta
                </a>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">{{ data_get($selectedConfig, 'warning') }}</div>
                <div class="alert alert-info">
                    <div><strong>Documentos obligatorios:</strong> Carta reconocimiento a director ejecutivo y Certificado Cotizaciones AFP Histórico Tipo B con RUT de Empleador.</div>
                    <div class="mt-1"><strong>Además debes adjuntar al menos 1 documento adicional</strong> entre Certificado de Antigüedad, Contratos de Trabajo, Decretos, Orden de Trabajo, Finiquitos o Nombramientos, considerando solo documentos vigentes, aprobados o pendientes más los que agregues ahora.</div>
                </div>

                @if ($rejectedByType->isNotEmpty())
                    <div class="alert alert-danger">
                        Tienes documentos rechazados. Debes adjuntar aquí sus reemplazos corregidos antes de volver a guardar el trámite.
                    </div>
                @endif

                @if ($errors->has('documentos'))
                    <div class="alert alert-danger">
                        @foreach ($errors->get('documentos') as $message)
                            <div>{{ $message }}</div>
                        @endforeach
                    </div>
                @endif

                <div id="document-rows" data-documents='@json($documentsConfig)'>
                    <div class="alert alert-info {{ count($rows) ? 'd-none' : '' }} mb-3" id="documents-empty-state">
                        Agrega aquí nuevos documentos o reemplazos para esta edición.
                    </div>
                    @foreach ($rows as $index => $row)
                        @include('tramites.partials.bienios-document-row', [
                            'index' => $index,
                            'row' => $row,
                            'documentsConfig' => $documentsConfig,
                        ])
                    @endforeach
                </div>

                <div class="border rounded p-3 bg-light mt-3">
                    <div class="fw-semibold mb-2">Agregar documentos</div>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($documentsConfig as $docKey => $docConfig)
                            @continue(array_key_exists('show_add_button', $docConfig) && $docConfig['show_add_button'] === false)
                            @php
                                $isSingle = empty($docConfig['multiple']);
                                $hasApproved = $approvedByType->has($docKey);
                                $hasPending = $pendingByType->has($docKey);
                                $hasRejectedReplacementRow = collect($rows)->contains(fn ($row) => ($row['tipo_documento'] ?? null) === $docKey);
                                $isDisabled = false;
                                $disabledReason = '';

                                if ($isSingle && $hasApproved) {
                                    $isDisabled = true;
                                    $disabledReason = 'Ya existe un documento aprobado para este tipo.';
                                } elseif ($isSingle && $hasPending) {
                                    $isDisabled = true;
                                    $disabledReason = 'Ya existe un documento pendiente para este tipo.';
                                } elseif ($isSingle && $hasRejectedReplacementRow) {
                                    $isDisabled = true;
                                    $disabledReason = 'Ya tienes un reemplazo pendiente de carga para este tipo.';
                                }
                            @endphp
                            <div>
                                <button type="button" class="btn btn-outline-secondary btn-sm js-add-doc" data-doc-type="{{ $docKey }}" data-multiple="{{ !empty($docConfig['multiple']) ? '1' : '0' }}" @disabled($isDisabled)>
                                    <i class="bi bi-plus-circle"></i> {{ $docConfig['label'] }}
                                </button>
                                @if ($isDisabled && $disabledReason !== '')
                                    <div class="small text-muted mt-1">{{ $disabledReason }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save"></i> Guardar cambios
            </button>
            <a href="{{ route('tramites.show', $tramite) }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>

    <template id="bienios-row-template">
        @include('tramites.partials.bienios-document-row', [
            'index' => '__INDEX__',
            'row' => [
                'tipo_documento' => '__DOC_TYPE__',
                'formato' => 'pdf',
                'fecha_inicio' => null,
                'fecha_termino' => null,
            ],
            'documentsConfig' => $documentsConfig,
        ])
    </template>
@endsection

@push('scripts')
    @include('partials.form-validation')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const rowsWrapper = document.getElementById('document-rows');
            const rowTemplate = document.getElementById('bienios-row-template');
            const addButtons = Array.from(document.querySelectorAll('.js-add-doc'));
            const emptyState = document.getElementById('documents-empty-state');
            let nextIndex = document.querySelectorAll('.tramite-doc-row').length;

            function updateEmptyState() {
                if (!emptyState || !rowsWrapper) return;
                const hasRows = rowsWrapper.querySelectorAll('.tramite-doc-row').length > 0;
                emptyState.classList.toggle('d-none', hasRows);
            }

            function updateRowVisibility(row) {
                const select = row.querySelector('.js-doc-type');
                if (!select) return;
                const option = select.options[select.selectedIndex];
                const requiresPeriod = option ? option.dataset.requiresPeriod === '1' : false;
                const startWrap = row.querySelector('.js-date-start-wrap');
                const endWrap = row.querySelector('.js-date-end-wrap');
                if (startWrap) startWrap.classList.toggle('d-none', !requiresPeriod);
                if (endWrap) endWrap.classList.toggle('d-none', !requiresPeriod);
                if (!requiresPeriod) {
                    const startInput = row.querySelector('.js-date-start');
                    const endInput = row.querySelector('.js-date-end');
                    if (startInput) startInput.value = '';
                    if (endInput) endInput.value = '';
                }
            }

            function updateAddButtonsState() {
                const currentCounts = {};
                rowsWrapper.querySelectorAll('.tramite-doc-row').forEach((row) => {
                    const select = row.querySelector('.js-doc-type');
                    const docType = select ? select.value : '';
                    if (!docType) return;
                    currentCounts[docType] = (currentCounts[docType] || 0) + 1;
                });
                addButtons.forEach((btn) => {
                    const docType = btn.dataset.docType;
                    const multiple = btn.dataset.multiple === '1';
                    if (!multiple && (currentCounts[docType] || 0) >= 1) {
                        btn.disabled = true;
                    }
                });
            }

            function bindRow(row) {
                const select = row.querySelector('.js-doc-type');
                const removeBtn = row.querySelector('.js-remove-row');
                if (select) {
                    select.addEventListener('change', function() {
                        updateRowVisibility(row);
                        updateAddButtonsState();
                    });
                    updateRowVisibility(row);
                }
                if (removeBtn) {
                    removeBtn.addEventListener('click', function() {
                        row.remove();
                        updateEmptyState();
                        updateAddButtonsState();
                    });
                }
            }

            function addRow(docType) {
                if (!rowTemplate || !rowsWrapper) return;
                let html = rowTemplate.innerHTML.replace(/__INDEX__/g, String(nextIndex)).replace(/__DOC_TYPE__/g, docType);
                const wrap = document.createElement('div');
                wrap.innerHTML = html.trim();
                const row = wrap.firstElementChild;
                if (!row) return;
                const select = row.querySelector('.js-doc-type');
                if (select && docType) select.value = docType;
                rowsWrapper.appendChild(row);
                bindRow(row);
                nextIndex += 1;
                updateEmptyState();
                updateAddButtonsState();
            }

            document.querySelectorAll('.tramite-doc-row').forEach(bindRow);
            addButtons.forEach((btn) => btn.addEventListener('click', function() { addRow(btn.dataset.docType || ''); }));
            updateEmptyState();
            updateAddButtonsState();
        });
    </script>
@endpush
