@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
    <style>
        #padron-filtros .form-control, #padron-filtros .form-select,
        #padron-filtros .select2-container--bootstrap-5 .select2-selection--single {
            min-height: 2.65rem;
            border-color: var(--slep-border, #dbe4f0);
            border-radius: .75rem;
            color: var(--slep-text, #0f172a);
        }
        #padron-filtros .form-control:focus, #padron-filtros .form-select:focus,
        #padron-filtros .select2-container--bootstrap-5.select2-container--focus .select2-selection {
            border-color: var(--slep-blue, #0d6efd);
            box-shadow: 0 0 0 .18rem rgba(13, 110, 253, .12);
        }
        .padron-select-dropdown.select2-dropdown { border-color: #dbe4f0; border-radius: .75rem; overflow: hidden; }
        .padron-select-dropdown .select2-search__field { min-height: 2.65rem; border-radius: .75rem; }
        #padron-filtros .btn-primary {
            --bs-btn-bg: var(--slep-blue, #0d6efd);
            --bs-btn-border-color: var(--slep-blue, #0d6efd);
            --bs-btn-hover-bg: #0b5ed7;
            --bs-btn-hover-border-color: #0b5ed7;
        }
        #padron-filtros .btn-outline-secondary { color: #334155; border-color: #dbe4f0; }
        #padron-filtros .btn-outline-secondary:hover { color: #0f172a; background: #f8fbff; }
    </style>
@endpush

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h3 class="m-0">Reemplazos</h3>
            <div class="text-muted">Gestión de reemplazos y padrón mensual de personal por establecimiento</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary" href="{{ route('reemplazos.buscador-postulantes.index') }}">
                <i class="bi bi-search"></i> Ir al buscador de postulantes y funcionarios
            </a>
            @role('admin')
                <a href="{{ route('reemplazos.individual.index') }}" class="btn btn-outline-primary">
                    <i class="bi bi-person-plus"></i> Gestionar registro individual
                </a>
                <a href="{{ route('reemplazos.personal.import') }}" class="btn btn-primary">
                    <i class="bi bi-upload"></i> Carga masiva
                </a>
            @endrole
        </div>
    </div>

    <ul class="nav nav-pills mb-3">
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('reemplazos.index') ? 'active' : '' }}" href="{{ route('reemplazos.index') }}">
                Inicio
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('reemplazos.buscador-postulantes.*') ? 'active' : '' }}"
                href="{{ route('reemplazos.buscador-postulantes.index') }}">
                Buscador postulantes/funcionarios
            </a>
        </li>
        @role('admin')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('reemplazos.personal.*') ? 'active' : '' }}"
                    href="{{ route('reemplazos.personal.import') }}">
                    Carga masiva
                </a>
            </li>
        @endrole
    </ul>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if (session('traspaso_bloqueos_resumen'))
        @php
            $traspasoResumen = session('traspaso_bloqueos_resumen');
        @endphp
        <div class="alert alert-info">
            <div class="fw-semibold mb-2">Verificación de bloqueos por funcionario</div>
            <div class="small mb-2">
                Origen: <strong>{{ $traspasoResumen['periodo_origen'] ?? '—' }}</strong> ·
                Destino: <strong>{{ $traspasoResumen['periodo_destino'] ?? '—' }}</strong>
            </div>
            <div class="d-flex flex-wrap gap-2 mb-2">
                <span class="badge text-bg-secondary">Encontrados: {{ $traspasoResumen['bloqueos_encontrados'] ?? 0 }}</span>
                <span class="badge text-bg-success">Traspasados: {{ $traspasoResumen['traspasados'] ?? 0 }}</span>
                <span class="badge text-bg-warning">Ya existían: {{ $traspasoResumen['ya_existian'] ?? 0 }}</span>
                <span class="badge text-bg-danger">No encontrados: {{ $traspasoResumen['no_encontrados'] ?? 0 }}</span>
                <span class="badge text-bg-light">Omitidos: {{ $traspasoResumen['omitidos_no_bloqueables'] ?? 0 }}</span>
            </div>
            @if (!empty($traspasoResumen['detalle']))
                <details class="small">
                    <summary>Ver detalle de la verificación</summary>
                    <div class="table-responsive mt-2">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>RUT</th>
                                    <th>Nombre</th>
                                    <th>RBD</th>
                                    <th>Motivo</th>
                                    <th>Resultado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($traspasoResumen['detalle'] as $detalle)
                                    <tr>
                                        <td>{{ $detalle['rut'] ?? '—' }}</td>
                                        <td>{{ $detalle['nombre'] ?? '—' }}</td>
                                        <td>{{ $detalle['rbd'] ?? '—' }}</td>
                                        <td>{{ $detalle['motivo'] ?? '—' }}</td>
                                        <td>{{ $detalle['resultado'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="text-muted mt-1">Se muestran hasta 80 registros de detalle.</div>
                </details>
            @endif
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1">No fue posible completar la acción:</div>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (!$hasPadron)
        <div class="alert alert-warning">
            <div class="fw-semibold">Aún no hay padrón mensual cargado.</div>
            <div class="small mt-1">
                @role('admin')
                    Puedes iniciar la operación desde <a href="{{ route('reemplazos.personal.import') }}">Carga masiva</a>.
                @else
                    Solicita al administrador la carga mensual para habilitar esta vista.
                @endrole
            </div>
        </div>
    @else
        @if (!empty($filters['historico']))
            <div class="alert alert-info">
                Padrón archivado: muestra la última copia disponible del período, conservando los IDs contractuales.
                Es de solo lectura. Los bloqueos indicados son los actuales del funcionario por RUT, no un historial de bloqueos.
            </div>
        @endif
        @if ($lockedWithoutEstablecimiento)
            <div class="alert alert-warning">
                <div class="fw-semibold">Tu usuario no tiene establecimiento asignado.</div>
                <div class="small mt-1">
                    No es posible mostrar el padrón hasta que se asigne un establecimiento al usuario funcionario.
                </div>
            </div>
        @endif

        @php
            $userCanManageBloqueos = empty($filters['historico']) && auth()->check() && method_exists(auth()->user(), 'hasAnyRole') && auth()->user()->hasAnyRole(['admin', 'funcionario_slep', 'coordinador_uatp', 'supervisor_plani', 'coordinador_plani']);
        @endphp

        <div class="slep-card mb-3" id="padron-filtros">
            <div class="p-3 p-lg-4">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                    <div>
                        <h2 class="h5 mb-1"><i class="bi bi-people" aria-hidden="true"></i> Padrón mensual de personal</h2>
                        <div class="text-muted small">
                            Vista operativa del padrón por establecimiento para el período seleccionado.
                            El bloqueo sigue al funcionario por RUT en todos sus contratos, aunque cambie de establecimiento; no requiere traspaso manual.
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('reemplazos.index', ['periodo' => $filters['period_key']]) }}" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-clockwise"></i> Limpiar filtros
                        </a>
                        @if ($userCanManageBloqueos)
                            <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#traspasarBloqueosModal">
                                <i class="bi bi-arrow-left-right"></i> Verificar bloqueos
                            </button>
                        @endif
                        <a href="{{ route('reemplazos.export', request()->query()) }}" class="btn btn-outline-success-dark">
                            <i class="bi bi-download"></i> Exportar CSV
                        </a>
                    </div>
                </div>

                <form method="GET" action="{{ route('reemplazos.index') }}" class="row g-3 align-items-start">
                    <div class="col-md-6 col-xl-2">
                        <label for="padron-periodo" class="form-label">Período</label>
                        <select name="periodo" id="padron-periodo" class="form-select py-2">
                            @foreach ($periodOptions as $period)
                                <option value="{{ $period->key }}" @selected($filters['period_key'] === $period->key)>
                                    {{ $period->label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <label for="padron-comuna" class="form-label">Comuna</label>
                        <select name="comuna" id="padron-comuna" class="form-select py-2 @error('comuna') is-invalid @enderror">
                            <option value="">Todas las comunas</option>
                            @if ($filters['comuna'] !== '' && !$comunas->contains($filters['comuna']))
                                <option value="{{ $filters['comuna'] }}" selected>{{ $filters['comuna'] }}</option>
                            @endif
                            @foreach ($comunas as $comuna)
                                <option value="{{ $comuna }}" @selected($filters['comuna'] === $comuna)>{{ $comuna }}</option>
                            @endforeach
                        </select>
                        @error('comuna')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-12 col-xl-7">
                        <label for="padron-establecimiento" class="form-label">Establecimiento</label>
                        @if ($isFuncionarioEstab)
                            <input type="text" id="padron-establecimiento" class="form-control py-2" value="{{ $forcedEstablecimiento?->nombre_establecimiento ?? 'Sin establecimiento asignado' }}" disabled>
                            @if ($forcedEstablecimiento)
                                <input type="hidden" name="establecimiento_id" value="{{ $forcedEstablecimiento->id }}">
                            @endif
                            <div class="form-text">Tu rol solo puede consultar el padrón de tu establecimiento.</div>
                        @else
                            <select name="establecimiento_id" id="padron-establecimiento" class="form-select py-2">
                                <option value="">Todos los establecimientos</option>
                                @foreach ($establecimientos as $establecimiento)
                                    <option value="{{ $establecimiento->id }}" @selected((int) $filters['establecimiento_id'] === (int) $establecimiento->id)>
                                        {{ $establecimiento->nombre_establecimiento }} (RBD {{ $establecimiento->rbd }})
                                    </option>
                                @endforeach
                            </select>
                        @endif
                    </div>

                    <div class="col-md-8 col-xl-9">
                        <label for="padron-busqueda" class="form-label">Búsqueda</label>
                        <input type="text" name="q" id="padron-busqueda" class="form-control py-2" value="{{ $filters['q'] }}"
                            placeholder="RUT, nombre, RBD o establecimiento">
                    </div>

                    <div class="col-md-4 col-xl-3">
                        <label for="padron-por-pagina" class="form-label">Registros por página</label>
                        <select name="per_page" id="padron-por-pagina" class="form-select py-2">
                            @foreach ([15, 25, 50, 100] as $perPage)
                                <option value="{{ $perPage }}" @selected((int) $filters['per_page'] === $perPage)>{{ $perPage }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12 d-flex flex-wrap gap-2">
                        <button class="btn btn-primary">
                            <i class="bi bi-funnel"></i> Aplicar filtros
                        </button>
                        <a href="{{ route('reemplazos.index', ['periodo' => $filters['period_key']]) }}" class="btn btn-outline-secondary">
                            Quitar filtros adicionales
                        </a>
                    </div>
                </form>
            </div>
        </div>

        @if ($userCanManageBloqueos)
            <div class="modal fade" id="traspasarBloqueosModal" tabindex="-1" aria-labelledby="traspasarBloqueosModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <form method="POST" action="{{ route('reemplazos.personal.traspasar-bloqueos') }}" class="modal-content">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" id="traspasarBloqueosModalLabel">Verificar bloqueos activos por RUT</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-warning small">
                                Los bloqueos siguen al funcionario por RUT automáticamente. Esta acción verifica su presencia en ambos períodos, incluso con otro RBD o ID contractual. No copia, desactiva ni modifica bloqueos.
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Padrón origen <span class="text-danger">*</span></label>
                                <select name="periodo_origen" class="form-select" required>
                                    @foreach ($periodOptions as $period)
                                        <option value="{{ $period->key }}" @selected($period->key !== $filters['period_key'] && $loop->first)>
                                            {{ $period->label }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">Período desde donde se leerán los bloqueos activos.</div>
                            </div>
                            <div class="mb-0">
                                <label class="form-label">Padrón destino <span class="text-danger">*</span></label>
                                <select name="periodo_destino" class="form-select" required>
                                    @foreach ($periodOptions as $period)
                                        <option value="{{ $period->key }}" @selected($filters['period_key'] === $period->key)>
                                            {{ $period->label }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">Período donde se verificará el mismo RUT, sin restringir por establecimiento.</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button class="btn btn-warning">
                                <i class="bi bi-arrow-left-right"></i> Verificar bloqueos
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <div class="row g-3 mb-3">
            <div class="col-lg-3 col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">Período</div>
                        <div class="fs-5 fw-semibold">{{ $selectedPeriodLabel }}</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">Registros filtrados</div>
                        <div class="fs-5 fw-semibold">{{ number_format($summary['total'] ?? 0, 0, ',', '.') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">Establecimientos en resultado</div>
                        <div class="fs-5 fw-semibold">{{ number_format($summary['establecimientos'] ?? 0, 0, ',', '.') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">Última actualización visible</div>
                        <div class="fw-semibold">{{ cl_datetime($summary['ultima_actualizacion'] ?? null, 'd/m/Y H:i') }}</div>
                        @if ($latestFile)
                            <div class="small text-muted mt-1">Archivo: {{ $latestFile }}</div>
                        @endif
                    </div>
                </div>
            </div>            <div class="col-lg-3 col-md-6">
                <div class="card h-100 border-warning-subtle">
                    <div class="card-body">
                        <div class="text-muted small">Personal bloqueado visible</div>
                        <div class="fs-5 fw-semibold text-warning-emphasis">{{ number_format($summary['bloqueados'] ?? 0, 0, ',', '.') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <div>
                        <h5 class="card-title mb-1">Resumen por establecimiento</h5>
                        <div class="text-muted small">Cantidad de registros del padrón según filtros aplicados.</div>
                    </div>
                    <span class="badge text-bg-light">{{ $resumenEstablecimientos->count() }} establecimiento(s)</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 90px;">RBD</th>
                                <th>Establecimiento</th>
                                <th style="width: 160px;">Registros</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($resumenEstablecimientos as $item)
                                <tr>
                                    <td>{{ $item->rbd ?: '—' }}</td>
                                    <td>{{ $item->establecimiento_nombre ?: 'Sin establecimiento' }}</td>
                                    <td>{{ number_format($item->total_registros, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">No hay resultados para el filtro aplicado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @php
            $userCanManageBloqueos = empty($filters['historico']) && auth()->check() && method_exists(auth()->user(), 'hasAnyRole') && auth()->user()->hasAnyRole(['admin', 'funcionario_slep', 'coordinador_uatp', 'supervisor_plani', 'coordinador_plani']);
            $canEditPadronRows = empty($filters['historico']) && auth()->check() && method_exists(auth()->user(), 'hasRole') && auth()->user()->hasRole('admin') && !empty($filters['establecimiento_id']);
            $showAccionesPadron = $canEditPadronRows || $userCanManageBloqueos;
        @endphp

        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <div>
                        <h5 class="card-title mb-1">Detalle del padrón</h5>
                        <div class="text-muted small">Personal cargado para {{ $selectedPeriodLabel }}.</div>
                    </div>
                    <span class="badge text-bg-light">{{ number_format($personal->total(), 0, ',', '.') }} registro(s)</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle">
                        <thead>
                            <tr>
                                <th style="min-width: 90px;">RBD</th>
                                <th style="min-width: 240px;">Establecimiento</th>
                                <th style="min-width: 120px;">RUT</th>
                                <th style="min-width: 220px;">Nombre</th>
                                <th style="min-width: 110px;">F. ingreso</th>
                                <th style="min-width: 110px;">F. término</th>
                                <th style="min-width: 150px;">Tipo contrato</th>
                                <th style="min-width: 140px;">Estatuto</th>
                                <th style="min-width: 140px;">Escalafón</th>
                                <th style="min-width: 110px;">Jornada</th>
                                <th style="min-width: 140px;">Financiamiento</th>
                                @if ($showAccionesPadron)
                                    <th style="min-width: 260px;">Acciones</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($personal as $row)
                                @php
                                    $estatutoRow = strtoupper(trim((string) $row->estatuto));
                                    $escalafonRow = strtoupper(trim((string) $row->escalafon));
                                    $textoClasificacionRow = trim($estatutoRow . ' ' . $escalafonRow);
                                    $rowEsDocente = in_array($estatutoRow, ['DOCENTE', 'PROFESOR', 'PROFESORA'], true) || str_contains($estatutoRow, 'DOC');
                                    $rowEsAaee = in_array($estatutoRow, ['AAEE', 'ASISTENTE DE LA EDUCACION', 'ASISTENTE DE LA EDUCACIÓN'], true)
                                        || str_contains($textoClasificacionRow, 'AAEE')
                                        || str_contains($textoClasificacionRow, 'ASISTENTE DE LA EDUCACION')
                                        || str_contains($textoClasificacionRow, 'ASISTENTE DE LA EDUCACIÓN')
                                        || str_contains($textoClasificacionRow, 'ASISTENTES DE LA EDUCACION')
                                        || str_contains($textoClasificacionRow, 'ASISTENTES DE LA EDUCACIÓN');
                                    $rowEsBloqueable = $rowEsDocente || $rowEsAaee;
                                    $rowTipoBloqueo = $rowEsAaee ? 'AAEE' : 'Docente';
                                    $rowTipoBloqueoDetalle = $rowEsAaee ? 'asistente de la educación' : 'docente';
                                @endphp
                                <tr>
                                    <td>{{ $row->rbd }}</td>
                                    <td>{{ $row->establecimiento?->nombre_establecimiento ?? '—' }}</td>
                                    <td>{{ $row->rut }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $row->nombre }}</div>
                                        @if ($row->bloqueoActivo)
                                            <span class="badge text-bg-warning" title="{{ $row->bloqueoActivo->motivo }}">
                                                <i class="bi bi-lock-fill"></i> {{ $rowTipoBloqueo }} bloqueado
                                            </span>
                                            <div class="small text-muted mt-1">Motivo: {{ $row->bloqueoActivo->motivo }}</div>
                                        @endif
                                    </td>
                                    <td>{{ optional($row->fecha_ingreso)->format('d/m/Y') ?: '—' }}</td>
                                    <td>{{ optional($row->fecha_termino)->format('d/m/Y') ?: '—' }}</td>
                                    <td>{{ $row->tipocontrato ?: '—' }}</td>
                                    <td>{{ $row->estatuto ?: '—' }}</td>
                                    <td>{{ $row->escalafon ?: '—' }}</td>
                                    <td>{{ $row->jornada ?? '—' }}</td>
                                    <td>{{ $row->financiamiento ?: '—' }}</td>
                                    @if ($showAccionesPadron)
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                @if ($canEditPadronRows)
                                                    <a href="{{ route('reemplazos.personal.edit', array_merge(['reemplazoPersonal' => $row->id], request()->query())) }}"
                                                        class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-pencil-square"></i> Editar
                                                    </a>
                                                @endif

                                                @if ($userCanManageBloqueos)
                                                    @if ($rowEsBloqueable && $row->bloqueoActivo)
                                                        <form method="POST" action="{{ route('reemplazos.personal.desbloquear', $row) }}" onsubmit="return confirm('¿Desbloquear a este funcionario en TODOS sus contratos y establecimientos?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            @foreach (request()->query() as $key => $value)
                                                                @if (is_scalar($value))
                                                                    <input type="hidden" name="return[{{ $key }}]" value="{{ $value }}">
                                                                @endif
                                                            @endforeach
                                                            <button class="btn btn-sm btn-outline-success">
                                                                <i class="bi bi-unlock"></i> Desbloquear
                                                            </button>
                                                        </form>
                                                    @elseif ($rowEsBloqueable)
                                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#bloquearDocenteModal{{ $row->id }}">
                                                            <i class="bi bi-lock"></i> Bloquear
                                                        </button>

                                                        <div class="modal fade" id="bloquearDocenteModal{{ $row->id }}" tabindex="-1" aria-labelledby="bloquearDocenteModalLabel{{ $row->id }}" aria-hidden="true">
                                                            <div class="modal-dialog">
                                                                <form method="POST" action="{{ route('reemplazos.personal.bloquear', $row) }}" class="modal-content">
                                                                    @csrf
                                                                    @foreach (request()->query() as $key => $value)
                                                                        @if (is_scalar($value))
                                                                            <input type="hidden" name="return[{{ $key }}]" value="{{ $value }}">
                                                                        @endif
                                                                    @endforeach
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title" id="bloquearDocenteModalLabel{{ $row->id }}">Bloquear {{ $rowTipoBloqueoDetalle }} titular</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <div class="alert alert-warning small">
                                                                            El {{ $rowTipoBloqueoDetalle }} quedará impedido de ser usado en nuevas solicitudes de reemplazo en todos sus contratos y establecimientos mientras el bloqueo esté activo. Un traslado o una nueva carga de padrón no lo elimina.
                                                                        </div>
                                                                        <div class="mb-2">
                                                                            <div class="fw-semibold">{{ $row->rut }} - {{ $row->nombre }}</div>
                                                                            <div class="small text-muted">{{ $row->establecimiento?->nombre_establecimiento ?? '—' }} · RBD {{ $row->rbd }}</div>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Motivo <span class="text-danger">*</span></label>
                                                                            <input type="text" name="motivo" class="form-control" maxlength="255" required placeholder="Ej.: titular no gestionable para reemplazo">
                                                                        </div>
                                                                        <div class="mb-0">
                                                                            <label class="form-label">Observación</label>
                                                                            <textarea name="observacion" class="form-control" rows="3" maxlength="2000" placeholder="Detalle administrativo opcional"></textarea>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                                        <button class="btn btn-danger"><i class="bi bi-lock"></i> Bloquear {{ $rowTipoBloqueo }}</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    @endif
                                                @endif
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $showAccionesPadron ? 13 : 12 }}" class="text-center text-muted py-4">No hay personal para el filtro aplicado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($personal->hasPages())
                    <div class="mt-3">
                        {{ $personal->links() }}
                    </div>
                @endif
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const establecimiento = document.querySelector('select#padron-establecimiento');
            if (!establecimiento || !window.jQuery?.fn?.select2) return;
            window.jQuery(establecimiento).select2({
                theme: 'bootstrap-5', width: '100%', allowClear: true,
                placeholder: 'Todos los establecimientos', dropdownCssClass: 'padron-select-dropdown',
                language: { noResults: () => 'No se encontraron establecimientos' },
            });
        });
    </script>
@endpush
