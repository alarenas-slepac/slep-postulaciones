@extends('layouts.app')

@section('content')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Cursos</h1>
            <div class="text-muted small">Mantenedor administrativo de cursos y niveles para plantas y planes de estudio.</div>
        </div>
        <a class="btn btn-primary" href="{{ route('admin.cursos.create') }}">
            <i class="bi bi-plus-circle"></i> Nuevo curso
        </a>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
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
            <div class="col-md-4">
                <label class="form-label">Buscar</label>
                <input type="text" class="form-control" name="q" value="{{ $q }}" placeholder="Nombre o código">
            </div>
            <div class="col-md-3">
                <label class="form-label">Nivel educativo</label>
                <select class="form-select" name="nivel_educativo">
                    <option value="">Todos</option>
                    @foreach ($niveles as $itemNivel)
                        <option value="{{ $itemNivel }}" @selected($nivel === $itemNivel)>{{ $itemNivel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Modalidad</label>
                <select class="form-select" name="modalidad">
                    <option value="">Todas</option>
                    @foreach ($modalidades as $itemModalidad)
                        <option value="{{ $itemModalidad }}" @selected($modalidad === $itemModalidad)>{{ $itemModalidad }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Estado</label>
                <select class="form-select" name="activo">
                    <option value="">Todos</option>
                    <option value="1" @selected($activo === '1')>Activos</option>
                    <option value="0" @selected($activo === '0')>Inactivos</option>
                </select>
            </div>
            <div class="col-12 d-flex flex-wrap gap-2">
                <button class="btn btn-outline-primary" type="submit">
                    <i class="bi bi-funnel"></i> Filtrar
                </button>
                <a class="btn btn-outline-danger" href="{{ route('admin.cursos.index') }}">Limpiar</a>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 80px;">Orden</th>
                        <th>Curso</th>
                        <th>Código</th>
                        <th>Nivel educativo</th>
                        <th>Modalidad</th>
                        <th>Estado</th>
                        <th class="text-end" style="width: 230px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($cursos as $curso)
                        <tr>
                            <td>{{ $curso->orden }}</td>
                            <td class="fw-semibold">{{ $curso->nombre }}</td>
                            <td>{{ $curso->codigo }}</td>
                            <td>{{ $curso->nivel_educativo }}</td>
                            <td>{{ $curso->modalidad ?: '—' }}</td>
                            <td>
                                @if ($curso->activo)
                                    <span class="badge text-bg-success">Activo</span>
                                @else
                                    <span class="badge bg-secondary">Inactivo</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                    <a class="btn btn-sm btn-outline-info" href="{{ route('admin.cursos.show', $curso) }}">Ver</a>
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.cursos.edit', $curso) }}">Editar</a>
                                    <form method="POST" action="{{ route('admin.cursos.destroy', $curso) }}" onsubmit="return confirm('¿Eliminar este curso?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No hay cursos registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">
            {{ $cursos->links() }}
        </div>
    </div>
@endsection
