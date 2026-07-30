@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 mb-0">Áreas de desempeño</h1>
        <a class="btn btn-primary" href="{{ route('admin.areas-desempeno.create') }}">Nueva área</a>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form class="card card-body mb-3" method="GET" action="{{ route('admin.areas-desempeno.index') }}">
        <div class="row g-2">
            <div class="col-md-3">
                <label class="form-label">Estamento</label>
                <select name="estamento" class="form-select">
                    <option value="">Todos</option>
                    <option value="docente" @selected($estamento === 'docente')>Docente</option>
                    <option value="asistente" @selected($estamento === 'asistente')>Asistente</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Buscar</label>
                <input type="text" name="q" class="form-control" value="{{ $q }}"
                    placeholder="Nombre del área...">
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button class="btn btn-outline-primary w-100">Filtrar</button>
                <a class="btn btn-outline-secondary w-100" href="{{ route('admin.areas-desempeno.index') }}">Limpiar</a>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Estamento</th>
                        <th>Nombre</th>
                        <th>Activo</th>
                        <th class="text-end" style="width: 160px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($areas as $a)
                        <tr>
                            <td>{{ $a->estamento }}</td>
                            <td>{{ $a->nombre }}</td>
                            <td>
                                @if ($a->activo)
                                <span class="badge text-bg-success">Sí</span>
                                @else
                                    <span class="badge bg-secondary">No</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary"
                                    href="{{ route('admin.areas-desempeno.edit', $a) }}">
                                    Editar
                                </a>

                                <form class="d-inline" method="POST"
                                    action="{{ route('admin.areas-desempeno.destroy', $a) }}"
                                    onsubmit="return confirm('¿Eliminar área?');">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">Sin resultados</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-body">
            {{ $areas->links() }}
        </div>
    </div>
@endsection
