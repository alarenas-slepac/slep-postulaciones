@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 mb-0">Valores hora por establecimiento</h1>
        <a class="btn btn-primary" href="{{ route('admin.establecimiento-valores-hora.create') }}">Nuevo valor</a>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="GET" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-7">
                <label class="form-label">Establecimiento</label>
                <select class="form-select" name="establecimiento">
                    <option value="">— Todos —</option>
                    @foreach ($establecimientos as $e)
                        @php $name = $e->nombre_establecimiento ?? $e->nombre ?? '—'; @endphp
                        <option value="{{ $e->id }}" @selected((string) $e->id === (string) $establecimientoId)>
                            {{ $e->rbd }} - {{ $name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Rol</label>
                <select class="form-select" name="rol">
                    <option value="">— Todos —</option>
                    @foreach ($roles as $k => $label)
                        <option value="{{ $k }}" @selected($k === $rol)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-grid">
                <button class="btn btn-outline-secondary" type="submit">Filtrar</button>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Establecimiento</th>
                        <th>Rol</th>
                        <th class="text-end">Valor hora</th>
                        <th>Activo</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $it)
                        <tr>
                            @php $name = $it->establecimiento?->nombre_establecimiento ?? $it->establecimiento?->nombre ?? '—'; @endphp
                            <td>{{ $it->establecimiento?->rbd }} - {{ $name }}</td>
                            <td>{{ $roles[$it->rol] ?? $it->rol }}</td>
                            <td class="text-end">${{ number_format((float) $it->valor_hora, 0, ',', '.') }}</td>
                            <td>{!! $it->activo ? '<span class="badge text-bg-success">Sí</span>' : '<span class="badge text-bg-secondary">No</span>' !!}</td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.establecimiento-valores-hora.edit', $it) }}">Editar</a>
                                <form method="POST" action="{{ route('admin.establecimiento-valores-hora.destroy', $it) }}" class="d-inline"
                                    onsubmit="return confirm('¿Eliminar este valor?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">Sin registros.</td>
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
