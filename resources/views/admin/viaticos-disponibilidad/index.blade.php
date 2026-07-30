@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <div class="text-uppercase text-muted small fw-semibold">Control presupuestario</div>
            <h1 class="h3 mb-1">Disponibilidad presupuestaria de viáticos</h1>
            <p class="text-muted mb-0">Mantenedor de montos disponibles para viáticos de Administración Central y Establecimientos.</p>
        </div>
        <a href="{{ route('admin.viaticos-disponibilidad.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Nuevo registro
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm">{{ session('success') }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="text-muted small fw-semibold text-uppercase">Monto inicial</div>
                    <div class="fs-4 fw-bold">${{ number_format((int) ($resumen->monto_inicial ?? 0), 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="text-muted small fw-semibold text-uppercase">Comprometido</div>
                    <div class="fs-4 fw-bold text-warning">${{ number_format((int) ($resumen->monto_comprometido ?? 0), 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="text-muted small fw-semibold text-uppercase">Ejecutado</div>
                    <div class="fs-4 fw-bold text-info">${{ number_format((int) ($resumen->monto_ejecutado ?? 0), 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="text-muted small fw-semibold text-uppercase">Saldo disponible</div>
                    <div class="fs-4 fw-bold text-success">${{ number_format((int) ($resumen->saldo_disponible ?? 0), 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">Año</label>
                    <input type="number" name="anio" class="form-control" value="{{ $filters['anio'] ?? '' }}" min="2020" max="2100" placeholder="{{ now()->year }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Origen</label>
                    <select name="origen_tipo" class="form-select">
                        <option value="">Todos</option>
                        @foreach($origenes as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['origen_tipo'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Estado</label>
                    <select name="activo" class="form-select">
                        <option value="">Todos</option>
                        <option value="1" @selected(($filters['activo'] ?? '') === '1')>Activos</option>
                        <option value="0" @selected(($filters['activo'] ?? '') === '0')>Inactivos</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary flex-fill"><i class="bi bi-filter me-1"></i> Filtrar</button>
                    <a href="{{ route('admin.viaticos-disponibilidad.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Año</th>
                        <th>Origen</th>
                        <th class="text-end">Inicial</th>
                        <th class="text-end">Comprometido</th>
                        <th class="text-end">Ejecutado</th>
                        <th class="text-end">Saldo</th>
                        <th>Vigencia</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($disponibilidades as $disponibilidad)
                        <tr>
                            <td class="fw-semibold">{{ $disponibilidad->anio }}</td>
                            <td>
                                <span class="badge rounded-pill text-bg-primary bg-opacity-10 text-primary border border-primary-subtle">{{ $disponibilidad->origenLabel() }}</span>
                                @if($disponibilidad->observaciones)
                                    <div class="small text-muted mt-1">{{ Str::limit($disponibilidad->observaciones, 80) }}</div>
                                @endif
                            </td>
                            <td class="text-end">${{ number_format((int) $disponibilidad->monto_inicial, 0, ',', '.') }}</td>
                            <td class="text-end text-warning">${{ number_format((int) $disponibilidad->monto_comprometido, 0, ',', '.') }}</td>
                            <td class="text-end text-info">${{ number_format((int) $disponibilidad->monto_ejecutado, 0, ',', '.') }}</td>
                            <td class="text-end">
                                <div class="fw-semibold text-success">${{ number_format((int) $disponibilidad->saldo_disponible, 0, ',', '.') }}</div>
                                <div class="progress mt-1" style="height: 6px; min-width: 120px;">
                                    <div class="progress-bar bg-success" style="width: {{ min(100, $disponibilidad->porcentajeDisponible()) }}%"></div>
                                </div>
                            </td>
                            <td class="small">
                                Desde {{ optional($disponibilidad->vigente_desde)->format('d-m-Y') }}<br>
                                Hasta {{ optional($disponibilidad->vigente_hasta)->format('d-m-Y') ?: 'Sin término' }}
                            </td>
                            <td>
                                @if($disponibilidad->activo)
                                    <span class="badge rounded-pill text-bg-success">Activo</span>
                                @else
                                    <span class="badge rounded-pill text-bg-secondary">Inactivo</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.viaticos-disponibilidad.edit', $disponibilidad) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil-square"></i> Editar
                                </a>
                                <form action="{{ route('admin.viaticos-disponibilidad.destroy', $disponibilidad) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este registro de disponibilidad?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-5">No existen registros de disponibilidad presupuestaria.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($disponibilidades->hasPages())
            <div class="card-footer bg-white border-0">
                {{ $disponibilidades->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
