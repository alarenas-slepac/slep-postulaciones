@extends('layouts.app')

@section('content')
@php
    $activeRole = auth()->user()?->activeRoleName();
    $puedeCargaMasiva = in_array($activeRole, ['admin', 'funcionario_slep'], true);
    $puedeGestionFuncionariosAc = in_array($activeRole, ['admin', 'funcionario_slep', 'coordinador_gdp'], true);
    $puedeRevisarSolicitudes = in_array($activeRole, ['admin', 'funcionario_slep', 'coordinador_gdp'], true);
@endphp
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0">Administracion · Cargas Familiares</h1>
        <div class="text-muted small">Consulta cargas importadas por carga masiva y nuevas acreditaciones ingresadas por usuarios.</div>
    </div>
    <div class="d-flex gap-2">
        @if ($puedeRevisarSolicitudes && Route::has('tramites.cargas-familiares.review.index'))
            <a href="{{ route('tramites.cargas-familiares.review.index') }}" class="btn btn-outline-primary btn-sm">Revision de solicitudes</a>
        @endif
        @if ($puedeGestionFuncionariosAc && Route::has('tramites.cargas-familiares.admin.funcionarios-ac.import'))
            <a href="{{ route('tramites.cargas-familiares.admin.funcionarios-ac.import') }}" class="btn btn-outline-success btn-sm">Funcionarios AC</a>
        @endif
        @if ($puedeCargaMasiva && Route::has('tramites.cargas-familiares.import'))
            <a href="{{ route('tramites.cargas-familiares.import') }}" class="btn btn-primary btn-sm">Carga masiva</a>
        @endif
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md">
        <div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Cargas masivas</div><div class="h4 mb-0">{{ number_format($stats['cargas_total'] ?? 0, 0, ',', '.') }}</div></div></div>
    </div>
    <div class="col-md">
        <div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Asociadas a usuarios</div><div class="h4 mb-0 text-success">{{ number_format($stats['cargas_asociadas'] ?? 0, 0, ',', '.') }}</div></div></div>
    </div>
    <div class="col-md">
        <div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Sin asociar</div><div class="h4 mb-0 text-warning">{{ number_format($stats['cargas_sin_asociar'] ?? 0, 0, ',', '.') }}</div></div></div>
    </div>
    <div class="col-md">
        <div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Solicitudes</div><div class="h4 mb-0">{{ number_format($stats['solicitudes_total'] ?? 0, 0, ',', '.') }}</div></div></div>
    </div>
    <div class="col-md">
        <div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Pendientes</div><div class="h4 mb-0 text-primary">{{ number_format($stats['solicitudes_pendientes'] ?? 0, 0, ',', '.') }}</div></div></div>
    </div>
</div>

