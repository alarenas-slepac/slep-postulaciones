@extends('layouts.app')

@section('content')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Asignaturas personalizadas</h1>
            <div class="text-muted small">Consulta asignaturas creadas por establecimientos en bloques flexibles y horas de libre disposición.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary" href="{{ route('admin.establecimiento-planes.index') }}">
                <i class="bi bi-ui-checks-grid"></i> Configurar planes EE
            </a>
        </div>
    </div>

    <div class="alert alert-info small">
        Esta vista es sólo de consulta. Las asignaturas personalizadas no se incorporan al catálogo maestro de Asignaturas; quedan vinculadas a la configuración del plan del establecimiento que las creó.
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Registros personalizados</div>
                    <div class="fs-4 fw-bold">{{ number_format((int) ($resumen->total_registros ?? 0), 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Establecimientos con personalizadas</div>
                    <div class="fs-4 fw-bold">{{ number_format((int) ($resumen->total_establecimientos ?? 0), 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Horas semanales declaradas</div>
                    <div class="fs-4 fw-bold">{{ number_format((float) ($resumen->total_horas_semanales ?? 0), 2, ',', '.') }}</div>
                </div>
            </div>
        </div>
    </div>

    <form method="GET" class="card card-body shadow-sm mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Buscar</label>
                <input type="text" class="form-control" name="q" value="{{ $q }}" placeholder="Asignatura, RBD, establecimiento, curso o bloque">
            </div>
            <div class="col-md-2">
                <label class="form-label">Año</label>
                <input type="number" class="form-control" name="anio" value="{{ $anio }}" placeholder="2026">
            </div>
            <div class="col-md-4">
                <label class="form-label">Establecimiento</label>
                <select class="form-select" name="establecimiento_id">
                    <option value="">Todos</option>
                    @foreach ($establecimientos as $comuna => $itemsEstablecimientos)
                        <optgroup label="{{ $comuna }}">
                            @foreach ($itemsEstablecimientos as $establecimiento)
                                <option value="{{ $establecimiento->id }}" @selected((string) $establecimientoId === (string) $establecimiento->id)>
                                    {{ $establecimiento->rbd }} — {{ $establecimiento->nombre_establecimiento }}
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Curso base</label>
                <select class="form-select" name="curso_id">
                    <option value="">Todos</option>
                    @foreach ($cursos as $curso)
                        <option value="{{ $curso->id }}" @selected((string) $cursoId === (string) $curso->id)>{{ $curso->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 d-flex flex-wrap gap-2">
                <button class="btn btn-outline-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
                <a class="btn btn-outline-danger" href="{{ route('admin.asignaturas-personalizadas.index') }}">Limpiar</a>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th>Asignatura personalizada</th>
                        <th>Establecimiento</th>
                        <th>Curso/sección</th>
                        <th>Bloque</th>
                        <th class="text-end">Horas sem.</th>
                        <th class="text-end">Horas anuales</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        @php
                            $estadoLabel = ucfirst(str_replace('_', ' ', (string) $item->configuracion_estado));
                            $badge = match ($item->configuracion_estado) {
                                'enviado' => 'text-bg-info',
                                'observado' => 'text-bg-warning',
                                'aprobado' => 'text-bg-success',
                                'cerrado' => 'text-bg-secondary',
                                default => 'text-bg-primary',
                            };
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $item->nombre }}</div>
                                @if ($item->observacion)
                                    <div class="small text-muted">{{ $item->observacion }}</div>
                                @endif
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $item->rbd }} — {{ $item->establecimiento_nombre }}</div>
                                <div class="small text-muted">{{ $item->establecimiento_comuna ?: 'Sin comuna' }}</div>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $item->nombre_seccion ?: $item->curso_nombre }}</div>
                                <div class="small text-muted">{{ $item->curso_nombre }} · {{ $item->regimen_jec ?: 'Sin régimen' }} · Matrícula {{ number_format((int) ($item->matricula ?? 0), 0, ',', '.') }}</div>
                            </td>
                            <td>
                                <div>{{ $item->bloque_nombre ?: 'Sin bloque' }}</div>
                                <div class="small text-muted">{{ $item->plan_nombre ?: 'Sin plan' }}</div>
                            </td>
                            <td class="text-end fw-semibold">{{ number_format((float) $item->horas_semanales, 2, ',', '.') }}</td>
                            <td class="text-end">{{ $item->horas_anuales !== null ? number_format((float) $item->horas_anuales, 2, ',', '.') : '—' }}</td>
                            <td><span class="badge {{ $badge }}">{{ $estadoLabel }}</span></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-info" href="{{ route('admin.establecimiento-planes.show', $item->configuracion_id) }}">Ver plan</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No hay asignaturas personalizadas registradas para los filtros seleccionados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">
            {{ $items->links() }}
        </div>
    </div>
@endsection
