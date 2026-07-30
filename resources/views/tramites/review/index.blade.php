@extends('layouts.app')

@section('content')
    @php
        $activeEstamento = $filters['estamento'] ?? 'docente';
        $baseQuery = request()->except(['page', 'estamento']);
        $exportQuery = request()->query();
        $clearQuery = ['estamento' => $activeEstamento];
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Trámites</h1>
            <div class="text-muted small">Bandeja de solicitudes separadas por estamento del solicitante.</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if (auth()->user()?->hasAnyRole(['admin', 'funcionario_slep', 'coordinador_gdp']))
                <a href="{{ route('tramites.cargas-familiares.admin.index') }}" class="btn btn-outline-dark">
                    <i class="bi bi-clipboard-data"></i> Administrar cargas familiares
                </a>
            @endif
            <a href="{{ route('tramites.cargas-familiares.review.index') }}" class="btn btn-outline-primary">
                <i class="bi bi-people"></i> Revisar cargas familiares
            </a>
            @if (!empty($canExportReviewExcel))
                <a href="{{ route('tramites.export.review-excel', $exportQuery) }}" class="btn btn-success">
                    <i class="bi bi-file-earmark-excel"></i> Exportar Excel
                </a>
            @endif
        </div>
    </div>

    @if ($errors->has('general'))
        <div class="alert alert-danger">{{ $errors->first('general') }}</div>
    @endif

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <ul class="nav nav-tabs mb-3" role="tablist">
        @foreach (($estamentosDisponibles ?? []) as $estamentoKey => $estamentoLabel)
            @php
                $tabQuery = array_merge($baseQuery, ['estamento' => $estamentoKey]);
                $isActive = $activeEstamento === $estamentoKey;
            @endphp
            <li class="nav-item" role="presentation">
                <a class="nav-link {{ $isActive ? 'active fw-semibold' : '' }}" href="{{ route('tramites.index', $tabQuery) }}" role="tab" aria-selected="{{ $isActive ? 'true' : 'false' }}">
                    {{ $estamentoLabel }}
                    <span class="badge {{ $isActive ? 'text-bg-primary' : 'text-bg-secondary' }} ms-1">
                        {{ number_format((int) ($tabCounts[$estamentoKey] ?? 0), 0, ',', '.') }}
                    </span>
                </a>
            </li>
        @endforeach
    </ul>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('tramites.index') }}" class="row g-3 align-items-end">
                <input type="hidden" name="estamento" value="{{ $activeEstamento }}">

                <div class="col-md-4 col-lg-3">
                    <label for="tipo" class="form-label">Tipo de trámite</label>
                    <select name="tipo" id="tipo" class="form-select">
                        <option value="">Todos</option>
                        @foreach (($tiposDisponibles ?? []) as $tipoKey => $tipoLabel)
                            <option value="{{ $tipoKey }}" @selected(($filters['tipo'] ?? '') === $tipoKey)>{{ $tipoLabel }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4 col-lg-3">
                    <label for="estados" class="form-label">Estado</label>
                    <select name="estados[]" id="estados" class="form-select" multiple size="4">
                        @foreach (($estadosDisponibles ?? []) as $estadoKey => $estadoLabel)
                            <option value="{{ $estadoKey }}" @selected(in_array($estadoKey, $filters['estados'] ?? [], true))>{{ $estadoLabel }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Puedes seleccionar más de un estado. El filtro se aplica sólo a la pestaña activa.</div>
                </div>

                <div class="col-md-2 col-lg-2">
                    <label for="fecha_desde" class="form-label">Fecha desde</label>
                    <input type="date" name="fecha_desde" id="fecha_desde" class="form-control" value="{{ $filters['fecha_desde'] ?? '' }}">
                </div>

                <div class="col-md-2 col-lg-2">
                    <label for="fecha_hasta" class="form-label">Fecha hasta</label>
                    <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control" value="{{ $filters['fecha_hasta'] ?? '' }}">
                </div>

                <div class="col-md-12 col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">
                        <i class="bi bi-funnel"></i> Filtrar
                    </button>
                    <a href="{{ route('tramites.index', $clearQuery) }}" class="btn btn-outline-secondary">
                        Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div>
                <div class="fw-semibold">Solicitudes {{ strtolower($estamentosDisponibles[$activeEstamento] ?? 'del estamento seleccionado') }}</div>
                <div class="small text-muted">Los filtros, la paginación y la exportación Excel respetan esta pestaña.</div>
            </div>
            <span class="badge text-bg-primary">{{ number_format((int) $tramites->total(), 0, ',', '.') }} solicitud(es)</span>
        </div>
        <div class="card-body p-0">
            @if ($tramites->count())
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Solicitante</th>
                                <th>Tipo</th>
                                <th>Estado trámite</th>
                                <th>Estamento</th>
                                <th>Establecimiento</th>
                                <th>Enviado</th>
                                <th>Documentos</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tramites as $tramite)
                                @php
                                    $owner = $tramite->user;
                                    $ownerRole = $owner?->roles?->pluck('name')->intersect(['postulante', 'funcionario'])->first();
                                    $estamentoLabel = $activeEstamento === 'asistente' ? 'Asistente de la Educación' : 'Docente';
                                @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $tramite->id }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $tramite->nombre_completo_snapshot ?: ($owner?->nombre_completo ?: '—') }}</div>
                                        <div class="small text-muted">
                                            {{ $tramite->rut_snapshot ?: 'Sin RUT' }}
                                            @if ($ownerRole)
                                                · {{ $ownerRole === 'funcionario' ? 'Funcionario' : 'Postulante' }}
                                            @endif
                                        </div>
                                    </td>
                                    <td>{{ $tramite->tipo_label }}</td>
                                    <td><span class="badge {{ $tramite->estado_badge_class }}">{{ $tramite->estado_label }}</span></td>
                                    <td>
                                        <span class="badge {{ $activeEstamento === 'asistente' ? 'text-bg-success' : 'text-bg-primary' }}">{{ $estamentoLabel }}</span>
                                        @if ($tramite->estatuto_snapshot || $tramite->escalafon_snapshot)
                                            <div class="small text-muted mt-1">{{ $tramite->estatuto_snapshot ?: 'Sin estatuto' }}{{ $tramite->escalafon_snapshot ? ' · ' . $tramite->escalafon_snapshot : '' }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $tramite->establecimiento_nombre_snapshot ?: '—' }}</td>
                                    <td>{{ optional($tramite->enviado_at)->timezone(config('app.display_timezone', 'America/Santiago'))->format('d-m-Y H:i') ?: '—' }}</td>
                                    <td>{{ $tramite->documentos_count }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('tramites.show', $tramite) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i> Revisar
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-4 text-center text-muted">No hay trámites enviados para revisión en la pestaña activa con el filtro aplicado.</div>
            @endif
        </div>
    </div>

    @if ($tramites->hasPages())
        <div class="mt-3">
            {{ $tramites->links() }}
        </div>
    @endif
@endsection
