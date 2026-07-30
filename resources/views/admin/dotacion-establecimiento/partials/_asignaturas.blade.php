@php
    $fmt = fn ($value) => \App\Support\DotacionEstablecimientoCalculator::formatHoras($value);
    $itemsAsignaturas = collect(data_get($asignaturas ?? [], 'items', []));
    $resumenAsignaturas = data_get($asignaturas ?? [], 'resumen', []);
    $resumenAsignaturasTotal = data_get($asignaturas ?? [], 'resumen_total', []);
    $opcionesAsignaturas = data_get($asignaturas ?? [], 'opciones', []);
    $filtrosAsignaturas = $asignaturasFiltros ?? [];
    $filtrosActivos = collect($filtrosAsignaturas)->filter(fn ($value) => trim((string) $value) !== '')->isNotEmpty();
    $signed = function ($value) use ($fmt) {
        $numero = (float) $value;
        return ($numero > 0.01 ? '+' : '').$fmt($numero);
    };
@endphp

<div class="dotacion-section bg-white mb-4">
    <div class="dotacion-section-header d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-center">
        <div>
            <div class="dotacion-eyebrow mb-1">Consolidado curricular</div>
            <h2 class="h5 fw-bold mb-1">Horas por asignatura</h2>
            <div class="text-muted small">Agrupa las horas aula y su equivalencia contractual por asignatura, identificando por separado la cobertura de docentes titulares, otros docentes y asistentes de la educación.</div>
        </div>
        <span class="badge rounded-pill text-bg-primary px-3 py-2">{{ $itemsAsignaturas->count() }} asignatura{{ $itemsAsignaturas->count() === 1 ? '' : 's' }}</span>
    </div>

    <div class="card-body p-3 p-lg-4">
        <div class="row g-3 mb-4">
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="p-3 rounded-4 bg-light h-100">
                    <div class="small text-muted">Horas aula plan</div>
                    <div class="h4 fw-bold mb-0">{{ $fmt(data_get($resumenAsignaturas, 'horas_aula_plan', 0)) }}</div>
                    <div class="small text-muted">Requeridas</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="p-3 rounded-4 bg-light h-100">
                    <div class="small text-muted">Horas aula asignadas</div>
                    <div class="h4 fw-bold text-primary mb-0">{{ $fmt(data_get($resumenAsignaturas, 'horas_aula_asignadas', 0)) }}</div>
                    <div class="small text-muted">Cobertura {{ number_format((float) data_get($resumenAsignaturas, 'porcentaje_cobertura', 0), 1, ',', '.') }}%</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="p-3 rounded-4 bg-light h-100">
                    <div class="small text-muted">Contrato requerido</div>
                    <div class="h4 fw-bold text-info mb-0">{{ $fmt(data_get($resumenAsignaturas, 'horas_contrato_requeridas', 0)) }}</div>
                    <div class="small text-muted">65/35 + 60/40 + especial</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="p-3 rounded-4 bg-light h-100">
                    <div class="small text-muted">Horas aula titulares</div>
                    <div class="h4 fw-bold text-success mb-0">{{ $fmt(data_get($resumenAsignaturas, 'horas_aula_titulares', 0)) }}</div>
                    <div class="small text-muted">{{ number_format((float) data_get($resumenAsignaturas, 'porcentaje_cobertura_titular', 0), 1, ',', '.') }}% de lo asignado</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="p-3 rounded-4 bg-light h-100">
                    <div class="small text-muted">Contrato titulares</div>
                    <div class="h4 fw-bold text-success mb-0">{{ $fmt(data_get($resumenAsignaturas, 'horas_contrato_titulares', 0)) }}</div>
                    <div class="small text-muted">Equivalencia contractual</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="p-3 rounded-4 bg-light h-100">
                    <div class="small text-muted">Aula cubierta por AAEE</div>
                    <div class="h4 fw-bold text-info mb-0">{{ $fmt(data_get($resumenAsignaturas, 'horas_aula_asistentes', 0)) }}</div>
                    <div class="small text-muted">Asistentes de la educación</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="p-3 rounded-4 bg-light h-100">
                    <div class="small text-muted">Contrato AAEE</div>
                    <div class="h4 fw-bold text-info mb-0">{{ $fmt(data_get($resumenAsignaturas, 'horas_contrato_asistentes', 0)) }}</div>
                    <div class="small text-muted">Informado manualmente</div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-sm-6">
                <div class="p-3 rounded-4 bg-light h-100">
                    <div class="small text-muted">Asignaturas pendientes</div>
                    <div class="h4 fw-bold text-warning mb-0">{{ number_format((int) data_get($resumenAsignaturas, 'asignaturas_pendientes', 0), 0, ',', '.') }}</div>
                    <div class="small text-muted">Sin cubrir o parciales</div>
                </div>
            </div>
        </div>

        <form method="GET" action="{{ route('admin.dotacion-establecimiento.show', $establecimiento) }}" class="border rounded-4 p-3 mb-4 bg-light-subtle">
            <input type="hidden" name="anio" value="{{ $anio }}">
            <input type="hidden" name="tab" value="asignaturas">
            <div class="row g-3 align-items-end">
                <div class="col-xl-4 col-md-6">
                    <label class="form-label fw-semibold" for="asignatura_q">Asignatura</label>
                    <input type="search" class="form-control" id="asignatura_q" name="asignatura_q" value="{{ data_get($filtrosAsignaturas, 'q', '') }}" placeholder="Buscar por nombre de asignatura">
                </div>
                <div class="col-xl-2 col-md-3">
                    <label class="form-label fw-semibold" for="asignatura_proporcion">Proporción</label>
                    <select class="form-select" id="asignatura_proporcion" name="asignatura_proporcion">
                        <option value="">Todas</option>
                        @foreach (collect(data_get($opcionesAsignaturas, 'proporciones', [])) as $proporcion)
                            <option value="{{ $proporcion }}" @selected(data_get($filtrosAsignaturas, 'proporcion') === $proporcion)>{{ $proporcion }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-3">
                    <label class="form-label fw-semibold" for="asignatura_estado">Cobertura</label>
                    <select class="form-select" id="asignatura_estado" name="asignatura_estado">
                        <option value="">Todos los estados</option>
                        @foreach (collect(data_get($opcionesAsignaturas, 'estados', [])) as $estado)
                            <option value="{{ data_get($estado, 'key') }}" @selected(data_get($filtrosAsignaturas, 'estado') === data_get($estado, 'key'))>{{ data_get($estado, 'label') }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-6">
                    <label class="form-label fw-semibold" for="asignatura_titulares">Cobertura titular</label>
                    <select class="form-select" id="asignatura_titulares" name="asignatura_titulares">
                        <option value="">Todas</option>
                        <option value="con" @selected(data_get($filtrosAsignaturas, 'titulares') === 'con')>Con horas titulares</option>
                        <option value="sin" @selected(data_get($filtrosAsignaturas, 'titulares') === 'sin')>Sin horas titulares</option>
                        <option value="mixta" @selected(data_get($filtrosAsignaturas, 'titulares') === 'mixta')>Cobertura mixta</option>
                    </select>
                </div>
                <div class="col-xl-2 col-md-6 d-flex gap-2">
                    <button class="btn btn-primary flex-grow-1" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
                    @if ($filtrosActivos)
                        <a class="btn btn-outline-secondary" href="{{ route('admin.dotacion-establecimiento.show', [$establecimiento, 'anio' => $anio, 'tab' => 'asignaturas']) }}" title="Limpiar filtros"><i class="bi bi-x-lg"></i></a>
                    @endif
                </div>
            </div>
        </form>

        @if ($filtrosActivos)
            <div class="alert alert-info border-0 rounded-4 small">
                Los indicadores y la tabla consideran las asignaturas filtradas. Total general sin filtros: {{ $fmt(data_get($resumenAsignaturasTotal, 'horas_aula_plan', 0)) }} horas aula de plan en {{ data_get($resumenAsignaturasTotal, 'asignaturas_total', 0) }} asignaturas.
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="min-width: 230px;">Asignatura</th>
                        <th style="min-width: 95px;">Proporción</th>
                        <th class="text-end" style="min-width: 92px;">Aula plan</th>
                        <th class="text-end" style="min-width: 105px;">Aula asignada</th>
                        <th class="text-end" style="min-width: 115px;">Contrato requerido</th>
                        <th class="text-end" style="min-width: 115px;">Contrato asignado</th>
                        <th class="text-end" style="min-width: 105px;">Aula titulares</th>
                        <th class="text-end" style="min-width: 115px;">Contrato titulares</th>
                        <th class="text-end" style="min-width: 105px;">Aula AAEE</th>
                        <th class="text-end" style="min-width: 110px;">Contrato AAEE</th>
                        <th class="text-end" style="min-width: 105px;">Aula no titulares</th>
                        <th class="text-end" style="min-width: 118px;">Contrato no titulares</th>
                        <th class="text-end" style="min-width: 90px;">Saldo aula</th>
                        <th class="text-end" style="min-width: 105px;">Saldo contrato</th>
                        <th class="text-end" style="min-width: 105px;">Cobertura titular</th>
                        <th style="min-width: 110px;">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($itemsAsignaturas as $item)
                        @php
                            $saldoAula = (float) data_get($item, 'saldo_aula', 0);
                            $saldoContrato = (float) data_get($item, 'saldo_contrato', 0);
                            $estado = data_get($item, 'estado', []);
                            $detalle = collect(data_get($item, 'detalle', []));
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-bold">{{ data_get($item, 'asignatura', 'Asignatura') }}</div>
                                <div class="small text-muted">{{ $detalle->count() }} curso{{ $detalle->count() === 1 ? '' : 's' }} / sección</div>
                            </td>
                            <td>
                                @foreach ((array) data_get($item, 'proporciones', []) as $proporcion)
                                    <span class="badge rounded-pill dotacion-badge-soft">{{ $proporcion }}</span>
                                @endforeach
                                @foreach ((array) data_get($item, 'origenes_proporcion', []) as $origen)
                                    <div class="small text-muted mt-1">{{ $origen }}</div>
                                @endforeach
                            </td>
                            <td class="text-end fw-semibold">{{ $fmt(data_get($item, 'horas_aula_plan', 0)) }}</td>
                            <td class="text-end text-primary fw-bold">{{ $fmt(data_get($item, 'horas_aula_asignadas', 0)) }}</td>
                            <td class="text-end">{{ $fmt(data_get($item, 'horas_contrato_requeridas_total', 0)) }}</td>
                            <td class="text-end fw-semibold">{{ $fmt(data_get($item, 'horas_contrato_asignadas_total', 0)) }}</td>
                            <td class="text-end text-success fw-semibold">{{ $fmt(data_get($item, 'horas_aula_titulares', 0)) }}</td>
                            <td class="text-end text-success fw-semibold">{{ $fmt(data_get($item, 'horas_contrato_titulares', 0)) }}</td>
                            <td class="text-end text-info fw-semibold">{{ $fmt(data_get($item, 'horas_aula_asistentes', 0)) }}</td>
                            <td class="text-end text-info fw-semibold">{{ $fmt(data_get($item, 'horas_contrato_asistentes', 0)) }}</td>
                            <td class="text-end">{{ $fmt(data_get($item, 'horas_aula_no_titulares', 0)) }}</td>
                            <td class="text-end">{{ $fmt(data_get($item, 'horas_contrato_no_titulares', 0)) }}</td>
                            <td class="text-end {{ $saldoAula > 0.01 ? 'text-warning' : ($saldoAula < -0.01 ? 'text-danger' : 'text-success') }} fw-semibold">{{ $signed($saldoAula) }}</td>
                            <td class="text-end {{ $saldoContrato > 0.01 ? 'text-warning' : ($saldoContrato < -0.01 ? 'text-danger' : 'text-success') }} fw-semibold">{{ $signed($saldoContrato) }}</td>
                            <td class="text-end">{{ number_format((float) data_get($item, 'porcentaje_cobertura_titular', 0), 1, ',', '.') }}%</td>
                            <td><span class="badge rounded-pill {{ data_get($estado, 'class', 'text-bg-secondary') }}">{{ data_get($estado, 'label', 'Sin estado') }}</span></td>
                        </tr>
                        <tr class="table-light">
                            <td colspan="16" class="p-2">
                                <details>
                                    <summary class="fw-semibold text-primary" style="cursor: pointer;">Ver detalle por curso y docente</summary>
                                    <div class="table-responsive mt-3">
                                        <table class="table table-sm table-bordered bg-white mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Curso / sección</th>
                                                    <th>Bloque / origen</th>
                                                    <th>Proporción</th>
                                                    <th class="text-end">Aula plan</th>
                                                    <th class="text-end">Aula asignada</th>
                                                    <th class="text-end">Contrato requerido</th>
                                                    <th>Personal asignado</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($detalle as $cursoDetalle)
                                                    <tr>
                                                        <td>{{ data_get($cursoDetalle, 'curso', '—') }}</td>
                                                        <td class="small">{{ data_get($cursoDetalle, 'bloque', '—') }}</td>
                                                        <td><span class="badge rounded-pill dotacion-badge-soft">{{ data_get($cursoDetalle, 'proporcion', '—') }}</span><div class="small text-muted mt-1">{{ data_get($cursoDetalle, 'origen_proporcion', 'Regla general') }}</div></td>
                                                        <td class="text-end">{{ $fmt(data_get($cursoDetalle, 'horas_aula_plan', 0)) }}</td>
                                                        <td class="text-end text-primary fw-semibold">{{ $fmt(data_get($cursoDetalle, 'horas_aula_asignadas', 0)) }}</td>
                                                        <td class="text-end">{{ $fmt(data_get($cursoDetalle, 'horas_contrato_requeridas', 0)) }}</td>
                                                        <td>
                                                            @forelse (collect(data_get($cursoDetalle, 'asignaciones', [])) as $asignacionDetalle)
                                                                <div class="small mb-1">
                                                                    <span class="fw-semibold">{{ data_get($asignacionDetalle, 'docente', 'Personal') }}</span>
                                                                    · <span class="badge rounded-pill {{ data_get($asignacionDetalle, 'estamento_cobertura') === 'asistente' ? 'text-bg-info' : 'text-bg-primary' }}">{{ data_get($asignacionDetalle, 'estamento_cobertura_label', 'Docente') }}</span>
                                                                    · {{ $fmt(data_get($asignacionDetalle, 'horas_aula', 0)) }} h aula
                                                                    @if (data_get($asignacionDetalle, 'estamento_cobertura') === 'asistente')
                                                                        · {{ $fmt(data_get($asignacionDetalle, 'horas_contrato_registradas', 0)) }} h contrato AAEE
                                                                    @else
                                                                        · <span class="{{ data_get($asignacionDetalle, 'titularidad.es_titular', false) ? 'text-success fw-semibold' : 'text-muted' }}">{{ data_get($asignacionDetalle, 'titularidad.label', 'No determinada') }}</span>
                                                                    @endif
                                                                    · {{ data_get($asignacionDetalle, 'tipo_contrato', 'Sin tipo contrato') }}
                                                                </div>
                                                            @empty
                                                                <span class="small text-muted">Sin personal asignado.</span>
                                                            @endforelse
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="16" class="text-center text-muted py-5">No se encontraron asignaturas para los filtros aplicados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="alert alert-secondary border-0 rounded-4 mt-4 mb-0 small">
            <strong>Criterio de titularidad:</strong> se consideran titulares los registros cuyo tipo de contrato contiene “Titular” o “Planta”. Contrata, reemplazo, suplencia, honorarios y vínculos temporales se clasifican como no titulares. Los tipos no reconocidos se informan como “No determinada” y no se suman como titulares. La cobertura de asistentes de la educación se informa por separado y no se clasifica como docente titular o no titular.
        </div>
    </div>
</div>
