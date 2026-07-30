@extends('layouts.app')

@section('content')
@php($canManageRestrictedRuts = auth()->user()?->hasAnyRole(['admin', 'coordinador_gdp']))
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Ficha de restricción</h1>
            <p class="text-muted mb-0">{{ $restrictedRut->display_name ?: ($restrictedRut->courtRecord?->nombre ?: 'Sin nombre') }} · {{ $restrictedRut->rut_formatted }}</p>
        </div>
        <div class="d-flex gap-2">
            @if ($canManageRestrictedRuts)
                <a href="{{ route('admin.restricted-ruts.manual.create') }}" class="btn btn-outline-primary">Nuevo bloqueo manual</a>
            @endif
            <a href="{{ route('admin.restricted-ruts.index') }}" class="btn btn-outline-secondary">Volver</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @unless ($canManageRestrictedRuts)
        <div class="alert alert-info">Esta ficha está disponible en modo solo lectura para tu rol.</div>
    @endunless

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted">Estado final</div>
                    <div class="mt-2">
                        @if ($flags['blocked'])
                            <span class="badge bg-danger">Bloqueado para ejercer</span>
                        @else
                        <span class="badge text-bg-success">No bloqueado actualmente</span>
                        @endif
                    </div>
                    <hr>
                    <div class="small text-muted">Usuario registrado</div>
                    <div class="fw-semibold mt-1">{{ $linkedUser?->nombre_completo ?: 'No existe usuario asociado' }}</div>
                    @if ($linkedUser)
                        <div class="text-muted small">{{ $linkedUser->email }}</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5">Resumen</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <div class="small text-muted">Fuente judicial</div>
                                <div class="fw-semibold">{{ $restrictedRut->courtRecord ? 'Sí' : 'No' }}</div>
                                @if ($restrictedRut->courtRecord)
                                    <div class="small mt-2">{{ $restrictedRut->courtRecord->juzgado_origen ?: 'Sin juzgado' }}</div>
                                    <div class="small">{{ $restrictedRut->courtRecord->rit ?: 'Sin RIT' }}</div>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <div class="small text-muted">Fuente manual</div>
                                <div class="fw-semibold">{{ $restrictedRut->manualRecord ? 'Sí' : 'No' }}</div>
                                @if ($restrictedRut->manualRecord)
                                    <div class="small mt-2">{{ optional($restrictedRut->manualRecord->fecha_inicio_prohibicion)->format('d-m-Y') }} al {{ optional($restrictedRut->manualRecord->fecha_termino_prohibicion)->format('d-m-Y') }}</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Registro judicial</span>
                    @if ($restrictedRut->courtRecord && $canManageRestrictedRuts)
                        <form method="POST" action="{{ route('admin.restricted-ruts.court.toggle', $restrictedRut->courtRecord) }}">
                            @csrf
                                    <button class="btn btn-sm {{ $restrictedRut->courtRecord->activa ? 'btn-outline-danger' : 'btn-outline-success-dark' }}">
                                {{ $restrictedRut->courtRecord->activa ? 'Desactivar' : 'Activar' }}
                            </button>
                        </form>
                    @endif
                </div>
                <div class="card-body">
                    @if ($restrictedRut->courtRecord)
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Nombre</dt>
                            <dd class="col-sm-8">{{ $restrictedRut->courtRecord->nombre ?: '—' }}</dd>
                            <dt class="col-sm-4">RUN original</dt>
                            <dd class="col-sm-8">{{ $restrictedRut->courtRecord->run_original ?: '—' }}</dd>
                            <dt class="col-sm-4">Juzgado</dt>
                            <dd class="col-sm-8">{{ $restrictedRut->courtRecord->juzgado_origen ?: '—' }}</dd>
                            <dt class="col-sm-4">RIT</dt>
                            <dd class="col-sm-8">{{ $restrictedRut->courtRecord->rit ?: '—' }}</dd>
                            <dt class="col-sm-4">Fecha fallo</dt>
                            <dd class="col-sm-8">{{ optional($restrictedRut->courtRecord->fecha_fallo)->format('d-m-Y') ?: '—' }}</dd>
                            <dt class="col-sm-4">Inhabilidad</dt>
                            <dd class="col-sm-8">{{ $restrictedRut->courtRecord->inhabilidad_texto ?: '—' }}</dd>
                            <dt class="col-sm-4">Activa</dt>
                            <dd class="col-sm-8">{{ $restrictedRut->courtRecord->activa ? 'Sí' : 'No' }}</dd>
                        </dl>
                    @else
                        <p class="text-muted mb-0">No existe registro judicial para este RUT.</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Bloqueo manual</span>
                    @if ($canManageRestrictedRuts)
                        <div class="d-flex gap-2">
                            @if ($restrictedRut->manualRecord)
                                <a href="{{ route('admin.restricted-ruts.manual.edit', $restrictedRut->manualRecord) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                                <form method="POST" action="{{ route('admin.restricted-ruts.manual.toggle', $restrictedRut->manualRecord) }}">
                                    @csrf
                                    <button class="btn btn-sm {{ $restrictedRut->manualRecord->activa ? 'btn-outline-danger' : 'btn-outline-success-dark' }}">
                                        {{ $restrictedRut->manualRecord->activa ? 'Desactivar' : 'Activar' }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endif
                </div>
                <div class="card-body">
                    @if ($restrictedRut->manualRecord)
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Inicio</dt>
                            <dd class="col-sm-8">{{ optional($restrictedRut->manualRecord->fecha_inicio_prohibicion)->format('d-m-Y') ?: '—' }}</dd>
                            <dt class="col-sm-4">Término</dt>
                            <dd class="col-sm-8">{{ optional($restrictedRut->manualRecord->fecha_termino_prohibicion)->format('d-m-Y') ?: '—' }}</dd>
                            <dt class="col-sm-4">Comentario</dt>
                            <dd class="col-sm-8">{{ $restrictedRut->manualRecord->comentario ?: '—' }}</dd>
                            <dt class="col-sm-4">Activa</dt>
                            <dd class="col-sm-8">{{ $restrictedRut->manualRecord->activa ? 'Sí' : 'No' }}</dd>
                        </dl>
                    @else
                        <p class="text-muted mb-0">No existe bloqueo manual para este RUT.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
