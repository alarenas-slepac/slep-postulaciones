@extends('layouts.app')

@section('content')
    <div class="slep-card p-4 d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
        <div>
            <div class="small text-muted text-uppercase mb-2"><i class="bi bi-people me-1" aria-hidden="true"></i> Planificación de dotación</div>
            <h1 class="h2 fw-bold mb-1">Dotación funciones y planes</h1>
            <div class="text-muted small">Consolidado de funciones directivas, técnico-pedagógicas normativas, PIE, planes normativos y otras funciones declaradas y/o no normativas.</div>
        </div>
        @if (\App\Support\DotacionConvivenciaAnual::puedeConfigurar($activeRole))
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('admin.dotacion-funciones.convivencia.index', ['anio' => $anio]) }}" class="btn btn-primary rounded-pill"><i class="bi bi-file-earmark-spreadsheet me-1" aria-hidden="true"></i> Carga de horas de Convivencia</a>
                <a href="{{ route('admin.dotacion-funciones.maximos.index', ['anio' => $anio]) }}" class="btn btn-outline-primary rounded-pill"><i class="bi bi-upload me-1" aria-hidden="true"></i> Carga de máximos por bloque</a>
            </div>
        @endif
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="GET" class="slep-card p-4 mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-lg-4 col-md-6">
                <label for="dotacion-q" class="form-label">Buscar</label>
                <input id="dotacion-q" type="text" class="form-control rounded-3" name="q" value="{{ $q }}" placeholder="RBD, establecimiento o comuna">
            </div>
            <div class="col-lg-2 col-md-3">
                <label for="dotacion-anio" class="form-label">Año</label>
                <input id="dotacion-anio" type="number" class="form-control rounded-3" name="anio" value="{{ $anio }}" min="2020" max="2100">
            </div>
            <div class="col-lg-3 col-md-4">
                <label for="dotacion-comuna" class="form-label">Comuna</label>
                <select id="dotacion-comuna" class="form-select rounded-3" name="comuna" @disabled($activeRole === 'funcionario_directivo_estab')>
                    <option value="">Todas</option>
                    @foreach ($comunas as $comunaOpcion)
                        <option value="{{ $comunaOpcion }}" @selected($comuna === $comunaOpcion)>{{ $comunaOpcion }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3 col-md-5 d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-outline-primary rounded-pill"><i class="bi bi-funnel"></i> Filtrar</button>
                <a href="{{ route('admin.dotacion-funciones.index') }}" class="btn btn-outline-secondary rounded-pill">Limpiar</a>
            </div>
        </div>
    </form>

    <div class="alert alert-info rounded-4 small mb-4" role="note">
        <div class="fw-semibold"><i class="bi bi-grid-3x3-gap"></i> Consolidado por establecimiento</div>
        <div>Las horas se separan en <strong>Directivos</strong>, <strong>Técnico-pedagógicas normativas</strong>, <strong>PIE</strong>, <strong>Planes</strong> y <strong>Otras funciones declaradas y/o no normativas</strong>. Los establecimientos marcados como sala cuna no participan en este proceso.</div>
    </div>

    <div class="slep-card overflow-hidden">
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>RBD</th>
                        <th>Establecimiento</th>
                        <th>Comuna</th>
                        <th class="text-end">Matrícula</th>
                        <th class="text-end">Cursos NEE</th>
                        <th class="text-end">Directivos</th>
                        <th class="text-end">Téc. ped.</th>
                        <th class="text-end">PIE</th>
                        <th class="text-end">Planes</th>
                        <th class="text-end">Otras decl./no norm.</th>
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
                            <td colspan="13" class="text-center text-muted py-4">No se encontraron establecimientos para los filtros aplicados.</td>
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
