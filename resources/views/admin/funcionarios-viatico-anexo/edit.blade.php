@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h1 class="h3 mb-1">Editar funcionario con viático por anexo</h1>
            <p class="text-muted mb-0">Al guardar, el RUT se vuelve a validar contra el último padrón activo/cargado.</p>
        </div>
        <a href="{{ route('admin.funcionarios-viatico-anexo.index') }}" class="btn btn-outline-secondary">Volver</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.funcionarios-viatico-anexo.update', $registro) }}">
                @method('PUT')
                @include('admin.funcionarios-viatico-anexo._form')
            </form>
        </div>
    </div>
</div>
@endsection
