@extends('layouts.app')

@section('content')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Detalle porcentaje de alumnos prioritarios</h1>
            <div class="text-muted small">Registro administrativo por establecimiento y año.</div>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-primary" href="{{ route('admin.alumnos-prioritarios.edit', $item) }}">Editar</a>
            <a class="btn btn-outline-secondary" href="{{ route('admin.alumnos-prioritarios.index') }}">Volver</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="text-muted small">Año</div>
                    <div class="fw-semibold">{{ $item->anio }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Porcentaje</div>
                    <div class="fw-semibold">{{ number_format((float) $item->porcentaje, 2, ',', '.') }}%</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">RBD</div>
                    <div class="fw-semibold">{{ $item->establecimiento?->rbd ?? '—' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Comuna</div>
                    <div class="fw-semibold">{{ $item->establecimiento?->comuna ?? '—' }}</div>
                </div>
                <div class="col-12">
                    <div class="text-muted small">Establecimiento</div>
                    <div class="fw-semibold">{{ $item->establecimiento?->nombre_establecimiento ?? 'Establecimiento no disponible' }}</div>
                </div>
                <div class="col-12">
                    <div class="text-muted small">Observación</div>
                    <div>{{ $item->observacion ?: 'Sin observación.' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Creado por</div>
                    <div>{{ $item->creadoPor?->display_name ?? '—' }} · {{ optional($item->created_at)->format('d-m-Y H:i') }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Última actualización</div>
                    <div>{{ $item->actualizadoPor?->display_name ?? '—' }} · {{ optional($item->updated_at)->format('d-m-Y H:i') }}</div>
                </div>
            </div>
        </div>
    </div>
@endsection
