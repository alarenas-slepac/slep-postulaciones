@php
    $displayTimezone = $displayTimezone ?? config('app.display_timezone', 'America/Santiago');
    $canEditCalculoPeriodos = $canEditCalculoPeriodos ?? false;
    $canGenerateRex = $canGenerateRex ?? false;
    $periodBlocks = $tramite->calculo_periodos_blocks_collection;
    $summary = $tramite->calculo_periodos_resumen;
    $defaultRecognitionDate = old('fecha_reconocimiento', optional($tramite->rex_fecha_reconocimiento)->format('Y-m-d') ?: optional($tramite->enviado_at)->format('Y-m-d') ?: now()->format('Y-m-d'));
    $approvedDocuments = $tramite->documentos->filter(fn ($documento) => (string) $documento->estado_revision === 'aprobado')->values();

    $formatDurationText = function (array $duration): string {
        $years = (int) data_get($duration, 'years', 0);
        $months = (int) data_get($duration, 'months', 0);
        $days = (int) data_get($duration, 'days', 0);

        return sprintf('%d año%s, %d mes%s y %d día%s',
            $years,
            $years === 1 ? '' : 's',
            $months,
            $months === 1 ? '' : 'es',
            $days,
            $days === 1 ? '' : 's'
        );
    };

    $formatDate = function ($value): string {
        if (empty($value)) {
            return '—';
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('d-m-Y');
        } catch (\Throwable $e) {
            return '—';
        }
    };
@endphp

