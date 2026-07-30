@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ $establecimientoCurso->nombre_seccion }}</h1>
            <div class="text-muted small">Curso por establecimiento · Año {{ $establecimientoCurso->anio }}</div>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-primary" href="{{ route('admin.establecimiento-cursos.edit', $establecimientoCurso) }}">Editar</a>
            <a class="btn btn-outline-danger" href="{{ route('admin.establecimiento-cursos.index') }}">Volver</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="text-muted small">RBD</div>
                    <div class="fw-semibold">{{ $establecimientoCurso->rbd }}</div>
                </div>
                <div class="col-md-8">
                    <div class="text-muted small">Establecimiento</div>
                    <div class="fw-semibold">{{ $establecimientoCurso->establecimiento?->nombre_establecimiento }}</div>
                    <div class="text-muted small">{{ $establecimientoCurso->establecimiento?->comuna }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Curso base</div>
                    <div class="fw-semibold">{{ $establecimientoCurso->curso?->nombre }}</div>
                </div>
                <div class="col-md-2">
                    <div class="text-muted small">Letra</div>
                    <div class="fw-semibold">{{ $establecimientoCurso->letra ?: '—' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Régimen JEC</div>
                    <div class="fw-semibold">{{ $establecimientoCurso->regimen_jec }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Matrícula</div>
                    <div class="fw-semibold">{{ number_format((int) $establecimientoCurso->matricula, 0, ',', '.') }}</div>
                </div>
                <div class="col-12">
                    <hr>
                    <div class="text-muted small">Plan de estudio asociado</div>
                    @if ($establecimientoCurso->planEstudio)
                        <div class="fw-semibold">{{ $establecimientoCurso->planEstudio->nombre_plan }}</div>
                        <div class="text-muted small">
                            {{ $establecimientoCurso->planEstudio->anio }} · {{ $establecimientoCurso->planEstudio->regimen_jec }} · {{ $establecimientoCurso->planEstudio->horas_semanales_total }} horas semanales
                        </div>
                    @else
                        <span class="badge text-bg-warning">Sin plan asociado</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
