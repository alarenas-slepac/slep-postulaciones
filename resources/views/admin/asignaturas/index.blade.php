@extends('layouts.app')

@section('content')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Asignaturas</h1>
            <div class="text-muted small">Catálogo administrativo de asignaturas oficiales, diferenciadas, electivas, libre disposición y personalizadas.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-success" href="{{ route('admin.asignaturas.template') }}">
                <i class="bi bi-file-earmark-excel"></i> Plantilla
            </a>
            <a class="btn btn-outline-primary" href="{{ route('admin.asignaturas.import') }}">
                <i class="bi bi-upload"></i> Carga masiva
            </a>
            <a class="btn btn-primary" href="{{ route('admin.asignaturas.create') }}">
                <i class="bi bi-plus-circle"></i> Nueva asignatura
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
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
            <div class="col-md-3">
                <label class="form-label">Buscar</label>
                <input type="text" class="form-control" name="q" value="{{ $q }}" placeholder="Nombre, código u observación">
            </div>
            <div class="col-md-2">
                <label class="form-label">Nivel</label>
                <select class="form-select" name="nivel_educativo">
                    <option value="">Todos</option>
                    @foreach ($niveles as $itemNivel)
                        <option value="{{ $itemNivel }}" @selected($nivel === $itemNivel)>{{ $itemNivel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Área</label>
                <select class="form-select" name="area">
                    <option value="">Todas</option>
                    @foreach ($areas as $itemArea)
                        <option value="{{ $itemArea }}" @selected($area === $itemArea)>{{ $itemArea }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tipo</label>
                <select class="form-select" name="tipo_asignatura">
                    <option value="">Todos</option>
                    @foreach ($tipos as $tipoKey => $tipoLabel)
                        <option value="{{ $tipoKey }}" @selected($tipo === $tipoKey)>{{ $tipoLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label">Oficial</label>
                <select class="form-select" name="es_oficial">
                    <option value="">Todos</option>
                    <option value="1" @selected($oficial === '1')>Sí</option>
                    <option value="0" @selected($oficial === '0')>No</option>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label">Estado</label>
                <select class="form-select" name="activo">
                    <option value="">Todos</option>
                    <option value="1" @selected($activo === '1')>Activas</option>
                    <option value="0" @selected($activo === '0')>Inactivas</option>
                </select>
            </div>
            <div class="col-md-1 d-grid">
                <button class="btn btn-outline-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
            </div>
            <div class="col-12">
                <a class="btn btn-outline-danger" href="{{ route('admin.asignaturas.index') }}">Limpiar</a>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th>Asignatura</th>
                        <th>Código</th>
                        <th>Nivel</th>
                        <th>Área</th>
                        <th>Tipo</th>
                        <th>Origen</th>
                        <th>Estado</th>
                        <th class="text-end" style="width: 230px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($asignaturas as $asignatura)
                        <tr>
                            <td class="fw-semibold">{{ $asignatura->nombre }}</td>
                            <td>{{ $asignatura->codigo }}</td>
                            <td>{{ $asignatura->nivel_educativo ?: '—' }}</td>
                            <td>{{ $asignatura->area ?: '—' }}</td>
                            <td><span class="badge bg-primary">{{ $asignatura->tipo_asignatura_label }}</span></td>
                            <td>
                                @if ($asignatura->es_oficial)
                                    <span class="badge text-bg-info">Oficial</span>
                                @else
                                    <span class="badge text-bg-secondary">Propia</span>
                                @endif
                            </td>
                            <td>
                                @if ($asignatura->activo)
                                    <span class="badge text-bg-success">Activa</span>
                                @else
                                    <span class="badge bg-secondary">Inactiva</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                    <a class="btn btn-sm btn-outline-info" href="{{ route('admin.asignaturas.show', $asignatura) }}">Ver</a>
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.asignaturas.edit', $asignatura) }}">Editar</a>
                                    <form method="POST" action="{{ route('admin.asignaturas.destroy', $asignatura) }}" onsubmit="return confirm('¿Eliminar esta asignatura?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No hay asignaturas registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">
            {{ $asignaturas->links() }}
        </div>
    </div>
@endsection
