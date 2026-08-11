@extends('layouts.app')

@section('content')
    @php
        $estadoClases = [
            'en_tramite' => 'text-bg-warning',
            'aprobada' => 'text-bg-success',
            'rechazada' => 'text-bg-danger',
        ];
    @endphp

    <div class="container-fluid py-3">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h1 class="h4 mb-1">Autorizaciones docentes</h1>
                <p class="text-muted mb-0">Seguimiento administrativo paralelo a las solicitudes de reemplazo.</p>
            </div>
            <a href="{{ route('gestion.solicitudes-reemplazo.index') }}" class="btn btn-outline-secondary">Volver a reemplazos</a>
        </div>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="row g-3 mb-4">
            @foreach (\App\Models\SolicitudReemplazoAutorizacionDocente::estados() as $estado => $label)
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="text-muted small">{{ $label }}</div>
                            <div class="display-6 fw-semibold">{{ (int) ($totales[$estado] ?? 0) }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card shadow-sm">
            <div class="card-header">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label small">Buscar</label>
                        <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Solicitud, autorización, RUT, postulante o establecimiento">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Estado</label>
                        <select name="estado" class="form-select">
                            <option value="">Todos</option>
                            @foreach (\App\Models\SolicitudReemplazoAutorizacionDocente::estados() as $estado => $label)
                                <option value="{{ $estado }}" @selected(request('estado') === $estado)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button class="btn btn-primary" type="submit">Filtrar</button>
                        <a class="btn btn-outline-secondary" href="{{ route('gestion.autorizaciones-docentes.index') }}">Limpiar</a>
                    </div>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Solicitud</th>
                                <th>Postulante</th>
                                <th>Establecimiento / área</th>
                                <th>Solicitud de autorización</th>
                                <th>N.º autorización</th>
                                <th style="min-width: 290px;">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($autorizaciones as $autorizacion)
                                @php
                                    $solicitud = $autorizacion->solicitud;
                                    $usuario = $autorizacion->postulante?->user;
                                @endphp
                                <tr>
                                    <td>
                                        @if ($solicitud)
                                            <a class="fw-semibold text-decoration-none" href="{{ route('gestion.solicitudes-reemplazo.show', $solicitud) }}">
                                                {{ $solicitud->numero_solicitud }}
                                            </a>
                                            <div class="small text-muted">Flujo: {{ $solicitud->estado }}</div>
                                        @else
                                            <span class="text-muted">Solicitud no disponible</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $usuario?->full_name ?? '—' }}</div>
                                        <div class="small text-muted">{{ $usuario?->rut ?? '—' }}</div>
                                    </td>
                                    <td>
                                        <div>{{ $solicitud?->establecimiento?->nombre_establecimiento ?? '—' }}</div>
                                        <div class="small text-muted">{{ $solicitud?->areaDesempeno?->nombre ?? '—' }}</div>
                                    </td>
                                    <td>
                                        <div>{{ cl_datetime($autorizacion->solicitado_at, 'd/m/Y H:i') }}</div>
                                        <div class="small text-muted">{{ $autorizacion->correo_destino ?: 'Correo pendiente' }}</div>
                                        @if (! $autorizacion->correo_enviado_at)
                                            <span class="badge text-bg-danger mt-1">Correo no enviado</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $autorizacion->numero_autorizacion ?: 'Pendiente' }}</div>
                                        @if ($autorizacion->numero_registrado_at)
                                            <div class="small text-muted">{{ cl_datetime($autorizacion->numero_registrado_at, 'd/m/Y H:i') }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="mb-2">
                                            <span class="badge {{ $estadoClases[$autorizacion->estado] ?? 'text-bg-secondary' }}">
                                                {{ $autorizacion->estado_label }}
                                            </span>
                                        </div>
                                        <form method="POST" action="{{ route('gestion.autorizaciones-docentes.estado.update', $autorizacion) }}" class="row g-2">
                                            @csrf
                                            @method('PATCH')
                                            <div class="col-7">
                                                <select name="estado" class="form-select form-select-sm" aria-label="Estado de autorización">
                                                    @foreach (\App\Models\SolicitudReemplazoAutorizacionDocente::estados() as $estado => $label)
                                                        <option value="{{ $estado }}" @selected($autorizacion->estado === $estado)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-5">
                                                <button type="submit" class="btn btn-sm btn-primary w-100">Actualizar</button>
                                            </div>
                                            <div class="col-12">
                                                <input type="text" name="observacion_estado" value="{{ $autorizacion->observacion_estado }}" class="form-control form-control-sm" maxlength="2000" placeholder="Observación opcional">
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">No hay autorizaciones docentes registradas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($autorizaciones->hasPages())
                <div class="card-footer">{{ $autorizaciones->links() }}</div>
            @endif
        </div>

        <div class="alert alert-info mt-3 mb-0">
            Cambiar el estado de una autorización no cambia ni detiene el estado de la solicitud de reemplazo.
        </div>
    </div>
@endsection
