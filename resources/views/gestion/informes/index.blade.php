@extends('layouts.app')

@push('styles')
    <style>
        .report-card {
            border: 0;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1.25rem rgba(15, 23, 42, 0.08);
        }

        .report-empty {
            min-height: 220px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: #6c757d;
        }

        .report-chip {
            border-radius: 999px;
        }

        .report-checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 0.75rem 1rem;
        }

        .report-filter-box {
            border: 1px solid #e9ecef;
            border-radius: 1rem;
            background: #f8fafc;
            padding: 1rem;
        }

        .report-summary-card {
            border: 1px solid #e9ecef;
            border-radius: 1rem;
            background: #fff;
            padding: 1rem;
            min-height: 100%;
        }

        .report-summary-value {
            font-size: 1.35rem;
            font-weight: 700;
            line-height: 1.1;
        }
    </style>
@endpush

@section('content')
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h4 mb-1">Informes de planillas</h1>
            <p class="text-muted mb-0">Consulta y exportación de informes de solicitudes de reemplazo por tipo de planilla.</p>
        </div>
        <span class="badge text-bg-primary-subtle border border-primary-subtle text-primary-emphasis px-3 py-2 report-chip">
            Informes disponibles: Planilla BRP / Reemplazo Maternidad (DIPRES) / Matriz S DIPRES / Matriz C DIPRES
        </span>
    </div>

    <div class="card report-card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('gestion.informes.index') }}" class="row g-3 align-items-end">
                <input type="hidden" name="buscar" value="1">

                <div class="col-12 col-lg-4">
                    <label for="tipo_planilla" class="form-label fw-semibold">Tipo de planilla</label>
                    <select name="tipo_planilla" id="tipo_planilla" class="form-select @error('tipo_planilla') is-invalid @enderror" required>
                        @foreach ($tiposPlanilla as $value => $label)
                            <option value="{{ $value }}" @selected(($filtros['tipo_planilla'] ?? 'brp') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('tipo_planilla')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 col-md-6 col-lg-3" id="fecha-inicio-wrapper" @if ($isMatrizDipres) hidden @endif>
                    <label for="fecha_inicio" class="form-label fw-semibold">Fecha de inicio</label>
                    <input type="date" name="fecha_inicio" id="fecha_inicio" value="{{ $filtros['fecha_inicio'] ?? '' }}"
                        class="form-control @error('fecha_inicio') is-invalid @enderror" @unless($isMatrizDipres) required @endunless>
                    @error('fecha_inicio')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 col-md-6 col-lg-3" id="fecha-termino-wrapper" @if ($isMatrizDipres) hidden @endif>
                    <label for="fecha_termino" class="form-label fw-semibold">Fecha de término</label>
                    <input type="date" name="fecha_termino" id="fecha_termino" value="{{ $filtros['fecha_termino'] ?? '' }}"
                        class="form-control @error('fecha_termino') is-invalid @enderror" @unless($isMatrizDipres) required @endunless>
                    @error('fecha_termino')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>


                <div class="col-12 col-md-6 col-lg-3" id="matriz-s-trimestre-wrapper" @unless ($isMatrizDipres) hidden @endunless>
                    <label for="matriz_s_trimestre" class="form-label fw-semibold">Trimestre matriz DIPRES</label>
                    <select name="matriz_s_trimestre" id="matriz_s_trimestre" class="form-select @error('matriz_s_trimestre') is-invalid @enderror" @if($isMatrizDipres) required @endif>
                        @foreach ($opcionesTrimestres as $value => $label)
                            <option value="{{ $value }}" @selected((string) ($filtros['matriz_s_trimestre'] ?? '1') === (string) $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('matriz_s_trimestre')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 col-md-6 col-lg-2" id="matriz-s-anio-wrapper" @unless ($isMatrizDipres) hidden @endunless>
                    <label for="matriz_s_anio" class="form-label fw-semibold">Año</label>
                    <input type="number" min="2020" max="2100" name="matriz_s_anio" id="matriz_s_anio" value="{{ $filtros['matriz_s_anio'] ?? 2026 }}"
                        class="form-control @error('matriz_s_anio') is-invalid @enderror" @if($isMatrizDipres) required @endif>
                    @error('matriz_s_anio')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 col-lg-3" id="matriz-s-rango-wrapper" @unless ($isMatrizDipres) hidden @endunless>
                    <div class="report-filter-box py-2 px-3 h-100">
                        <div class="small text-muted fw-semibold">Rango de corte</div>
                        <div class="fw-semibold" id="matriz-s-rango-text">
                            {{ isset($matrizSRango['inicio'], $matrizSRango['termino']) ? \Illuminate\Support\Carbon::parse($matrizSRango['inicio'])->format('d-m-Y') . ' a ' . \Illuminate\Support\Carbon::parse($matrizSRango['termino'])->format('d-m-Y') : '—' }}
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="bi bi-search"></i> Buscar
                    </button>
                    <a href="{{ route('gestion.informes.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>

                <div class="col-12" id="tipos-reemplazo-wrapper" @if (!$isDipres) hidden @endif>
                    <div class="report-filter-box mt-1">
                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-2 mb-3">
                            <div>
                                <label class="form-label fw-semibold mb-1">Filtrar por tipo de reemplazo</label>
                                <div class="small text-muted">Selecciona uno o más tipos de reemplazo para el informe DIPRES.</div>
                            </div>
                            @if ($isDipres)
                                <div class="small text-muted align-self-lg-center">{{ count($filtros['tipos_reemplazo'] ?? []) }} seleccionado(s)</div>
                            @endif
                        </div>
                        <div class="report-checkbox-grid">
                            @foreach ($opcionesTiposReemplazo as $tipo)
                                <div class="form-check">
                                    <input class="form-check-input @error('tipos_reemplazo') is-invalid @enderror"
                                        type="checkbox"
                                        name="tipos_reemplazo[]"
                                        value="{{ $tipo }}"
                                        id="tipo_reemplazo_{{ $loop->index }}"
                                        @checked(in_array($tipo, $filtros['tipos_reemplazo'] ?? [], true))>
                                    <label class="form-check-label" for="tipo_reemplazo_{{ $loop->index }}">{{ $tipo }}</label>
                                </div>
                            @endforeach
                        </div>
                        @error('tipos_reemplazo')
                            <div class="text-danger small mt-2">{{ $message }}</div>
                        @enderror
                        @error('tipos_reemplazo.*')
                            <div class="text-danger small mt-2">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card report-card">
        <div class="card-header bg-white border-0 pt-4 px-4 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <h2 class="h5 mb-1">Resultados de búsqueda</h2>
                <div class="text-muted small">
                    <strong>{{ $selectedPlanillaLabel }}</strong>.<br>
                    @if ($isMatrizS)
                        Consulta de continuidades habilitada. Rango calculado automáticamente por trimestre y año: <strong>{{ $matrizSRango['inicio'] ?? '' }}</strong> a <strong>{{ $matrizSRango['termino'] ?? '' }}</strong>.
                        <br>Se muestran las cadenas de reemplazo que tienen vigencia dentro del trimestre seleccionado y pueden exportarse en formato Excel Matriz S DIPRES.
                    @elseif ($isMatrizC)
                        Continuidades de cese habilitadas. Rango calculado automáticamente por trimestre y año: <strong>{{ $matrizSRango['inicio'] ?? '' }}</strong> a <strong>{{ $matrizSRango['termino'] ?? '' }}</strong>.
                        <br>Se incluyen ceses que terminan antes del cierre del trimestre y ceses del último día del trimestre anterior sin continuidad posterior.
                    @else
                        Solicitudes con estado <code>aceptada</code> o <code>cerrado</code>, cuyo funcionario titular corresponde a <strong>docente</strong> según <code>estatuto</code>, y cuya <code>fecha_inicio_trabajo</code> y <code>fecha_termino</code> quedan dentro del rango consultado.
                        @if ($isDipres)
                            <br>Además, se filtran por los <strong>tipos de reemplazo</strong> seleccionados.
                        @endif
                    @endif
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                @if ($searched)
                    <span class="badge text-bg-light border px-3 py-2">{{ number_format($totalRows, 0, ',', '.') }} registro(s)</span>
                @endif

                @if ($searched && $rows->isNotEmpty())
                    @if ($isMatrizS)
                        <a href="{{ route('gestion.informes.export', ['tipo_planilla' => $filtros['tipo_planilla'], 'matriz_s_trimestre' => $filtros['matriz_s_trimestre'] ?? '1', 'matriz_s_anio' => $filtros['matriz_s_anio'] ?? 2026]) }}"
                            class="btn btn-success">
                            <i class="bi bi-file-earmark-excel"></i> Exportar Matriz S DIPRES
                        </a>
                    @elseif ($isMatrizC)
                        <a href="{{ route('gestion.informes.export', ['tipo_planilla' => $filtros['tipo_planilla'], 'matriz_s_trimestre' => $filtros['matriz_s_trimestre'] ?? '1', 'matriz_s_anio' => $filtros['matriz_s_anio'] ?? 2026]) }}"
                            class="btn btn-success">
                            <i class="bi bi-file-earmark-excel"></i> Exportar Matriz C DIPRES
                        </a>
                    @else
                        <a href="{{ route('gestion.informes.export', ['tipo_planilla' => $filtros['tipo_planilla'], 'fecha_inicio' => $filtros['fecha_inicio'], 'fecha_termino' => $filtros['fecha_termino'], 'tipos_reemplazo' => $filtros['tipos_reemplazo'] ?? []]) }}"
                            class="btn btn-success">
                            <i class="bi bi-file-earmark-excel"></i> Exportar informe
                        </a>
                    @endif
                @endif
            </div>
        </div>
        <div class="card-body pt-0 px-4 pb-4">
            @if (!$searched)
                <div class="report-empty">
                    <div>
                        <div class="fs-2 mb-2"><i class="bi bi-table"></i></div>
                        <div class="fw-semibold mb-1">Ingresa el rango de fechas para consultar un informe</div>
                        <div class="small text-muted">Puedes usar Planilla BRP, Informe Reemplazo Maternidad (DIPRES), Informe Matriz S DIPRES o Informe Matriz C DIPRES seleccionando trimestre y año.</div>
                    </div>
                </div>
            @elseif ($isMatrizDipres)
                @if ($isMatrizC)
                    @if ($rows->isEmpty())
                        <div class="alert alert-secondary mb-0">
                            No existen ceses de reemplazo que cumplan las reglas de Matriz C DIPRES para el trimestre seleccionado.
                        </div>
                    @else
                        @php
                            $matrizCTotalSolicitudes = $rows->sum(fn ($row) => (int) ($row['cantidad_solicitudes'] ?? 0));
                            $matrizCConContinuidad = $rows->filter(fn ($row) => (int) ($row['cantidad_solicitudes'] ?? 0) > 1)->count();
                            $matrizCConAlertas = $rows->filter(fn ($row) => !empty($row['tiene_datos_faltantes']))->count();
                            $matrizCCesoDentroTrimestre = $rows->filter(fn ($row) => str_contains((string) ($row['motivo_cese_matriz_c'] ?? ''), 'dentro del trimestre'))->count();
                            $matrizCCesoTrimestreAnterior = $rows->count() - $matrizCCesoDentroTrimestre;
                        @endphp

                        <div class="alert alert-info mb-3">
                            <div class="fw-semibold mb-1">Consulta de continuidad Matriz C DIPRES</div>
                            <div>Trimestre seleccionado: <strong>{{ $opcionesTrimestres[$filtros['matriz_s_trimestre'] ?? '1'] ?? '1er Trimestre' }}</strong>.</div>
                            <div>Año: <strong>{{ $filtros['matriz_s_anio'] ?? 2026 }}</strong>.</div>
                            <div>Rango trimestral: <strong>{{ $matrizSRango['inicio'] ?? '' }}</strong> a <strong>{{ $matrizSRango['termino'] ?? '' }}</strong>.</div>
                            <div>Día anterior al cierre trimestral: <strong>{{ $matrizCRangosCese['dia_anterior_termino_trimestre'] ?? '' }}</strong>.</div>
                            <div>Último día trimestre anterior: <strong>{{ $matrizCRangosCese['ultimo_dia_trimestre_anterior'] ?? '' }}</strong>.</div>
                            <div>Registros con datos faltantes: <strong>{{ number_format($matrizCConAlertas, 0, ',', '.') }}</strong>.</div>
                            <div class="small mt-2 mb-0">Se incluyen ceses ocurridos antes del cierre trimestral y ceses del último día del trimestre anterior sin continuidad posterior.</div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-6 col-xl-3">
                                <div class="report-summary-card">
                                    <div class="small text-muted fw-semibold">Cadenas de cese</div>
                                    <div class="report-summary-value">{{ number_format($rows->count(), 0, ',', '.') }}</div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-xl-3">
                                <div class="report-summary-card">
                                    <div class="small text-muted fw-semibold">Solicitudes asociadas</div>
                                    <div class="report-summary-value">{{ number_format($matrizCTotalSolicitudes, 0, ',', '.') }}</div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-xl-3">
                                <div class="report-summary-card">
                                    <div class="small text-muted fw-semibold">Cese dentro trimestre</div>
                                    <div class="report-summary-value">{{ number_format($matrizCCesoDentroTrimestre, 0, ',', '.') }}</div>
                                    <div class="small text-muted">Terminan antes del cierre.</div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-xl-3">
                                <div class="report-summary-card">
                                    <div class="small text-muted fw-semibold">Cese trimestre anterior</div>
                                    <div class="report-summary-value">{{ number_format($matrizCCesoTrimestreAnterior, 0, ',', '.') }}</div>
                                    <div class="small text-muted">Sin continuidad posterior.</div>
                                </div>
                            </div>
                        </div>

                        @if ($matrizCConAlertas > 0)
                            <div class="alert alert-warning mb-3">
                                Existen {{ number_format($matrizCConAlertas, 0, ',', '.') }} cadena(s) con datos personales o laborales faltantes. El detalle queda disponible en la columna <strong>OBSERVACIONES</strong>.
                            </div>
                        @endif

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Solicitud final</th>
                                        <th>Solicitudes asociadas</th>
                                        <th>RUT reemplazante</th>
                                        <th>Reemplazante</th>
                                        <th>RUT titular</th>
                                        <th>Titular</th>
                                        <th>Comuna</th>
                                        <th>Establecimiento</th>
                                        <th>Causal alejamiento</th>
                                        <th>Inicio continuidad</th>
                                        <th>Término continuidad</th>
                                        <th>Fecha alejamiento</th>
                                        <th>Jornada</th>
                                        <th>Regla aplicada</th>
                                        <th>Observaciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $index => $row)
                                        <tr>
                                            <td class="fw-semibold">{{ $index + 1 }}</td>
                                            <td>{{ $row['solicitud_final_id'] ?? '—' }}</td>
                                            <td>
                                                <span class="badge text-bg-light border">{{ $row['cantidad_solicitudes'] ?? 0 }}</span>
                                                <div class="small text-muted">{{ implode(', ', $row['solicitudes_ids'] ?? []) }}</div>
                                            </td>
                                            <td>{{ ($row['rut_reemplazo'] ?? '') !== '' ? $row['rut_reemplazo'] : '—' }}</td>
                                            <td>{{ ($row['nombre_completo_reemplazo'] ?? '') !== '' ? $row['nombre_completo_reemplazo'] : '—' }}</td>
                                            <td>{{ ($row['rut_titular'] ?? '') !== '' ? $row['rut_titular'] : '—' }}</td>
                                            <td>{{ ($row['nombre_titular'] ?? '') !== '' ? $row['nombre_titular'] : '—' }}</td>
                                            <td>{{ ($row['comuna'] ?? '') !== '' ? $row['comuna'] : '—' }}</td>
                                            <td>{{ ($row['nombre_establecimiento'] ?? '') !== '' ? $row['nombre_establecimiento'] : '—' }}</td>
                                            <td>{{ ($row['causal_alejamiento'] ?? '') !== '' ? $row['causal_alejamiento'] : '—' }}</td>
                                            <td>{{ $row['fecha_inicio_cadena'] ?? '—' }}</td>
                                            <td>{{ $row['fecha_termino_cadena'] ?? '—' }}</td>
                                            <td>{{ $row['fecha_alejamiento'] ?? '—' }}</td>
                                            <td>{{ ($row['jornada'] ?? '') !== '' ? $row['jornada'] : '0' }}</td>
                                            <td class="small">{{ $row['motivo_cese_matriz_c'] ?? '—' }}</td>
                                            <td class="small text-muted">
                                                @if (!empty($row['tiene_datos_faltantes']))
                                                    <span class="badge text-bg-warning mb-1">Datos faltantes</span>
                                                @endif
                                                <div>{{ $row['observaciones'] ?? '—' }}</div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @elseif ($rows->isEmpty())
                    <div class="alert alert-secondary mb-0">
                        No existen cadenas de reemplazo con vigencia dentro del trimestre seleccionado.
                    </div>
                @else
                    @php
                        $matrizSTotalSolicitudes = $rows->sum(fn ($row) => (int) ($row['cantidad_solicitudes'] ?? 0));
                        $matrizSConContinuidad = $rows->filter(fn ($row) => (int) ($row['cantidad_solicitudes'] ?? 0) > 1)->count();
                        $matrizSConAlertas = $rows->filter(fn ($row) => !empty($row['tiene_datos_faltantes']))->count();
                    @endphp

                    <div class="alert alert-info mb-3">
                        <div class="fw-semibold mb-1">Consulta de continuidad Matriz S DIPRES</div>
                        <div>Trimestre seleccionado: <strong>{{ $opcionesTrimestres[$filtros['matriz_s_trimestre'] ?? '1'] ?? '1er Trimestre' }}</strong>.</div>
                        <div>Año: <strong>{{ $filtros['matriz_s_anio'] ?? 2026 }}</strong>.</div>
                        <div>Rango calculado: <strong>{{ $matrizSRango['inicio'] ?? '' }}</strong> a <strong>{{ $matrizSRango['termino'] ?? '' }}</strong>.</div>
                        <div class="small mt-2 mb-0">Cada fila corresponde a una cadena de continuidad. El Excel se exporta con las columnas oficiales de Matriz S DIPRES.</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6 col-xl-3">
                            <div class="report-summary-card">
                                <div class="small text-muted fw-semibold">Cadenas encontradas</div>
                                <div class="report-summary-value">{{ number_format($rows->count(), 0, ',', '.') }}</div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-xl-3">
                            <div class="report-summary-card">
                                <div class="small text-muted fw-semibold">Solicitudes asociadas</div>
                                <div class="report-summary-value">{{ number_format($matrizSTotalSolicitudes, 0, ',', '.') }}</div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-xl-3">
                            <div class="report-summary-card">
                                <div class="small text-muted fw-semibold">Con continuidad</div>
                                <div class="report-summary-value">{{ number_format($matrizSConContinuidad, 0, ',', '.') }}</div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-xl-3">
                            <div class="report-summary-card">
                                <div class="small text-muted fw-semibold">Con datos faltantes</div>
                                <div class="report-summary-value">{{ number_format($matrizSConAlertas, 0, ',', '.') }}</div>
                                <div class="small text-muted">Se informan en observaciones.</div>
                            </div>
                        </div>
                    </div>

                    @if ($matrizSConAlertas > 0)
                        <div class="alert alert-warning mb-3">
                            Existen {{ number_format($matrizSConAlertas, 0, ',', '.') }} cadena(s) con datos personales o laborales faltantes. El Excel se generará igualmente y dejará el detalle en la columna <strong>OBSERVACIONES</strong>.
                        </div>
                    @endif
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Solicitud final</th>
                                    <th>Solicitudes asociadas</th>
                                    <th>RUT reemplazante</th>
                                    <th>Reemplazante</th>
                                    <th>RUT titular</th>
                                    <th>Titular</th>
                                    <th>Comuna</th>
                                    <th>Establecimiento</th>
                                    <th>Tipo reemplazo</th>
                                    <th>Inicio continuidad</th>
                                    <th>Término continuidad</th>
                                    <th>Jornada última solicitud</th>
                                    <th>Observaciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $index => $row)
                                    <tr>
                                        <td class="fw-semibold">{{ $index + 1 }}</td>
                                        <td>{{ $row['solicitud_final_id'] ?? '—' }}</td>
                                        <td>
                                            <span class="badge text-bg-light border">{{ $row['cantidad_solicitudes'] ?? 0 }}</span>
                                            <div class="small text-muted">{{ implode(', ', $row['solicitudes_ids'] ?? []) }}</div>
                                        </td>
                                        <td>{{ ($row['rut_reemplazo'] ?? '') !== '' ? $row['rut_reemplazo'] : '—' }}</td>
                                        <td>{{ ($row['nombre_completo_reemplazo'] ?? '') !== '' ? $row['nombre_completo_reemplazo'] : '—' }}</td>
                                        <td>{{ ($row['rut_titular'] ?? '') !== '' ? $row['rut_titular'] : '—' }}</td>
                                        <td>{{ ($row['nombre_titular'] ?? '') !== '' ? $row['nombre_titular'] : '—' }}</td>
                                        <td>{{ ($row['comuna'] ?? '') !== '' ? $row['comuna'] : '—' }}</td>
                                        <td>{{ ($row['nombre_establecimiento'] ?? '') !== '' ? $row['nombre_establecimiento'] : '—' }}</td>
                                        <td>{{ ($row['tipo_reemplazo'] ?? '') !== '' ? $row['tipo_reemplazo'] : '—' }}</td>
                                        <td>{{ $row['fecha_inicio_cadena'] ?? '—' }}</td>
                                        <td>{{ $row['fecha_termino_cadena'] ?? '—' }}</td>
                                        <td>{{ ($row['jornada'] ?? '') !== '' ? $row['jornada'] : '0,00' }}</td>
                                        <td class="small text-muted">
                                            @if (!empty($row['tiene_datos_faltantes']))
                                                <span class="badge text-bg-warning mb-1">Datos faltantes</span>
                                            @endif
                                            <div>{{ $row['observaciones'] ?? '—' }}</div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @elseif ($rows->isEmpty())
                <div class="alert alert-secondary mb-0">No existen solicitudes de funcionarios docentes que cumplan el criterio seleccionado.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>RUT reemplazo</th>
                                <th>Nombre completo reemplazo</th>
                                <th>RBD</th>
                                <th>Establecimiento</th>
                                <th>RUT funcionario a reemplazar</th>
                                <th>Funcionario a reemplazar</th>
                                @if ($isDipres)
                                    <th>Tipo reemplazo</th>
                                @endif
                                <th>Inicio trabajo</th>
                                <th>Término</th>
                                @unless ($isDipres)
                                    <th>Horas efectivamente reemplazadas</th>
                                @endunless
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $index => $row)
                                <tr>
                                    <td class="fw-semibold">{{ $index + 1 }}</td>
                                    <td>{{ $row['rut_reemplazo'] !== '' ? $row['rut_reemplazo'] : '—' }}</td>
                                    <td>{{ $row['nombre_completo_reemplazo'] !== '' ? $row['nombre_completo_reemplazo'] : '—' }}</td>
                                    <td>{{ $row['rbd_establecimiento'] !== '' ? $row['rbd_establecimiento'] : '—' }}</td>
                                    <td>{{ $row['nombre_establecimiento'] !== '' ? $row['nombre_establecimiento'] : '—' }}</td>
                                    <td>{{ $row['rut_funcionario_a_reemplazar'] !== '' ? $row['rut_funcionario_a_reemplazar'] : '—' }}</td>
                                    <td>{{ $row['nombre_funcionario_a_reemplazar'] !== '' ? $row['nombre_funcionario_a_reemplazar'] : '—' }}</td>
                                    @if ($isDipres)
                                        <td>{{ $row['tipo_reemplazo'] !== '' ? $row['tipo_reemplazo'] : '—' }}</td>
                                    @endif
                                    <td>{{ $row['fecha_inicio_trabajo'] !== '' ? $row['fecha_inicio_trabajo'] : '—' }}</td>
                                    <td>{{ $row['fecha_termino'] !== '' ? $row['fecha_termino'] : '—' }}</td>
                                    @unless ($isDipres)
                                        <td>{{ $row['horas_efectivamente_reemplazadas'] !== '' ? $row['horas_efectivamente_reemplazadas'] : '0,00' }}</td>
                                    @endunless
                                    <td><span class="badge text-bg-success">{{ ucfirst($row['estado']) }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tipoPlanilla = document.getElementById('tipo_planilla');
            const wrapper = document.getElementById('tipos-reemplazo-wrapper');
            const fechaInicioWrapper = document.getElementById('fecha-inicio-wrapper');
            const fechaTerminoWrapper = document.getElementById('fecha-termino-wrapper');
            const fechaInicio = document.getElementById('fecha_inicio');
            const fechaTermino = document.getElementById('fecha_termino');
            const matrizTrimestreWrapper = document.getElementById('matriz-s-trimestre-wrapper');
            const matrizAnioWrapper = document.getElementById('matriz-s-anio-wrapper');
            const matrizRangoWrapper = document.getElementById('matriz-s-rango-wrapper');
            const matrizTrimestre = document.getElementById('matriz_s_trimestre');
            const matrizAnio = document.getElementById('matriz_s_anio');
            const matrizRangoText = document.getElementById('matriz-s-rango-text');
            const dipresValue = 'dipres';
            const matrizSValue = 'matriz_s_dipres';
            const matrizCValue = 'matriz_c_dipres';

            function updateMatrizRango() {
                if (!matrizRangoText || !matrizTrimestre || !matrizAnio) return;
                const anio = matrizAnio.value || '2026';
                const rangos = {
                    '1': [`01-01-${anio}`, `31-03-${anio}`],
                    '2': [`01-04-${anio}`, `30-06-${anio}`],
                    '3': [`01-07-${anio}`, `30-09-${anio}`],
                    '4': [`01-10-${anio}`, `31-12-${anio}`],
                };
                const rango = rangos[matrizTrimestre.value] || rangos['1'];
                matrizRangoText.textContent = `${rango[0]} a ${rango[1]}`;
            }

            function toggleFiltros() {
                const value = tipoPlanilla ? tipoPlanilla.value : '';
                const isDipres = value === dipresValue;
                const isMatrizS = value === matrizSValue;
                const isMatrizC = value === matrizCValue;
                const isMatrizDipres = isMatrizS || isMatrizC;

                if (wrapper) wrapper.hidden = !isDipres;
                if (fechaInicioWrapper) fechaInicioWrapper.hidden = isMatrizDipres;
                if (fechaTerminoWrapper) fechaTerminoWrapper.hidden = isMatrizDipres;
                if (matrizTrimestreWrapper) matrizTrimestreWrapper.hidden = !isMatrizDipres;
                if (matrizAnioWrapper) matrizAnioWrapper.hidden = !isMatrizDipres;
                if (matrizRangoWrapper) matrizRangoWrapper.hidden = !isMatrizDipres;

                if (fechaInicio) fechaInicio.required = !isMatrizDipres;
                if (fechaTermino) fechaTermino.required = !isMatrizDipres;
                if (matrizTrimestre) matrizTrimestre.required = isMatrizDipres;
                if (matrizAnio) matrizAnio.required = isMatrizDipres;

                updateMatrizRango();
            }

            if (tipoPlanilla) {
                tipoPlanilla.addEventListener('change', toggleFiltros);
                if (matrizTrimestre) matrizTrimestre.addEventListener('change', updateMatrizRango);
                if (matrizAnio) matrizAnio.addEventListener('input', updateMatrizRango);
                toggleFiltros();
            }
        });
    </script>
@endpush
