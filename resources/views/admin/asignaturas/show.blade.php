@extends('layouts.app')

@section('content')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ $asignatura->nombre }}</h1>
            <div class="text-muted small">Detalle de asignatura</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-primary" href="{{ route('admin.asignaturas.edit', $asignatura) }}">Editar</a>
            <a class="btn btn-outline-danger" href="{{ route('admin.asignaturas.index') }}">Volver</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-md-3">Código</dt>
                <dd class="col-md-9">{{ $asignatura->codigo }}</dd>
                <dt class="col-md-3">Nivel educativo</dt>
                <dd class="col-md-9">{{ $asignatura->nivel_educativo ?: '—' }}</dd>
                <dt class="col-md-3">Área / sector</dt>
                <dd class="col-md-9">{{ $asignatura->area ?: '—' }}</dd>
                <dt class="col-md-3">Tipo</dt>
                <dd class="col-md-9">{{ $asignatura->tipo_asignatura_label }}</dd>
                <dt class="col-md-3">Origen</dt>
                <dd class="col-md-9">{{ $asignatura->es_oficial ? 'Oficial' : 'Propia / personalizada' }}</dd>
                <dt class="col-md-3">Estado</dt>
                <dd class="col-md-9">{{ $asignatura->activo ? 'Activa' : 'Inactiva' }}</dd>
                <dt class="col-md-3">Observación</dt>
                <dd class="col-md-9">{{ $asignatura->observacion ?: '—' }}</dd>
            </dl>
        </div>
    </div>
@endsection
