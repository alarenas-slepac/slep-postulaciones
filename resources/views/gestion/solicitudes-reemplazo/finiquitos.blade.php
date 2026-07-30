@extends('layouts.app')

@push('styles')
    <style>
        .cf-page-header {
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            border: 1px solid #d9e4f3;
            border-radius: 24px;
            box-shadow: 0 18px 44px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .cf-page-header__top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            padding: 1.5rem 1.75rem 1.25rem;
        }

        .cf-page-header__eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            font-size: .78rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #64748b;
            margin-bottom: .45rem;
        }

        .cf-page-header__eyebrow-icon {
            width: 2.75rem;
            height: 2.75rem;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
            color: #fff;
            box-shadow: 0 10px 24px rgba(37, 99, 235, .28);
            font-size: 1.2rem;
        }

        .cf-page-header__title {
            font-size: clamp(1.7rem, 2vw, 2.2rem);
            line-height: 1.1;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: .4rem;
        }

        .cf-page-header__subtitle {
            color: #475569;
            font-size: 1rem;
            margin-bottom: 0;
            max-width: 60rem;
        }

        .cf-role-pill {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .65rem 1rem;
            border-radius: 999px;
            border: 1px solid #cfe0ff;
            background: #eff6ff;
            color: #1d4ed8;
            font-weight: 700;
            white-space: nowrap;
            text-decoration: none;
        }

        .cf-summary-strip {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
            padding: 1.2rem 1.75rem 1.75rem;
            border-top: 1px solid #e5edf6;
            background: linear-gradient(180deg, #fcfdff 0%, #f8fbff 100%);
        }

        .cf-summary-card {
            border: 1px solid #dbe6f2;
            border-radius: 18px;
            background: #fff;
            padding: 1rem 1.1rem;
            min-height: 100%;
        }

        .cf-summary-card__label {
            display: flex;
            align-items: center;
            gap: .5rem;
            font-size: .82rem;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: .4rem;
        }

        .cf-summary-card__value {
            font-size: 1.55rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1;
            margin-bottom: .4rem;
        }

        .cf-summary-card__help {
            color: #64748b;
            font-size: .88rem;
            margin: 0;
        }

        .cf-panel {
            border: 1px solid #d9e4f3;
            border-radius: 22px;
            background: #fff;
            box-shadow: 0 14px 34px rgba(15, 23, 42, .06);
            overflow: hidden;
        }

        .cf-panel__header {
            padding: 1.25rem 1.5rem 1rem;
            border-bottom: 1px solid #e8eef5;
        }

        .cf-panel__eyebrow {
            font-size: .76rem;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: .35rem;
        }

        .cf-panel__title {
            font-size: 1.35rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: .35rem;
        }

        .cf-panel__subtitle {
            color: #64748b;
            margin-bottom: 0;
        }

        .cf-filter-body {
            padding: 1.4rem 1.5rem 1.5rem;
        }

        .cf-filter-body .form-control,
        .cf-filter-body .form-select {
            min-height: 42px;
        }

        .cf-filter-actions--inline {
            align-self: end;
        }

        .cf-filter-actions--inline .cf-btn-outline,
        .cf-filter-actions--inline .cf-btn-danger {
            min-width: 112px;
        }

        .cf-form-label {
            font-weight: 700;
            color: #0f172a;
            margin-bottom: .55rem;
        }

        .cf-filter-actions {
            display: flex;
            gap: .75rem;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .cf-btn-primary,
        .cf-btn-success,
        .cf-btn-secondary,
        .cf-btn-danger,
        .cf-btn-outline {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .55rem;
            min-height: 42px;
            border-radius: 14px;
            font-weight: 800;
            padding: .68rem 1rem;
            text-decoration: none;
            transition: .2s ease;
            border: 1px solid transparent;
            line-height: 1.1;
        }

        .cf-btn-primary {
            border-color: #2563eb;
            background: #2563eb;
            color: #fff;
            box-shadow: 0 12px 24px rgba(37, 99, 235, .22);
        }

        .cf-btn-primary:hover { color: #fff; background: #1d4ed8; border-color: #1d4ed8; }

        .cf-btn-success {
            border-color: #16a34a;
            background: #16a34a;
            color: #fff;
            box-shadow: 0 12px 24px rgba(22, 163, 74, .18);
        }

        .cf-btn-success:hover { color: #fff; background: #15803d; border-color: #15803d; }

        .cf-btn-secondary {
            border-color: #d9e4f3;
            background: #fff;
            color: #0f172a;
        }

        .cf-btn-secondary:hover { color: #0f172a; background: #f8fafc; }

        .cf-btn-danger {
            border-color: #fca5a5;
            background: #fff5f5;
            color: #dc2626;
        }

        .cf-btn-danger:hover { color: #b91c1c; background: #fee2e2; }

        .cf-btn-outline {
            border-color: #cfe0ff;
            background: #eff6ff;
            color: #1d4ed8;
        }

        .cf-btn-outline:hover { color: #1d4ed8; background: #dbeafe; }

        .cf-table-wrap {
            padding: 0 1.25rem 1.25rem;
        }

        .cf-table {
            margin-bottom: 0;
        }

        .cf-table thead th {
            background: #f8fafc;
            color: #334155;
            font-weight: 800;
            font-size: .9rem;
            border-bottom: 1px solid #dbe6f2;
            border-top: 0;
            padding: 1rem .9rem;
            white-space: nowrap;
        }

        .cf-table tbody td {
            padding: 1rem .9rem;
            vertical-align: top;
            border-color: #e8eef5;
        }

        .cf-name {
            font-size: 1.02rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: .25rem;
        }

        .cf-meta {
            color: #64748b;
            font-size: .9rem;
            line-height: 1.45;
        }

        .cf-badge,
        .cf-status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .35rem;
            border-radius: 999px;
            padding: .38rem .72rem;
            font-size: .82rem;
            font-weight: 800;
            line-height: 1;
            white-space: nowrap;
        }

        .cf-status-badge {
            background: #f8fafc;
            color: #334155;
            border: 1px solid #d9e4f3;
        }

        .cf-status-badge--success {
            background: #dcfce7;
            color: #166534;
            border-color: #bbf7d0;
        }

        .cf-status-badge--warning {
            background: #fef3c7;
            color: #92400e;
            border-color: #fde68a;
        }

        .cf-status-badge--dark {
            background: #0f172a;
            color: #fff;
            border-color: #0f172a;
        }

        .cf-status-badge--info {
            background: #dbeafe;
            color: #1d4ed8;
            border-color: #bfdbfe;
        }

        .cf-tabs {
            display: flex;
            gap: .75rem;
            flex-wrap: wrap;
            padding: 0 1.25rem 1.25rem;
        }

        .cf-tab {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .75rem 1rem;
            border-radius: 14px;
            border: 1px solid #d9e4f3;
            background: #fff;
            color: #334155;
            text-decoration: none;
            font-weight: 800;
        }

        .cf-tab.active {
            background: #2563eb;
            border-color: #2563eb;
            color: #fff;
        }

        .cf-action-stack {
            display: flex;
            flex-wrap: wrap;
            gap: .65rem;
            justify-content: flex-end;
        }

        .cf-flow-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
        }

        .cf-flow-card {
            border: 1px solid #dbe6f2;
            border-radius: 18px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            padding: 1rem;
            min-height: 100%;
        }

        .cf-flow-card__icon {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #eff6ff;
            color: #2563eb;
            font-size: 1.1rem;
            margin-bottom: .8rem;
        }

        .cf-flow-card__title {
            font-weight: 900;
            color: #0f172a;
            margin-bottom: .35rem;
        }

        .cf-flow-card__text {
            color: #64748b;
            font-size: .9rem;
            margin: 0;
        }

        .cf-empty-state {
            padding: 3rem 1.5rem;
            text-align: center;
            color: #64748b;
        }

        .cf-empty-state__icon {
            width: 4rem;
            height: 4rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #eff6ff;
            color: #2563eb;
            font-size: 1.55rem;
            margin-bottom: 1rem;
        }

        .cf-pagination {
            padding: 1rem 1.5rem 1.4rem;
            border-top: 1px solid #e8eef5;
        }

        .cf-pagination-summary {
            color: #64748b;
            font-size: .86rem;
            line-height: 1.45;
        }

        .cf-modal-card {
            border: 1px solid #d9e4f3;
            border-radius: 18px;
            background: #f8fafc;
            padding: 1rem;
        }

        @media (max-width: 991.98px) {
            .cf-summary-strip,
            .cf-flow-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .cf-page-header__top {
                flex-direction: column;
            }

            .cf-summary-strip,
            .cf-flow-grid {
                grid-template-columns: 1fr;
                padding: 1rem 1rem 1.25rem;
            }

            .cf-panel__header,
            .cf-filter-body,
            .cf-pagination {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .cf-table-wrap {
                padding: 0 1rem 1rem;
            }

            .cf-action-stack,
            .cf-filter-actions {
                justify-content: flex-start;
            }
        }
    </style>
@endpush

@section('content')
    <div class="gestion-finiquitos-index">
        @php
            $rutFmt = function ($rut) {
                $raw = strtoupper(preg_replace('/[^0-9K]/', '', (string) $rut));
                if ($raw === '' || strlen($raw) < 2) return $rut ?: '—';
                $cuerpo = substr($raw, 0, -1);
                $dv = substr($raw, -1);
                return preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $cuerpo) . '-' . $dv;
            };
            $nombreUser = function ($user) use ($rutFmt) {
                if (!$user) return '—';
                $nombre = trim(($user->apellido_paterno ?? '') . ' ' . ($user->apellido_materno ?? '') . ' ' . ($user->nombres ?? ''));
                return trim($nombre . (!empty($user->rut) ? ' (' . $rutFmt($user->rut) . ')' : '')) ?: '—';
            };
            $nombreReemplazante = function ($s) use ($nombreUser) {
                $user = $s->contratoPostulante?->user ?: $s->postulante?->user;
                return $user ? $nombreUser($user) : ($s->rut_reemplazo_normalizado ?: 'Sin reemplazante registrado');
            };
            $rutReemplazante = function ($s) use ($rutFmt) {
                $user = $s->contratoPostulante?->user ?: $s->postulante?->user;
                return $rutFmt($user?->rut ?: ($s->rut_reemplazo_normalizado ?: ''));
            };
            $categoriaLabel = fn ($s) => match ($s->categoria_finiquito ?? 'docentes') {
                'junji' => 'JUNJI / Sala Cuna',
                'asistentes' => 'Asistente Educación',
                default => 'Docente / Matriz C',
            };
            $aplicaFiniquito = fn ($s) => in_array(($s->categoria_finiquito ?? 'docentes'), ['junji', 'asistentes'], true);
            $estadoFiniquitoLabel = fn ($s) => match ((string) ($s->finiquito_estado ?? 'pendiente')) {
                'generado' => 'Generado',
                'completado' => 'Completado',
                default => 'Pendiente',
            };
            $estadoLabel = fn ($e) => match ($e) {
                'aceptada' => 'Aceptada',
                'cerrado', 'cerrada' => 'Cerrada',
                default => $e,
            };
            $fmtFecha = fn ($fecha) => $fecha ? \Illuminate\Support\Carbon::parse($fecha)->format('d-m-Y') : '—';
            $resumenFiniquitos = $resumenFiniquitos ?? ['total' => $finiquitos->total(), 'pendientes' => 0, 'generados' => 0, 'completados' => 0];
            $queryActual = request()->query();
            $returnToFiniquitos = route('gestion.solicitudes-reemplazo.finiquitos.index', $queryActual);
        @endphp

        <div class="cf-page-header mb-4">
            <div class="cf-page-header__top">
                <div class="d-flex gap-3 align-items-start">
                    <span class="cf-page-header__eyebrow-icon"><i class="bi bi-receipt-cutoff"></i></span>
                    <div>
                        <div class="cf-page-header__eyebrow">Gestión de reemplazos · Finiquitos</div>
                        <h1 class="cf-page-header__title">Finiquitos de reemplazos terminados</h1>
                        <p class="cf-page-header__subtitle">
                            Bandeja para funcionario SLEP que lista reemplazos con período laboral completo, continuidad revisada por RUT del reemplazante y término superior a 6 días.
                        </p>
                    </div>
                </div>
                <a href="{{ route('gestion.solicitudes-reemplazo.index') }}" class="cf-role-pill">
                    <i class="bi bi-arrow-left"></i> Volver a gestión
                </a>
            </div>

            <div class="cf-summary-strip">
                <div class="cf-summary-card">
                    <div class="cf-summary-card__label"><i class="bi bi-hourglass-split"></i> Pendientes</div>
                    <div class="cf-summary-card__value">{{ number_format($resumenFiniquitos['pendientes'] ?? 0, 0, ',', '.') }}</div>
                    <p class="cf-summary-card__help">Reemplazos elegibles sin documento firmado cargado.</p>
                </div>
                <div class="cf-summary-card">
                    <div class="cf-summary-card__label"><i class="bi bi-check2-circle"></i> Completados</div>
                    <div class="cf-summary-card__value">{{ number_format($resumenFiniquitos['completados'] ?? 0, 0, ',', '.') }}</div>
                    <p class="cf-summary-card__help">Finiquitos firmados cargados.</p>
                </div>
                <div class="cf-summary-card">
                    <div class="cf-summary-card__label"><i class="bi bi-file-earmark-pdf"></i> Generados</div>
                    <div class="cf-summary-card__value">{{ number_format($resumenFiniquitos['generados'] ?? 0, 0, ',', '.') }}</div>
                    <p class="cf-summary-card__help">PDF generado y pendiente de firma/carga.</p>
                </div>
                <div class="cf-summary-card">
                    <div class="cf-summary-card__label"><i class="bi bi-calendar2-check"></i> Fecha de corte</div>
                    <div class="cf-summary-card__value" style="font-size:1.25rem;">{{ $cutoff->format('d-m-Y') }}</div>
                    <p class="cf-summary-card__help">Más de 6 días desde fecha_termino.</p>
                </div>
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-success shadow-sm border-0">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger shadow-sm border-0">
                <div class="fw-semibold mb-1">Revisa lo siguiente:</div>
                <ul class="mb-0">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="cf-panel mb-4">
            <div class="cf-panel__header">
                <div class="cf-panel__eyebrow">Regla operativa</div>
                <div class="cf-panel__title"><i class="bi bi-diagram-3 me-1"></i> Control de continuidad documental</div>
                <p class="cf-panel__subtitle">El listado reconstruye continuidades por solicitud_anterior_id y, si no existe, por postulante, RUT del titular y fechas consecutivas.</p>
            </div>
            <div class="cf-filter-body">
                <div class="cf-flow-grid">
                    <div class="cf-flow-card">
                        <div class="cf-flow-card__icon"><i class="bi bi-calendar-range"></i></div>
                        <div class="cf-flow-card__title">1. Período laboral completo</div>
                        <p class="cf-flow-card__text">Se exige <code>fecha_inicio_trabajo</code> y <code>fecha_termino</code> informadas.</p>
                    </div>
                    <div class="cf-flow-card">
                        <div class="cf-flow-card__icon"><i class="bi bi-person-vcard"></i></div>
                        <div class="cf-flow-card__title">2. Continuidad por RUT</div>
                        <p class="cf-flow-card__text">Se agrupan los períodos por RUT del reemplazante, aunque cambie el ID interno.</p>
                    </div>
                    <div class="cf-flow-card">
                        <div class="cf-flow-card__icon"><i class="bi bi-cash-coin"></i></div>
                        <div class="cf-flow-card__title">3. Finiquito completado</div>
                        <p class="cf-flow-card__text">El PDF firmado se carga con usuario, fecha y observación de trazabilidad.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="cf-panel mb-4">
            <div class="cf-panel__header">
                <div class="cf-panel__eyebrow">Filtros</div>
                <div class="cf-panel__title"><i class="bi bi-funnel me-1"></i> Búsqueda de reemplazos</div>
                <p class="cf-panel__subtitle">Filtra por solicitud, comuna, establecimiento, titular, reemplazante, período laboral y estado de finiquito.</p>
            </div>
            <div class="cf-filter-body">
                <form method="GET" action="{{ route('gestion.solicitudes-reemplazo.finiquitos.index') }}" class="row g-3 align-items-end">
                    <input type="hidden" name="categoria" value="{{ $categoriaGestion ?? 'asistentes' }}">
                    <div class="col-xl-2 col-lg-3 col-md-6">
                        <label class="cf-form-label" for="numero">N° solicitud</label>
                        <input id="numero" type="text" name="numero" value="{{ request('numero') }}" class="form-control" placeholder="00001-2026">
                    </div>
                    <div class="col-xl-4 col-lg-6 col-md-12">
                        <label class="cf-form-label" for="establecimiento_id">Establecimiento</label>
                        <select id="establecimiento_id" name="establecimiento_id" class="form-select">
                            <option value="">Todos</option>
                            @foreach ($establecimientos as $ee)
                                <option value="{{ $ee->id }}" @selected((string) request('establecimiento_id') === (string) $ee->id)>
                                    {{ $ee->rbd }} - {{ $ee->nombre_establecimiento }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-3 col-md-6">
                        <label class="cf-form-label" for="comuna">Comuna</label>
                        <select id="comuna" name="comuna" class="form-select">
                            <option value="">Todas</option>
                            @foreach (($comunas ?? collect()) as $comuna)
                                <option value="{{ $comuna }}" @selected((string) request('comuna') === (string) $comuna)>{{ $comuna }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-3 col-md-6">
                        <label class="cf-form-label" for="estado_finiquito">Finiquito</label>
                        <select id="estado_finiquito" name="estado_finiquito" class="form-select">
                            <option value="pendientes" @selected($estadoFiniquito === 'pendientes')>Pendientes</option>
                            <option value="generados" @selected($estadoFiniquito === 'generados')>Generados</option>
                            <option value="completados" @selected($estadoFiniquito === 'completados')>Completados</option>
                            <option value="todos" @selected($estadoFiniquito === 'todos')>Todos</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-3 col-md-6 cf-filter-actions cf-filter-actions--inline">
                        <button class="cf-btn-outline" type="submit"><i class="bi bi-search"></i> Filtrar</button>
                        <a href="{{ route('gestion.solicitudes-reemplazo.finiquitos.index', ['categoria' => $categoriaGestion ?? 'asistentes']) }}" class="cf-btn-danger"><i class="bi bi-x-circle"></i> Limpiar</a>
                    </div>
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <label class="cf-form-label" for="titular">Titular</label>
                        <input id="titular" type="text" name="titular" value="{{ request('titular') }}" class="form-control" placeholder="RUT o nombre">
                    </div>
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <label class="cf-form-label" for="reemplazante">Reemplazante</label>
                        <input id="reemplazante" type="text" name="reemplazante" value="{{ request('reemplazante') }}" class="form-control" placeholder="RUT o nombre">
                    </div>
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <label class="cf-form-label" for="desde">Desde</label>
                        <input id="desde" type="date" name="desde" value="{{ request('desde') }}" class="form-control">
                    </div>
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <label class="cf-form-label" for="hasta">Hasta</label>
                        <input id="hasta" type="date" name="hasta" value="{{ request('hasta') }}" class="form-control">
                    </div>
                </form>
            </div>
        </div>

        <div class="cf-panel mb-4">
            <div class="cf-panel__header">
                <div class="cf-panel__eyebrow">Pestañas de gestión</div>
                <div class="cf-panel__title"><i class="bi bi-layout-three-columns me-1"></i> Clasificación del término</div>
                <p class="cf-panel__subtitle">JUNJI se identifica por <code>establecimiento.sala_cuna = true</code>. En Asistentes y JUNJI aplica finiquito; docentes no JUNJI se controlan para Matriz C.</p>
            </div>
            <div class="cf-tabs">
                <a class="cf-tab {{ ($categoriaGestion ?? 'asistentes') === 'asistentes' ? 'active' : '' }}" href="{{ route('gestion.solicitudes-reemplazo.finiquitos.index', array_merge(request()->except('page', 'categoria'), ['categoria' => 'asistentes'])) }}"><i class="bi bi-person-badge"></i> Asistentes</a>
                <a class="cf-tab {{ ($categoriaGestion ?? '') === 'junji' ? 'active' : '' }}" href="{{ route('gestion.solicitudes-reemplazo.finiquitos.index', array_merge(request()->except('page', 'categoria'), ['categoria' => 'junji'])) }}"><i class="bi bi-house-heart"></i> JUNJI / Sala Cuna</a>
                <a class="cf-tab {{ ($categoriaGestion ?? '') === 'docentes' ? 'active' : '' }}" href="{{ route('gestion.solicitudes-reemplazo.finiquitos.index', array_merge(request()->except('page', 'categoria'), ['categoria' => 'docentes'])) }}"><i class="bi bi-mortarboard"></i> Docentes Matriz C</a>
                <a class="cf-tab {{ ($categoriaGestion ?? '') === 'todos' ? 'active' : '' }}" href="{{ route('gestion.solicitudes-reemplazo.finiquitos.index', array_merge(request()->except('page', 'categoria'), ['categoria' => 'todos'])) }}"><i class="bi bi-collection"></i> Todos</a>
            </div>
        </div>

        <div class="cf-panel">
            <div class="cf-panel__header d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                    <div class="cf-panel__eyebrow">Bandeja funcionario SLEP</div>
                    <div class="cf-panel__title mb-1">Reemplazos para gestión de finiquito</div>
                    <p class="cf-panel__subtitle">Período mostrado: cadena completa de continuidad detectada; la jornada y datos base se toman desde la última solicitud.</p>
                </div>
                <div class="d-flex gap-2 align-items-center flex-wrap justify-content-end">
                    <a href="{{ route('gestion.solicitudes-reemplazo.finiquitos.exportar-excel', request()->except('page')) }}" class="cf-btn-success">
                        <i class="bi bi-file-earmark-excel"></i> Exportar Excel
                    </a>
                    <span class="cf-status-badge">{{ number_format($finiquitos->total(), 0, ',', '.') }} registro(s)</span>
                </div>
            </div>

            <div class="cf-table-wrap">
                <div class="table-responsive">
                    <table class="table cf-table align-middle">
                        <thead>
                            <tr>
                                <th>Solicitud</th>
                                <th>Reemplazante</th>
                                <th>Titular reemplazado</th>
                                <th>Tipo gestión</th>
                                <th>Establecimiento</th>
                                <th>Período laboral</th>
                                <th>Estado solicitud</th>
                                <th>Finiquito</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($finiquitos as $s)
                                <tr>
                                    <td>
                                        <div class="cf-name">{{ $s->numero_solicitud }}</div>
                                        <div class="cf-meta">ID {{ $s->id }}</div>
                                    </td>
                                    <td>
                                        <div class="cf-name">{{ $nombreReemplazante($s) }}</div>
                                        <div class="cf-meta"><i class="bi bi-person-vcard me-1"></i>RUT continuidad: {{ $rutReemplazante($s) }}</div>
                                    </td>
                                    <td>
                                        <div class="cf-name">{{ $s->funcionarioTitular?->nombre ?? '—' }}</div>
                                        <div class="cf-meta">{{ $rutFmt($s->funcionarioTitular?->rut ?? $s->rut_titular_normalizado ?? '') }}</div>
                                    </td>
                                    <td>
                                        <span class="cf-status-badge cf-status-badge--info">{{ $categoriaLabel($s) }}</span>
                                        <div class="cf-meta mt-2">{{ $aplicaFiniquito($s) ? 'Aplica finiquito' : 'Sólo cese Matriz C' }}</div>
                                    </td>
                                    <td>
                                        <div class="cf-name">{{ $s->establecimiento?->nombre_establecimiento ?? '—' }}</div>
                                        <div class="cf-meta">RBD {{ $s->establecimiento?->rbd ?? '—' }}</div>
                                    </td>
                                    <td>
                                        <div class="cf-name">{{ $fmtFecha($s->finiquito_periodo_inicio ?? $s->fecha_inicio_trabajo) }} <span class="text-muted fw-normal">a</span> {{ $fmtFecha($s->finiquito_periodo_termino ?? $s->fecha_termino) }}</div>
                                        <div class="cf-meta">Período continuo completo</div>
                                        @if (($s->finiquito_cadena_count ?? 1) > 1)
                                            <div class="cf-meta mt-1"><i class="bi bi-link-45deg me-1"></i>{{ $s->finiquito_cadena_count }} solicitudes: {{ implode(', ', array_slice($s->finiquito_cadena_numeros ?? [], 0, 4)) }}@if(count($s->finiquito_cadena_numeros ?? []) > 4) ... @endif</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="cf-status-badge cf-status-badge--dark">{{ $estadoLabel($s->estado) }}</span>
                                    </td>
                                    <td>
                                        @if (($s->finiquito_estado ?? null) === 'completado')
                                            <span class="cf-status-badge cf-status-badge--success"><i class="bi bi-check2-circle"></i> Completado</span>
                                            <div class="cf-meta mt-2">{{ cl_datetime($s->finiquito_firmado_cargado_at) }}</div>
                                            <div class="cf-meta">{{ $nombreUser($s->finiquitoFirmadoCargadoPor) }}</div>
                                        @elseif (($s->finiquito_estado ?? null) === 'generado')
                                            <span class="cf-status-badge cf-status-badge--info"><i class="bi bi-file-earmark-pdf"></i> Generado</span>
                                            <div class="cf-meta mt-2">{{ cl_datetime($s->finiquito_generado_at) }}</div>
                                            <div class="cf-meta">Monto: ${{ number_format((int) ($s->finiquito_monto ?? 0), 0, ',', '.') }}</div>
                                        @else
                                            <span class="cf-status-badge cf-status-badge--warning"><i class="bi bi-hourglass-split"></i> Pendiente</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="cf-action-stack">
                                            <a href="{{ route('gestion.solicitudes-reemplazo.show', ['solicitud' => $s, 'return_to' => $returnToFiniquitos]) }}" class="cf-btn-outline"><i class="bi bi-eye"></i> Ver</a>
                                            @if ($s->finiquito_pdf_path)
                                                <a href="{{ route('gestion.solicitudes-reemplazo.finiquitos.descargar-pdf', array_merge(['solicitud' => $s], $queryActual)) }}" class="cf-btn-outline"><i class="bi bi-file-earmark-pdf"></i> PDF generado</a>
                                            @endif
                                            @if ($s->finiquito_firmado_pdf_path)
                                                <a href="{{ route('gestion.solicitudes-reemplazo.finiquitos.descargar-firmado', array_merge(['solicitud' => $s], $queryActual)) }}" class="cf-btn-outline"><i class="bi bi-file-earmark-check"></i> PDF firmado</a>
                                            @endif
                                            @if (($s->finiquito_estado ?? null) === 'completado')
                                                <button type="button" class="cf-btn-danger" data-bs-toggle="modal" data-bs-target="#modalEliminarFiniquitoFirmado{{ $s->id }}">
                                                    <i class="bi bi-trash"></i> Eliminar firmado
                                                </button>
                                            @endif
                                            @if ($aplicaFiniquito($s))
                                                <button type="button" class="cf-btn-primary" data-bs-toggle="modal" data-bs-target="#modalGenerarFiniquito{{ $s->id }}">
                                                    <i class="bi bi-file-earmark-plus"></i> Generar PDF
                                                </button>
                                                <button type="button" class="cf-btn-success" data-bs-toggle="modal" data-bs-target="#modalCargarFiniquito{{ $s->id }}">
                                                    <i class="bi bi-upload"></i> Cargar finiquito
                                                </button>
                                            @endif
                                        </div>

                                        @if ($aplicaFiniquito($s))
                                            <div class="modal fade text-start" id="modalGenerarFiniquito{{ $s->id }}" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                                    <form class="modal-content border-0 shadow" method="POST" action="{{ route('gestion.solicitudes-reemplazo.finiquitos.generar-pdf', array_merge(['solicitud' => $s], $queryActual)) }}">
                                                        @csrf
                                                        <div class="modal-header border-0 pb-0">
                                                            <div>
                                                                <div class="cf-panel__eyebrow">Generación documental</div>
                                                                <h5 class="modal-title fw-bold">Generar finiquito PDF</h5>
                                                            </div>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="cf-modal-card mb-3">
                                                                <div class="row g-3">
                                                                    <div class="col-md-6"><div class="cf-meta">Reemplazante</div><div class="fw-bold">{{ $nombreReemplazante($s) }}</div></div>
                                                                    <div class="col-md-6"><div class="cf-meta">Período laboral continuo</div><div class="fw-bold">{{ $fmtFecha($s->finiquito_periodo_inicio ?? $s->fecha_inicio_trabajo) }} a {{ $fmtFecha($s->finiquito_periodo_termino ?? $s->fecha_termino) }}</div></div>
                                                                    <div class="col-12"><div class="cf-meta">Jornada del documento</div><div class="fw-bold">Se calculará desde la última solicitud de la cadena: {{ $s->numero_solicitud ?: ('ID ' . $s->id) }}</div></div>
                                                                </div>
                                                            </div>
                                                            <div class="row g-3">
                                                                <div class="col-md-4">
                                                                    <label class="cf-form-label">Fecha emisión</label>
                                                                    <input type="date" name="finiquito_fecha_emision" class="form-control" value="{{ now()->format('Y-m-d') }}" required>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label class="cf-form-label">Monto a indemnizar/pagar</label>
                                                                    <input type="number" name="finiquito_monto" class="form-control" min="0" step="1" value="{{ (int) ($s->finiquito_monto ?? 0) }}">
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label class="cf-form-label">Firmante</label>
                                                                    <select name="firmante_key" class="form-select" required>
                                                                        @foreach (($firmantesFiniquito ?? []) as $firmante)
                                                                            <option value="{{ $firmante['key'] }}">{{ $firmante['label'] }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>
                                                                <div class="col-12">
                                                                    <label class="cf-form-label">Observación interna opcional</label>
                                                                    <textarea name="finiquito_observacion" class="form-control" rows="2" maxlength="2000">{{ $s->finiquito_observacion }}</textarea>
                                                                </div>
                                                            </div>
                                                            <div class="alert alert-info border-0 small mt-3 mb-0">
                                                                Al generar el documento se creará un PDF y el estado del finiquito quedará como <strong>Generado</strong>. Si el firmante seleccionado es subrogante, el documento indicará <strong>Director Ejecutivo (S)</strong>.
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer border-0 pt-0">
                                                            <button type="button" class="cf-btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-circle"></i> Cancelar</button>
                                                            <button type="submit" class="cf-btn-primary"><i class="bi bi-file-earmark-pdf"></i> Generar PDF</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        @endif

                                        @if (($s->finiquito_estado ?? null) === 'completado')
                                            <div class="modal fade text-start" id="modalEliminarFiniquitoFirmado{{ $s->id }}" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <form class="modal-content border-0 shadow" method="POST" action="{{ route('gestion.solicitudes-reemplazo.finiquitos.eliminar-firmado', array_merge(['solicitud' => $s], $queryActual)) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <div class="modal-header border-0 pb-0">
                                                            <div>
                                                                <div class="cf-panel__eyebrow text-danger">Eliminación documental</div>
                                                                <h5 class="modal-title fw-bold">Eliminar finiquito firmado</h5>
                                                            </div>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="cf-modal-card mb-3">
                                                                <div class="cf-meta">Solicitud</div>
                                                                <div class="fw-bold">{{ $s->numero_solicitud ?: ('ID ' . $s->id) }}</div>
                                                                <div class="cf-meta mt-2">Reemplazante</div>
                                                                <div class="fw-bold">{{ $nombreReemplazante($s) }}</div>
                                                            </div>
                                                            <div class="alert alert-warning border-0 small mb-0">
                                                                Esta acción eliminará el PDF firmado cargado y el finiquito dejará de estar disponible para el postulante en <strong>Mis Finiquitos</strong>.
                                                                El estado volverá a <strong>Generado</strong> si existe PDF base generado, o a <strong>Pendiente</strong> si no existe.
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer border-0 pt-0">
                                                            <button type="button" class="cf-btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-circle"></i> Cancelar</button>
                                                            <button type="submit" class="cf-btn-danger"><i class="bi bi-trash"></i> Eliminar firmado</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        @endif

                                        @if ($aplicaFiniquito($s))
                                            <div class="modal fade text-start" id="modalCargarFiniquito{{ $s->id }}" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                                    <form class="modal-content border-0 shadow" method="POST" enctype="multipart/form-data" action="{{ route('gestion.solicitudes-reemplazo.finiquitos.cargar-firmado', array_merge(['solicitud' => $s], $queryActual)) }}">
                                                        @csrf
                                                        <div class="modal-header border-0 pb-0">
                                                            <div>
                                                                <div class="cf-panel__eyebrow">Carga documental</div>
                                                                <h5 class="modal-title fw-bold">Cargar finiquito firmado</h5>
                                                            </div>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="cf-modal-card mb-3">
                                                                <div class="row g-3">
                                                                    <div class="col-md-6">
                                                                        <div class="cf-meta">Solicitud final</div>
                                                                        <div class="fw-bold">{{ $s->numero_solicitud }}</div>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <div class="cf-meta">Reemplazante</div>
                                                                        <div class="fw-bold">{{ $nombreReemplazante($s) }}</div>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <div class="cf-meta">Estado actual</div>
                                                                        <div class="fw-bold">{{ $estadoFiniquitoLabel($s) }}</div>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <div class="cf-meta">Período laboral</div>
                                                                        <div class="fw-bold">{{ $fmtFecha($s->finiquito_periodo_inicio ?? $s->fecha_inicio_trabajo) }} a {{ $fmtFecha($s->finiquito_periodo_termino ?? $s->fecha_termino) }}</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="row g-3">
                                                                <div class="col-12">
                                                                    <label class="cf-form-label" for="finiquito_firmado_pdf_{{ $s->id }}">Finiquito firmado en PDF <span class="text-danger">*</span></label>
                                                                    <input id="finiquito_firmado_pdf_{{ $s->id }}" type="file" name="finiquito_firmado_pdf" class="form-control" accept="application/pdf,.pdf" required>
                                                                    <div class="form-text">Sólo PDF. Tamaño máximo: 20 MB.</div>
                                                                </div>
                                                                <div class="col-12">
                                                                    <label class="cf-form-label" for="finiquito_firmado_observacion_{{ $s->id }}">Observación opcional</label>
                                                                    <textarea id="finiquito_firmado_observacion_{{ $s->id }}" name="finiquito_firmado_observacion" class="form-control" rows="3" maxlength="2000" placeholder="Ej.: documento firmado por trabajador, carga de respaldo escaneado, observación administrativa.">{{ $s->finiquito_firmado_observacion }}</textarea>
                                                                </div>
                                                            </div>
                                                            <div class="alert alert-info border-0 small mt-3 mb-0">
                                                                Al cargar el PDF firmado, el estado del finiquito cambiará a <strong>Completado</strong> y quedará disponible para el postulante en <strong>Mis Finiquitos</strong>.
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer border-0 pt-0">
                                                            <button type="button" class="cf-btn-secondary" data-bs-dismiss="modal"><i class="bi bi-x-circle"></i> Cancelar</button>
                                                            <button type="submit" class="cf-btn-success"><i class="bi bi-upload"></i> Cargar finiquito</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9">
                                        <div class="cf-empty-state">
                                            <div class="cf-empty-state__icon"><i class="bi bi-inbox"></i></div>
                                            <div class="fw-bold text-dark mb-1">No hay reemplazos para finiquito</div>
                                            <div>No existen registros que cumplan la regla de período laboral completo, continuidad por RUT y más de 6 días desde el término.</div>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($finiquitos->hasPages())
                <div class="cf-pagination">
                    <div class="cf-pagination-summary mb-2">
                        Mostrando <strong>{{ $finiquitos->firstItem() }}</strong> a <strong>{{ $finiquitos->lastItem() }}</strong> de <strong>{{ $finiquitos->total() }}</strong> registros.
                    </div>
                    {{ $finiquitos->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
