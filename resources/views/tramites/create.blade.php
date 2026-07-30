@extends('layouts.app')

@section('content')
    @php
        $tipos = (array) $tipos;
        $selectedType = old('tipo', $tipoSeleccionado);
        $selectedConfig = (array) data_get($tipos, $selectedType, []);
        $documentsConfig = (array) data_get($selectedConfig, 'documentos', []);
        $oldDocs = old('documentos');
        $rows = [];
        $defaultRows = [];

        foreach ($documentsConfig as $docKey => $docConfig) {
            if (!empty($docConfig['preload_on_create'])) {
                $defaultRows[] = [
                    'tipo_documento' => $docKey,
                    'formato' => 'pdf',
                    'fecha_inicio' => null,
                    'fecha_termino' => null,
                ];
            }
        }

        if (is_array($oldDocs) && count($oldDocs)) {
            $rows = array_values($oldDocs);

            $existingDocTypes = collect($rows)
                ->pluck('tipo_documento')
                ->filter()
                ->map(fn ($value) => (string) $value)
                ->values()
                ->all();

            foreach ($defaultRows as $defaultRow) {
                if (!in_array($defaultRow['tipo_documento'], $existingDocTypes, true)) {
                    array_unshift($rows, $defaultRow);
                }
            }
        } else {
            $rows = $defaultRows;
        }
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Nuevo trámite</h1>
            <div class="text-muted small">Completa el formulario y adjunta la documentación del trámite que corresponda.</div>
        </div>
        <a href="{{ route('tramites.index') }}" class="btn btn-outline-secondary">Volver</a>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('tramites.store') }}" enctype="multipart/form-data" class="js-validate" novalidate>
        @csrf

        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold">Datos base del solicitante</div>
            <div class="card-body">
                @if (!($autofill['ok'] ?? false))
                    <div class="alert alert-danger mb-0">
                        <strong>No es posible autocompletar este trámite.</strong><br>
                        {{ $autofill['message'] ?? 'No se encontraron datos para el RUT en reemplazos_personal.' }}
                    </div>
                @else
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">RUT</label>
                            <input type="text" class="form-control" value="{{ $autofill['rut'] }}" readonly>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Nombre completo</label>
                            <input type="text" class="form-control" value="{{ $autofill['nombre_completo'] }}" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Correo</label>
                            <input type="text" class="form-control" value="{{ $autofill['email'] }}" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Establecimiento</label>
                            <input type="text" class="form-control" value="{{ $autofill['establecimiento_label'] }}" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Estatuto</label>
                            <input type="text" class="form-control" value="{{ $autofill['estatuto'] }}" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Escalafón</label>
                            <input type="text" class="form-control" value="{{ $autofill['escalafon'] }}" readonly>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold">Tipo de trámite</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Tipo de trámite <span class="text-danger">*</span></label>
                        <select name="tipo" id="tipo" class="form-select @error('tipo') is-invalid @enderror" {{ !($autofill['ok'] ?? false) ? 'disabled' : '' }}>
                            <option value="">Seleccione...</option>
                            @foreach ($tipos as $key => $tipo)
                                <option value="{{ $key }}" @selected($selectedType === $key)>{{ $tipo['label'] }}</option>
                            @endforeach
                        </select>
                        @error('tipo')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-3 {{ $selectedType === 'reconocimiento_bienios' ? '' : 'd-none' }}" id="bienios-card">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                <span>Trámite de reconocimiento de bienios</span>
                <a href="{{ route('tramites.template.download', 'reconocimiento_bienios') }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-download"></i> Descargar plantilla carta
                </a>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    {{ data_get($selectedConfig, 'warning') }}
                </div>

                <div class="alert alert-info">
                    <div><strong>Documentos obligatorios:</strong> Carta reconocimiento a director ejecutivo y Certificado Cotizaciones AFP Histórico Tipo B con RUT de Empleador.</div>
                    <div class="mt-1"><strong>Además debes adjuntar al menos 1 documento adicional</strong> entre Certificado de Antigüedad, Contratos de Trabajo, Decretos, Orden de Trabajo, Finiquitos o Nombramientos.</div>
                </div>

                <div class="alert alert-primary border-primary-subtle" id="bienios-plazo-alert">
                    <div class="d-flex gap-2">
                        <div class="fs-5 text-primary"><i class="bi bi-clock-history"></i></div>
                        <div>
                            <div class="fw-semibold">Plazo estimado de tramitación</div>
                            <div>Al enviar esta solicitud, se informa que el trámite de Reconocimiento de Bienios podrá demorar hasta un máximo de <strong>30 días corridos</strong> desde su recepción, sujeto a revisión documental y validación de antecedentes.</div>
                            <div class="small text-muted mt-1">También recibirás esta información en el correo registrado en tu cuenta.</div>
                        </div>
                    </div>
                </div>

                @if ($errors->has('documentos'))
                    <div class="alert alert-danger">
                        @foreach ($errors->get('documentos') as $message)
                            <div>{{ $message }}</div>
                        @endforeach
                    </div>
                @endif

                <div id="document-rows" data-documents='@json($documentsConfig)'>
                    <div class="alert alert-info {{ count($rows) ? 'd-none' : '' }} mb-3" id="documents-empty-state">
                        Aún no has agregado documentos adicionales. Usa los botones inferiores para incorporar uno o más archivos complementarios al trámite.
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
                            <button type="button" class="btn btn-outline-secondary btn-sm js-add-doc" data-doc-type="{{ $docKey }}" data-multiple="{{ !empty($docConfig['multiple']) ? '1' : '0' }}">
                                <i class="bi bi-plus-circle"></i> {{ $docConfig['label'] }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary" {{ !($autofill['ok'] ?? false) ? 'disabled' : '' }}>
                <i class="bi bi-send"></i> Enviar
            </button>
            <a href="{{ route('tramites.index') }}" class="btn btn-outline-secondary">Cancelar</a>
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
            const tipo = document.getElementById('tipo');
            const bieniosCard = document.getElementById('bienios-card');
            const rowsWrapper = document.getElementById('document-rows');
            const rowTemplate = document.getElementById('bienios-row-template');
            const addButtons = Array.from(document.querySelectorAll('.js-add-doc'));
            const emptyState = document.getElementById('documents-empty-state');
            let nextIndex = {{ count($rows) }};

            function updateRowVisibility(row) {
                const select = row.querySelector('.js-doc-type');
                if (!select) {
                    return;
                }

                const startWrap = row.querySelector('.js-date-start-wrap');
                const endWrap = row.querySelector('.js-date-end-wrap');
                const startInput = row.querySelector('.js-date-start');
                const endInput = row.querySelector('.js-date-end');
                const needsPeriod = select.selectedOptions?.[0]?.dataset?.requiresPeriod === '1';

                if (startWrap) {
                    startWrap.classList.toggle('d-none', !needsPeriod);
                }
                if (endWrap) {
                    endWrap.classList.toggle('d-none', !needsPeriod);
                }

                if (!needsPeriod) {
                    if (startInput) startInput.value = '';
                    if (endInput) endInput.value = '';
                }
            }

            function updateEmptyState() {
                if (!emptyState || !rowsWrapper) {
                    return;
                }

                const rows = rowsWrapper.querySelectorAll('.tramite-doc-row').length;
                emptyState.classList.toggle('d-none', rows > 0);
            }

            function countRowsByDocType(docType) {
                return rowsWrapper.querySelectorAll('.tramite-doc-row .js-doc-type').length
                    ? Array.from(rowsWrapper.querySelectorAll('.tramite-doc-row .js-doc-type')).filter((select) => select.value === docType).length
                    : 0;
            }

            function updateAddButtonsState() {
                addButtons.forEach((btn) => {
                    const docType = btn.dataset.docType || '';
                    const allowsMultiple = btn.dataset.multiple === '1';
                    const count = countRowsByDocType(docType);
                    const disabled = !allowsMultiple && count >= 1;
                    btn.disabled = disabled;
                    btn.classList.toggle('disabled', disabled);
                });
            }

            function bindRow(row) {
                const select = row.querySelector('.js-doc-type');

                if (select && select.dataset.bound !== '1') {
                    select.dataset.bound = '1';
                    select.addEventListener('change', function() {
                        updateRowVisibility(row);
                        updateAddButtonsState();
                    });
                }

                updateRowVisibility(row);
            }

            function addRow(docType) {
                if (!rowTemplate || !rowsWrapper) {
                    return;
                }

                let html = rowTemplate.innerHTML
                    .replace(/__INDEX__/g, String(nextIndex))
                    .replace(/__DOC_TYPE__/g, docType);

                const wrap = document.createElement('div');
                wrap.innerHTML = html.trim();
                const row = wrap.firstElementChild;
                if (!row) {
                    return;
                }

                const select = row.querySelector('.js-doc-type');
                if (select && docType) {
                    select.value = docType;
                }

                rowsWrapper.appendChild(row);
                bindRow(row);
                nextIndex += 1;
                updateEmptyState();
                updateAddButtonsState();
            }

            function toggleTipo() {
                const selected = tipo?.value || '';
                if (bieniosCard) {
                    bieniosCard.classList.toggle('d-none', selected !== 'reconocimiento_bienios');
                }
            }

            document.querySelectorAll('.tramite-doc-row').forEach(bindRow);

            rowsWrapper?.addEventListener('click', function(event) {
                const removeBtn = event.target.closest('.js-remove-row');
                if (!removeBtn || !rowsWrapper.contains(removeBtn)) {
                    return;
                }

                event.preventDefault();
                const row = removeBtn.closest('.tramite-doc-row');
                if (row) {
                    row.remove();
                    updateEmptyState();
                    updateAddButtonsState();
                }
            });

            addButtons.forEach((btn) => {
                btn.addEventListener('click', function() {
                    addRow(btn.dataset.docType || '');
                });
            });

            tipo?.addEventListener('change', toggleTipo);
            toggleTipo();
            updateEmptyState();
            updateAddButtonsState();
        });
    </script>
@endpush
