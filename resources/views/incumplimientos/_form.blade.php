@php
    $selectedFuncionarioId = old('reemplazo_personal_id', $item->reemplazo_personal_id ?? null);
    $selectedFuncionarioText = $selectedFuncionarioOption['text'] ?? null;
    $selectedFuncionarioIdForJs = $selectedFuncionarioOption['id'] ?? ($selectedFuncionarioId ? (string) $selectedFuncionarioId : '');
    $selectedFuncionarioTextForJs = $selectedFuncionarioText ?? '';
    $selectedEstablecimientoId = old('establecimiento_id', $selectedEstablecimientoId ?? ($item->establecimiento_id ?? null));
@endphp

<div class="row g-3 incumplimiento-form" id="incumplimiento-form-root"
    data-selected-funcionario-id="{{ $selectedFuncionarioIdForJs }}"
    data-selected-funcionario-text="{{ $selectedFuncionarioTextForJs }}">

    @if ($isAdmin)
        <div class="col-md-6">
            <label class="form-label">Establecimiento <span class="text-danger">*</span></label>
            <select name="establecimiento_id" id="establecimiento_id"
                class="form-select @error('establecimiento_id') is-invalid @enderror">
                <option value="">Selecciona establecimiento</option>
                @foreach ($establecimientos as $establecimiento)
                    <option value="{{ $establecimiento->id }}" @selected((int) $selectedEstablecimientoId === (int) $establecimiento->id)>
                        {{ $establecimiento->nombre_establecimiento }} @if ($establecimiento->rbd)
                            (RBD {{ $establecimiento->rbd }})
                        @endif
                    </option>
                @endforeach
            </select>
            @error('establecimiento_id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    @else
        <input type="hidden" name="establecimiento_id" value="{{ $forcedEstablecimiento?->id }}">
        <div class="col-12">
            <div class="alert alert-info mb-0">
                <div class="fw-semibold">Establecimiento</div>
                <div>{{ $forcedEstablecimiento?->nombre_establecimiento ?: 'Sin establecimiento asociado' }}</div>
                @if ($forcedEstablecimiento?->rbd)
                    <div class="small text-muted">RBD {{ $forcedEstablecimiento->rbd }}</div>
                @endif
            </div>
        </div>
    @endif

    <div class="col-md-6">
        <label class="form-label">Funcionario <span class="text-danger">*</span></label>
        <select name="reemplazo_personal_id" id="reemplazo_personal_id"
            class="form-select @error('reemplazo_personal_id') is-invalid @enderror"
            {{ !$selectedEstablecimientoId && $isAdmin ? 'disabled' : '' }}>
            @if ($selectedFuncionarioIdForJs && $selectedFuncionarioTextForJs)
                <option value="{{ $selectedFuncionarioIdForJs }}" selected>{{ $selectedFuncionarioTextForJs }}</option>
            @endif
        </select>
        <div class="form-text">Busca por RUT o nombre del funcionario del padrón del establecimiento.</div>
        @error('reemplazo_personal_id')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 d-none" id="incumplimiento-fechas-wrap">
        <div class="card border-0 bg-light">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Fecha desde <span class="text-danger">*</span></label>
                        <input type="date" name="fecha_desde" id="fecha_desde"
                            class="form-control @error('fecha_desde') is-invalid @enderror"
                            value="{{ old('fecha_desde', optional($item->fecha_desde)->format('Y-m-d')) }}">
                        @error('fecha_desde')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Fecha hasta <span class="text-danger">*</span></label>
                        <input type="date" name="fecha_hasta" id="fecha_hasta"
                            class="form-control @error('fecha_hasta') is-invalid @enderror"
                            value="{{ old('fecha_hasta', optional($item->fecha_hasta)->format('Y-m-d')) }}"
                            {{ old('fecha_desde', optional($item->fecha_desde)->format('Y-m-d')) ? '' : 'disabled' }}>
                        @error('fecha_hasta')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 d-none" id="incumplimiento-tiempo-wrap">
        <div class="card border-0 bg-light">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Días <span class="text-danger">*</span></label>
                        <input type="number" name="dias" id="dias" min="0" step="1"
                            class="form-control @error('dias') is-invalid @enderror"
                            value="{{ old('dias', $item->dias ?? 0) }}"
                            {{ old('fecha_desde', optional($item->fecha_desde)->format('Y-m-d')) && old('fecha_hasta', optional($item->fecha_hasta)->format('Y-m-d')) ? '' : 'disabled' }}>
                        <div class="form-text">Si ambas fechas son iguales, se sugiere 1 y puedes bajarlo a 0.</div>
                        @error('dias')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Horas <span class="text-danger">*</span></label>
                        <input type="number" name="horas" id="horas" min="0" max="12" step="1"
                            class="form-control @error('horas') is-invalid @enderror"
                            value="{{ old('horas', $item->horas ?? 0) }}"
                            {{ old('fecha_desde', optional($item->fecha_desde)->format('Y-m-d')) && old('fecha_hasta', optional($item->fecha_hasta)->format('Y-m-d')) ? '' : 'disabled' }}>
                        @error('horas')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Minutos <span class="text-danger">*</span></label>
                        <input type="number" name="minutos" id="minutos" min="0" max="60" step="1"
                            class="form-control @error('minutos') is-invalid @enderror"
                            value="{{ old('minutos', $item->minutos ?? 0) }}"
                            {{ old('fecha_desde', optional($item->fecha_desde)->format('Y-m-d')) && old('fecha_hasta', optional($item->fecha_hasta)->format('Y-m-d')) ? '' : 'disabled' }}>
                        @error('minutos')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <style>
        .incumplimiento-form .select2-container .select2-selection--single {
            height: calc(2.25rem + 2px);
            padding: .375rem .75rem;
            border: 1px solid #ced4da;
        }
        .incumplimiento-form .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 1.5;
            padding-left: 0;
        }
        .incumplimiento-form .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: calc(2.25rem + 2px);
        }
    </style>
