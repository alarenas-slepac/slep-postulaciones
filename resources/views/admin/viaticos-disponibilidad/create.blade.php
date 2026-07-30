@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <div class="text-uppercase text-muted small fw-semibold">Control presupuestario</div>
            <h1 class="h3 mb-1">Nueva disponibilidad de viáticos</h1>
            <p class="text-muted mb-0">Registra monto disponible por origen y vigencia.</p>
        </div>
        <a href="{{ route('admin.viaticos-disponibilidad.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Volver
        </a>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('admin.viaticos-disponibilidad.store') }}">
                @include('admin.viaticos-disponibilidad._form')
            </form>
        </div>
    </div>
</div>
@endsection
