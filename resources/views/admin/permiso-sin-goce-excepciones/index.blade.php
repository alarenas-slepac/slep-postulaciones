@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h1 class="h4 mb-1">Excepciones permiso sin goce</h1>
        <p class="text-muted mb-0">Titulares docentes autorizados para solicitar el tipo de reemplazo “Permiso sin goce de sueldo”.</p>
    </div>
    <a href="{{ route('admin.permiso-sin-goce-excepciones.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Agregar excepción
    </a>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label">Buscar</label>
                <input type="text" name="q" class="form-control" value="{{ $search }}" placeholder="RUT, nombre u observación">
            </div>
            <div class="col-md-3">
                <label class="form-label">Estado</label>
                <select name="estado" class="form-select">
                    <option value="activos" @selected($estado === 'activos')>Activos</option>
                    <option value="todos" @selected($estado === 'todos')>Todos</option>
                    <option value="inactivos" @selected($estado === 'inactivos')>Inactivos</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-outline-primary" type="submit">Filtrar</button>
                <a class="btn btn-outline-secondary" href="{{ route('admin.permiso-sin-goce-excepciones.index') }}">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>RUT</th>
                    <th>Titular</th>
                    <th>Estado</th>
                    <th>Observación</th>
                    <th>Actualización</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td class="fw-semibold">{{ $item->rut_normalizado }}</td>
                        <td>{{ $item->nombre_titular ?: '—' }}</td>
                        <td>
                            @if ($item->activo)
                                <span class="badge bg-success">Activo</span>
                            @else
                                <span class="badge bg-secondary">Inactivo</span>
                            @endif
                        </td>
                        <td class="text-muted">{{ $item->observacion ?: '—' }}</td>
                        <td class="small text-muted">
                            {{ optional($item->updated_at)->format('d/m/Y H:i') ?: '—' }}
                            @if ($item->actualizador)
                                <br>{{ $item->actualizador->nombre_completo ?: $item->actualizador->email }}
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a class="btn btn-outline-primary" href="{{ route('admin.permiso-sin-goce-excepciones.edit', $item) }}">Editar</a>
                                <form method="POST" action="{{ route('admin.permiso-sin-goce-excepciones.toggle', $item) }}" onsubmit="return confirm('¿Confirmas cambiar el estado de esta excepción?')">
                                    @csrf
                                    <button class="btn btn-outline-{{ $item->activo ? 'warning' : 'success' }}" type="submit">
                                        {{ $item->activo ? 'Desactivar' : 'Activar' }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No hay excepciones registradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($items->hasPages())
        <div class="card-footer">{{ $items->links() }}</div>
    @endif
</div>
@endsection
