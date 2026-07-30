@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <div class="text-uppercase text-muted small fw-bold">Catálogos</div>
            <h1 class="h3 mb-0">Viáticos y reembolsos</h1>
            <p class="text-muted mb-0">Valores vigentes por estamento, cargo/función y tramo, incluyendo 100%, 60% y 40%.</p>
        </div>
        <a href="{{ route('admin.viaticos-reembolsos.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Nuevo valor
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Estamento</label>
                    <select name="estamento" class="form-select">
                        <option value="">Todos</option>
                        @foreach($estamentos as $estamento)
                            <option value="{{ $estamento }}" @selected(($filters['estamento'] ?? '') === $estamento)>{{ $estamento }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Cargo / función</label>
                    <input type="text" name="cargo_funcion" value="{{ $filters['cargo_funcion'] ?? '' }}" class="form-control" placeholder="Buscar...">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Estado</label>
                    <select name="activo" class="form-select">
                        <option value="">Todos</option>
                        <option value="1" @selected(($filters['activo'] ?? '') === '1')>Activos</option>
                        <option value="0" @selected(($filters['activo'] ?? '') === '0')>Inactivos</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Fecha referencia</label>
                    <input type="date" name="fecha_referencia" value="{{ $filters['fecha_referencia'] ?? '' }}" class="form-control">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-outline-primary flex-fill" type="submit">Filtrar</button>
                    <a href="{{ route('admin.viaticos-reembolsos.index') }}" class="btn btn-light">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.viaticos-reembolsos.activar-vigentes') }}" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Activar registros vigentes a fecha</label>
                    <input type="date" name="fecha_referencia" value="{{ now()->toDateString() }}" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-success">Activar vigentes</button>
                </div>
                <div class="col-md-4 text-muted small">
                    Desactiva registros fuera de vigencia y activa los que cubren la fecha seleccionada.
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Estamento</th>
                        <th>Cargo / función</th>
                        <th>Vigencia</th>
                        <th class="text-end">100%</th>
                        <th class="text-end">60%</th>
                        <th class="text-end">40%</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($valores as $valor)
                        <tr>
                            <td class="fw-semibold">{{ $valor->estamento }}</td>
                            <td>{{ $valor->cargo_funcion }}</td>
                            <td>
                                {{ optional($valor->vigente_desde)->format('d-m-Y') }}
                                <span class="text-muted">a</span>
                                {{ optional($valor->vigente_hasta)->format('d-m-Y') ?: 'Sin término' }}
                            </td>
                            <td class="text-end fw-semibold">${{ number_format((int) $valor->valor_100, 0, ',', '.') }}</td>
                            <td class="text-end fw-semibold text-primary">${{ number_format((int) ($valor->valor_60 ?? $valor->valor_60_calculado), 0, ',', '.') }}</td>
                            <td class="text-end fw-semibold">${{ number_format((int) $valor->valor_40, 0, ',', '.') }}</td>
                            <td>
                                <span class="badge rounded-pill {{ $valor->activo ? 'text-bg-success' : 'text-bg-secondary' }}">
                                    {{ $valor->activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.viaticos-reembolsos.edit', $valor) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                                <form method="POST" action="{{ route('admin.viaticos-reembolsos.destroy', $valor) }}" class="d-inline" onsubmit="return confirm('¿Eliminar este valor?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No existen registros para los filtros seleccionados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">
            {{ $valores->links() }}
        </div>
    </div>
</div>
@endsection
