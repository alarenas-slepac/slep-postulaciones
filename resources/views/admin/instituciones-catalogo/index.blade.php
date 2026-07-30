@extends('layouts.app')
@section('content')
<style>
    .catalog-actions {
        display: inline-flex;
        align-items: center;
        justify-content: flex-end;
        gap: .35rem;
        flex-wrap: nowrap;
    }
    .catalog-action-form {
        display: inline-flex;
        margin: 0;
    }
    .catalog-action-btn {
        width: 34px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        border-radius: .45rem;
        line-height: 1;
    }
    .catalog-action-btn i {
        font-size: .95rem;
    }
</style>
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div><h2 class="h4 mb-1">Catálogo de instituciones</h2><p class="text-muted mb-0">Mantención manual e importación masiva de instituciones para Declaración de Sostenedores/Establecimientos.</p></div>
        <div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="{{ route('admin.instituciones-catalogo.template') }}">Descargar plantilla</a><a class="btn btn-primary" href="{{ route('admin.instituciones-catalogo.create') }}">Nueva institución</a></div>
    </div>
    @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <div class="card mb-3"><div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-6"><label class="form-label">Buscar institución</label><input type="text" name="q" class="form-control" value="{{ $q }}" placeholder="Nombre de la institución"></div>
            <div class="col-md-6 d-flex gap-2"><button class="btn btn-primary" type="submit">Buscar</button><a class="btn btn-outline-secondary" href="{{ route('admin.instituciones-catalogo.index') }}">Limpiar</a></div>
        </form>
    </div></div>
    <div class="card mb-3"><div class="card-body">
        <form method="POST" action="{{ route('admin.instituciones-catalogo.import.store') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-8"><label class="form-label">Importar instituciones masivamente</label><input type="file" name="archivo" class="form-control" accept=".xlsx,.xls,.csv" required><div class="form-text">Use la plantilla oficial. Se tomará una columna como nombre_institucion/institucion/institución/nombre.</div></div>
            <div class="col-md-4 d-flex gap-2"><button class="btn btn-outline-primary" type="submit">Importar</button></div>
        </form>
    </div></div>
    <div class="card"><div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead><tr><th>ID</th><th>Nombre</th><th class="text-end">Acciones</th></tr></thead><tbody>
    @forelse ($items as $item)
        <tr><td>{{ $item->id }}</td><td>{{ $item->nombre }}</td><td class="text-end"><div class="catalog-actions"><a class="btn btn-sm btn-outline-primary catalog-action-btn" href="{{ route('admin.instituciones-catalogo.show', $item) }}" title="Ver institución" data-bs-toggle="tooltip" aria-label="Ver institución"><i class="bi bi-eye"></i></a><a class="btn btn-sm btn-outline-secondary catalog-action-btn" href="{{ route('admin.instituciones-catalogo.edit', $item) }}" title="Editar institución" data-bs-toggle="tooltip" aria-label="Editar institución"><i class="bi bi-pencil"></i></a><form method="POST" class="catalog-action-form" action="{{ route('admin.instituciones-catalogo.destroy', $item) }}" onsubmit="return confirm('¿Eliminar esta institución?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger catalog-action-btn" type="submit" title="Eliminar institución" data-bs-toggle="tooltip" aria-label="Eliminar institución"><i class="bi bi-trash"></i></button></form></div></td></tr>
    @empty
        <tr><td colspan="3" class="text-center text-muted py-4">No hay instituciones registradas.</td></tr>
    @endforelse
    </tbody></table></div></div>
    <div class="mt-3">{{ $items->links() }}</div>
</div>
@endsection
