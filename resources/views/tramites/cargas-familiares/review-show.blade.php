@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0">Revisión solicitud #{{ $solicitud->id }}</h1>
        <div class="text-muted small">{{ $solicitud->tipo_solicitud_label }} · <span class="badge {{ $solicitud->estado_badge_class }}">{{ $solicitud->estado_label }}</span></div>
    </div>
    <a href="{{ route('tramites.cargas-familiares.review.index') }}" class="btn btn-outline-secondary">Volver</a>
</div>

@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if (session('warning'))<div class="alert alert-warning">{{ session('warning') }}</div>@endif

<div class="card shadow-sm mb-3 border-primary">
    <div class="card-header fw-semibold">Resolución de la solicitud</div>
    <div class="card-body">
        <form method="POST" action="{{ route('tramites.cargas-familiares.review.resolve', $solicitud) }}" class="row g-3 align-items-end">
            @csrf
            @method('PATCH')
            <div class="col-md-3">
                <label class="form-label">Estado</label>
                <select name="estado" class="form-select" required>
                    @foreach (['en_revision' => 'En revisión', 'observado' => 'Observado', 'aprobado' => 'Aprobado', 'rechazado' => 'Rechazado'] as $key => $label)
                        <option value="{{ $key }}" @selected($solicitud->estado === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-7">
                <label class="form-label">Observación de revisión</label>
                <input type="text" name="observacion_revision" class="form-control" value="{{ old('observacion_revision', $solicitud->observacion_revision) }}" maxlength="2000">
            </div>
            <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-check2-circle"></i> Guardar</button></div>
        </form>
    </div>
</div>

@include('tramites.cargas-familiares.partials.solicitud-detail', ['solicitud' => $solicitud, 'canReview' => true])
@endsection
