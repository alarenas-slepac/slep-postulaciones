@extends('layouts.app')

@section('content')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Porcentaje de Alumnos Prioritarios</h1>
            <div class="text-muted small">Mantenedor administrativo de porcentajes por establecimiento y año.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-success" href="{{ route('admin.alumnos-prioritarios.template') }}">
                <i class="bi bi-file-earmark-excel"></i> Plantilla
            </a>
            <a class="btn btn-outline-primary" href="{{ route('admin.alumnos-prioritarios.import') }}">
                <i class="bi bi-upload"></i> Carga masiva
            </a>
            <a class="btn btn-primary" href="{{ route('admin.alumnos-prioritarios.create') }}">
                <i class="bi bi-plus-circle"></i> Nuevo registro
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (session('import_errors'))
        <div class="alert alert-warning">
            <div class="fw-semibold mb-1">Observaciones de la última carga masiva</div>
            <ul class="mb-0">
                @foreach (session('import_errors') as $importError)
                    <li>{{ $importError }}</li>
                @endforeach
            </ul>
            <div class="small mt-2">Se muestran como máximo las primeras 80 observaciones.</div>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="GET" class="card card-body shadow-sm mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label">Año</label>
                <input type="number" class="form-control" name="anio" min="2020" max="2100" value="{{ $anio }}" placeholder="Todos">
            </div>
            <div class="col-md-3">
                <label class="form-label">Comuna</label>
                <select class="form-select" name="comuna">
                    <option value="">Todas</option>
                    @foreach ($comunas as $itemComuna)
                        <option value="{{ $itemComuna }}" @selected($comuna === $itemComuna)>{{ $itemComuna }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">Buscar establecimiento / RBD</label>
                <input type="text" class="form-control" name="q" value="{{ $q }}" placeholder="Nombre o RBD">
            </div>
            <div class="col-md-2 d-grid">
                <button class="btn btn-outline-primary" type="submit">
                    <i class="bi bi-funnel"></i> Filtrar
                </button>
            </div>
        </div>
        @if ($anio !== '' || $comuna !== '' || $q !== '')
            <div class="mt-2">
                <a href="{{ route('admin.alumnos-prioritarios.index') }}" class="btn btn-sm btn-outline-danger">Limpiar filtros</a>
            </div>
        @endif
    </form>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th>Año</th>
                        <th>RBD</th>
                        <th>Establecimiento</th>
                        <th>Comuna</th>
                        <th class="text-end">% Prioritarios</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td><span class="badge text-bg-primary">{{ $item->anio }}</span></td>
                            <td>{{ $item->establecimiento?->rbd }}</td>
                            <td class="fw-semibold">{{ $item->establecimiento?->nombre_establecimiento ?? 'Establecimiento no disponible' }}</td>
                            <td>{{ $item->establecimiento?->comuna ?? '—' }}</td>
                            <td class="text-end fw-semibold">{{ number_format((float) $item->porcentaje, 2, ',', '.') }}%</td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-info" href="{{ route('admin.alumnos-prioritarios.show', $item) }}">Ver</a>
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.alumnos-prioritarios.edit', $item) }}">Editar</a>
                                <form method="POST" action="{{ route('admin.alumnos-prioritarios.destroy', $item) }}" class="d-inline" onsubmit="return confirm('¿Eliminar este porcentaje de alumnos prioritarios?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No hay registros de porcentaje de alumnos prioritarios.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($items->hasPages())
            <div class="card-body">{{ $items->links() }}</div>
        @endif
    </div>
@endsection