<div class="d-flex flex-column gap-3">
    <div class="card shadow-sm border-0">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span>Cálculo de períodos confirmados</span>
            <span class="badge text-bg-primary">{{ $summary['total_periodos'] ?? 0 }} período(s)</span>
        </div>
        <div class="card-body d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
            <div class="alert alert-info mb-0 flex-grow-1">
                <div class="fw-semibold">Validación manual de períodos</div>
                <div class="small mb-0">La captura automática/OCR fue desactivada. Al aprobar un documento, sus fechas declaradas quedan disponibles aquí para confirmar, modificar o complementar manualmente. El cálculo de bienios se basa sólo en los períodos guardados en esta pestaña.</div>
            </div>
            @if ($canGenerateRex && $periodBlocks->where(fn ($block) => count((array) data_get($block, 'periodos', [])) > 0)->isNotEmpty())
                <form method="POST" action="{{ route('tramites.resolucion.upload-pdf', $tramite) }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-sm-8 col-lg-9">
                        <label for="resolucion_pdf_calculo" class="form-label small mb-1">REX firmada en PDF</label>
                        <input type="file" name="resolucion_pdf" id="resolucion_pdf_calculo" class="form-control form-control-sm @error('resolucion_pdf') is-invalid @enderror" accept="application/pdf,.pdf" required>
                        <div class="form-text small">Carga directa de la REX firmada por GDP. Máximo 10 MB.</div>
                        @error('resolucion_pdf')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-sm-4 col-lg-3 d-grid">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-upload"></i> Cargar REX Firmada
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    @if ($canEditCalculoPeriodos && $approvedDocuments->isNotEmpty())
        <div class="card shadow-sm border-0">
            <div class="card-header fw-semibold">Documentos aprobados disponibles para cálculo</div>
            <div class="card-body d-flex flex-column gap-3">
                <div class="small text-muted">Confirma las fechas declaradas, modifícalas si corresponde o agrega tramos adicionales para el mismo documento. Los documentos obligatorios que no acreditan período pueden quedar sin guardar fechas.</div>

                @foreach ($approvedDocuments as $documento)
                    @php
                        $block = $periodBlocks->first(fn ($item) => (int) data_get($item, 'documento_id') === (int) $documento->id);
                        $existingPeriods = collect((array) data_get($block, 'periodos', []))->filter(fn ($item) => is_array($item))->values();
                        if ($existingPeriods->isEmpty() && $documento->fecha_inicio && $documento->fecha_termino) {
                            $existingPeriods = collect([[
                                'inicio' => $documento->fecha_inicio->format('Y-m-d'),
                                'termino' => $documento->fecha_termino->format('Y-m-d'),
                                'referencia' => 'Período declarado por el solicitante',
                            ]]);
                        }
                        $rows = $existingPeriods->values()->all();
                        $minimumRows = max(count($rows) + 3, 3);
                    @endphp
                    <div class="border rounded p-3 bg-light-subtle">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                            <div>
                                <div class="fw-semibold">{{ $documento->tipo_documento_label }}</div>
                                <div class="small text-muted">{{ $documento->original_name ?: basename((string) $documento->path) }}</div>
                                <div class="small text-muted">Período declarado: {{ $documento->fecha_inicio ? $documento->fecha_inicio->format('d-m-Y') : '—' }} al {{ $documento->fecha_termino ? $documento->fecha_termino->format('d-m-Y') : '—' }}</div>
                            </div>
                            <span class="badge {{ count((array) data_get($block, 'periodos', [])) > 0 ? 'text-bg-success' : 'text-bg-warning' }}">
                                {{ count((array) data_get($block, 'periodos', [])) > 0 ? 'Con períodos guardados' : 'Pendiente de confirmar fechas' }}
                            </span>
                        </div>

                        @if ($errors->has('periodos_documento_' . $documento->id))
                            <div class="alert alert-danger py-2">{{ $errors->first('periodos_documento_' . $documento->id) }}</div>
                        @endif

                        <form method="POST" action="{{ route('tramites.calculo-periodos.documento.store', [$tramite, $documento]) }}">
                            @csrf
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-2">
                                    <thead>
                                        <tr>
                                            <th style="width: 50px;">#</th>
                                            <th style="width: 180px;">Fecha inicio</th>
                                            <th style="width: 180px;">Fecha término</th>
                                            <th>Referencia / observación</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @for ($i = 0; $i < $minimumRows; $i++)
                                            @php
                                                $row = (array) ($rows[$i] ?? []);
                                            @endphp
                                            <tr>
                                                <td>{{ $i + 1 }}</td>
                                                <td>
                                                    <input type="date" name="periodos[{{ $i }}][inicio]" class="form-control form-control-sm" value="{{ data_get($row, 'inicio', '') }}">
                                                </td>
                                                <td>
                                                    <input type="date" name="periodos[{{ $i }}][termino]" class="form-control form-control-sm" value="{{ data_get($row, 'termino', '') }}">
                                                </td>
                                                <td>
                                                    <input type="text" name="periodos[{{ $i }}][referencia]" class="form-control form-control-sm" maxlength="160" value="{{ data_get($row, 'referencia', '') }}" placeholder="Ej. tramo principal, período adicional, ajuste manual">
                                                </td>
                                            </tr>
                                        @endfor
                                    </tbody>
                                </table>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="bi bi-check2-circle"></i> Guardar fechas del documento
                                </button>
                            </div>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-header fw-semibold">Cálculo de bienios</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6 col-xl-3">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">Total de días acumulados</div>
                        <div class="fw-semibold fs-5">{{ number_format((int) ($summary['total_dias'] ?? 0), 0, ',', '.') }}</div>
                        <div class="small text-muted">Suma de días de todos los períodos confirmados/manuales.</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">Equivalencia acumulada</div>
                        <div class="fw-semibold">{{ $formatDurationText((array) ($summary['duracion'] ?? [])) }}</div>
                        <div class="small text-muted">Expresado como años, meses y días.</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">Bienios cumplidos</div>
                        <div class="fw-semibold fs-5">{{ (int) ($summary['bienios'] ?? 0) }}</div>
                        <div class="small text-muted">Cada 2 años completos equivalen a 1 bienio. Máximo: {{ (int) ($summary['max_bienios'] ?? 15) }} bienios.</div>
                        @if (!empty($summary['bienios_topados']))
                            <div class="small text-danger mt-1">Se aplicó tope máximo de 15 bienios (30 años).</div>
                        @endif
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">Tiempo para el siguiente bienio</div>
                        @if ((int) ($summary['bienios'] ?? 0) >= (int) ($summary['max_bienios'] ?? 15))
                            <div class="fw-semibold">Tope máximo alcanzado</div>
                            <div class="small text-muted">No corresponde calcular bienios adicionales.</div>
                        @else
                            <div class="fw-semibold">{{ $formatDurationText((array) ($summary['duracion_para_siguiente_bienio'] ?? [])) }}</div>
                            <div class="small text-muted">Faltan {{ number_format((int) ($summary['dias_para_siguiente_bienio'] ?? 0), 0, ',', '.') }} día(s) para completar el bienio {{ (int) ($summary['siguiente_bienio'] ?? 1) }}.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($canEditCalculoPeriodos)
        <div class="card shadow-sm border-0">
            <div class="card-header fw-semibold">Ingresar período manual sin documento específico</div>
            <div class="card-body">
                <form method="POST" action="{{ route('tramites.calculo-periodos.manual.store', $tramite) }}" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-md-3">
                        <label for="manual_fecha_inicio" class="form-label">Fecha inicio</label>
                        <input type="date" name="manual_fecha_inicio" id="manual_fecha_inicio" class="form-control @error('manual_fecha_inicio') is-invalid @enderror" value="{{ old('manual_fecha_inicio') }}" required>
                        @error('manual_fecha_inicio')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label for="manual_fecha_termino" class="form-label">Fecha término</label>
                        <input type="date" name="manual_fecha_termino" id="manual_fecha_termino" class="form-control @error('manual_fecha_termino') is-invalid @enderror" value="{{ old('manual_fecha_termino') }}" required>
                        @error('manual_fecha_termino')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="manual_referencia" class="form-label">Referencia opcional</label>
                        <input type="text" name="manual_referencia" id="manual_referencia" class="form-control @error('manual_referencia') is-invalid @enderror" value="{{ old('manual_referencia') }}" maxlength="160" placeholder="Ej. Ajuste manual / tramo complementario">
                        @error('manual_referencia')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Agregar período
                        </button>
                    </div>
                </form>
                <div class="small text-muted mt-2">Usa este formulario sólo cuando el período no deba quedar asociado a un documento específico.</div>
            </div>
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-header fw-semibold">Períodos consolidados por bloque</div>
        <div class="card-body">
            @if ($periodBlocks->isEmpty())
                <div class="text-center text-muted py-4">Aprueba un documento para habilitar la confirmación manual de períodos.</div>
            @else
                <div class="d-flex flex-column gap-3">
                    @foreach ($periodBlocks as $block)
                        @php
                            $blockIndex = $loop->index;
                            $periods = collect(data_get($block, 'periodos', []));
                            $confirmedAt = data_get($block, 'confirmed_at');
                            $captureExecutedAt = data_get($block, 'captura_ejecutada_at');
                        @endphp
                        <div class="border rounded p-3 bg-white shadow-sm">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                                <div>
                                    <div class="fw-semibold">{{ data_get($block, 'documento_label', 'Documento') }}</div>
                                    <div class="small text-muted">{{ data_get($block, 'documento_nombre', '—') }}</div>
                                </div>
                                <div class="small text-muted text-md-end">
                                    <div><span class="fw-semibold">Origen:</span> {{ data_get($block, 'captura_metodo', '—') }}</div>
                                    <div><span class="fw-semibold">Registrado por:</span> {{ data_get($block, 'confirmed_by_name', '—') }}</div>
                                    <div><span class="fw-semibold">Confirmado/guardado:</span> {{ $confirmedAt ? \Illuminate\Support\Carbon::parse($confirmedAt)->timezone($displayTimezone)->format('d-m-Y H:i') : '—' }}</div>
                                    <div><span class="fw-semibold">Última actualización:</span> {{ $captureExecutedAt ? \Illuminate\Support\Carbon::parse($captureExecutedAt)->timezone($displayTimezone)->format('d-m-Y H:i') : '—' }}</div>
                                </div>
                            </div>

                            @if ($periods->isEmpty())
                                <div class="alert alert-warning py-2 mb-0">Documento aprobado pendiente de completar períodos para que aporte al cálculo.</div>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Inicio</th>
                                                <th>Término</th>
                                                <th>Días</th>
                                                <th>Referencia</th>
                                                @if ($canEditCalculoPeriodos)
                                                    <th class="text-end">Acciones</th>
                                                @endif
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($periods as $period)
                                                @php
                                                    $periodIndex = $loop->index;
                                                    $days = \App\Models\Tramite::calculatePeriodDays(data_get($period, 'inicio'), data_get($period, 'termino'));
                                                    $reference = trim((string) data_get($period, 'referencia', '')) ?: '—';
                                                @endphp
                                                <tr>
                                                    <td>{{ $loop->iteration }}</td>
                                                    <td>{{ $formatDate(data_get($period, 'inicio')) }}</td>
                                                    <td>{{ $formatDate(data_get($period, 'termino')) }}</td>
                                                    <td>{{ $days !== null ? number_format($days, 0, ',', '.') : '—' }}</td>
                                                    <td>{{ $reference }}</td>
                                                    @if ($canEditCalculoPeriodos)
                                                        <td class="text-end">
                                                            <form method="POST" action="{{ route('tramites.calculo-periodos.periodo.destroy', [$tramite, $blockIndex, $periodIndex]) }}" onsubmit="return confirm('¿Eliminar este período del cálculo? Esta acción no eliminará el documento aprobado.');" class="d-inline">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                    <i class="bi bi-trash"></i> Eliminar período
                                                                </button>
                                                            </form>
                                                        </td>
                                                    @endif
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
