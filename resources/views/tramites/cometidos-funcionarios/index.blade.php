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

        .cf-form-label {
            font-weight: 700;
            color: #0f172a;
            margin-bottom: .55rem;
        }

        .cf-filter-actions {
            display: flex;
            gap: .75rem;
            flex-wrap: wrap;
        }

        .cf-btn-primary,
        .cf-btn-secondary,
        .cf-btn-danger,
        .cf-btn-outline {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .55rem;
            min-height: 48px;
            border-radius: 14px;
            font-weight: 800;
            padding: .8rem 1.15rem;
            text-decoration: none;
            transition: .2s ease;
        }

        .cf-btn-primary {
            border: 1px solid #2563eb;
            background: #2563eb;
            color: #fff;
            box-shadow: 0 12px 24px rgba(37, 99, 235, .22);
        }

        .cf-btn-primary:hover { color: #fff; background: #1d4ed8; border-color: #1d4ed8; }

        .cf-btn-secondary {
            border: 1px solid #d9e4f3;
            background: #fff;
            color: #0f172a;
        }

        .cf-btn-secondary:hover { color: #0f172a; background: #f8fafc; }

        .cf-btn-danger {
            border: 1px solid #fca5a5;
            background: #fff5f5;
            color: #dc2626;
        }

        .cf-btn-danger:hover { color: #b91c1c; background: #fee2e2; }

        .cf-btn-outline {
            border: 1px solid #cfe0ff;
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
            font-size: 1.05rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: .25rem;
        }

        .cf-meta {
            color: #64748b;
            font-size: .92rem;
            line-height: 1.45;
        }

        .cf-badge,
        .cf-status-badge,
        .cf-gasto-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .35rem;
            border-radius: 999px;
            padding: .38rem .72rem;
            font-size: .82rem;
            font-weight: 800;
            line-height: 1;
        }

        .cf-status-badge {
            background: #f8fafc;
            color: #334155;
            border: 1px solid #d9e4f3;
        }

        .cf-gasto-badge--viatico {
            background: #fef3c7;
            color: #92400e;
        }

        .cf-gasto-badge--reembolso {
            background: #cffafe;
            color: #0f766e;
        }

        .cf-gasto-badge--sin-gasto {
            background: #e2e8f0;
            color: #475569;
        }

        .cf-action-stack {
            display: flex;
            flex-wrap: wrap;
            gap: .65rem;
            justify-content: flex-end;
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

        .cf-pagination-footer {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .cf-pagination-summary {
            color: #64748b;
            font-size: .86rem;
            line-height: 1.45;
        }

        .cf-pagination-summary strong {
            color: #0f172a;
        }

        .cf-pagination-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-end;
            gap: .5rem;
        }

        .cf-pagination-pages {
            width: 100%;
        }

        .cf-page-link,
        .cf-page-link-disabled {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            min-height: 2.2rem;
            padding: .5rem .85rem;
            border-radius: .85rem;
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            color: #1d4ed8;
            text-decoration: none;
            font-weight: 700;
            font-size: .84rem;
        }

        .cf-page-link:hover {
            background: #dbeafe;
            color: #1e40af;
        }

        .cf-page-link-disabled {
            border-color: #e2e8f0;
            background: #f8fafc;
            color: #94a3b8;
            cursor: not-allowed;
        }



        .cf-tabs-panel {
            border: 1px solid #d9e4f3;
            border-radius: 22px;
            background: #ffffff;
            box-shadow: 0 14px 34px rgba(15, 23, 42, .06);
            padding: .85rem;
        }

        .cf-tabs-nav {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .75rem;
        }

        .cf-tab-link {
            display: flex;
            align-items: center;
            gap: .8rem;
            min-height: 72px;
            padding: .85rem 1rem;
            border-radius: 16px;
            border: 1px solid #d9e4f3;
            background: #f8fafc;
            color: #334155;
            text-decoration: none;
            transition: .2s ease;
        }

        .cf-tab-link:hover {
            background: #eff6ff;
            color: #1d4ed8;
            border-color: #bfdbfe;
        }

        .cf-tab-link.is-active {
            background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%);
            border-color: #60a5fa;
            color: #1d4ed8;
            box-shadow: 0 12px 24px rgba(37, 99, 235, .12);
        }

        .cf-tab-icon {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #e2e8f0;
            color: #334155;
            flex: 0 0 auto;
            font-size: 1.1rem;
        }

        .cf-tab-link.is-active .cf-tab-icon {
            background: #2563eb;
            color: #ffffff;
        }

        .cf-tab-title {
            display: block;
            font-weight: 900;
            line-height: 1.15;
        }

        .cf-tab-count {
            display: block;
            font-size: .82rem;
            color: #64748b;
            margin-top: .25rem;
        }

        .cf-tab-link.is-active .cf-tab-count {
            color: #1d4ed8;
        }

        @media (max-width: 991.98px) {
            .cf-summary-strip {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .cf-tabs-nav {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .cf-page-header__top {
                flex-direction: column;
            }

            .cf-summary-strip {
                grid-template-columns: 1fr;
                padding: 1rem 1rem 1.25rem;
            }

            .cf-panel__header,
            .cf-filter-body {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .cf-table-wrap {
                padding: 0 1rem 1rem;
            }

            .cf-action-stack {
                justify-content: flex-start;
            }

            .cf-tabs-nav {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $estadoSeleccionado = request('estado');
        $nombreSeleccionado = request('nombre');
        $rutSeleccionado = request('rut');
        $establecimientoSeleccionado = request('establecimiento_id');
        $seguimientoEstadoSeleccionado = request('seguimiento_estado');
        $seguimientoEstablecimientoSeleccionado = request('seguimiento_establecimiento_id');
        $seguimientoMesSeleccionado = request('seguimiento_mes');
        $seguimientoQueryParams = collect([
            'seguimiento_estado' => $seguimientoEstadoSeleccionado,
            'seguimiento_establecimiento_id' => $seguimientoEstablecimientoSeleccionado,
            'seguimiento_mes' => $seguimientoMesSeleccionado,
        ])->filter(fn ($value) => filled($value))->all();
        $estadoLabel = $estadoSeleccionado && isset($estados[$estadoSeleccionado]) ? $estados[$estadoSeleccionado] : 'Todos los estados';
        $roleLabels = [
            'funcionario_estab' => 'Vista establecimiento',
            'coordinador_uatp' => 'Vista UATP',
            'director_ejecutivo' => 'Vista Director Ejecutivo',
            'supervisor_plani' => 'Vista Planificación',
            'coordinador_plani' => 'Vista Planificación',
            'coordinador_gdp' => 'Vista GDP',
            'funcionario_slep' => 'Vista SLEP',
            'funcionario_daf' => 'Vista DAF',
            'funcionario_daf_compra' => 'Vista DAF Compra',
            'funcionario_ac' => 'Vista Administración Central',
            'funcionario_juridica' => 'Vista Jurídica',
            'admin' => 'Vista administración',
        ];
        $roleLabel = $roleLabels[$activeRole] ?? 'Vista del módulo';

        if ($usaBandejasInternas) {
            $bandejasCometidos = [
                [
                    'key' => 'ac_por_autorizar',
                    'grupo' => 'Administración Central',
                    'eyebrow' => 'Administración Central · Bandeja de gestión',
                    'title' => 'AC · Solicitudes por autorizar o gestionar',
                    'subtitle' => 'Cometidos emitidos por funcionarios de Administración Central que requieren acción del rol activo.',
                    'items' => $cometidosAcPorAutorizar,
                    'total' => $cuentaAcPorAutorizar,
                    'icon' => 'bi-building-gear',
                    'empty_title' => 'No hay solicitudes AC pendientes de gestión',
                    'empty_text' => 'No se encontraron cometidos de Administración Central que requieran autorización o gestión para este rol con los filtros actuales.',
                ],
                [
                    'key' => 'ac_autorizados',
                    'grupo' => 'Administración Central',
                    'eyebrow' => 'Administración Central · Seguimiento',
                    'title' => 'AC · Cometidos autorizados, en seguimiento o finalizados',
                    'subtitle' => 'Cometidos de Administración Central autorizados, en seguimiento o cerrados para consulta histórica del rol activo.',
                    'items' => $cometidosAcAutorizados,
                    'total' => $cuentaAcAutorizados,
                    'icon' => 'bi-check2-circle',
                    'empty_title' => 'No hay cometidos AC en seguimiento',
                    'empty_text' => 'No se encontraron cometidos de Administración Central autorizados, en seguimiento o cerrados con los filtros actuales.',
                ],
                [
                    'key' => 'estab_por_autorizar',
                    'grupo' => 'Establecimientos',
                    'eyebrow' => 'Establecimientos · Bandeja de gestión',
                    'title' => 'Establecimientos · Solicitudes por autorizar o gestionar',
                    'subtitle' => 'Cometidos emitidos desde establecimientos que requieren acción del rol activo.',
                    'items' => $cometidosEstabPorAutorizar,
                    'total' => $cuentaEstabPorAutorizar,
                    'icon' => 'bi-building',
                    'empty_title' => 'No hay solicitudes de establecimientos pendientes de gestión',
                    'empty_text' => 'No se encontraron cometidos de establecimientos que requieran autorización o gestión para este rol con los filtros actuales.',
                ],
                [
                    'key' => 'estab_autorizados',
                    'grupo' => 'Establecimientos',
                    'eyebrow' => 'Establecimientos · Seguimiento',
                    'title' => 'Establecimientos · Cometidos autorizados, en seguimiento o finalizados',
                    'subtitle' => 'Cometidos de establecimientos autorizados, en seguimiento o cerrados para consulta histórica del rol activo.',
                    'items' => $cometidosEstabAutorizados,
                    'total' => $cuentaEstabAutorizados,
                    'icon' => 'bi-check2-circle',
                    'empty_title' => 'No hay cometidos de establecimientos en seguimiento',
                    'empty_text' => 'No se encontraron cometidos de establecimientos autorizados, en seguimiento o cerrados con los filtros actuales.',
                ],
            ];
            $paginadoresInternos = collect([$cometidosAcPorAutorizar, $cometidosAcAutorizados, $cometidosEstabPorAutorizar, $cometidosEstabAutorizados])->filter();
            $cuentaPagina = $paginadoresInternos->sum(fn ($p) => $p->count());
            $cuentaTotal = $cuentaAcPorAutorizar + $cuentaAcAutorizados + $cuentaEstabPorAutorizar + $cuentaEstabAutorizados;
            $cuentaViatico = $paginadoresInternos->sum(fn ($p) => $p->getCollection()->where('solicita_viatico', true)->count());
            $cuentaReembolso = $paginadoresInternos->sum(fn ($p) => $p->getCollection()->where('solicita_reembolso', true)->count());
        } else {
            $bandejasCometidos = [
                [
                    'key' => 'establecimiento',
                    'eyebrow' => 'Listado',
                    'title' => 'Solicitudes registradas',
                    'subtitle' => 'Solicitudes de cometido registradas por el establecimiento.',
                    'items' => $cometidos,
                    'total' => $cometidos->total(),
                    'icon' => 'bi-collection',
                    'empty_title' => 'No hay solicitudes registradas',
                    'empty_text' => 'No se encontraron cometidos con los criterios actuales. Ajusta los filtros o limpia la búsqueda para volver a consultar.',
                ],
            ];
            $cuentaPagina = $cometidos->count();
            $cuentaTotal = $cometidos->total();
            $cuentaViatico = $cometidos->where('solicita_viatico', true)->count();
            $cuentaReembolso = $cometidos->where('solicita_reembolso', true)->count();
        }

        $bandejasCollection = collect($bandejasCometidos);
        $activeBandeja = $usaBandejasInternas
            ? ($bandejasCollection->firstWhere('key', $activeCometidosTab) ?: $bandejasCollection->first())
            : $bandejasCollection->first();
        $bandejasVisibles = $usaBandejasInternas
            ? $bandejasCollection->where('key', $activeBandeja['key'] ?? $activeCometidosTab)->values()->all()
            : $bandejasCometidos;
        $activeCometidosTab = $activeBandeja['key'] ?? $activeCometidosTab;
        $esTabSeguimiento = in_array($activeCometidosTab, ['ac_autorizados', 'estab_autorizados'], true);
        $tabQueryBase = request()->except(['tab', 'ac_por_autorizar_page', 'ac_autorizados_page', 'estab_por_autorizar_page', 'estab_autorizados_page', 'page']);
        $filterActionParams = ['tab' => $activeCometidosTab];
    @endphp

    <div class="container py-4">
        <div class="cf-page-header mb-4">
            <div class="cf-page-header__top">
                <div>
                    <div class="cf-page-header__eyebrow">
                        <span class="cf-page-header__eyebrow-icon"><i class="bi bi-briefcase"></i></span>
                        <span>Trámites · Cometidos funcionarios</span>
                    </div>
                    <h1 class="cf-page-header__title">Cometidos funcionarios</h1>
                    <p class="cf-page-header__subtitle">
                        Bandeja de solicitudes de cometido funcionario con visual renovada, filtros rápidos y acceso al detalle del trámite según el rol activo del usuario.
                        @if ($usaBandejasInternas)
                            Para roles internos se muestran bandejas separadas por origen: Administración Central y Establecimientos, además de gestión y seguimiento.
                        @endif
                    </p>
                </div>

                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="cf-role-pill">
                        <i class="bi bi-person-badge"></i>
                        {{ $roleLabel }}
                    </span>

                    @if (in_array($activeRole, ['funcionario_estab', 'funcionario_ac'], true) && Route::has('tramites.cometidos-funcionarios.create'))
                        <a href="{{ route('tramites.cometidos-funcionarios.create') }}" class="cf-btn-primary">
                            <i class="bi bi-plus-circle"></i>
                            Nueva solicitud
                        </a>
                    @endif
                </div>
            </div>

            <div class="cf-summary-strip">
                <div class="cf-summary-card">
                    <div class="cf-summary-card__label"><i class="bi bi-list-check"></i> Solicitudes visibles</div>
                    <div class="cf-summary-card__value">{{ number_format($cuentaTotal, 0, ',', '.') }}</div>
                    <p class="cf-summary-card__help">Total según el rol activo y filtros aplicables.</p>
                </div>
                <div class="cf-summary-card">
                    <div class="cf-summary-card__label"><i class="bi bi-funnel"></i> Estado actual del filtro</div>
                    <div class="cf-summary-card__value" style="font-size: 1.15rem; line-height: 1.25;">{{ $estadoLabel }}</div>
                    <p class="cf-summary-card__help">Refina la bandeja por estado, nombre, RUT y establecimiento.</p>
                </div>
                <div class="cf-summary-card">
                    <div class="cf-summary-card__label"><i class="bi bi-cash-coin"></i> Con viático</div>
                    <div class="cf-summary-card__value">{{ number_format($cuentaViatico, 0, ',', '.') }}</div>
                    <p class="cf-summary-card__help">Solicitudes visibles en esta página que incluyen viático.</p>
                </div>
                <div class="cf-summary-card">
                    <div class="cf-summary-card__label"><i class="bi bi-receipt-cutoff"></i> Con reembolso</div>
                    <div class="cf-summary-card__value">{{ number_format($cuentaReembolso, 0, ',', '.') }}</div>
                    <p class="cf-summary-card__help">Solicitudes visibles en esta página que incluyen reembolso.</p>
                </div>
            </div>
        </div>

        {{-- Aviso de fase de desarrollo retirado para funcionario_estab. --}}

        @if (session('success'))
            <div class="alert alert-success border-0 shadow-sm mb-4">
                <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            </div>
        @endif

        @if ($usaBandejasInternas)
            <div class="cf-tabs-panel mb-4">
                <div class="cf-tabs-nav" role="tablist" aria-label="Bandejas de cometidos funcionarios">
                    @foreach ($bandejasCometidos as $tabBandeja)
                        @php
                            $tabParams = array_merge($tabQueryBase, ['tab' => $tabBandeja['key']]);
                        @endphp
                        <a href="{{ route('tramites.cometidos-funcionarios.index', $tabParams) }}" class="cf-tab-link {{ $activeCometidosTab === $tabBandeja['key'] ? 'is-active' : '' }}">
                            <span class="cf-tab-icon"><i class="bi {{ $tabBandeja['icon'] }}"></i></span>
                            <span>
                                <span class="cf-tab-title">{{ $tabBandeja['title'] }}</span>
                                <span class="cf-tab-count">{{ number_format($tabBandeja['total'], 0, ',', '.') }} registro(s)</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <form method="GET" class="cf-panel mb-4">
            <input type="hidden" name="tab" value="{{ $activeCometidosTab }}">
            <div class="cf-panel__header">
                <div class="cf-panel__eyebrow">Búsqueda y filtrado</div>
                <div class="cf-panel__title">Filtrar {{ $activeBandeja['title'] ?? 'bandeja de cometidos' }}</div>
                <p class="cf-panel__subtitle">
                    @if ($usaBandejasInternas)
                        Los filtros se aplican sólo a la pestaña activa. Cambiar de pestaña mantiene la separación entre Administración Central y Establecimientos.
                    @else
                        Busca solicitudes por estado, nombre del funcionario, RUT y, cuando corresponda, por establecimiento.
                    @endif
                </p>
            </div>

            <div class="cf-filter-body">
                <div class="row g-3">
                    @if ($esTabSeguimiento)
                        <div class="col-lg-4 col-md-6">
                            <label for="seguimiento_estado" class="cf-form-label">Estado</label>
                            <select id="seguimiento_estado" name="seguimiento_estado" class="form-select">
                                <option value="">Todos</option>
                                @foreach ($estados as $key => $label)
                                    <option value="{{ $key }}" @selected($seguimientoEstadoSeleccionado === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <label for="seguimiento_establecimiento_id" class="cf-form-label">Establecimiento</label>
                            <select id="seguimiento_establecimiento_id" name="seguimiento_establecimiento_id" class="form-select" @disabled($activeCometidosTab === 'ac_autorizados')>
                                <option value="">{{ $activeCometidosTab === 'ac_autorizados' ? 'No aplica para Administración Central' : 'Todos' }}</option>
                                @if ($activeCometidosTab !== 'ac_autorizados')
                                    @foreach ($establecimientos as $establecimiento)
                                        <option value="{{ $establecimiento->id }}" @selected((string) $seguimientoEstablecimientoSeleccionado === (string) $establecimiento->id)>
                                            {{ $establecimiento->nombre_establecimiento }}
                                            @if (!empty($establecimiento->rbd))
                                                · RBD {{ $establecimiento->rbd }}
                                            @endif
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <label for="seguimiento_mes" class="cf-form-label">Mes</label>
                            <input id="seguimiento_mes" type="month" name="seguimiento_mes" value="{{ $seguimientoMesSeleccionado }}" class="form-control">
                        </div>
                    @else
                        <div class="col-lg-3 col-md-6">
                            <label for="estado" class="cf-form-label">Estado</label>
                            <select id="estado" name="estado" class="form-select">
                                <option value="">Todos</option>
                                @foreach ($estados as $key => $label)
                                    <option value="{{ $key }}" @selected($estadoSeleccionado === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <label for="nombre" class="cf-form-label">Nombre o apellido</label>
                            <input id="nombre" type="text" name="nombre" value="{{ $nombreSeleccionado }}" class="form-control" placeholder="Ej.: María José, Bustos, Astete...">
                        </div>

                        <div class="col-lg-3 col-md-6">
                            <label for="rut" class="cf-form-label">RUT</label>
                            <input id="rut" type="text" name="rut" value="{{ $rutSeleccionado }}" class="form-control" placeholder="Ej.: 15927919-7">
                        </div>

                        @if ($puedeFiltrarEstablecimiento)
                            <div class="col-lg-3 col-md-6">
                                <label for="establecimiento_id" class="cf-form-label">Establecimiento</label>
                                <select id="establecimiento_id" name="establecimiento_id" class="form-select" @disabled($activeCometidosTab === 'ac_por_autorizar')>
                                    <option value="">{{ $activeCometidosTab === 'ac_por_autorizar' ? 'No aplica para Administración Central' : 'Todos' }}</option>
                                    @if ($activeCometidosTab !== 'ac_por_autorizar')
                                        @foreach ($establecimientos as $establecimiento)
                                            <option value="{{ $establecimiento->id }}" @selected((string) $establecimientoSeleccionado === (string) $establecimiento->id)>
                                                {{ $establecimiento->nombre_establecimiento }}
                                                @if (!empty($establecimiento->rbd))
                                                    · RBD {{ $establecimiento->rbd }}
                                                @endif
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                        @endif
                    @endif

                    <div class="col-12 pt-2">
                        <div class="cf-filter-actions">
                            <button class="cf-btn-outline" type="submit">
                                <i class="bi bi-search"></i>
                                Filtrar pestaña
                            </button>
                            <a href="{{ route('tramites.cometidos-funcionarios.index', ['tab' => $activeCometidosTab]) }}" class="cf-btn-danger">
                                <i class="bi bi-x-circle"></i>
                                Limpiar pestaña
                            </a>
                            @if ($esTabSeguimiento && Route::has('tramites.cometidos-funcionarios.seguimiento.exportar-excel'))
                                <a href="{{ route('tramites.cometidos-funcionarios.seguimiento.exportar-excel', $seguimientoQueryParams) }}" class="cf-btn-secondary">
                                    <i class="bi bi-file-earmark-excel"></i>
                                    Descargar nómina Excel
                                </a>
                            @endif
                            @if ($esTabSeguimiento && Route::has('tramites.cometidos-funcionarios.seguimiento.documentos-zip'))
                                <a href="{{ route('tramites.cometidos-funcionarios.seguimiento.documentos-zip', $seguimientoQueryParams) }}" class="cf-btn-secondary">
                                    <i class="bi bi-file-earmark-zip"></i>
                                    Descargar documentos ZIP
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </form>

        @foreach ($bandejasVisibles as $bandeja)
            <div class="cf-panel mb-4">
                <div class="cf-panel__header d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <div class="cf-panel__eyebrow">{{ $bandeja['eyebrow'] }}</div>
                        <div class="cf-panel__title">{{ $bandeja['title'] }}</div>
                        <p class="cf-panel__subtitle mb-0">
                            {{ $bandeja['subtitle'] }} Se muestran {{ number_format($bandeja['items']->count(), 0, ',', '.') }} registros en esta página y {{ number_format($bandeja['total'], 0, ',', '.') }} en total.
                        </p>
                    </div>
                    <span class="cf-role-pill">
                        <i class="bi {{ $bandeja['icon'] }}"></i>
                        {{ number_format($bandeja['total'], 0, ',', '.') }} solicitud(es)
                    </span>
                </div>

                <div class="cf-table-wrap">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle cf-table">
                            <thead>
                                <tr>
                                    <th>N° cometido</th>
                                    <th>Fecha</th>
                                    <th>Funcionario</th>
                                    <th>Origen / dependencia</th>
                                    <th>Destino</th>
                                    <th>Gasto</th>
                                    <th>Estado</th>
                                    <th class="text-end">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($bandeja['items'] as $item)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold text-dark">{{ $item->numero_cometido_interno ?: ('#' . $item->id) }}</div>
                                            <div class="cf-meta">Identificador del trámite</div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark">{{ optional($item->fecha_solicitud)->format('d-m-Y') ?? '—' }}</div>
                                            <div class="cf-meta">Creación de solicitud</div>
                                        </td>
                                        <td>
                                            <div class="cf-name">{{ $item->funcionario_nombre ?: 'Sin nombre registrado' }}</div>
                                            <div class="cf-meta">{{ $item->funcionario_rut ?: 'Sin RUT' }} · {{ $item->estamento ?: 'Sin estamento' }}</div>
                                            @if ($item->cargo_funcion)
                                                <div class="cf-meta">{{ $item->cargo_funcion }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $esItemAc = method_exists($item, 'esAdministracionCentral') ? $item->esAdministracionCentral() : (($item->origen_cometido ?? null) === 'administracion_central');
                                                $dependenciaAc = $item->funcionarioAcAutorizado->subdireccion_dependencia
                                                    ?? $item->funcionarioAcAutorizado->unidad_departamento
                                                    ?? null;
                                            @endphp
                                            @if ($esItemAc)
                                                <div class="fw-semibold text-dark">Administración Central</div>
                                                <div class="cf-meta">{{ $dependenciaAc ?: 'Dependencia no registrada' }}</div>
                                            @else
                                                <div class="fw-semibold text-dark">{{ $item->establecimiento->nombre_establecimiento ?? '—' }}</div>
                                                <div class="cf-meta">RBD: {{ $item->rbd ?? '—' }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark">{{ $item->comuna_destino_nombre ?: '—' }}</div>
                                            <div class="cf-meta">{{ optional($item->fecha_desde)->format('d-m-Y') ?: '—' }} a {{ optional($item->fecha_hasta)->format('d-m-Y') ?: '—' }}</div>
                                            @if ($item->destino)
                                                <div class="cf-meta">{{ $item->destino }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column gap-2 align-items-start">
                                                @if ($item->solicita_viatico)
                                                    <span class="cf-gasto-badge cf-gasto-badge--viatico">Viático</span>
                                                @endif
                                                @if ($item->solicita_reembolso)
                                                    <span class="cf-gasto-badge cf-gasto-badge--reembolso">Reembolso</span>
                                                @endif
                                                @if (($item->estado ?? null) === 'pendiente_autorizacion_director_sin_disponibilidad')
                                                    <span class="cf-gasto-badge cf-gasto-badge--reembolso">Director Ejecutivo</span>
                                                @endif
                                                @if (in_array(($item->estado ?? null), ['informe_pendiente_funcionario', 'informe_pendiente_jefatura', 'pendiente_rendicion_informe', 'rendicion_enviada_pendiente_informe'], true))
                                                    <span class="cf-gasto-badge cf-gasto-badge--viatico">Informe pendiente</span>
                                                @endif
                                                @if (in_array(($item->estado ?? null), ['en_daf_contable_viatico', 'en_daf_contable_reembolso'], true))
                                                    <span class="cf-gasto-badge cf-gasto-badge--reembolso">DAF contable</span>
                                                @endif
                                                @if (!$item->solicita_viatico && !$item->solicita_reembolso)
                                                    <span class="cf-gasto-badge cf-gasto-badge--sin-gasto">Sin gasto</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <span class="cf-status-badge">{{ $item->etiquetaEstado() }}</span>
                                        </td>
                                        <td>
                                            <div class="cf-action-stack">
                                                <a href="{{ route('tramites.cometidos-funcionarios.show', $item) }}" class="cf-btn-outline">
                                                    <i class="bi bi-eye"></i>
                                                    Ver
                                                </a>
                                                @if (($activeRole === 'funcionario_estab' && $item->esEditablePorEstablecimiento()) || ($activeRole === 'funcionario_ac' && method_exists($item, 'esEditablePorFuncionarioAc') && $item->esEditablePorFuncionarioAc()))
                                                    <a href="{{ route('tramites.cometidos-funcionarios.edit', $item) }}" class="cf-btn-secondary">
                                                        <i class="bi bi-pencil"></i>
                                                        Editar
                                                    </a>
                                                @endif
                                                @if (($activeRole === 'funcionario_estab' && method_exists($item, 'esEliminablePorEstablecimiento') && $item->esEliminablePorEstablecimiento()) || ($activeRole === 'funcionario_ac' && method_exists($item, 'esEliminablePorFuncionarioAc') && $item->esEliminablePorFuncionarioAc()))
                                                    <form method="POST" action="{{ route('tramites.cometidos-funcionarios.destroy', $item) }}" class="m-0" onsubmit="return confirm('¿Confirma que desea eliminar este cometido funcionario? Esta acción no se puede deshacer.');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="cf-btn-danger">
                                                            <i class="bi bi-trash"></i>
                                                            Eliminar
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="p-0 border-0">
                                            <div class="cf-empty-state">
                                                <div class="cf-empty-state__icon"><i class="bi bi-inbox"></i></div>
                                                <h3 class="h5 fw-bold text-dark mb-2">{{ $bandeja['empty_title'] }}</h3>
                                                <p class="mb-0">{{ $bandeja['empty_text'] }}</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @php
                    $paginadorBandeja = $bandeja['items'];
                    $desdeBandeja = $paginadorBandeja->firstItem() ?? 0;
                    $hastaBandeja = $paginadorBandeja->lastItem() ?? 0;
                    $paginaActualBandeja = $paginadorBandeja->currentPage();
                    $ultimaPaginaBandeja = max(1, $paginadorBandeja->lastPage());
                @endphp

                <div class="cf-pagination">
                    <div class="cf-pagination-footer">
                        <div class="cf-pagination-summary">
                            <strong>{{ $bandeja['title'] }}</strong><br>
                            Mostrando {{ number_format($desdeBandeja, 0, ',', '.') }} a {{ number_format($hastaBandeja, 0, ',', '.') }} de {{ number_format($paginadorBandeja->total(), 0, ',', '.') }} registro(s).
                            Página {{ number_format($paginaActualBandeja, 0, ',', '.') }} de {{ number_format($ultimaPaginaBandeja, 0, ',', '.') }}.
                        </div>

                        <div class="cf-pagination-actions">
                            @if ($paginadorBandeja->onFirstPage())
                                <span class="cf-page-link-disabled"><i class="bi bi-chevron-left"></i> Anterior</span>
                            @else
                                <a class="cf-page-link" href="{{ $paginadorBandeja->previousPageUrl() }}"><i class="bi bi-chevron-left"></i> Anterior</a>
                            @endif

                            @if ($paginadorBandeja->hasMorePages())
                                <a class="cf-page-link" href="{{ $paginadorBandeja->nextPageUrl() }}">Siguiente <i class="bi bi-chevron-right"></i></a>
                            @else
                                <span class="cf-page-link-disabled">Siguiente <i class="bi bi-chevron-right"></i></span>
                            @endif
                        </div>

                        @if ($paginadorBandeja->hasPages())
                            <div class="cf-pagination-pages">
                                {{ $paginadorBandeja->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
