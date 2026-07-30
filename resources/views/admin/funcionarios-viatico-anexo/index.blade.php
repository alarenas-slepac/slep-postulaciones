@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h1 class="h3 mb-1">Funcionarios con viático por anexo</h1>
            <p class="text-muted mb-0">RUT habilitados para mostrar la casilla de viático en cometidos funcionarios.</p>
        </div>
        <a href="{{ route('admin.funcionarios-viatico-anexo.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Nuevo registro
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-7">
                    <label class="form-label" for="q">Buscar</label>
                    <input type="search" name="q" id="q" class="form-control" value="{{ request('q') }}" placeholder="RUT, nombre, establecimiento o cargo">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="estado">Estado</label>
                    <select name="estado" id="estado" class="form-select">
                        <option value="">Todos</option>
                        <option value="activos" @selected(request('estado') === 'activos')>Activos</option>
                        <option value="inactivos" @selected(request('estado') === 'inactivos')>Inactivos</option>
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-outline-primary">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>RUT</th>
                        <th>Funcionario</th>
                        <th>Establecimiento</th>
                        <th>Cargo / función</th>
                        <th>Estado</th>
                        <th>Validación</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($registros as $registro)
                        <tr>
                            <td class="fw-semibold">{{ $registro->rut }}</td>
                            <td>{{ $registro->nombre_completo ?: 'No informado' }}</td>
                            <td>{{ $registro->establecimiento_nombre ?: 'No informado' }}</td>
                            <td>
                                <div>{{ $registro->cargo_funcion ?: 'Sin cargo' }}</div>
                                <small class="text-muted">{{ $registro->estamento ?: 'Sin estamento' }}</small>
                            </td>
                            <td>
                                <span class="badge {{ $registro->activo ? 'text-bg-success' : 'text-bg-secondary' }}">
                                    {{ $registro->activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td>{{ optional($registro->validado_at)->format('d-m-Y H:i') ?: 'Sin validación' }}</td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="{{ route('admin.funcionarios-viatico-anexo.edit', $registro) }}" class="btn btn-outline-primary">Editar</a>
                                    <form method="POST" action="{{ route('admin.funcionarios-viatico-anexo.toggle', $registro) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-secondary">{{ $registro->activo ? 'Desactivar' : 'Activar' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.funcionarios-viatico-anexo.destroy', $registro) }}" class="d-inline" onsubmit="return confirm('¿Eliminar este registro?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger">Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">No hay funcionarios registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">
            {{ $registros->links() }}
        </div>
    </div>
</div>
@endsection
