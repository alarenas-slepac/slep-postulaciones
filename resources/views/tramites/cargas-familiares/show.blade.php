@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0">Solicitud de Cargas Familiares #{{ $solicitud->id }}</h1>
        <div class="text-muted small">{{ $solicitud->tipo_solicitud_label }} · <span class="badge {{ $solicitud->estado_badge_class }}">{{ $solicitud->estado_label }}</span></div>
    </div>
    <a href="{{ route('tramites.cargas-familiares.index') }}" class="btn btn-outline-secondary">Volver</a>
</div>

@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if (session('warning'))<div class="alert alert-warning">{{ session('warning') }}</div>@endif

@include('tramites.cargas-familiares.partials.solicitud-detail', ['solicitud' => $solicitud, 'canReview' => false])
@endsection
