@extends('layouts.app')

@section('content')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Dotación funciones y planes</h1>
            <div class="text-muted small">Consolidado de funciones directivas, técnico-pedagógicas, PIE, planes normativos y otras funciones docentes declaradas.</div>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="GET" class="card card-body shadow-sm mb-3">
        <div class="row g-3 align-items-end">
            <div class="col-lg-4 col-md-6">
                <label class="form-label">Buscar</label>
                <input type="text" class="form-control" name="q" value="{{ $q }}" placeholder="RBD, establecimiento o comuna">
            </div>
            <div class="col-lg-2 col-md-3">
                <label class="form-label">Año</label>
                <input type="number" class="form-control" name="anio" value="{{ $anio }}" min="2020" max="2100">
            </div>
            <div class="col-lg-3 col-md-4">
                <label class="form-label">Comuna</label>
                <select class="form-select" name="comuna" @disabled($activeRole === 'funcionario_directivo_estab')>
                    <option value="">Todas</option>
                    @foreach ($comunas as $comunaOpcion)
                        <option value="{{ $comunaOpcion }}" @selected($comuna === $comunaOpcion)>{{ $comunaOpcion }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3 col-md-5 d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-outline-primary"><i class="bi bi-funnel"></i> Filtrar</button>
                <a href="{{ route('admin.dotacion-funciones.index') }}" class="btn btn-outline-danger">Limpiar</a>
            </div>
        </div>
    </form>

    <div class="alert alert-info shadow-sm small mb-3">
        <div class="fw-semibold"><i class="bi bi-grid-3x3-gap"></i> Consolidado por establecimiento</div>
        <div>Las horas se separan en <strong>Directivos</strong>, <strong>Técnico-pedagógicas</strong>, <strong>PIE</strong>, <strong>Planes</strong> y <strong>Otras funciones declaradas</strong>. Los establecimientos marcados como sala cuna no participan en este proceso.</div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th>RBD</th>
                        <th>Establecimiento</th>
                        <th>Comuna</th>
                        <th class="text-end">Matrícula</th>
                        <th class="text-end">Cursos NEE</th>
                        <th class="text-end">NT1+NT2</th>
                        <th class="text-end">Directivos</th>
                        <th class="text-end">Téc. ped.</th>
                        <th class="text-end">PIE</th>
                        <th class="text-end">Planes</th>
                        <th class="text-end">Otras</th>
                        <th class="text-end">Total hrs</th>
                        <th class="text-end">Pendientes</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($establecimientos as $establecimiento)
                        @php
                            $resumen = $establecimiento->dotacion_resumen ?? [];
                            $consolidado = $resumen['consolidado_por_bloque'] ?? [];
                        @endphp
                        <tr>
                            <td class="text-nowrap">{{ $establecimiento->rbd }}</td>
                            <td>
                                <div class="fw-semibold">{{ $establecimiento->nombre_establecimiento }}</div>
                                <div class="text-muted small">{{ $establecimiento->clasificacion ?? '—' }}</div>
                            </td>
                            <td>{{ $establecimiento->comuna ?: '—' }}</td>
                            <td class="text-end">{{ number_format((int) ($resumen['matricula_total'] ?? 0), 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format((int) ($resumen['cursos_nee'] ?? 0), 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format((int) ($resumen['matricula_nt1_nt2'] ?? 0), 0, ',', '.') }}</td>
                            <td class="text-end fw-semibold text-primary">{{ number_format((int) ($consolidado['directiva']['total'] ?? 0), 0, ',', '.') }}</td>
                            <td class="text-end fw-semibold text-success">{{ number_format((int) ($consolidado['tecnico_pedagogica']['total'] ?? 0), 0, ',', '.') }}</td>
                            <td class="text-end fw-semibold text-info">{{ number_format((int) ($consolidado['pie']['total'] ?? 0), 0, ',', '.') }}</td>
                            <td class="text-end fw-semibold text-warning">{{ number_format((int) ($consolidado['planes_programas']['total'] ?? 0), 0, ',', '.') }}</td>
                            <td class="text-end fw-semibold">{{ number_format((int) ($consolidado['otras_funciones_docentes']['total'] ?? 0), 0, ',', '.') }}</td>
                            <td class="text-end fs-6 fw-bold text-primary">{{ number_format((int) ($resumen['horas_totales'] ?? 0), 0, ',', '.') }}</td>
                            <td class="text-end">
                                @if ((int) ($resumen['pendientes_revision'] ?? 0) > 0)
                                    <span class="badge text-bg-warning">{{ (int) $resumen['pendientes_revision'] }}</span>
                                @else
                                    <span class="badge text-bg-success">0</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.dotacion-funciones.show', [$establecimiento, 'anio' => $anio]) }}">
                                    <i class="bi bi-eye"></i> Ver dotación
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="14" class="text-center text-muted py-4">No se encontraron establecimientos para los filtros aplicados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($establecimientos->hasPages())
            <div class="card-footer bg-white">
                {{ $establecimientos->links() }}
            </div>
        @endif
    </div>
@endsection
