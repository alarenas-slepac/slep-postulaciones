@extends('layouts.app')

@section('content')
    @php
        $fmt = fn ($value) => \App\Support\DotacionEstablecimientoCalculator::formatHoras($value);
        $itemsPagina = collect(method_exists($establecimientos, 'items') ? $establecimientos->items() : $establecimientos);
        $totalMatriculaPagina = $itemsPagina->sum(fn ($ee) => (int) (($ee->dotacion_establecimiento_resumen['matricula_total'] ?? 0)));
        $totalCursosPagina = $itemsPagina->sum(fn ($ee) => (int) (($ee->dotacion_establecimiento_resumen['cursos_total'] ?? 0)));
        $totalDocentesPagina = $itemsPagina->sum(fn ($ee) => (int) (($ee->dotacion_establecimiento_resumen['docentes_total'] ?? 0)));
        $totalContratoPagina = $itemsPagina->sum(fn ($ee) => (float) (($ee->dotacion_establecimiento_resumen['horas_contrato_docentes'] ?? 0)));
    @endphp

    <style>
        .dotacion-hero { background: linear-gradient(135deg, #ffffff 0%, #f7fbff 55%, #eef5ff 100%); border: 1px solid #dbe8fb; border-radius: 1.25rem; box-shadow: 0 10px 30px rgba(15, 23, 42, .06); }
        .dotacion-icon { width: 44px; height: 44px; display: inline-flex; align-items: center; justify-content: center; border-radius: 999px; background: #0d6efd; color: #fff; box-shadow: 0 10px 20px rgba(13, 110, 253, .22); }
        .dotacion-section { border: 1px solid #dce7f5; border-radius: 1rem; box-shadow: 0 8px 20px rgba(15, 23, 42, .045); overflow: hidden; }
        .dotacion-section-header { background: #fff; border-bottom: 1px solid #e6edf6; padding: 1rem 1.25rem; }
        .dotacion-eyebrow { color: #64748b; font-size: .75rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
        .dotacion-kpi { border: 1px solid #e5ecf6; border-radius: 1rem; box-shadow: 0 8px 20px rgba(15, 23, 42, .045); height: 100%; }
    </style>

    <div class="dotacion-hero p-4 p-lg-5 mb-4">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-4">
            <div class="d-flex gap-3 align-items-start">
                <span class="dotacion-icon"><i class="bi bi-building-check fs-5"></i></span>
                <div>
                    <div class="dotacion-eyebrow mb-1">Administración · Dotación docente</div>
                    <h1 class="display-6 fw-bold mb-1">Dotación Establecimiento</h1>
                    <p class="mb-0 text-muted fs-6">Consolidado de cursos, planes de estudio, docentes y funciones por establecimiento.</p>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-start justify-content-xl-end">
                @if (in_array($activeRole, ['admin', 'coordinador_uatp', 'supervisor_plani'], true))
                    <a class="btn btn-primary rounded-pill px-4" href="{{ route('admin.dotacion-establecimiento.index', ['informe_avance' => 1, 'anio' => $anio]) }}">
                        <i class="bi bi-bar-chart-line"></i> Informe de avance
                    </a>
                    <a class="btn btn-success rounded-pill px-4" href="{{ route('admin.dotacion-establecimiento.index', ['informe_avance' => 1, 'export_excel' => 1, 'anio' => $anio]) }}">
                        <i class="bi bi-file-earmark-excel"></i> Excel de avance
                    </a>
                @endif
                @if (Route::has('admin.dotacion-funciones.index') && $activeRole !== 'supervisor_plani')
                    <a class="btn btn-outline-primary rounded-pill px-4" href="{{ route('admin.dotacion-funciones.index', ['anio' => $anio]) }}">
                        <i class="bi bi-diagram-3"></i> Dotación funciones y planes
                    </a>
                @endif
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success rounded-4 shadow-sm border-0">{{ session('status') }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6"><div class="card dotacion-kpi border-0"><div class="card-body"><div class="text-muted small fw-semibold">Establecimientos visibles</div><div class="fs-3 fw-bold text-primary">{{ number_format($establecimientos->total(), 0, ',', '.') }}</div><div class="small text-muted">Según filtros y rol activo.</div></div></div></div>
        <div class="col-xl-3 col-md-6"><div class="card dotacion-kpi border-0"><div class="card-body"><div class="text-muted small fw-semibold">Matrícula página</div><div class="fs-3 fw-bold">{{ number_format($totalMatriculaPagina, 0, ',', '.') }}</div><div class="small text-muted">Suma de registros visibles.</div></div></div></div>
        <div class="col-xl-3 col-md-6"><div class="card dotacion-kpi border-0"><div class="card-body"><div class="text-muted small fw-semibold">Cursos página</div><div class="fs-3 fw-bold text-primary">{{ number_format($totalCursosPagina, 0, ',', '.') }}</div><div class="small text-muted">Cursos con matrícula.</div></div></div></div>
        <div class="col-xl-3 col-md-6"><div class="card dotacion-kpi border-0"><div class="card-body"><div class="text-muted small fw-semibold">Horas contrato docentes</div><div class="fs-3 fw-bold text-success">{{ $fmt($totalContratoPagina) }}</div><div class="small text-muted">Base contractual visible.</div></div></div></div>
    </div>

    <form method="GET" class="card dotacion-section mb-4">
        <div class="dotacion-section-header">
            <div class="dotacion-eyebrow">Búsqueda y filtrado</div>
            <h2 class="h5 fw-bold mb-1">Filtrar dotación</h2>
            <div class="text-muted small">Busca por RBD, establecimiento o comuna. Los establecimientos sala cuna no participan en este proceso.</div>
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-lg-4 col-md-6">
                    <label class="form-label fw-semibold">Buscar</label>
                    <input type="text" class="form-control" name="q" value="{{ $q }}" placeholder="RBD, establecimiento o comuna">
                </div>
                <div class="col-lg-2 col-md-3">
                    <label class="form-label fw-semibold">Año</label>
                    <input type="number" class="form-control" name="anio" value="{{ $anio }}" min="2020" max="2100">
                </div>
                <div class="col-lg-3 col-md-4">
                    <label class="form-label fw-semibold">Comuna</label>
                    <select class="form-select" name="comuna" @disabled($activeRole === 'funcionario_directivo_estab')>
                        <option value="">Todas</option>
                        @foreach ($comunas as $comunaOpcion)
                            <option value="{{ $comunaOpcion }}" @selected($comuna === $comunaOpcion)>{{ $comunaOpcion }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3 col-md-5 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-outline-primary rounded-pill px-4"><i class="bi bi-funnel"></i> Filtrar</button>
                    <a href="{{ route('admin.dotacion-establecimiento.index') }}" class="btn btn-outline-danger rounded-pill px-4">Limpiar</a>
                </div>
            </div>
        </div>
    </form>

    <div class="card dotacion-section">
        <div class="dotacion-section-header d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <div class="dotacion-eyebrow">Listado</div>
                <h2 class="h5 fw-bold mb-1">Establecimientos con dotación calculada</h2>
                <div class="text-muted small">Se muestran {{ $establecimientos->count() }} registros en esta página y {{ $establecimientos->total() }} en total.</div>
            </div>
            <span class="badge rounded-pill text-bg-light border">{{ $establecimientos->total() }} establecimiento(s)</span>
        </div>
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>RBD</th>
                        <th>Establecimiento</th>
                        <th>Comuna</th>
                        <th class="text-end">Matrícula</th>
                        <th class="text-end">Cursos</th>
                        <th class="text-end">Docentes</th>
                        <th class="text-end">Hrs plan</th>
                        <th class="text-end">Contrato docentes</th>
                        <th class="text-end">Bloque dotación</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($establecimientos as $establecimiento)
                        @php
                            $resumen = $establecimiento->dotacion_establecimiento_resumen ?? [];
                            $bloques = $establecimiento->dotacion_establecimiento_bloques ?? [];
                            $totalBloques = collect($bloques)->sum(fn ($bloque) => (float) ($bloque['total'] ?? 0));
                        @endphp
                        <tr>
                            <td class="text-nowrap fw-semibold">{{ $establecimiento->rbd }}</td>
                            <td><div class="fw-bold">{{ $establecimiento->nombre_establecimiento }}</div><div class="text-muted small">{{ $establecimiento->clasificacion ?? '—' }}</div></td>
                            <td>{{ $establecimiento->comuna ?: '—' }}</td>
                            <td class="text-end">{{ number_format((int) ($resumen['matricula_total'] ?? 0), 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format((int) ($resumen['cursos_total'] ?? 0), 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format((int) ($resumen['docentes_total'] ?? 0), 0, ',', '.') }}</td>
                            <td class="text-end fw-semibold text-primary">{{ $fmt($resumen['horas_plan_total'] ?? 0) }}</td>
                            <td class="text-end fw-semibold">{{ $fmt($resumen['horas_contrato_docentes'] ?? 0) }}</td>
                            <td class="text-end fw-semibold text-warning">{{ $fmt($totalBloques) }}</td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-primary rounded-pill px-3" href="{{ route('admin.dotacion-establecimiento.show', [$establecimiento, 'anio' => $anio]) }}"><i class="bi bi-eye"></i> Ver dotación</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center text-muted py-4">No se encontraron establecimientos para los filtros aplicados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($establecimientos->hasPages())
            <div class="card-footer bg-white">{{ $establecimientos->links() }}</div>
        @endif
    </div>
@endsection
