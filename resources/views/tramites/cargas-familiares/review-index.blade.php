@extends('layouts.app')

@section('content')
@php
    $activeRole = auth()->user()?->activeRoleName();
    $puedeCargaMasiva = in_array($activeRole, ['admin', 'funcionario_slep'], true);
@endphp
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0">Revisión · Mis Cargas Familiares</h1>
        <div class="text-muted small">Bandeja de solicitudes de asignación familiar y maternal.</div>
    </div>
    <div class="d-flex gap-2">
        @if ($puedeCargaMasiva && Route::has('tramites.cargas-familiares.import'))
            <a href="{{ route('tramites.cargas-familiares.import') }}" class="btn btn-outline-success"><i class="bi bi-upload"></i> Carga masiva</a>
        @endif
        <a href="{{ route('tramites.cargas-familiares.admin.index') }}" class="btn btn-outline-secondary">Volver</a>
    </div>
</div>

@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('tramites.cargas-familiares.review.index') }}" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Estado</label>
                <select name="estado" class="form-select">
                    <option value="">Todos</option>
                    @foreach ($estados as $estado)
                        <option value="{{ $estado }}" @selected(($filters['estado'] ?? '') === $estado)>{{ ucfirst(str_replace('_', ' ', $estado)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3"><label class="form-label">RUN beneficiario</label><input type="text" name="rut" class="form-control" value="{{ $filters['rut'] ?? '' }}"></div>
            <div class="col-md-2"><label class="form-label">Desde</label><input type="date" name="fecha_desde" class="form-control" value="{{ $filters['fecha_desde'] ?? '' }}"></div>
            <div class="col-md-2"><label class="form-label">Hasta</label><input type="date" name="fecha_hasta" class="form-control" value="{{ $filters['fecha_hasta'] ?? '' }}"></div>
            <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary flex-fill"><i class="bi bi-funnel"></i> Filtrar</button><a href="{{ route('tramites.cargas-familiares.review.index') }}" class="btn btn-outline-secondary">Limpiar</a></div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        @if ($solicitudes->count())
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>#</th><th>Solicitante</th><th>Tipo</th><th>Estado</th><th>Enviada</th><th>Causantes</th><th>Docs</th><th class="text-end">Acciones</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($solicitudes as $solicitud)
                            <tr>
                                <td class="fw-semibold">{{ $solicitud->id }}</td>
                                <td><div class="fw-semibold">{{ $solicitud->user?->nombre_completo ?: '—' }}</div><div class="small text-muted">{{ $solicitud->user?->rut ?: 'Sin RUN' }}</div></td>
                                <td>{{ $solicitud->tipo_solicitud_label }}</td>
                                <td><span class="badge {{ $solicitud->estado_badge_class }}">{{ $solicitud->estado_label }}</span></td>
                                <td>{{ $solicitud->fecha_envio?->timezone(config('app.display_timezone', 'America/Santiago'))->format('d-m-Y H:i') ?: '—' }}</td>
                                <td>{{ $solicitud->causantes_count }}</td>
                                <td>{{ $solicitud->documentos_count }}</td>
                                <td class="text-end"><a href="{{ route('tramites.cargas-familiares.review.show', $solicitud) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> Revisar</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-4 text-center text-muted">No hay solicitudes con el filtro aplicado.</div>
        @endif
    </div>
</div>
@if ($solicitudes->hasPages())<div class="mt-3">{{ $solicitudes->links() }}</div>@endif
@endsection
