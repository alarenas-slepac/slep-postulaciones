@extends('layouts.app')

@section('content')
    @php
        $fmt = fn ($value) => \App\Support\DotacionEstablecimientoCalculator::formatHoras($value);
    @endphp

    <style>
        .avance-hero { background: linear-gradient(135deg, #ffffff 0%, #f7fbff 55%, #eef5ff 100%); border: 1px solid #dbe8fb; border-radius: 1.25rem; box-shadow: 0 10px 30px rgba(15, 23, 42, .06); }
        .avance-icon { width: 46px; height: 46px; display: inline-flex; align-items: center; justify-content: center; border-radius: 999px; background: #0d6efd; color: #fff; box-shadow: 0 10px 20px rgba(13, 110, 253, .22); }
        .avance-card { border: 1px solid #dce7f5; border-radius: 1rem; box-shadow: 0 8px 20px rgba(15, 23, 42, .045); }
        .avance-eyebrow { color: #64748b; font-size: .75rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
        .avance-kpi { border: 1px solid #e5ecf6; border-radius: 1rem; box-shadow: 0 8px 20px rgba(15, 23, 42, .045); height: 100%; }
        .avance-progress { height: .72rem; background: #e9eef6; border-radius: 999px; }
        .avance-progress .progress-bar { border-radius: 999px; }
        .avance-table th { white-space: nowrap; }
        .avance-observaciones { max-width: 330px; }
        .avance-desglose { min-width: 270px; }
    </style>

    <div class="avance-hero p-4 p-lg-5 mb-4">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-4">
            <div class="d-flex gap-3 align-items-start">
                <span class="avance-icon"><i class="bi bi-bar-chart-line fs-5"></i></span>
                <div>
                    <div class="avance-eyebrow mb-1">Administración · Dotación Establecimiento</div>
                    <h1 class="display-6 fw-bold mb-1">Informe de avance</h1>
                    <p class="mb-0 text-muted">Seguimiento de configuración de planes y asignación de horas aula por establecimiento.</p>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-start justify-content-xl-end">
                <a class="btn btn-danger rounded-pill px-4" href="{{ route('admin.dotacion-establecimiento.index', array_filter([
                    'informe_avance' => 1,
                    'export_pdf' => 1,
                    'anio' => $anio,
                    'comuna' => $comuna !== '' ? $comuna : null,
                    'establecimiento_id' => $establecimientoId > 0 ? $establecimientoId : null,
                ])) }}">
                    <i class="bi bi-file-earmark-pdf"></i> Informe PDF global
                </a>
                <a class="btn btn-success rounded-pill px-4" href="{{ route('admin.dotacion-establecimiento.index', array_filter([
                    'informe_avance' => 1,
                    'export_excel' => 1,
                    'anio' => $anio,
                    'comuna' => $comuna !== '' ? $comuna : null,
                    'establecimiento_id' => $establecimientoId > 0 ? $establecimientoId : null,
                ])) }}">
                    <i class="bi bi-file-earmark-excel"></i> Exportar Excel completo
                </a>
                <a class="btn btn-outline-primary rounded-pill px-4" href="{{ route('admin.dotacion-establecimiento.index', ['anio' => $anio]) }}">
                    <i class="bi bi-arrow-left"></i> Volver a Dotación
                </a>
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.dotacion-establecimiento.index') }}" class="card avance-card mb-4">
        <input type="hidden" name="informe_avance" value="1">
        <div class="card-body p-4">
            <div class="avance-eyebrow mb-1">Filtros</div>
            <h2 class="h5 fw-bold mb-3">Seleccionar universo del informe</h2>
            <div class="row g-3 align-items-end">
                <div class="col-lg-2 col-md-4">
                    <label class="form-label fw-semibold">Año</label>
                    <input type="number" class="form-control" name="anio" value="{{ $anio }}" min="2020" max="2100">
                </div>
                <div class="col-lg-3 col-md-4">
                    <label class="form-label fw-semibold">Comuna</label>
                    <select class="form-select" name="comuna">
                        <option value="">Todas las comunas</option>
                        @foreach ($comunas as $comunaOpcion)
                            <option value="{{ $comunaOpcion }}" @selected($comuna === $comunaOpcion)>{{ $comunaOpcion }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-5 col-md-8">
                    <label class="form-label fw-semibold">Establecimiento</label>
                    <select class="form-select" name="establecimiento_id">
                        <option value="">Todos los establecimientos</option>
                        @foreach ($opcionesEstablecimientos as $opcion)
                            <option value="{{ $opcion->id }}" @selected($establecimientoId === (int) $opcion->id)>
                                {{ $opcion->rbd }} · {{ $opcion->nombre_establecimiento }}{{ $opcion->comuna ? ' · '.$opcion->comuna : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-4 d-flex gap-2">
                    <button class="btn btn-primary rounded-pill px-4" type="submit"><i class="bi bi-search"></i> Consultar</button>
                    <a class="btn btn-outline-danger rounded-pill" href="{{ route('admin.dotacion-establecimiento.index', ['informe_avance' => 1, 'anio' => $anio]) }}" title="Limpiar filtros"><i class="bi bi-x-lg"></i></a>
                </div>
            </div>
        </div>
    </form>

    <div class="alert alert-light border rounded-4 d-flex align-items-start gap-2 mb-4">
        <i class="bi bi-info-circle text-primary mt-1"></i>
        <div>El informe en pantalla se presenta paginado para mantener un tiempo de respuesta seguro. Las exportaciones <strong>PDF global</strong> y <strong>Excel</strong> incluyen todos los establecimientos que cumplen los filtros aplicados, no solo los registros visibles en la página actual.</div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6"><div class="card avance-kpi border-0"><div class="card-body"><div class="text-muted small fw-semibold">Establecimientos visibles</div><div class="fs-3 fw-bold text-primary">{{ number_format($resumenPagina['total'] ?? 0, 0, ',', '.') }}</div><div class="small text-muted">de {{ number_format($establecimientos->total(), 0, ',', '.') }} resultado(s).</div></div></div></div>
        <div class="col-xl-3 col-md-6"><div class="card avance-kpi border-0"><div class="card-body"><div class="text-muted small fw-semibold">Promedio configuración planes</div><div class="fs-3 fw-bold">{{ number_format($resumenPagina['promedio_planes'] ?? 0, 1, ',', '.') }}%</div><div class="small text-muted">{{ number_format($resumenPagina['cursos_pendientes'] ?? 0, 0, ',', '.') }} curso(s) pendientes.</div></div></div></div>
        <div class="col-xl-3 col-md-6"><div class="card avance-kpi border-0"><div class="card-body"><div class="text-muted small fw-semibold">Promedio asignación de horas aula</div><div class="fs-3 fw-bold text-primary">{{ number_format($resumenPagina['promedio_asignacion'] ?? 0, 1, ',', '.') }}%</div><div class="small text-muted">{{ $fmt($resumenPagina['horas_aula_pendientes'] ?? $resumenPagina['horas_pendientes'] ?? 0) }} horas aula pendientes.</div></div></div></div>
        <div class="col-xl-3 col-md-6"><div class="card avance-kpi border-0"><div class="card-body"><div class="text-muted small fw-semibold">Promedio general</div><div class="fs-3 fw-bold text-success">{{ number_format($resumenPagina['promedio_general'] ?? 0, 1, ',', '.') }}%</div><div class="small text-muted">{{ number_format($resumenPagina['completos'] ?? 0, 0, ',', '.') }} completo(s) en la página.</div></div></div></div>
    </div>

    <div class="card avance-card overflow-hidden">
        <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <div class="avance-eyebrow">Resultados</div>
                <h2 class="h5 fw-bold mb-1">Avance por establecimiento</h2>
                <div class="text-muted small">Planes configurados, cobertura de horas y observaciones de control.</div>
            </div>
            <span class="badge rounded-pill text-bg-light border">{{ $establecimientos->total() }} establecimiento(s)</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 avance-table">
                <thead class="table-light">
                    <tr>
                        <th>Establecimiento</th>
                        <th style="min-width: 240px;">Configuración de planes</th>
                        <th style="min-width: 260px;">Asignación de horas</th>
                        <th style="min-width: 190px;">Avance general</th>
                        <th>Desglose</th>
                        <th>Observaciones</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($establecimientos as $establecimiento)
                        @php $avance = $establecimiento->dotacion_avance ?? []; @endphp
                        <tr>
                            <td>
                                <div class="fw-bold">{{ $establecimiento->nombre_establecimiento }}</div>
                                <div class="text-muted small">RBD {{ $establecimiento->rbd }} · {{ $establecimiento->comuna ?: 'Sin comuna' }}</div>
                                <span class="badge rounded-pill {{ data_get($avance, 'estado.class', 'text-bg-secondary') }} mt-2">{{ data_get($avance, 'estado.label', 'Sin estado') }}</span>
                            </td>
                            <td>
                                @include('admin.dotacion-establecimiento.partials._avance-barra', [
                                    'label' => 'Planes configurados',
                                    'porcentaje' => data_get($avance, 'planes.porcentaje', 0),
                                    'detalle' => data_get($avance, 'planes.configurados', 0).' de '.data_get($avance, 'planes.total', 0).' cursos configurados · '.data_get($avance, 'planes.pendientes', 0).' pendiente(s)',
                                ])
                            </td>
                            <td>
                                @include('admin.dotacion-establecimiento.partials._avance-barra', [
                                    'label' => 'Horas aula asignadas',
                                    'porcentaje' => data_get($avance, 'asignacion.porcentaje', 0),
                                    'detalle' => $fmt(data_get($avance, 'asignacion.horas_aula_asignadas', 0)).' de '.$fmt(data_get($avance, 'asignacion.horas_aula_requeridas', 0)).' h aula · Pendientes: '.$fmt(data_get($avance, 'asignacion.horas_aula_pendientes', 0)),
                                ])
                                @if (data_get($avance, 'asignacion.horas_aula_excedidas', 0) > 0)
                                    <div class="small text-danger fw-semibold mt-1">Exceso: {{ $fmt(data_get($avance, 'asignacion.horas_aula_excedidas', 0)) }} horas aula</div>
                                @endif
                            </td>
                            <td>
                                @include('admin.dotacion-establecimiento.partials._avance-barra', [
                                    'label' => 'Avance consolidado',
                                    'porcentaje' => data_get($avance, 'porcentaje_general', 0),
                                    'detalle' => '50% planes + 50% asignación de horas aula',
                                ])
                            </td>
                            <td class="avance-desglose">
                                @foreach (collect(data_get($avance, 'desglose', [])) as $grupo)
                                    <div class="d-flex justify-content-between gap-3 small border-bottom py-1">
                                        <span>{{ $grupo['label'] }}</span>
                                        <span class="fw-semibold text-nowrap">{{ number_format($grupo['porcentaje'], 1, ',', '.') }}%</span>
                                    </div>
                                @endforeach
                            </td>
                            <td class="avance-observaciones">
                                @if (collect(data_get($avance, 'observaciones', []))->isEmpty())
                                    <span class="text-success small"><i class="bi bi-check-circle"></i> Sin observaciones</span>
                                @else
                                    <ul class="small mb-0 ps-3">
                                        @foreach (collect(data_get($avance, 'observaciones', []))->take(4) as $observacion)
                                            <li>{{ $observacion }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary rounded-pill" href="{{ route('admin.dotacion-establecimiento.show', ['establecimiento' => $establecimiento, 'anio' => $anio]) }}">Ver dotación</a>
                                <a class="btn btn-sm btn-primary rounded-pill" href="{{ route('admin.dotacion-establecimiento.show', ['establecimiento' => $establecimiento, 'anio' => $anio, 'tab' => 'asignacion']) }}">Asignar horas</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-5">No existen establecimientos para los filtros seleccionados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($establecimientos->hasPages())
            <div class="card-footer bg-white border-top p-3">{{ $establecimientos->links() }}</div>
        @endif
    </div>
@endsection
