@extends('layouts.app')

@section('content')
@php
    $activeRole = auth()->user()?->activeRoleName();
    $cargasSolicitanteRoles = (array) config('cargas_familiares.acceso_solicitantes.roles_habilitados', ['funcionario_ac']);
    $puedeCrearSolicitud = in_array($activeRole, $cargasSolicitanteRoles, true) && Route::has('tramites.cargas-familiares.create');
@endphp
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0">Mis Cargas Familiares</h1>
        <div class="text-muted small">Acredita nuevos causantes, actualiza cargas vigentes y revisa el estado de tus solicitudes.</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Volver al panel</a>
        @if ($puedeCrearSolicitud)
            <a href="{{ route('tramites.cargas-familiares.create') }}" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Nueva solicitud</a>
        @endif
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif
@if (session('warning'))
    <div class="alert alert-warning">{{ session('warning') }}</div>
@endif

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Cargas vigentes asociadas a tu RUN</div>
                <div class="display-6">{{ number_format($cargasVigentes->count(), 0, ',', '.') }}</div>
                <div class="small text-muted">Se vinculan por RUN + DV del beneficiario importado en carga masiva.</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Solicitudes realizadas</div>
                <div class="display-6">{{ number_format($solicitudes->total(), 0, ',', '.') }}</div>
                <div class="small text-muted">Incluye nuevas inscripciones y actualizaciones.</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100 border-primary">
            <div class="card-body">
                <div class="fw-semibold mb-1">Documentos base requeridos</div>
                <div class="small text-muted">Formulario de Solicitud, Declaracion Jurada de Ingresos y documentos requeridos segun el codigo de causante seleccionado.</div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header fw-semibold">Cargas vigentes</div>
    <div class="card-body p-0">
        @if ($cargasVigentes->count())
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Causante</th>
                            <th>RUT</th>
                            <th>Parentesco</th>
                            <th>Fecha nacimiento</th>
                            <th>Tramo</th>
                            <th>Comuna origen</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cargasVigentes as $carga)
                            <tr>
                                <td class="fw-semibold">{{ $carga->causante_nombre_completo ?: '—' }}</td>
                                <td>{{ $carga->causante_rut_completo ?: '—' }}</td>
                                <td>{{ $carga->parentesco ?: '—' }}</td>
                                <td>{{ $carga->fecha_nacimiento?->format('d-m-Y') ?: '—' }}</td>
                                <td>{{ $carga->tramo ?: '—' }}</td>
                                <td>{{ $carga->comuna_origen ?: '—' }}</td>
                                <td><span class="badge {{ $carga->estado_carga_badge_class }}">{{ $carga->estado_carga_label }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-4 text-center text-muted">No se encontraron cargas vigentes asociadas a tu RUN. Si corresponden, se mostrarán cuando sean importadas o cuando tu RUN coincida con la carga masiva.</div>
        @endif
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header fw-semibold">Mis solicitudes</div>
    <div class="card-body p-0">
        @if ($solicitudes->count())
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th>Enviada</th>
                            <th>Causantes</th>
                            <th>Documentos</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($solicitudes as $solicitud)
                            <tr>
                                <td class="fw-semibold">{{ $solicitud->id }}</td>
                                <td>{{ $solicitud->tipo_solicitud_label }}</td>
                                <td><span class="badge {{ $solicitud->estado_badge_class }}">{{ $solicitud->estado_label }}</span></td>
                                <td>{{ $solicitud->fecha_envio?->timezone(config('app.display_timezone', 'America/Santiago'))->format('d-m-Y H:i') ?: '—' }}</td>
                                <td>{{ $solicitud->causantes_count }}</td>
                                <td>{{ $solicitud->documentos_count }}</td>
                                <td class="text-end">
                                    <a href="{{ route('tramites.cargas-familiares.show', $solicitud) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> Ver</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-4 text-center text-muted">Aún no has enviado solicitudes de cargas familiares.</div>
        @endif
    </div>
</div>

@if ($solicitudes->hasPages())
    <div class="mt-3">{{ $solicitudes->links() }}</div>
@endif
@endsection
