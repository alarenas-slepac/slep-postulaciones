@extends('layouts.app')

@section('content')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Detalle curso</h1>
            <div class="text-muted small">Información del curso o nivel registrado.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-primary" href="{{ route('admin.cursos.edit', $curso) }}">Editar</a>
            <a class="btn btn-outline-secondary" href="{{ route('admin.cursos.index') }}">Volver</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="text-muted small">Orden</div>
                    <div class="fw-semibold">{{ $curso->orden }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Código</div>
                    <div class="fw-semibold">{{ $curso->codigo }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Estado</div>
                    <div>
                        @if ($curso->activo)
                            <span class="badge text-bg-success">Activo</span>
                        @else
                            <span class="badge bg-secondary">Inactivo</span>
                        @endif
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Creado</div>
                    <div>{{ optional($curso->created_at)->format('d-m-Y H:i') ?: '—' }}</div>
                </div>
                <div class="col-12">
                    <div class="text-muted small">Nombre</div>
                    <div class="fw-semibold fs-5">{{ $curso->nombre }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Nivel educativo</div>
                    <div>{{ $curso->nivel_educativo }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Modalidad</div>
                    <div>{{ $curso->modalidad ?: 'Sin modalidad específica' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Última actualización</div>
                    <div>{{ optional($curso->updated_at)->format('d-m-Y H:i') ?: '—' }}</div>
                </div>
            </div>
        </div>
    </div>
@endsection