@endpush

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const $establecimiento = $('#establecimiento_id');
            const $funcionario = $('#reemplazo_personal_id');
            const root = document.getElementById('incumplimiento-form-root');
            const fechasWrap = document.getElementById('incumplimiento-fechas-wrap');
            const tiempoWrap = document.getElementById('incumplimiento-tiempo-wrap');
            const fechaDesde = document.getElementById('fecha_desde');
            const fechaHasta = document.getElementById('fecha_hasta');
            const dias = document.getElementById('dias');
            const horas = document.getElementById('horas');
            const minutos = document.getElementById('minutos');
            const selectedFuncionarioId = root.dataset.selectedFuncionarioId || '';
            const selectedFuncionarioText = root.dataset.selectedFuncionarioText || '';
            const isAdmin = {{ $isAdmin ? 'true' : 'false' }};

            function parseLocalDate(value) {
                if (!value) return null;
                const [y, m, d] = value.split('-').map(Number);
                if (!y || !m || !d) return null;
                return new Date(y, m - 1, d);
            }

            function diffDays(startValue, endValue) {
                const start = parseLocalDate(startValue);
                const end = parseLocalDate(endValue);
                if (!start || !end) return 0;
                const ms = end.setHours(0,0,0,0) - start.setHours(0,0,0,0);
                return Math.max(0, Math.round(ms / 86400000));
            }

            function updateDateState() {
                const hasFuncionario = !!$funcionario.val();
                fechasWrap.classList.toggle('d-none', !hasFuncionario);
                if (!hasFuncionario) {
                    fechaDesde.value = '';
                    fechaHasta.value = '';
                    fechaHasta.disabled = true;
                    return updateDurationState();
                }

                const hasDesde = !!fechaDesde.value;
                fechaHasta.disabled = !hasDesde;
                if (hasDesde) {
                    fechaHasta.min = fechaDesde.value;
                    if (fechaHasta.value && fechaHasta.value < fechaDesde.value) {
                        fechaHasta.value = fechaDesde.value;
                    }
                } else {
                    fechaHasta.removeAttribute('min');
                    fechaHasta.value = '';
                }

                updateDurationState();
            }

            function updateDurationState() {
                const enabled = !!fechaDesde.value && !!fechaHasta.value;
                tiempoWrap.classList.toggle('d-none', !enabled);
                [dias, horas, minutos].forEach(el => {
                    el.disabled = !enabled;
                });

                if (!enabled) {
                    return;
                }

                const delta = diffDays(fechaDesde.value, fechaHasta.value);
                dias.min = delta > 0 ? String(delta) : '0';

                if (delta === 0) {
                    if (dias.value === '') {
                        dias.value = '1';
                    }
                } else if (dias.value === '' || Number(dias.value) < delta) {
                    dias.value = String(delta);
                }

                if (horas.value === '') horas.value = '0';
                if (minutos.value === '') minutos.value = '0';
            }

            function resetFuncionarioSelection() {
                $funcionario.val(null).trigger('change');
                $funcionario.prop('disabled', true);
                updateDateState();
            }

            function currentEstablecimientoId() {
                if (!isAdmin) {
                    return '{{ $forcedEstablecimiento?->id }}';
                }
                return $establecimiento.val() || '';
            }

            function initFuncionarioSelect() {
                $funcionario.select2({
                    width: '100%',
                    placeholder: 'Buscar funcionario...',
                    allowClear: true,
                    ajax: {
                        url: '{{ route('incumplimientos.ajax.funcionarios') }}',
                        delay: 250,
                        dataType: 'json',
                        data: function (params) {
                            return {
                                term: params.term || '',
                                establecimiento_id: currentEstablecimientoId(),
                            };
                        },
                        processResults: function (data) {
                            return data;
                        }
                    },
                    language: {
                        inputTooShort: () => 'Escribe para buscar funcionario',
                        noResults: () => 'Sin resultados',
                        searching: () => 'Buscando...',
                    }
                });

                $funcionario.on('change', function () {
                    updateDateState();
                });
            }

            if (isAdmin && $establecimiento.length) {
                $establecimiento.select2({
                    width: '100%',
                    placeholder: 'Buscar establecimiento',
                    allowClear: true,
                });

                $establecimiento.on('change', function () {
                    resetFuncionarioSelection();
                    if ($establecimiento.val()) {
                        $funcionario.prop('disabled', false);
                    }
                });
            }

            initFuncionarioSelect();

            if (!isAdmin || currentEstablecimientoId()) {
                $funcionario.prop('disabled', false);
            }

            if (selectedFuncionarioId && selectedFuncionarioText && $funcionario.find('option[value="' + selectedFuncionarioId + '"]').length === 0) {
                const option = new Option(selectedFuncionarioText, selectedFuncionarioId, true, true);
                $funcionario.append(option).trigger('change');
            }

            fechaDesde.addEventListener('change', updateDateState);
            fechaHasta.addEventListener('change', updateDateState);
            [dias, horas, minutos].forEach(function (input) {
                input.addEventListener('input', function () {
                    const raw = String(input.value || '').replace(/[^\d]/g, '');
                    input.value = raw === '' ? '' : String(parseInt(raw, 10));
                    if (input.value === 'NaN') input.value = '';
                });
            });

            updateDateState();
        });
    </script>
@endpush
