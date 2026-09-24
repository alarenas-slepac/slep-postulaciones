@extends('layouts.app')

@section('content')
    @php
        $estadoClases = [
            'en_tramite' => 'is-warning',
            'aprobada' => 'is-success',
            'rechazada' => 'is-danger',
        ];
    @endphp

    <div class="ad-page py-4">
        <div class="ad-hero">
            <div class="ad-hero-main">
                <span class="ad-hero-icon" aria-hidden="true"><i class="bi bi-patch-check"></i></span>
                <div>
                    <div class="ad-eyebrow">Gestión · Solicitudes de reemplazo</div>
                    <h1 class="ad-hero-title">Autorizaciones docentes</h1>
                    <p class="ad-hero-subtitle">Seguimiento administrativo paralelo a las solicitudes de reemplazo.</p>
                </div>
            </div>
            <div class="ad-hero-actions">
                <a href="{{ route('gestion.solicitudes-reemplazo.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i> Volver a reemplazos
                </a>
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-success ad-alert" role="status"><i class="bi bi-check-circle" aria-hidden="true"></i>{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger ad-alert" role="alert">
                <strong>Revisa los datos ingresados.</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="row g-3 mb-4">
            @foreach (\App\Models\SolicitudReemplazoAutorizacionDocente::estados() as $estado => $label)
                <div class="col-sm-6 col-lg-4">
                    <div class="ad-stat {{ $estadoClases[$estado] ?? '' }}">
                        <span class="ad-stat-label">{{ $label }}</span>
                        <strong class="ad-stat-value">{{ (int) ($totales[$estado] ?? 0) }}</strong>
                        <span class="ad-stat-help">Autorizaciones docentes</span>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card ad-panel mb-4">
            <div class="card-header">
                <div class="ad-panel-kicker">Búsqueda</div>
                <h2 class="ad-panel-title">Filtros</h2>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('gestion.autorizaciones-docentes.index') }}" class="row g-3 align-items-end">
                    <div class="col-12 col-lg-6">
                        <label class="form-label" for="ad-filter-search">Buscar</label>
                        <input id="ad-filter-search" type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Solicitud, autorización, RUT, postulante o establecimiento" maxlength="120">
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <label class="form-label" for="ad-filter-status">Estado</label>
                        <select id="ad-filter-status" name="estado" class="form-select">
                            <option value="">Todos los estados</option>
                            @foreach (\App\Models\SolicitudReemplazoAutorizacionDocente::estados() as $estado => $label)
                                <option value="{{ $estado }}" @selected(request('estado') === $estado)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="ad-filter-actions">
                            <button class="btn btn-primary" type="submit"><i class="bi bi-funnel" aria-hidden="true"></i> Filtrar</button>
                            <a class="btn btn-outline-secondary" href="{{ route('gestion.autorizaciones-docentes.index') }}">Limpiar</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card ad-panel">
            <div class="card-header">
                <div class="ad-panel-kicker">Seguimiento</div>
                <h2 class="ad-panel-title">Listado de autorizaciones</h2>
                <p class="ad-panel-subtitle">Consulta los antecedentes y actualiza el estado de cada autorización.</p>
            </div>
            <div class="table-responsive">
                <table class="table ad-table align-middle">
                    <thead>
                        <tr>
                            <th>Solicitud</th>
                            <th>Postulante</th>
                            <th>Establecimiento / área</th>
                            <th>Solicitud de autorización</th>
                            <th>N.º autorización</th>
                            <th>Estado y actualización</th>
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
                                        <a class="ad-table-primary" href="{{ route('gestion.solicitudes-reemplazo.show', $solicitud) }}">
                                            {{ $solicitud->numero_solicitud }}
                                        </a>
                                        <div class="ad-table-meta">Flujo: {{ $solicitud->estado }}</div>
                                    @else
                                        <span class="ad-table-meta">Solicitud no disponible</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="ad-table-primary">{{ $usuario?->full_name ?? '—' }}</div>
                                    <div class="ad-table-meta">{{ $usuario?->rut ?? '—' }}</div>
                                </td>
                                <td>
                                    <div class="ad-table-primary">{{ $solicitud?->establecimiento?->nombre_establecimiento ?? '—' }}</div>
                                    <div class="ad-table-meta">{{ $solicitud?->areaDesempeno?->nombre ?? '—' }}</div>
                                </td>
                                <td>
                                    <div>{{ cl_datetime($autorizacion->solicitado_at, 'd/m/Y H:i') }}</div>
                                    <div class="ad-table-meta">{{ $autorizacion->correo_destino ?: 'Correo pendiente' }}</div>
                                    @if (! $autorizacion->correo_enviado_at)
                                        <span class="ad-chip is-danger mt-1">Correo no enviado</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="ad-table-primary">{{ $autorizacion->numero_autorizacion ?: 'Pendiente' }}</div>
                                    @if ($autorizacion->numero_registrado_at)
                                        <div class="ad-table-meta">{{ cl_datetime($autorizacion->numero_registrado_at, 'd/m/Y H:i') }}</div>
                                    @endif
                                </td>
                                <td class="ad-status-cell">
                                    <div class="ad-current-status">
                                        <span class="ad-current-label">Estado actual</span>
                                        <span class="ad-chip {{ $estadoClases[$autorizacion->estado] ?? '' }}">{{ $autorizacion->estado_label }}</span>
                                    </div>
                                    <form method="POST" action="{{ route('gestion.autorizaciones-docentes.estado.update', $autorizacion) }}" class="ad-row-form">
                                        @csrf
                                        @method('PATCH')
                                        <div>
                                            <label class="form-label" for="ad-estado-{{ $autorizacion->id }}">Nuevo estado</label>
                                            <select id="ad-estado-{{ $autorizacion->id }}" name="estado" class="form-select form-select-sm" required>
                                                @foreach (\App\Models\SolicitudReemplazoAutorizacionDocente::estados() as $estado => $label)
                                                    <option value="{{ $estado }}" @selected($autorizacion->estado === $estado)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="form-label" for="ad-observacion-{{ $autorizacion->id }}">Observación <span class="ad-optional">opcional</span></label>
                                            <input id="ad-observacion-{{ $autorizacion->id }}" type="text" name="observacion_estado" value="{{ $autorizacion->observacion_estado }}" class="form-control form-control-sm" maxlength="2000" placeholder="Escribe una observación">
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-primary">Actualizar estado</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="ad-empty">
                                    <i class="bi bi-patch-question" aria-hidden="true"></i>
                                    No hay autorizaciones docentes registradas.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($autorizaciones->hasPages())
                <div class="card-footer">{{ $autorizaciones->links() }}</div>
            @endif
        </div>

        <div class="alert alert-info ad-alert mt-3 mb-0">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            Cambiar el estado de una autorización no cambia ni detiene el estado de la solicitud de reemplazo.
        </div>
    </div>
@endsection