<div class="alert alert-info small">
    Los filtros de <strong>carga masiva</strong> y los filtros de <strong>nuevas acreditaciones</strong> son independientes. La busqueda por nombre admite nombres compuestos y apellidos separados; la busqueda por RUN ignora puntos y guion.
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
        <span>Filtros · Cargas importadas por carga masiva</span>
        <span class="badge text-bg-light border text-dark">{{ number_format($stats['cargas_filtradas'] ?? 0, 0, ',', '.') }} resultado(s)</span>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('tramites.cargas-familiares.admin.index') }}" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Buscar RUN o nombre</label>
                <input type="text" name="carga_q" value="{{ $cargaFilters['q'] ?? '' }}" class="form-control" placeholder="Beneficiario o causante">
            </div>
            <div class="col-md-2">
                <label class="form-label">Comuna</label>
                <select name="carga_comuna" class="form-select">
                    <option value="">Todas</option>
                    @foreach ($comunas as $comuna)
                        <option value="{{ $comuna }}" @selected(($cargaFilters['comuna'] ?? '') === $comuna)>{{ $comuna }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Periodo</label>
                <select name="carga_periodo" class="form-select">
                    <option value="">Todos</option>
                    @foreach ($periodos as $periodo)
                        <option value="{{ $periodo }}" @selected(($cargaFilters['periodo'] ?? '') === $periodo)>{{ $periodo }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Estado carga</label>
                <select name="carga_estado" class="form-select">
                    <option value="">Todos</option>
                    @foreach ($estadosCarga as $estado)
                        <option value="{{ $estado }}" @selected(($cargaFilters['estado'] ?? '') === $estado)>{{ ucfirst($estado) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Vinculacion</label>
                <select name="carga_vinculacion" class="form-select">
                    <option value="" @selected(($cargaFilters['vinculacion'] ?? '') === '')>Todas</option>
                    <option value="asociadas" @selected(($cargaFilters['vinculacion'] ?? '') === 'asociadas')>Asociadas</option>
                    <option value="sin_asociar" @selected(($cargaFilters['vinculacion'] ?? '') === 'sin_asociar')>Sin asociar</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Codigo causante / SIAGF</label>
                <select name="carga_codigo" class="form-select">
                    <option value="">Todos</option>
                    @foreach ($codigosCausante as $codigo => $nombre)
                        <option value="{{ $codigo }}" @selected(($cargaFilters['codigo'] ?? '') === (string) $codigo)>{{ $nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-grid">
                <button class="btn btn-primary">Filtrar cargas</button>
            </div>
            <div class="col-md-2 d-grid">
                <a href="{{ route('tramites.cargas-familiares.admin.index') }}" class="btn btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header fw-semibold">Cargas importadas por carga masiva</div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Periodo</th>
                    <th>Comuna</th>
                    <th>Beneficiario</th>
                    <th>Causante</th>
                    <th>Parentesco</th>
                    <th>Codigo</th>
                    <th>Estado</th>
                    <th>Usuario</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($cargas as $carga)
                    <tr>
                        <td>{{ $carga->periodo_carga ?: '-' }}</td>
                        <td>{{ $carga->comuna_origen ?: '-' }}</td>
                        <td>
                            <div class="fw-semibold">{{ $carga->beneficiario_nombre_completo ?: trim(($carga->beneficiario_apellido_paterno ?? '') . ' ' . ($carga->beneficiario_apellido_materno ?? '') . ' ' . ($carga->beneficiario_nombres ?? '')) ?: '-' }}</div>
                            <div class="small text-muted">{{ $carga->beneficiario_rut_completo ?: $carga->beneficiario_run_normalizado }}</div>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $carga->causante_nombre_completo ?: trim(($carga->causante_apellido_paterno ?? '') . ' ' . ($carga->causante_apellido_materno ?? '') . ' ' . ($carga->causante_nombres ?? '')) }}</div>
                            <div class="small text-muted">{{ $carga->causante_rut_completo ?: $carga->causante_run_normalizado }}</div>
                        </td>
                        <td>{{ $carga->parentesco ?: '-' }}</td>
                        <td>
                            <div>{{ $carga->codigo_tipo_causante ?: '-' }}</div>
                            @if ($carga->codigo_siagf)
                                <div class="small text-muted">SIAGF: {{ $carga->codigo_siagf }}</div>
                            @endif
                        </td>
                        <td><span class="badge {{ $carga->estado_carga_badge_class ?? 'text-bg-light border text-dark' }}">{{ $carga->estado_carga_label ?? $carga->estado_carga }}</span></td>
                        <td>
                            @if ($carga->user)
                                <span class="badge text-bg-success">Asociada</span>
                                <div class="small text-muted">{{ $carga->user->rut ?? '' }}</div>
                            @else
                                <span class="badge text-bg-warning">Sin asociar</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('tramites.cargas-familiares.admin.cargas.show', $carga) }}" class="btn btn-sm btn-outline-primary">Detalle</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">No hay cargas masivas con los filtros aplicados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($cargas->hasPages())
        <div class="card-footer">{{ $cargas->links() }}</div>
    @endif
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
        <span>Filtros · Nuevas acreditaciones y actualizaciones</span>
        <span class="badge text-bg-light border text-dark">{{ number_format($stats['solicitudes_filtradas'] ?? 0, 0, ',', '.') }} resultado(s)</span>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('tramites.cargas-familiares.admin.index') }}" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Buscar RUN o nombre</label>
                <input type="text" name="solicitud_q" value="{{ $solicitudFilters['q'] ?? '' }}" class="form-control" placeholder="Solicitante o causante">
            </div>
            <div class="col-md-2">
                <label class="form-label">Estado solicitud</label>
                <select name="solicitud_estado" class="form-select">
                    <option value="">Todas</option>
                    @foreach ($estadosSolicitud as $estado)
                        <option value="{{ $estado }}" @selected(($solicitudFilters['estado'] ?? '') === $estado)>{{ str_replace('_', ' ', ucfirst($estado)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tipo solicitud</label>
                <select name="solicitud_tipo" class="form-select">
                    <option value="">Todos</option>
                    @foreach ($tiposSolicitud as $tipo => $label)
                        <option value="{{ $tipo }}" @selected(($solicitudFilters['tipo'] ?? '') === $tipo)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Desde</label>
                <input type="date" name="solicitud_fecha_desde" value="{{ $solicitudFilters['fecha_desde'] ?? '' }}" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Hasta</label>
                <input type="date" name="solicitud_fecha_hasta" value="{{ $solicitudFilters['fecha_hasta'] ?? '' }}" class="form-control">
            </div>
            <div class="col-md-2 d-grid">
                <button class="btn btn-primary">Filtrar solicitudes</button>
            </div>
            <div class="col-md-2 d-grid">
                <a href="{{ route('tramites.cargas-familiares.admin.index') }}" class="btn btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header fw-semibold">Nuevas acreditaciones y actualizaciones ingresadas</div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Fecha envio</th>
                    <th>Solicitante</th>
                    <th>Tipo</th>
                    <th>Estado</th>
                    <th>Causantes</th>
                    <th>Documentos</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($solicitudes as $solicitud)
                    <tr>
                        <td>{{ optional($solicitud->fecha_envio)->format('d/m/Y H:i') ?: '-' }}</td>
                        <td>
                            <div class="fw-semibold">{{ $solicitud->user->nombre_completo ?? $solicitud->user->display_name ?? 'Usuario' }}</div>
                            <div class="small text-muted">{{ $solicitud->user->rut ?? '' }}</div>
                        </td>
                        <td>{{ str_replace('_', ' ', ucfirst($solicitud->tipo_solicitud)) }}</td>
                        <td><span class="badge text-bg-light border text-dark">{{ str_replace('_', ' ', ucfirst($solicitud->estado)) }}</span></td>
                        <td>{{ $solicitud->causantes_count ?? $solicitud->causantes->count() }}</td>
                        <td>{{ $solicitud->documentos_count ?? '-' }}</td>
                        <td class="text-end">
                            <a href="{{ route('tramites.cargas-familiares.review.show', $solicitud) }}" class="btn btn-sm btn-outline-primary">Ver</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No hay solicitudes con los filtros aplicados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($solicitudes->hasPages())
        <div class="card-footer">{{ $solicitudes->links() }}</div>
    @endif
</div>
@endsection
