@extends('layouts.app')

@push('styles')
    <style>
        .stats-card {
            border: 0;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1.25rem rgba(15, 23, 42, 0.08);
        }

        .stats-kpi {
            min-height: 100%;
            border: 0;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1.25rem rgba(15, 23, 42, 0.08);
        }

        .stats-kpi__icon {
            width: 3rem;
            height: 3rem;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            background: rgba(13, 110, 253, 0.12);
            color: #0d6efd;
        }

        .stats-chart {
            min-height: 320px;
        }

        .stats-empty {
            min-height: 220px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: #6c757d;
        }
    </style>
@endpush

@section('content')
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h4 mb-1">Estadísticas de solicitudes de reemplazo</h1>
            <p class="text-muted mb-0">Resumen general considerando todos los estados de solicitudes del sistema.</p>
        </div>
        <div class="text-muted small">
            @if ($establecimientoActual)
                <span class="badge text-bg-primary-subtle border border-primary-subtle text-primary-emphasis px-3 py-2">
                    Establecimiento: {{ $establecimientoActual->nombre_establecimiento }}
                </span>
            @else
                <span class="badge text-bg-secondary-subtle border border-secondary-subtle text-secondary-emphasis px-3 py-2">
                    Todos los establecimientos
                </span>
            @endif
        </div>
    </div>

    <div class="card stats-card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('gestion.estadisticas.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-lg-8">
                    <label for="establecimiento_id" class="form-label fw-semibold">Filtrar por establecimiento</label>
                    <select name="establecimiento_id" id="establecimiento_id" class="form-select">
                        <option value="">Todos los establecimientos</option>
                        @foreach ($establecimientos as $establecimiento)
                            <option value="{{ $establecimiento->id }}" @selected((int) $establecimientoId === (int) $establecimiento->id)>
                                {{ $establecimiento->nombre_establecimiento }}
                                @if (!empty($establecimiento->rbd))
                                    (RBD {{ $establecimiento->rbd }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-lg-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel"></i> Aplicar filtro
                    </button>
                    <a href="{{ route('gestion.estadisticas.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card stats-kpi">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <div class="text-muted text-uppercase small fw-semibold mb-2">Total solicitudes</div>
                            <div class="display-6 fw-bold mb-1">{{ number_format($totalSolicitudes, 0, ',', '.') }}</div>
                            <div class="text-muted small">Registros acumulados según filtro aplicado.</div>
                        </div>
                        <span class="stats-kpi__icon"><i class="bi bi-files"></i></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card stats-kpi">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <div class="text-muted text-uppercase small fw-semibold mb-2">Días solicitados</div>
                            <div class="display-6 fw-bold mb-1">{{ number_format($diasSolicitados, 0, ',', '.') }}</div>
                            <div class="text-muted small">Calculados entre <code>fecha_inicio</code> y <code>fecha_termino</code>.</div>
                        </div>
                        <span class="stats-kpi__icon"><i class="bi bi-calendar-range"></i></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card stats-kpi">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <div class="text-muted text-uppercase small fw-semibold mb-2">Días autorizados</div>
                            <div class="display-6 fw-bold mb-1">{{ number_format($diasAutorizados, 0, ',', '.') }}</div>
                            <div class="text-muted small">Calculados entre <code>fecha_inicio_trabajo</code> y <code>fecha_termino</code>.</div>
                        </div>
                        <span class="stats-kpi__icon"><i class="bi bi-check2-square"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-5">
            <div class="card stats-card h-100">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h2 class="h5 mb-1">Solicitudes por estado</h2>
                    <div class="text-muted small">Distribución porcentual y volumen por estado.</div>
                </div>
                <div class="card-body pt-0 px-4 pb-4">
                    @if ($porEstado->isNotEmpty())
                        <div id="chartEstados" class="stats-chart"></div>
                    @else
                        <div class="stats-empty">No hay datos para graficar con el filtro actual.</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-7">
            <div class="card stats-card h-100">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h2 class="h5 mb-1">Comparativo de días</h2>
                    <div class="text-muted small">Contraste entre días solicitados y días autorizados.</div>
                </div>
                <div class="card-body pt-0 px-4 pb-4">
                    @if ($totalSolicitudes > 0)
                        <div id="chartDias" class="stats-chart"></div>
                    @else
                        <div class="stats-empty">No hay solicitudes para mostrar en el gráfico.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-6">
            <div class="card stats-card h-100">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
                    <div>
                        <h2 class="h5 mb-1">{{ $rankingTitle }}</h2>
                        <div class="text-muted small">{{ $rankingSubtitle }}</div>
                    </div>
                    <span class="badge text-bg-light border">Top {{ $rankingLimit }}</span>
                </div>
                <div class="card-body pt-0 px-4 pb-4">
                    @if ($rankingRows->isNotEmpty())
                        <div id="chartRanking" class="stats-chart"></div>
                    @else
                        <div class="stats-empty">No hay datos suficientes para construir el ranking.</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="card stats-card h-100">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
                    <div>
                        <h2 class="h5 mb-1">Detalle del ranking</h2>
                        <div class="text-muted small">
                            @if ($rankingMode === 'establecimientos')
                                Ranking de establecimientos con mayor volumen de solicitudes.
                            @else
                                Ranking de funcionarios agrupado por <code>reemplazo_personal_id</code> dentro del establecimiento filtrado.
                            @endif
                        </div>
                    </div>
                    <span class="badge text-bg-light border">{{ number_format($rankingRows->count(), 0, ',', '.') }} registro(s)</span>
                </div>
                <div class="card-body pt-0 px-4 pb-4">
                    @if ($rankingRows->isEmpty())
                        <div class="alert alert-secondary mb-0">No existen registros para el ranking con el criterio seleccionado.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ $rankingMode === 'establecimientos' ? 'Establecimiento' : 'Funcionario' }}</th>
                                        <th class="text-end">Solicitudes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rankingRows as $index => $row)
                                        <tr>
                                            <td class="fw-semibold">{{ $index + 1 }}</td>
                                            <td>
                                                <div class="fw-semibold">{{ $row['nombre'] }}</div>
                                                <div class="text-muted small">{{ $row['detalle'] }}</div>
                                            </td>
                                            <td class="text-end fw-semibold">{{ number_format($row['total'], 0, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card stats-card">
        <div class="card-header bg-white border-0 pt-4 px-4 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
            <div>
                <h2 class="h5 mb-1">Detalle por estado</h2>
                <div class="text-muted small">Cantidad de solicitudes y peso relativo sobre el total filtrado.</div>
            </div>
            <span class="badge text-bg-light border">{{ number_format($porEstado->count(), 0, ',', '.') }} estado(s)</span>
        </div>
        <div class="card-body pt-0 px-4 pb-4">
            @if ($porEstado->isEmpty())
                <div class="alert alert-secondary mb-0">No existen solicitudes para el criterio seleccionado.</div>
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Estado</th>
                                <th class="text-end">Solicitudes</th>
                                <th class="text-end">Porcentaje</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($porEstado as $row)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $row['label'] }}</div>
                                        <div class="text-muted small"><code>{{ $row['estado'] }}</code></div>
                                    </td>
                                    <td class="text-end fw-semibold">{{ number_format($row['total'], 0, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format($row['porcentaje'], 1, ',', '.') }}%</td>
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
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        (() => {
            const estadoChart = @json($estadoChart);
            const diasChart = @json($diasChart);
            const rankingChart = @json($rankingChart);
            const rankingMode = @json($rankingMode);

            if (document.querySelector('#chartEstados') && Array.isArray(estadoChart.series) && estadoChart.series.length) {
                const estados = new ApexCharts(document.querySelector('#chartEstados'), {
                    chart: {
                        type: 'donut',
                        height: 320,
                        toolbar: { show: false }
                    },
                    series: estadoChart.series,
                    labels: estadoChart.labels,
                    legend: {
                        position: 'bottom'
                    },
                    dataLabels: {
                        enabled: true
                    },
                    tooltip: {
                        y: {
                            formatter: (value) => `${value} solicitud(es)`
                        }
                    },
                    noData: {
                        text: 'Sin datos'
                    }
                });

                estados.render();
            }

            if (document.querySelector('#chartDias') && Array.isArray(diasChart.series) && diasChart.series.length) {
                const dias = new ApexCharts(document.querySelector('#chartDias'), {
                    chart: {
                        type: 'bar',
                        height: 320,
                        toolbar: { show: false }
                    },
                    series: [{
                        name: 'Días',
                        data: diasChart.series,
                    }],
                    xaxis: {
                        categories: diasChart.labels,
                    },
                    plotOptions: {
                        bar: {
                            borderRadius: 8,
                            columnWidth: '45%'
                        }
                    },
                    dataLabels: {
                        enabled: true
                    },
                    tooltip: {
                        y: {
                            formatter: (value) => `${value} día(s)`
                        }
                    },
                    noData: {
                        text: 'Sin datos'
                    }
                });

                dias.render();
            }

            if (document.querySelector('#chartRanking') && Array.isArray(rankingChart.series) && rankingChart.series.length) {
                const ranking = new ApexCharts(document.querySelector('#chartRanking'), {
                    chart: {
                        type: 'bar',
                        height: 320,
                        toolbar: { show: false }
                    },
                    series: [{
                        name: 'Solicitudes',
                        data: rankingChart.series,
                    }],
                    xaxis: {
                        categories: rankingChart.labels,
                        labels: {
                            trim: true,
                            hideOverlappingLabels: false,
                        }
                    },
                    plotOptions: {
                        bar: {
                            horizontal: true,
                            borderRadius: 6,
                            barHeight: '55%'
                        }
                    },
                    dataLabels: {
                        enabled: true
                    },
                    tooltip: {
                        y: {
                            formatter: (value) => `${value} solicitud(es)`
                        }
                    },
                    noData: {
                        text: 'Sin datos'
                    }
                });

                ranking.render();
            }
        })();
    </script>
@endpush
