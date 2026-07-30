@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <div class="text-uppercase text-muted small fw-bold">Catálogos</div>
            <h1 class="h3 mb-0">Editar valor de viático / reembolso</h1>
        </div>
        <a href="{{ route('admin.viaticos-reembolsos.index') }}" class="btn btn-outline-secondary">Volver</a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.viaticos-reembolsos.update', $valor) }}">
                @csrf
                @method('PUT')
                @include('admin.viaticos-reembolsos._form')
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('admin.viaticos-reembolsos.index') }}" class="btn btn-light">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
