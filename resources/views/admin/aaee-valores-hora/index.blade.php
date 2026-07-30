@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 mb-0">Valores hora AAEE</h1>
        <a class="btn btn-primary" href="{{ route('admin.aaee-valores-hora.create') }}">Nuevo valor</a>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="GET" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label">Área de desempeño</label>
                <select class="form-select" name="area">
                    <option value="">— Todas —</option>
                    @foreach ($areas as $a)
                        <option value="{{ $a->id }}" @selected((string) $a->id === (string) $areaId)>{{ $a->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Categoría</label>
                <select class="form-select" name="categoria">
                    <option value="">— Todas —</option>
                    @foreach ($categorias as $c)
                        <option value="{{ $c }}" @selected($c === $categoria)>{{ ucfirst($c) }}</option>
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
                        <th>Área</th>
                        <th>Categoría</th>
                        <th class="text-end">Valor hora</th>
                        <th>Activo</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $it)
                        <tr>
                            <td>{{ $it->areaDesempeno?->nombre ?? '—' }}</td>
                            <td>{{ ucfirst($it->categoria) }}</td>
                            <td class="text-end">${{ number_format((float) $it->valor_hora, 0, ',', '.') }}</td>
                            <td>{!! $it->activo ? '<span class="badge text-bg-success">Sí</span>' : '<span class="badge text-bg-secondary">No</span>' !!}</td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.aaee-valores-hora.edit', $it) }}">Editar</a>
                                <form method="POST" action="{{ route('admin.aaee-valores-hora.destroy', $it) }}" class="d-inline"
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
