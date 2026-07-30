@extends('layouts.app')

@section('content')
    <div class="gestion-solicitudes-index">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="h4 mb-1">Gestión de solicitudes de reemplazo</h1>
                <div class="text-muted">UATP / Planificación / GDP / SLEP</div>
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <div class="fw-semibold mb-1">Revisa lo siguiente:</div>
                <ul class="mb-0">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php
            $estadoLabel = function ($e) {
                return match ($e) {
                    'pendiente_uatp' => 'Pendiente UATP',
                    'pendiente_validacion' => 'Pendiente de Validación',
                    'pendiente_gdp' => 'Pendiente GDP',
                    'derivada_slep' => 'Derivada a SLEP',
                    'aceptada' => 'Aceptada',
                    'cerrado' => 'Cerrado',
                    'rechazada' => 'Rechazada',
                    'rechazada_uatp' => 'Rechazada UATP',
                    'rechazada_plani' => 'Rechazada Planificación',
                    'rechazada_gdp' => 'Rechazada GDP',
                    default => $e,
                };
            };

            $estadoClass = function ($e) {
                return match ($e) {
                    'pendiente_uatp' => 'text-bg-warning',
                    'pendiente_validacion' => 'text-bg-primary',
                    'pendiente_gdp' => 'text-bg-warning',
                    'derivada_slep' => 'text-bg-info',
                    'aceptada' => 'text-bg-success',
                    'cerrado' => 'text-bg-dark',
                    'rechazada', 'rechazada_uatp', 'rechazada_plani', 'rechazada_gdp' => 'text-bg-danger',
                    default => 'text-bg-secondary',
                };
            };

            $reemplazoTxt = function ($s) {
                $p = $s->postulante ?? $s->contratoPostulante ?? null;
                if (!$p || !$p->user) {
                    return null;
                }
                $u = $p->user;
                $nombre = trim(($u->apellido_paterno ?? '') . ' ' . ($u->apellido_materno ?? '') . ' ' . ($u->nombres ?? ''));
                $rut = $u->rut ?? null;
                return trim($nombre . ($rut ? " ($rut)" : ''));
            };

            $fechaSolicitudTxt = fn($s) => cl_datetime($s->created_at);
            $fechaDecisionUatpTxt = fn($s) => cl_datetime($s->uatp_decision_at);
            $fmtHoras = function ($n) {
                $n = (float) ($n ?? 0);
                $s = rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
                return $s === '' ? '0' : $s;
            };
            $titularEsDocente = function ($solicitud) {
                $estatuto = strtoupper(trim((string) ($solicitud->funcionarioTitular?->estatuto ?? '')));
                return $estatuto !== '' && (in_array($estatuto, ['DOCENTE', 'PROFESOR', 'PROFESORA'], true) || str_contains($estatuto, 'DOC'));
            };
            $decisionUatpUserTxt = function ($s) {
                $u = $s->uatpDecisionUser ?? null;
                if (!$u) return null;
                $nombre = trim(($u->apellido_paterno ?? '') . ' ' . ($u->apellido_materno ?? '') . ' ' . ($u->nombres ?? ''));
                $rut = $u->rut ?? null;
                return trim($nombre . ($rut ? ' (' . $rut . ')' : ''));
            };
            $decisionPlaniUserTxt = function ($s) {
                $u = $s->planiDecisionUser ?? null;
                if (!$u) return null;
                $nombre = trim(($u->apellido_paterno ?? '') . ' ' . ($u->apellido_materno ?? '') . ' ' . ($u->nombres ?? ''));
                $rut = $u->rut ?? null;
                return trim($nombre . ($rut ? ' (' . $rut . ')' : ''));
            };
            $userAuth = auth()->user();
            $isGdp = $userAuth && method_exists($userAuth, 'hasAnyRole') ? $userAuth->hasAnyRole(['admin', 'coordinador_gdp']) : false;
            $isAdmin = $userAuth && method_exists($userAuth, 'hasRole') ? $userAuth->hasRole('admin') : false;
            $isCoordinadorGdp = $userAuth && method_exists($userAuth, 'hasRole') ? $userAuth->hasRole('coordinador_gdp') : false;
            $isFuncionarioSlepRole = $userAuth && method_exists($userAuth, 'hasRole') ? $userAuth->hasRole('funcionario_slep') : false;
            $isCoordinadorUatp = $userAuth && method_exists($userAuth, 'hasRole') ? $userAuth->hasRole('coordinador_uatp') : false;
            $isCoordinadorPlani = $userAuth && method_exists($userAuth, 'hasAnyRole') ? $userAuth->hasAnyRole(['supervisor_plani', 'coordinador_plani']) : false;
            $canExportSolicitudes = $userAuth && method_exists($userAuth, 'hasAnyRole') ? $userAuth->hasAnyRole(['admin', 'coordinador_gdp']) : false;

            $hasPendientesUatpFilters = collect(['p_numero', 'p_establecimiento_id', 'p_titular', 'p_desde', 'p_hasta'])
                ->contains(fn ($key) => filled(request($key)));
            $hasPendientesValidacionFilters = collect(['v_numero', 'v_establecimiento_id', 'v_titular', 'v_desde', 'v_hasta'])
                ->contains(fn ($key) => filled(request($key)));
            $hasOtrasFilters = collect(['o_numero', 'o_establecimiento_id', 'o_estado', 'o_desde', 'o_hasta', 'o_titular', 'o_reemplazo', 'o_derivada_a'])
                ->contains(fn ($key) => filled(request($key)));

            $expandPendientesUatp = $hasPendientesUatpFilters || $isCoordinadorUatp;
            $expandPendientesValidacion = $hasPendientesValidacionFilters || $isCoordinadorPlani;
            $expandOtras = $hasOtrasFilters || $isCoordinadorGdp || $isFuncionarioSlepRole;

            if ($isAdmin) {
                $expandPendientesUatp = $hasPendientesUatpFilters;
                $expandPendientesValidacion = $hasPendientesValidacionFilters;
                $expandOtras = $hasOtrasFilters;
            }
        @endphp

        @if (!empty($canViewPendientesValidacion) && $pendientesValidacion)
            <div class="card mb-4">
                <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                    @if (!empty($canCollapsePendientesValidacion))
                        <button class="btn btn-link p-0 text-decoration-none fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePendientesValidacion" aria-expanded="{{ $expandPendientesValidacion ? 'true' : 'false' }}" aria-controls="collapsePendientesValidacion">
                            Solicitudes Pendiente de Validación de Planificación
                            <span class="ms-2 text-muted" style="font-size: .85rem;">(clic para expandir o contraer)</span>
                        </button>
                    @else
                        <span>Solicitudes Pendiente de Validación de Planificación</span>
                    @endif
                    <div class="d-flex align-items-center gap-2">
                        @if ($canExportSolicitudes && Route::has('gestion.solicitudes-reemplazo.exportar'))
                            <a class="btn btn-sm btn-outline-success" href="{{ route('gestion.solicitudes-reemplazo.exportar', array_merge(request()->query(), ['scope' => 'validacion'])) }}">
                                <i class="bi bi-file-earmark-spreadsheet"></i> Descargar
                            </a>
                        @endif
                        <span class="badge text-bg-primary">{{ $pendientesValidacion->total() }}</span>
                    </div>
                </div>
                @if (!empty($canCollapsePendientesValidacion))
                    <div class="collapse {{ $expandPendientesValidacion ? 'show' : '' }}" id="collapsePendientesValidacion">
                @endif
                <div class="card-body">
                    <form method="GET" class="row g-2 align-items-end mb-3">
                        <input type="hidden" name="p_numero" value="{{ request('p_numero') }}">
                        <input type="hidden" name="p_establecimiento_id" value="{{ request('p_establecimiento_id') }}">
                        <input type="hidden" name="p_titular" value="{{ request('p_titular') }}">
                        <input type="hidden" name="p_desde" value="{{ request('p_desde') }}">
                        <input type="hidden" name="p_hasta" value="{{ request('p_hasta') }}">
                        <input type="hidden" name="o_numero" value="{{ request('o_numero') }}">
                        <input type="hidden" name="o_establecimiento_id" value="{{ request('o_establecimiento_id') }}">
                        <input type="hidden" name="o_estado" value="{{ request('o_estado') }}">
                        <input type="hidden" name="o_desde" value="{{ request('o_desde') }}">
                        <input type="hidden" name="o_hasta" value="{{ request('o_hasta') }}">
                        <input type="hidden" name="o_titular" value="{{ request('o_titular') }}">
                        <input type="hidden" name="o_reemplazo" value="{{ request('o_reemplazo') }}">
                        <input type="hidden" name="o_derivada_a" value="{{ request('o_derivada_a') }}">

                        <div class="col-12 col-md-2">
                            <label class="form-label mb-1">N° solicitud</label>
                            <input type="text" name="v_numero" value="{{ request('v_numero') }}" class="form-control form-control-sm">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label mb-1">Establecimiento</label>
                            <select name="v_establecimiento_id" class="form-select form-select-sm js-establecimiento-select2">
                                <option value="">Todos</option>
                                @foreach ($establecimientos as $e)
                                    <option value="{{ $e->id }}" @selected((string) $e->id === (string) request('v_establecimiento_id'))>{{ $e->nombre_establecimiento }} (RBD {{ $e->rbd }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label mb-1">Titular</label>
                            <input type="text" name="v_titular" value="{{ request('v_titular') }}" class="form-control form-control-sm" placeholder="Nombre o RUT">
                        </div>
                        <div class="col-6 col-md-1">
                            <label class="form-label mb-1">Desde</label>
                            <input type="date" name="v_desde" value="{{ request('v_desde') }}" class="form-control form-control-sm">
                        </div>
                        <div class="col-6 col-md-1">
                            <label class="form-label mb-1">Hasta</label>
                            <input type="date" name="v_hasta" value="{{ request('v_hasta') }}" class="form-control form-control-sm">
                        </div>
                        <div class="col-12 col-md-1 d-grid"><button class="btn btn-sm btn-primary">Filtrar</button></div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>N°</th>
                                    <th>Fecha aprobación UATP</th>
                                    <th>Establecimiento</th>
                                    <th>Titular</th>
                                    <th>Área desempeño</th>
                                    <th>Justificación UATP</th>
                                    <th>Estado</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($pendientesValidacion as $s)
                                    @php
                                        $establecimientoTxt = $s->establecimiento ? ($s->establecimiento->nombre_establecimiento . ' (RBD ' . $s->establecimiento->rbd . ')') : '—';
                                        $titularTxt = $s->funcionarioTitular ? trim($s->funcionarioTitular->nombre . ($s->funcionarioTitular->rut ? ' (' . $s->funcionarioTitular->rut . ')' : '')) : '—';
                                    @endphp
                                    <tr>
                                        <td class="text-nowrap">{{ $s->numero_solicitud }}</td>
                                        <td>{{ $fechaDecisionUatpTxt($s) }}</td>
                                        <td>{{ $establecimientoTxt }}</td>
                                        <td>{{ $titularTxt }}</td>
                                        <td>{{ $s->areaDesempeno?->nombre ?? '—' }}</td>
                                        <td class="small" style="max-width: 320px; white-space: normal;">{{ \Illuminate\Support\Str::limit($s->justificacion_tecnica_uatp, 160) }}</td>
                                        <td>
                                            <span class="badge {{ $estadoClass($s->estado) }}">{{ $estadoLabel($s->estado) }}</span>
                                            @if ($decisionUatpUserTxt($s))
                                                <div class="text-muted small mt-1">UATP: {{ $decisionUatpUserTxt($s) }}</div>
                                            @endif
                                        </td>
                                        <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-primary" href="{{ route('gestion.solicitudes-reemplazo.show', $s) }}">Ver</a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="text-muted">No hay solicitudes pendientes de validación.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $pendientesValidacion->links() }}</div>
                </div>
                @if (!empty($canCollapsePendientesValidacion))</div>@endif
            </div>
        @endif

        <div class="card mb-4">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                @if (!empty($canCollapsePendientesUatp))
                    <button class="btn btn-link p-0 text-decoration-none fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePendientesUatp" aria-expanded="{{ $expandPendientesUatp ? 'true' : 'false' }}" aria-controls="collapsePendientesUatp">
                        Solicitudes Pendiente de Aprobación UATP
                        <span class="ms-2 text-muted" style="font-size: .85rem;">(clic para expandir o contraer)</span>
                    </button>
                @else
                    <span>Solicitudes Pendiente de Aprobación UATP</span>
                @endif
                <div class="d-flex align-items-center gap-2">
                    @if ($canExportSolicitudes && Route::has('gestion.solicitudes-reemplazo.exportar'))
                        <a class="btn btn-sm btn-outline-success" href="{{ route('gestion.solicitudes-reemplazo.exportar', array_merge(request()->query(), ['scope' => 'uatp'])) }}">
                            <i class="bi bi-file-earmark-spreadsheet"></i> Descargar
                        </a>
                    @endif
                    <span class="badge text-bg-warning">{{ $pendientesUatp->total() }}</span>
                </div>
            </div>
            @if (!empty($canCollapsePendientesUatp))
                <div class="collapse {{ $expandPendientesUatp ? 'show' : '' }}" id="collapsePendientesUatp">
            @endif
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end mb-3">
                    <input type="hidden" name="o_numero" value="{{ request('o_numero') }}">
                    <input type="hidden" name="o_establecimiento_id" value="{{ request('o_establecimiento_id') }}">
                    <input type="hidden" name="o_estado" value="{{ request('o_estado') }}">
                    <input type="hidden" name="o_desde" value="{{ request('o_desde') }}">
                    <input type="hidden" name="o_hasta" value="{{ request('o_hasta') }}">
                    <input type="hidden" name="o_titular" value="{{ request('o_titular') }}">
                    <input type="hidden" name="o_reemplazo" value="{{ request('o_reemplazo') }}">
                    <input type="hidden" name="o_derivada_a" value="{{ request('o_derivada_a') }}">
                    <input type="hidden" name="v_numero" value="{{ request('v_numero') }}">
                    <input type="hidden" name="v_establecimiento_id" value="{{ request('v_establecimiento_id') }}">
                    <input type="hidden" name="v_titular" value="{{ request('v_titular') }}">
                    <input type="hidden" name="v_desde" value="{{ request('v_desde') }}">
                    <input type="hidden" name="v_hasta" value="{{ request('v_hasta') }}">

                    <div class="col-12 col-md-2">
                        <label class="form-label mb-1">N° solicitud</label>
                        <input type="text" name="p_numero" value="{{ request('p_numero') }}" class="form-control form-control-sm">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label mb-1">Establecimiento</label>
                        <select name="p_establecimiento_id" class="form-select form-select-sm js-establecimiento-select2">
                            <option value="">Todos</option>
                            @foreach ($establecimientos as $e)
                                <option value="{{ $e->id }}" @selected((string) $e->id === (string) request('p_establecimiento_id'))>{{ $e->nombre_establecimiento }} (RBD {{ $e->rbd }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label mb-1">Titular</label>
                        <input type="text" name="p_titular" value="{{ request('p_titular') }}" class="form-control form-control-sm" placeholder="Nombre o RUT">
                    </div>
                    <div class="col-6 col-md-1">
                        <label class="form-label mb-1">Desde</label>
                        <input type="date" name="p_desde" value="{{ request('p_desde') }}" class="form-control form-control-sm">
                    </div>
                    <div class="col-6 col-md-1">
                        <label class="form-label mb-1">Hasta</label>
                        <input type="date" name="p_hasta" value="{{ request('p_hasta') }}" class="form-control form-control-sm">
                    </div>
                    <div class="col-12 col-md-1 d-grid">
                        <button class="btn btn-sm btn-primary">Filtrar</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>N°</th>
                                <th>Fecha de solicitud</th>
                                <th>Establecimiento</th>
                                <th>Titular</th>
                                <th>Área desempeño</th>
                                <th>Periodo</th>
                                <th>Horas aula</th>
                                <th>Reemplazo</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pendientesUatp as $s)
                                @php
                                    $establecimientoTxt = $s->establecimiento ? ($s->establecimiento->nombre_establecimiento . ' (RBD ' . $s->establecimiento->rbd . ')') : '—';
                                    $titularTxt = $s->funcionarioTitular ? trim($s->funcionarioTitular->nombre . ($s->funcionarioTitular->rut ? ' (' . $s->funcionarioTitular->rut . ')' : '')) : '—';
                                    $reemplazo = $reemplazoTxt($s) ?? '—';
                                    $mostrarHorasAula = $titularEsDocente($s);
                                @endphp
                                <tr>
                                    <td class="text-nowrap">{{ $s->numero_solicitud }}</td>
                                    <td>{{ $fechaSolicitudTxt($s) }}</td>
                                    <td>{{ $establecimientoTxt }}</td>
                                    <td>{{ $titularTxt }}</td>
                                    <td>{{ $s->areaDesempeno?->nombre ?? '—' }}</td>
                                    <td>{{ optional($s->fecha_inicio)->format('d-m-Y') ?? '—' }} <span class="text-muted">a</span> {{ optional($s->fecha_termino)->format('d-m-Y') ?? '—' }}</td>
                                    <td class="small">
                                        @if ($mostrarHorasAula)
                                            <div><strong>T:</strong> C {{ $fmtHoras($s->horas_aula_cronologicas_titular) }} | P {{ $fmtHoras($s->horas_aula_pedagogicas_titular) }}</div>
                                            <div><strong>R:</strong> C {{ $fmtHoras($s->horas_aula_cronologicas_reemplazo) }} | P {{ $fmtHoras($s->horas_aula_pedagogicas_reemplazo) }}</div>
                                        @else
                                            <span class="text-muted">No aplica</span>
                                        @endif
                                    </td>
                                    <td>{{ $reemplazo }}</td>
                                    <td><span class="badge {{ $estadoClass($s->estado) }}">{{ $estadoLabel($s->estado) }}</span></td>
                                    <td class="text-end text-nowrap">
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('gestion.solicitudes-reemplazo.show', $s) }}">Ver</a>
                                        @if (!empty($s->contrato_trabajo_docx_path))
                                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('gestion.solicitudes-reemplazo.contrato-trabajo.download', $s) }}">CT</a>
                                        @endif
                                        @if (!empty($s->orden_trabajo_pdf_path))
                                            <a class="btn btn-sm btn-outline-success-dark" href="{{ route('gestion.solicitudes-reemplazo.ot.download', ['solicitud' => $s, 'regenerar' => 1]) }}">OT</a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="10" class="text-muted">No hay solicitudes pendientes UATP.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $pendientesUatp->links() }}</div>
            </div>
            @if (!empty($canCollapsePendientesUatp))</div>@endif
        </div>

        @if (!empty($showResumenSlep))
            <div class="card mb-4">
                <div class="card-header fw-semibold d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <span>Resumen solicitudes SLEP por usuario</span>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge text-bg-info">Derivada SLEP: {{ $totalesSlepEstados['derivada_slep'] ?? 0 }}</span>
                        <span class="badge text-bg-success">Aceptada: {{ $totalesSlepEstados['aceptada'] ?? 0 }}</span>
                        <span class="badge text-bg-dark">Cerrada: {{ $totalesSlepEstados['cerrado'] ?? 0 }}</span>
                        <span class="badge text-bg-secondary">Anulada: {{ $totalesSlepEstados['anulada'] ?? 0 }}</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th class="text-end">Derivada SLEP</th>
                                    <th class="text-end">Aceptada</th>
                                    <th class="text-end">Cerrada</th>
                                    <th class="text-end">Anulada</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($resumenSlepPorUsuario as $row)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $row['user_name'] }}</div>
                                            @if (!empty($row['rut']))<div class="text-muted" style="font-size:12px;">{{ $row['rut'] }}</div>@endif
                                        </td>
                                        <td class="text-end">{{ $row['derivada_slep'] }}</td>
                                        <td class="text-end">{{ $row['aceptada'] }}</td>
                                        <td class="text-end">{{ $row['cerrado'] }}</td>
                                        <td class="text-end">{{ $row['anulada'] }}</td>
                                        <td class="text-end fw-semibold">{{ $row['total'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-3">No hay solicitudes asignadas (derivada_a_user_id) para resumir.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        @if (!empty($showOtras))
            <div class="card">
                <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                    @if (!empty($canCollapseOtras))
                        <button class="btn btn-link p-0 text-decoration-none fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOtrasSolicitudes" aria-expanded="{{ $expandOtras ? 'true' : 'false' }}" aria-controls="collapseOtrasSolicitudes">
                            Solicitudes Pendiente de Gestión GDP
                            <span class="ms-2 text-muted" style="font-size: .85rem;">(clic para expandir o contraer)</span>
                        </button>
                    @else
                        <span>Solicitudes Pendiente de Gestión GDP</span>
                    @endif
                    <div class="d-flex align-items-center gap-2">
                        @if ($canExportSolicitudes && Route::has('gestion.solicitudes-reemplazo.exportar'))
                            <a class="btn btn-sm btn-outline-success" href="{{ route('gestion.solicitudes-reemplazo.exportar', array_merge(request()->query(), ['scope' => 'gdp'])) }}">
                                <i class="bi bi-file-earmark-spreadsheet"></i> Descargar
                            </a>
                        @endif
                        <span class="badge text-bg-secondary">{{ $otras?->total() ?? 0 }}</span>
                    </div>
                </div>
                @if (!empty($canCollapseOtras))
                    <div class="collapse {{ $expandOtras ? 'show' : '' }}" id="collapseOtrasSolicitudes">
                @endif
                <div class="card-body">
                    <form method="GET" class="row g-2 align-items-end mb-3">
                        <input type="hidden" name="p_numero" value="{{ request('p_numero') }}">
                        <input type="hidden" name="p_establecimiento_id" value="{{ request('p_establecimiento_id') }}">
                        <input type="hidden" name="p_titular" value="{{ request('p_titular') }}">
                        <input type="hidden" name="p_desde" value="{{ request('p_desde') }}">
                        <input type="hidden" name="p_hasta" value="{{ request('p_hasta') }}">
                        <input type="hidden" name="v_numero" value="{{ request('v_numero') }}">
                        <input type="hidden" name="v_establecimiento_id" value="{{ request('v_establecimiento_id') }}">
                        <input type="hidden" name="v_titular" value="{{ request('v_titular') }}">
                        <input type="hidden" name="v_desde" value="{{ request('v_desde') }}">
                        <input type="hidden" name="v_hasta" value="{{ request('v_hasta') }}">

                        <div class="col-12 col-md-2"><label class="form-label mb-1">N° solicitud</label><input type="text" name="o_numero" value="{{ request('o_numero') }}" class="form-control form-control-sm"></div>
                        <div class="col-12 col-md-3">
                            <label class="form-label mb-1">Establecimiento</label>
                            <select name="o_establecimiento_id" class="form-select form-select-sm js-establecimiento-select2">
                                <option value="">Todos</option>
                                @foreach ($establecimientos as $e)
                                    <option value="{{ $e->id }}" @selected((string) $e->id === (string) request('o_establecimiento_id'))>{{ $e->nombre_establecimiento }} (RBD {{ $e->rbd }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label mb-1">Estado</label>
                            <select name="o_estado" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                @foreach (['pendiente_validacion','pendiente_gdp','derivada_slep','aceptada','cerrado','rechazada','rechazada_uatp','rechazada_plani','rechazada_gdp'] as $st)
                                    <option value="{{ $st }}" @selected((string) $st === (string) request('o_estado'))>{{ $estadoLabel($st) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-md-1"><label class="form-label mb-1">Desde</label><input type="date" name="o_desde" value="{{ request('o_desde') }}" class="form-control form-control-sm"></div>
                        <div class="col-6 col-md-1"><label class="form-label mb-1">Hasta</label><input type="date" name="o_hasta" value="{{ request('o_hasta') }}" class="form-control form-control-sm"></div>
                        <div class="col-12 col-md-2"><label class="form-label mb-1">Titular</label><input type="text" name="o_titular" value="{{ request('o_titular') }}" class="form-control form-control-sm" placeholder="Nombre o RUT"></div>
                        <div class="col-12 col-md-2"><label class="form-label mb-1">Reemplazo</label><input type="text" name="o_reemplazo" value="{{ request('o_reemplazo') }}" class="form-control form-control-sm" placeholder="Nombre o RUT"></div>
                        <div class="col-12 col-md-3">
                            <label class="form-label mb-1">Derivada a</label>
                            <select name="o_derivada_a" class="form-select form-select-sm js-derivada-a-select2">
                                <option value="">Todas</option>
                                @foreach ($destinatarios as $u)
                                    <option value="{{ $u->id }}" @selected((string) $u->id === (string) request('o_derivada_a'))>{{ $u->full_name }}@if(!empty($u->rut)) ({{ $u->rut }})@endif</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-1 d-grid"><button class="btn btn-sm btn-primary">Filtrar</button></div>
                    </form>

                    @if ($canGdp)
                        <form method="POST" action="{{ route('gestion.solicitudes-reemplazo.gdp.derivar') }}" id="formDerivar">@csrf
                    @endif

                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    @if ($canGdp)<th style="width: 32px;"><input type="checkbox" id="chkAll"></th>@endif
                                    <th>N°</th>
                                    <th>Fecha solicitud</th>
                                    <th>Establecimiento</th>
                                    <th>Titular</th>
                                    <th>Área desempeño</th>
                                    <th>Periodo</th>
                                    <th>Horas aula</th>
                                    <th>Reemplazo</th>
                                    <th>Estado</th>
                                    <th>Fecha aprobación UATP</th>
                                    @if ($canGdp)<th>Derivar a</th>@endif
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($otras ?? collect() as $s)
                                    @php
                                        $establecimientoTxt = $s->establecimiento ? ($s->establecimiento->nombre_establecimiento . ' (RBD ' . $s->establecimiento->rbd . ')') : '—';
                                        $titularTxt = $s->funcionarioTitular ? trim($s->funcionarioTitular->nombre . ($s->funcionarioTitular->rut ? ' (' . $s->funcionarioTitular->rut . ')' : '')) : '—';
                                        $reemplazo = $reemplazoTxt($s) ?? '—';
                                        $derivadaUser = $s->derivadaA;
                                        $derivadaTxt = $derivadaUser ? trim(($derivadaUser->apellido_paterno ?? '') . ' ' . ($derivadaUser->apellido_materno ?? '') . ' ' . ($derivadaUser->nombres ?? '')) . (!empty($derivadaUser->rut) ? ' (' . $derivadaUser->rut . ')' : '') : null;
                                        $mostrarHorasAula = $titularEsDocente($s);
                                    @endphp
                                    <tr>
                                        @if ($canGdp)
                                            <td>
                                                @if ($s->estado === 'pendiente_gdp')
                                                    <input type="checkbox" class="form-check-input js-chk" name="selected[]" value="{{ $s->id }}">
                                                @endif
                                            </td>
                                        @endif
                                        <td class="text-nowrap">{{ $s->numero_solicitud }}</td>
                                        <td>{{ $fechaSolicitudTxt($s) }}</td>
                                        <td>{{ $establecimientoTxt }}</td>
                                        <td>{{ $titularTxt }}</td>
                                        <td>{{ $s->areaDesempeno?->nombre ?? '—' }}</td>
                                        <td>{{ optional($s->fecha_inicio)->format('d-m-Y') ?? '—' }} <span class="text-muted">a</span> {{ optional($s->fecha_termino)->format('d-m-Y') ?? '—' }}</td>
                                        <td class="small">
                                            @if ($mostrarHorasAula)
                                                <div><strong>T:</strong> C {{ $fmtHoras($s->horas_aula_cronologicas_titular) }} | P {{ $fmtHoras($s->horas_aula_pedagogicas_titular) }}</div>
                                                <div><strong>R:</strong> C {{ $fmtHoras($s->horas_aula_cronologicas_reemplazo) }} | P {{ $fmtHoras($s->horas_aula_pedagogicas_reemplazo) }}</div>
                                            @else
                                                <span class="text-muted">No aplica</span>
                                            @endif
                                        </td>
                                        <td>{{ $reemplazo }}</td>
                                        <td>
                                            <span class="badge {{ $estadoClass($s->estado) }}">{{ $estadoLabel($s->estado) }}</span>
                                            @if (!empty($s->justificacion_tecnica_uatp))
                                                <div class="small text-muted mt-1">Justificación UATP: {{ \Illuminate\Support\Str::limit($s->justificacion_tecnica_uatp, 90) }}</div>
                                            @endif
                                            @if (!empty($s->plani_motivo_rechazo))
                                                <div class="small text-muted mt-1">Rechazo Planificación: {{ \Illuminate\Support\Str::limit($s->plani_motivo_rechazo, 90) }}</div>
                                            @endif
                                            @if ($isGdp && in_array($s->estado, ['derivada_slep', 'aceptada', 'cerrado'], true) && $derivadaTxt)
                                                <div class="small text-muted mt-1">Derivada a: {{ $derivadaTxt }}</div>
                                            @endif
                                            @if (!empty($s->observacion_slep))
                                                <div class="small text-muted mt-1">Obs. SLEP: {{ \Illuminate\Support\Str::limit($s->observacion_slep, 90) }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <span>{{ $fechaDecisionUatpTxt($s) }}</span>
                                            @if ($decisionUatpUserTxt($s))
                                                <div class="small text-muted">UATP: {{ $decisionUatpUserTxt($s) }}</div>
                                            @endif
                                            @if ($decisionPlaniUserTxt($s) && in_array($s->estado, ['pendiente_gdp', 'rechazada_plani', 'derivada_slep', 'aceptada', 'cerrado'], true))
                                                <div class="small text-muted">Planificación: {{ $decisionPlaniUserTxt($s) }}</div>
                                            @endif
                                        </td>
                                        @if ($canGdp)
                                            <td>
                                                @if ($s->estado === 'pendiente_gdp')
                                                    <select class="form-select form-select-sm js-der" name="derivaciones[{{ $s->id }}]" disabled>
                                                        <option value="">Seleccione…</option>
                                                        @foreach ($destinatarios as $u)
                                                            <option value="{{ $u->id }}">{{ $u->full_name }}@if(!empty($u->rut)) ({{ $u->rut }})@endif</option>
                                                        @endforeach
                                                    </select>
                                                    <div class="small text-muted mt-1">Marque la casilla para habilitar.</div>
                                                @elseif ($s->estado === 'derivada_slep')
                                                    <select class="form-select form-select-sm js-reasignar" data-url="{{ route('gestion.solicitudes-reemplazo.gdp.reasignar', $s) }}">
                                                        <option value="">Seleccione…</option>
                                                        @foreach ($destinatarios as $u)
                                                            <option value="{{ $u->id }}" @selected((string) $u->id === (string) $s->derivada_a_user_id)>{{ $u->full_name }}@if(!empty($u->rut)) ({{ $u->rut }})@endif</option>
                                                        @endforeach
                                                    </select>
                                                    <div class="small text-muted mt-1">Reasignación inmediata.</div>
                                                @else
                                                    <span class="text-muted">{{ $derivadaTxt ?: '—' }}</span>
                                                @endif
                                            </td>
                                        @endif
                                        <td class="text-end text-nowrap">
                                            <a class="btn btn-sm btn-outline-primary" href="{{ route('gestion.solicitudes-reemplazo.show', $s) }}">Ver</a>
                                            @if (!empty($s->contrato_trabajo_docx_path))<a class="btn btn-sm btn-outline-secondary" href="{{ route('gestion.solicitudes-reemplazo.contrato-trabajo.download', $s) }}">CT</a>@endif
                                            @if (!empty($s->orden_trabajo_pdf_path))<a class="btn btn-sm btn-outline-success-dark" href="{{ route('gestion.solicitudes-reemplazo.ot.download', ['solicitud' => $s, 'regenerar' => 1]) }}">OT</a>@endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="{{ $canGdp ? 12 : 11 }}" class="text-muted">Sin solicitudes.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $otras?->links() }}</div>
                    @if ($canGdp)
                        <div class="mt-3"><button class="btn btn-sm btn-primary" type="submit" onclick="return confirm('¿Derivar las solicitudes seleccionadas?');">Derivar seleccionadas</button></div>
                        </form>
                    @endif
                </div>
                @if (!empty($canCollapseOtras))</div>@endif
            </div>
        @endif
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <style>
        .gestion-solicitudes-index,
        .gestion-solicitudes-index .table,
        .gestion-solicitudes-index .table th,
        .gestion-solicitudes-index .table td,
        .gestion-solicitudes-index .form-label,
        .gestion-solicitudes-index .form-control,
        .gestion-solicitudes-index .form-select,
        .gestion-solicitudes-index .btn,
        .gestion-solicitudes-index .badge,
        .gestion-solicitudes-index .card-header,
        .gestion-solicitudes-index .card-body,
        .gestion-solicitudes-index .page-link,
        .gestion-solicitudes-index .select2-container--default .select2-selection--single,
        .gestion-solicitudes-index .select2-container--default .select2-selection__rendered,
        .gestion-solicitudes-index .select2-container--default .select2-search__field,
        .select2-container .select2-results__option,
        .select2-container .select2-search__field {
            font-size: 13px;
        }
    </style>
@endpush

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        (function() {
            $(function() {
                $('.js-establecimiento-select2').select2({ width: '100%', placeholder: 'Buscar establecimiento', allowClear: true });
                $('.js-derivada-a-select2').select2({ width: '100%', placeholder: 'Buscar destinatario', allowClear: true });
            });
        })();
    </script>
    @if ($canGdp)
        <script>
            (function() {
                const chkAll = document.getElementById('chkAll');
                const rows = () => Array.from(document.querySelectorAll('.js-chk'));
                function syncRow(chk) {
                    const tr = chk.closest('tr');
                    const sel = tr ? tr.querySelector('.js-der') : null;
                    if (!sel) return;
                    sel.disabled = !chk.checked;
                    if (!chk.checked) sel.value = '';
                }
                rows().forEach(chk => chk.addEventListener('change', () => {
                    syncRow(chk);
                    if (chkAll) chkAll.checked = rows().every(c => c.checked || c.disabled);
                }));
                if (chkAll) {
                    chkAll.addEventListener('change', () => {
                        rows().forEach(chk => {
                            chk.checked = chkAll.checked;
                            syncRow(chk);
                        });
                    });
                }
                const token = @json(csrf_token());
                document.querySelectorAll('.js-reasignar').forEach(sel => {
                    sel.addEventListener('change', async () => {
                        const url = sel.getAttribute('data-url');
                        const val = sel.value;
                        if (!url || !val) return;
                        try {
                            const res = await fetch(url, {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': token ?? '',
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify({ derivada_a_user_id: val })
                            });
                            if (!res.ok) {
                                const txt = await res.text();
                                alert('No se pudo actualizar la derivación. ' + txt);
                                return;
                            }
                            window.location.reload();
                        } catch (e) {
                            alert('No se pudo actualizar la derivación.');
                        }
                    });
                });
            })();
        </script>
    @endif
@endpush
