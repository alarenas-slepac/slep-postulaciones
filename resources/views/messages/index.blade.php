@extends('layouts.app')

@section('content')
@php
    $qActual = request('q');
    $subdireccionActual = request('subdireccion');
    $unidadActual = request('unidad');
    $recentOnlyActual = request()->boolean('recent_only');
    $establecimientoQActual = request('establecimiento_q');
    $establecimientoComunaActual = request('establecimiento_comuna');
    $establishmentDirectoryOpen = filled($establecimientoQActual) || filled($establecimientoComunaActual);
@endphp

<div class="container-fluid py-4 messages-institutional-view">
    <style>
        .messages-institutional-view .module-shell { border: 1px solid #d7dee8; border-radius: 1.15rem; background: #fff; box-shadow: 0 .55rem 1.6rem rgba(15,23,42,.06); overflow: hidden; }
        .messages-institutional-view .module-hero { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 1rem; padding: 1.35rem 1.45rem; background: linear-gradient(135deg, #f8fbff 0%, #ffffff 100%); border-bottom: 1px solid #e5edf6; }
        .messages-institutional-view .module-title-wrap { display: flex; align-items: flex-start; gap: .95rem; min-width: 0; }
        .messages-institutional-view .module-icon { width: 2.9rem; height: 2.9rem; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; color: #fff; background: linear-gradient(135deg, #0d6efd 0%, #1d4ed8 100%); box-shadow: 0 .4rem 1rem rgba(13,110,253,.25); flex: 0 0 auto; }
        .messages-institutional-view .module-kicker { color: #64748b; font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; margin-bottom: .12rem; }
        .messages-institutional-view .module-title { color: #0f172a; font-size: clamp(1.45rem, 2vw, 2rem); font-weight: 850; line-height: 1.1; margin-bottom: .35rem; }
        .messages-institutional-view .module-help { max-width: 850px; color: #475569; line-height: 1.45; margin-bottom: 0; }
        .messages-institutional-view .module-badge { display: inline-flex; align-items: center; gap: .4rem; padding: .48rem .75rem; border-radius: 999px; background: #eef6ff; border: 1px solid #b9d9ff; color: #0d47a1; font-size: .8rem; font-weight: 800; }

        .messages-institutional-view .metric-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .9rem; padding: 1rem 1.45rem 1.25rem; background: #fff; }
        .messages-institutional-view .metric-card { border: 1px solid #dbe4f0; border-radius: 1rem; background: #fff; padding: .95rem 1rem; min-height: 6.2rem; box-shadow: 0 .25rem .8rem rgba(15,23,42,.035); }
        .messages-institutional-view .metric-label { display: flex; align-items: center; gap: .45rem; color: #64748b; font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .025em; margin-bottom: .35rem; }
        .messages-institutional-view .metric-number { color: #0f172a; font-size: 1.55rem; line-height: 1; font-weight: 900; margin-bottom: .45rem; }
        .messages-institutional-view .metric-help { color: #64748b; font-size: .78rem; line-height: 1.35; margin-bottom: 0; }
        .messages-institutional-view .quick-nav { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .85rem; }
        .messages-institutional-view .quick-nav-card { display: flex; align-items: flex-start; gap: .75rem; padding: .95rem 1rem; border: 1px solid #dbe4f0; border-radius: 1rem; background: #fff; color: #0f172a; text-decoration: none; box-shadow: 0 .25rem .8rem rgba(15,23,42,.035); transition: all .18s ease; }
        .messages-institutional-view .quick-nav-card:hover { border-color: #b9d9ff; background: #f8fbff; transform: translateY(-1px); color: #0f172a; }
        .messages-institutional-view .quick-nav-icon { width: 2.35rem; height: 2.35rem; border-radius: .85rem; display: inline-flex; align-items: center; justify-content: center; background: #eef6ff; color: #0d47a1; flex: 0 0 auto; }
        .messages-institutional-view .quick-nav-title { font-weight: 850; line-height: 1.25; }
        .messages-institutional-view .quick-nav-help { color: #64748b; font-size: .8rem; line-height: 1.35; margin-top: .15rem; }

        .messages-institutional-view .stage-panel-card { border: 1px solid #d7dee8; border-radius: 1rem; background: #fff; box-shadow: 0 .35rem 1.2rem rgba(15,23,42,.045); overflow: hidden; }
        .messages-institutional-view .stage-panel-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: .9rem; padding: 1rem; background: linear-gradient(135deg, #f8fbff 0%, #ffffff 100%); border-bottom: 1px solid #e5edf6; }
        .messages-institutional-view .stage-panel-title-wrap { display: flex; align-items: flex-start; gap: .8rem; min-width: 0; }
        .messages-institutional-view .stage-panel-icon { width: 2.55rem; height: 2.55rem; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 1.1rem; box-shadow: 0 .35rem .8rem rgba(15,23,42,.12); flex: 0 0 auto; }
        .messages-institutional-view .stage-panel-icon.is-directory { background: #0d6efd; }
        .messages-institutional-view .stage-panel-icon.is-establishment { background: #475569; }
        .messages-institutional-view .stage-panel-icon.is-filter { background: #475569; }
        .messages-institutional-view .stage-panel-icon.is-chat { background: #0f8f4d; }
        .messages-institutional-view .stage-panel-icon.is-search { background: #7c3aed; }
        .messages-institutional-view .stage-panel-kicker { color: #64748b; font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .035em; margin-bottom: .1rem; }
        .messages-institutional-view .stage-panel-help { color: #64748b; font-size: .84rem; margin-top: .18rem; line-height: 1.35; }
        .messages-institutional-view .stage-panel-body { padding: 1rem; }

        .messages-institutional-view .main-layout { display: grid; grid-template-columns: minmax(0, 1.5fr) minmax(340px, .9fr); gap: 1rem; align-items: start; }
        .messages-institutional-view .form-label { color: #0f172a; font-weight: 750; }
        .messages-institutional-view .form-control, .messages-institutional-view .form-select { border-radius: .85rem; border-color: #d7dee8; padding: .7rem .85rem; box-shadow: none; }
        .messages-institutional-view .form-control:focus, .messages-institutional-view .form-select:focus { border-color: #93c5fd; box-shadow: 0 0 0 .18rem rgba(13,110,253,.12); }
        .messages-institutional-view .cometido-btn { display: inline-flex; align-items: center; justify-content: center; gap: .45rem; border-radius: .85rem; padding: .68rem .95rem; font-weight: 800; border: 1px solid transparent; text-decoration: none; transition: all .18s ease; }
        .messages-institutional-view .cometido-btn.is-primary { background: linear-gradient(135deg, #0d6efd 0%, #1d4ed8 100%); color: #fff; }
        .messages-institutional-view .cometido-btn.is-secondary { background: #fff; color: #334155; border-color: #d7dee8; }
        .messages-institutional-view .cometido-btn.is-danger { background: #fff; color: #dc3545; border-color: #ffc9cf; }
        .messages-institutional-view .cometido-btn.is-primary:hover { box-shadow: 0 .45rem 1rem rgba(13,110,253,.2); }
        .messages-institutional-view .cometido-btn.is-secondary:hover { background: #f8fafc; color: #0f172a; }

        .messages-institutional-view .directory-tree { display: grid; gap: 1rem; }
        .messages-institutional-view .subdir-card { border: 1px solid #e3eaf3; border-radius: 1rem; overflow: hidden; background: #fff; }
        .messages-institutional-view .subdir-header { display: flex; justify-content: space-between; gap: .75rem; align-items: flex-start; padding: .95rem 1rem; background: #f8fbff; border-bottom: 1px solid #e5edf6; }
        .messages-institutional-view .subdir-title { font-weight: 900; color: #0f172a; line-height: 1.25; }
        .messages-institutional-view .unit-list { display: grid; gap: .85rem; padding: 1rem; }
        .messages-institutional-view .unit-title { color: #334155; font-size: .78rem; font-weight: 850; text-transform: uppercase; letter-spacing: .03em; margin-bottom: .65rem; display: flex; align-items: center; gap: .4rem; }
        .messages-institutional-view .contact-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(255px, 1fr)); gap: .75rem; }
        .messages-institutional-view .contact-card { border: 1px solid #e3eaf3; border-radius: .95rem; padding: .9rem; background: #fff; box-shadow: 0 .22rem .7rem rgba(15,23,42,.025); display: flex; flex-direction: column; gap: .75rem; min-height: 100%; }
        .messages-institutional-view .contact-head { display: flex; align-items: flex-start; gap: .75rem; }
        .messages-institutional-view .contact-name { font-weight: 850; color: #0f172a; line-height: 1.22; }
        .messages-institutional-view .contact-role { color: #64748b; font-size: .8rem; line-height: 1.35; margin-top: .12rem; }
        .messages-institutional-view .contact-meta { display: grid; gap: .35rem; color: #475569; font-size: .8rem; }
        .messages-institutional-view .contact-meta span { display: flex; align-items: flex-start; gap: .42rem; min-width: 0; word-break: break-word; }
        .messages-institutional-view .contact-actions { margin-top: auto; display: flex; justify-content: flex-end; }
        .messages-institutional-view .contact-actions .cometido-btn { padding: .55rem .78rem; font-size: .84rem; }
        .messages-institutional-view .establishment-logo { width: 3.15rem; height: 3.15rem; border-radius: .9rem; border: 1px solid #dbe4f0; display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto; overflow: hidden; background: #fff; box-shadow: 0 .3rem .8rem rgba(15,23,42,.08); }
        .messages-institutional-view .establishment-logo img { display: block; width: 100%; height: 100%; padding: .25rem; object-fit: contain; object-position: center; }
        .messages-institutional-view .establishment-collapse-actions { display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: .55rem; }
        .messages-institutional-view .establishment-collapse-toggle { padding: .5rem .75rem; font-size: .82rem; }
        .messages-institutional-view .establishment-collapse-toggle .toggle-icon { transition: transform .18s ease; }
        .messages-institutional-view .establishment-collapse-toggle[aria-expanded="true"] .toggle-icon { transform: rotate(180deg); }
        .messages-institutional-view .establishment-collapse-toggle .when-expanded { display: none; }
        .messages-institutional-view .establishment-collapse-toggle[aria-expanded="true"] .when-collapsed { display: none; }
        .messages-institutional-view .establishment-collapse-toggle[aria-expanded="true"] .when-expanded { display: inline; }
        .messages-institutional-view .avatar-pill { width: 2.45rem; height: 2.45rem; border-radius: .9rem; display: inline-flex; align-items: center; justify-content: center; font-weight: 900; flex: 0 0 auto; color: #fff; box-shadow: 0 .3rem .8rem rgba(15,23,42,.1); }
        .messages-institutional-view .avatar-pill.role-admin { background: #0d6efd; }
        .messages-institutional-view .avatar-pill.role-director { background: #1d4ed8; }
        .messages-institutional-view .avatar-pill.role-coord { background: #7c3aed; }
        .messages-institutional-view .avatar-pill.role-func, .messages-institutional-view .avatar-pill.role-ac { background: #0f8f4d; }
        .messages-institutional-view .avatar-pill.role-estab { background: #475569; }
        .messages-institutional-view .avatar-pill.role-muted { background: #94a3b8; }
        .messages-institutional-view .presence-dot { width: .7rem; height: .7rem; border-radius: 999px; display: inline-block; flex: 0 0 auto; margin-top: .22rem; background: #94a3b8; box-shadow: 0 0 0 3px #fff; }
        .messages-institutional-view .presence-dot.is-online { background: #16a34a; }
        .messages-institutional-view .info-chip { display: inline-flex; align-items: center; gap: .32rem; padding: .25rem .55rem; border-radius: 999px; background: #f1f5f9; border: 1px solid #dbe4f0; color: #475569; font-size: .76rem; font-weight: 800; }
        .messages-institutional-view .info-chip.is-primary { background: #eef6ff; border-color: #b9d9ff; color: #0d47a1; }
        .messages-institutional-view .info-chip.is-success { background: #ecfdf3; border-color: #bcebd0; color: #0f5132; }

        .messages-institutional-view .conversation-list { display: grid; gap: .75rem; }
        .messages-institutional-view .conversation-item { border: 1px solid #e3eaf3; border-radius: .95rem; background: #fff; padding: .85rem; text-decoration: none; color: inherit; display: flex; gap: .75rem; align-items: flex-start; transition: all .15s ease; }
        .messages-institutional-view .conversation-item:hover { border-color: #b9d9ff; background: #f8fbff; transform: translateY(-1px); }
        .messages-institutional-view .conversation-item.is-unread { border-color: #93c5fd; background: #f8fbff; box-shadow: 0 .25rem .8rem rgba(13,110,253,.08); }
        .messages-institutional-view .unread-pill { display: inline-flex; align-items: center; justify-content: center; min-width: 1.45rem; height: 1.45rem; padding: 0 .45rem; border-radius: 999px; background: #0d6efd; color: #fff; font-size: .72rem; font-weight: 900; }
        .messages-institutional-view .conversation-title { font-weight: 850; color: #0f172a; line-height: 1.25; }
        .messages-institutional-view .conversation-last { color: #64748b; font-size: .82rem; line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .messages-institutional-view .search-results { position: absolute; z-index: 1040; left: 0; right: 0; top: calc(100% + .35rem); display: none; max-height: 350px; overflow: auto; border: 1px solid #d7dee8; border-radius: .95rem; box-shadow: 0 .6rem 1.6rem rgba(15,23,42,.16); background: #fff; }
        .messages-institutional-view .search-result-item,
        .messages-user-search-results .search-result-item { display: flex; align-items: flex-start; gap: .75rem; padding: .8rem .9rem; border-bottom: 1px solid #eef2f7; cursor: pointer; }
        .messages-institutional-view .search-result-item:hover,
        .messages-user-search-results .search-result-item:hover { background: #f8fbff; }
        .messages-user-search-results { position: fixed; z-index: 1085; display: none; max-height: 360px; overflow: auto; border: 1px solid #d7dee8; border-radius: .95rem; box-shadow: 0 .75rem 2rem rgba(15,23,42,.18); background: #fff; }
        .messages-user-search-results .search-empty-row { padding: .95rem 1rem; color: #64748b; font-size: .9rem; text-align: center; }
        .messages-institutional-view .empty-state { text-align: center; padding: 2rem 1rem; color: #64748b; border: 1px dashed #b8c5d6; border-radius: 1rem; background: #f8fafc; }
        .messages-institutional-view .empty-icon { width: 3rem; height: 3rem; border-radius: 999px; background: #f1f5f9; display: inline-flex; align-items: center; justify-content: center; color: #475569; font-size: 1.35rem; margin-bottom: .8rem; }

        @media (max-width: 1199.98px) { .messages-institutional-view .main-layout { grid-template-columns: 1fr; } }
        @media (max-width: 991.98px) { .messages-institutional-view .metric-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .messages-institutional-view .quick-nav { grid-template-columns: 1fr; } }
        @media (max-width: 767.98px) { .messages-institutional-view .metric-grid { grid-template-columns: 1fr; padding: 1rem; } .messages-institutional-view .module-hero { padding: 1rem; } .messages-institutional-view .contact-grid { grid-template-columns: 1fr; } }
    </style>

    <div class="module-shell mb-4">
        <div class="module-hero">
            <div class="module-title-wrap">
                <span class="module-icon"><i class="bi bi-chat-square-dots"></i></span>
                <div>
                    <div class="module-kicker">Mensajería institucional</div>
                    <h1 class="module-title">Mensajes SLEP</h1>
                    <p class="module-help">Bandeja de conversaciones, libreta institucional de Administración Central y nómina territorial de establecimientos con sus directores y datos de contacto.</p>
                </div>
            </div>
            <span class="module-badge"><i class="bi bi-person-lines-fill"></i> Directorios SLEP</span>
        </div>

        <div class="metric-grid">
            <div class="metric-card">
                <div class="metric-label"><i class="bi bi-chat-dots"></i> Conversaciones</div>
                <div class="metric-number">{{ number_format($metrics['conversations'] ?? 0, 0, ',', '.') }}</div>
                <p class="metric-help">Conversaciones en las que participas.</p>
            </div>
            <div class="metric-card">
                <div class="metric-label"><i class="bi bi-envelope-exclamation"></i> No leídos</div>
                <div class="metric-number">{{ number_format($metrics['unread'] ?? 0, 0, ',', '.') }}</div>
                <p class="metric-help">Mensajes pendientes en {{ number_format($metrics['unread_conversations'] ?? 0, 0, ',', '.') }} conversación(es).</p>
            </div>
            <div class="metric-card">
                <div class="metric-label"><i class="bi bi-person-check"></i> Contactos AC</div>
                <div class="metric-number">{{ number_format($metrics['directory'] ?? 0, 0, ',', '.') }}</div>
                <p class="metric-help">Funcionarios AC registrados con cuenta activa.</p>
            </div>
            <div class="metric-card">
                <div class="metric-label"><i class="bi bi-buildings"></i> Establecimientos</div>
                <div class="metric-number">{{ number_format($metrics['establishments'] ?? 0, 0, ',', '.') }}</div>
                <p class="metric-help">Nómina territorial con director/a y contacto registrado.</p>
            </div>
            <div class="metric-card">
                <div class="metric-label"><i class="bi bi-diagram-3"></i> Subdirecciones</div>
                <div class="metric-number">{{ number_format($metrics['subdirecciones'] ?? 0, 0, ',', '.') }}</div>
                <p class="metric-help">Agrupaciones institucionales disponibles.</p>
            </div>
            <div class="metric-card">
                <div class="metric-label"><i class="bi bi-building"></i> Unidades</div>
                <div class="metric-number">{{ number_format($metrics['unidades'] ?? 0, 0, ',', '.') }}</div>
                <p class="metric-help">Unidades o departamentos de contacto.</p>
            </div>
        </div>
    </div>

    <div class="quick-nav mb-4">
        <a href="#directoryPanel" class="quick-nav-card">
            <span class="quick-nav-icon"><i class="bi bi-person-lines-fill"></i></span>
            <span>
                <span class="quick-nav-title">Libreta SLEP</span>
                <span class="quick-nav-help d-block">Buscar personal de Administración Central por subdirección y unidad.</span>
            </span>
        </a>
        <a href="#conversationPanel" class="quick-nav-card">
            <span class="quick-nav-icon"><i class="bi bi-chat-dots"></i></span>
            <span>
                <span class="quick-nav-title">Mis conversaciones</span>
                <span class="quick-nav-help d-block">Acceder rápidamente a los chats activos o recientes.</span>
            </span>
        </a>
        <a href="#establishmentPanel" class="quick-nav-card">
            <span class="quick-nav-icon"><i class="bi bi-buildings"></i></span>
            <span>
                <span class="quick-nav-title">Establecimientos</span>
                <span class="quick-nav-help d-block">Consultar director o directora y su contacto institucional.</span>
            </span>
        </a>
        <a href="#directoryFilters" class="quick-nav-card">
            <span class="quick-nav-icon"><i class="bi bi-funnel"></i></span>
            <span>
                <span class="quick-nav-title">Filtros institucionales</span>
                <span class="quick-nav-help d-block">Afinar búsqueda por nombre, subdirección, unidad o conexión reciente.</span>
            </span>
        </a>
    </div>

    @if ($canStartGeneral)
        <div class="stage-panel-card mb-4">
            <div class="stage-panel-header">
                <div class="stage-panel-title-wrap">
                    <span class="stage-panel-icon is-search"><i class="bi bi-search"></i></span>
                    <div>
                        <div class="stage-panel-kicker">Conversación directa</div>
                        <h2 class="h5 mb-0">Buscar usuario del sistema</h2>
                        <div class="stage-panel-help">Disponible para roles internos SLEP con permiso amplio de mensajería.</div>
                    </div>
                </div>
            </div>
            <div class="stage-panel-body">
                <div class="position-relative">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                        <input id="userSearch" type="text" class="form-control border-start-0" placeholder="Buscar por nombre, apellido o correo..." autocomplete="off" data-search-url="{{ route('messages.search-users') }}" data-start-url="{{ route('messages.start') }}" data-show-template="{{ route('messages.show', ['conversation' => '__ID__']) }}">
                        <button id="btnStart" type="button" class="cometido-btn is-primary" disabled><i class="bi bi-chat-dots"></i> Iniciar</button>
                    </div>
                    <div id="searchList" class="search-results"></div>
                </div>
            </div>
        </div>
    @endif

    <div class="main-layout">
        <div>
            <div class="stage-panel-card mb-4" id="establishmentPanel">
                <div class="stage-panel-header">
                    <div class="stage-panel-title-wrap">
                        <span class="stage-panel-icon is-establishment"><i class="bi bi-buildings"></i></span>
                        <div>
                            <div class="stage-panel-kicker">Nómina territorial</div>
                            <h2 class="h5 mb-0">Establecimientos y contactos directivos</h2>
                            <div class="stage-panel-help">Consulta el nombre del director o directora y el contacto registrado para cada establecimiento.</div>
                        </div>
                    </div>
                    <div class="establishment-collapse-actions">
                        <span class="info-chip"><i class="bi bi-building-check"></i> {{ $establishmentItems->count() }} establecimiento(s)</span>
                        <button
                            type="button"
                            class="cometido-btn is-secondary establishment-collapse-toggle"
                            data-bs-toggle="collapse"
                            data-bs-target="#establishmentDirectoryCollapse"
                            aria-expanded="{{ $establishmentDirectoryOpen ? 'true' : 'false' }}"
                            aria-controls="establishmentDirectoryCollapse"
                        >
                            <i class="bi bi-chevron-down toggle-icon"></i>
                            <span class="when-collapsed">Mostrar contactos</span>
                            <span class="when-expanded">Ocultar contactos</span>
                        </button>
                    </div>
                </div>
                <div class="collapse {{ $establishmentDirectoryOpen ? 'show' : '' }}" id="establishmentDirectoryCollapse">
                    <div class="stage-panel-body">
                        <form method="GET" action="{{ route('messages.index') }}" class="row g-3 align-items-end mb-4">
                        @if(filled($qActual))
                            <input type="hidden" name="q" value="{{ $qActual }}">
                        @endif
                        @if(filled($subdireccionActual))
                            <input type="hidden" name="subdireccion" value="{{ $subdireccionActual }}">
                        @endif
                        @if(filled($unidadActual))
                            <input type="hidden" name="unidad" value="{{ $unidadActual }}">
                        @endif
                        @if($recentOnlyActual)
                            <input type="hidden" name="recent_only" value="1">
                        @endif

                        <div class="col-lg-7">
                            <label class="form-label">Buscar establecimiento o director/a</label>
                            <input type="text" name="establecimiento_q" value="{{ $establecimientoQActual }}" class="form-control" placeholder="Nombre, RBD, director/a o contacto">
                        </div>
                        <div class="col-lg-3">
                            <label class="form-label">Comuna</label>
                            <select name="establecimiento_comuna" class="form-select">
                                <option value="">Todas</option>
                                @foreach($establishmentComunas as $comuna)
                                    <option value="{{ $comuna }}" @selected($establecimientoComunaActual === $comuna)>{{ $comuna }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 d-flex gap-2">
                            <button type="submit" class="cometido-btn is-primary flex-fill" title="Filtrar establecimientos"><i class="bi bi-search"></i></button>
                            <a href="{{ route('messages.index', array_filter([
                                'q' => $qActual,
                                'subdireccion' => $subdireccionActual,
                                'unidad' => $unidadActual,
                                'recent_only' => $recentOnlyActual ? 1 : null,
                            ], fn ($value) => $value !== null && $value !== '')) }}" class="cometido-btn is-danger" title="Limpiar filtro de establecimientos"><i class="bi bi-x-circle"></i></a>
                        </div>
                        </form>

                        @if($establishmentGrouped->isEmpty())
                            <div class="empty-state">
                                <div class="empty-icon"><i class="bi bi-search"></i></div>
                                <div class="fw-semibold">No se encontraron establecimientos</div>
                                <div class="small">Ajusta los filtros o completa los datos directivos desde la administración de establecimientos.</div>
                            </div>
                        @else
                            <div class="directory-tree">
                                @foreach($establishmentGrouped as $comuna => $establecimientos)
                                    <div class="subdir-card">
                                        <div class="subdir-header">
                                            <div>
                                                <div class="subdir-title"><i class="bi bi-geo-alt me-1"></i> {{ $comuna }}</div>
                                                <div class="small text-muted">{{ $establecimientos->count() }} establecimiento(s)</div>
                                            </div>
                                        </div>
                                        <div class="unit-list">
                                            <div class="contact-grid">
                                                @foreach($establecimientos as $establecimiento)
                                                    <div class="contact-card">
                                                        <div class="contact-head">
                                                            @if($establecimiento['logo_url'])
                                                                <span class="establishment-logo">
                                                                    <img src="{{ $establecimiento['logo_url'] }}" alt="Logo de {{ $establecimiento['name'] }}" loading="lazy">
                                                                </span>
                                                            @else
                                                                <span class="avatar-pill role-estab">{{ $establecimiento['initials'] }}</span>
                                                            @endif
                                                            <div class="min-w-0">
                                                                <div class="contact-name">{{ $establecimiento['name'] }}</div>
                                                                <div class="contact-role">RBD {{ $establecimiento['rbd'] ?: 'sin registro' }}</div>
                                                            </div>
                                                        </div>
                                                        <div class="contact-meta">
                                                            <span><i class="bi bi-person-badge"></i> {{ $establecimiento['director_nombre'] ?: 'Director/a sin registrar' }}</span>
                                                            <span><i class="bi bi-telephone"></i> {{ $establecimiento['director_contacto'] ?: 'Contacto sin registrar' }}</span>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="stage-panel-card mb-4" id="directoryFilters">
                <div class="stage-panel-header">
                    <div class="stage-panel-title-wrap">
                        <span class="stage-panel-icon is-filter"><i class="bi bi-funnel"></i></span>
                        <div>
                            <div class="stage-panel-kicker">Libreta de direcciones</div>
                            <h2 class="h5 mb-0">Filtrar personal SLEP</h2>
                            <div class="stage-panel-help">La libreta muestra usuarios registrados con rol Funcionario AC y utiliza la nómina autorizada para completar subdirección, unidad y cargo.</div>
                        </div>
                    </div>
                </div>
                <div class="stage-panel-body">
                    <form method="GET" action="{{ route('messages.index') }}" class="row g-3 align-items-end">
                        @if(filled($establecimientoQActual))
                            <input type="hidden" name="establecimiento_q" value="{{ $establecimientoQActual }}">
                        @endif
                        @if(filled($establecimientoComunaActual))
                            <input type="hidden" name="establecimiento_comuna" value="{{ $establecimientoComunaActual }}">
                        @endif
                        <div class="col-lg-4">
                            <label class="form-label">Buscar</label>
                            <input type="text" name="q" value="{{ $qActual }}" class="form-control" placeholder="Nombre, correo, cargo o unidad">
                        </div>
                        <div class="col-lg-3">
                            <label class="form-label">Subdirección</label>
                            <select name="subdireccion" class="form-select">
                                <option value="">Todas</option>
                                @foreach(($directoryFilterOptions['subdirecciones'] ?? collect()) as $subdireccion)
                                    <option value="{{ $subdireccion }}" @selected($subdireccionActual === $subdireccion)>{{ $subdireccion }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-3">
                            <label class="form-label">Unidad</label>
                            <select name="unidad" class="form-select">
                                <option value="">Todas</option>
                                @foreach(($directoryFilterOptions['unidades'] ?? collect()) as $unidad)
                                    <option value="{{ $unidad }}" @selected($unidadActual === $unidad)>{{ $unidad }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="recent_only" value="1" id="recent_only" @checked($recentOnlyActual)>
                                <label class="form-check-label small" for="recent_only">Con conexión reciente</label>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="cometido-btn is-primary flex-fill"><i class="bi bi-search"></i></button>
                                <a href="{{ route('messages.index', array_filter([
                                    'establecimiento_q' => $establecimientoQActual,
                                    'establecimiento_comuna' => $establecimientoComunaActual,
                                ], fn ($value) => $value !== null && $value !== '')) }}" class="cometido-btn is-danger"><i class="bi bi-x-circle"></i></a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="stage-panel-card" id="directoryPanel">
                <div class="stage-panel-header">
                    <div class="stage-panel-title-wrap">
                        <span class="stage-panel-icon is-directory"><i class="bi bi-person-lines-fill"></i></span>
                        <div>
                            <div class="stage-panel-kicker">Directorio SLEP</div>
                            <h2 class="h5 mb-0">Funcionarios AC registrados</h2>
                            <div class="stage-panel-help">Agrupados por subdirección y unidad. Use “Enviar mensaje” para iniciar o abrir conversación.</div>
                        </div>
                    </div>
                    <span class="info-chip is-primary"><i class="bi bi-people"></i> {{ $directoryItems->count() }} contacto(s)</span>
                </div>
                <div class="stage-panel-body">
                    @if(! $canUseDirectory)
                        <div class="empty-state">
                            <div class="empty-icon"><i class="bi bi-lock"></i></div>
                            <div class="fw-semibold">Sin acceso a libreta institucional</div>
                            <div class="small">Tu rol actual no tiene habilitada la libreta SLEP.</div>
                        </div>
                    @elseif($directoryGrouped->isEmpty())
                        <div class="empty-state">
                            <div class="empty-icon"><i class="bi bi-search"></i></div>
                            <div class="fw-semibold">No se encontraron contactos</div>
                            <div class="small">Ajusta los filtros o revisa que existan usuarios registrados con rol Funcionario AC.</div>
                        </div>
                    @else
                        <div class="directory-tree">
                            @foreach($directoryGrouped as $subdireccion => $unidades)
                                <div class="subdir-card">
                                    <div class="subdir-header">
                                        <div>
                                            <div class="subdir-title"><i class="bi bi-diagram-3 me-1"></i> {{ $subdireccion }}</div>
                                            <div class="small text-muted">{{ $unidades->flatten(1)->count() }} contacto(s) disponible(s)</div>
                                        </div>
                                        <span class="info-chip">{{ $unidades->count() }} unidad(es)</span>
                                    </div>
                                    <div class="unit-list">
                                        @foreach($unidades as $unidad => $contactos)
                                            <div>
                                                <div class="unit-title"><i class="bi bi-building"></i> {{ $unidad }}</div>
                                                <div class="contact-grid">
                                                    @foreach($contactos as $contacto)
                                                        <div class="contact-card">
                                                            <div class="contact-head">
                                                                <span class="avatar-pill role-ac">{{ $contacto['initials'] }}</span>
                                                                <div class="min-w-0">
                                                                    <div class="contact-name">{{ $contacto['name'] }}</div>
                                                                    <div class="contact-role">{{ $contacto['cargo'] ?: 'Funcionario AC' }}</div>
                                                                </div>
                                                            </div>
                                                            <div class="contact-meta">
                                                                <span><i class="bi bi-envelope"></i> {{ $contacto['email'] ?: 'Sin correo registrado' }}</span>
                                                                <span><i class="bi bi-circle-fill {{ $contacto['online'] ? 'text-success' : 'text-secondary' }}"></i> {{ $contacto['online'] ? 'En línea' : $contacto['last_seen_label'] }}</span>
                                                            </div>
                                                            <div class="contact-actions">
                                                                <button type="button" class="cometido-btn is-primary js-start-conversation" data-user-id="{{ $contacto['user_id'] }}" data-user-name="{{ $contacto['name'] }}" data-start-url="{{ route('messages.start') }}" data-show-template="{{ route('messages.show', ['conversation' => '__ID__']) }}">
                                                                    <i class="bi bi-send"></i> Enviar mensaje
                                                                </button>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <aside>
            <div class="stage-panel-card" id="conversationPanel">
                <div class="stage-panel-header">
                    <div class="stage-panel-title-wrap">
                        <span class="stage-panel-icon is-chat"><i class="bi bi-chat-dots"></i></span>
                        <div>
                            <div class="stage-panel-kicker">Bandeja personal</div>
                            <h2 class="h5 mb-0">Mis conversaciones</h2>
                            <div class="stage-panel-help">Últimos contactos y mensajes registrados.</div>
                        </div>
                    </div>
                </div>
                <div class="stage-panel-body">
                    @if($conversations->isEmpty())
                        <div class="empty-state">
                            <div class="empty-icon"><i class="bi bi-chat-square"></i></div>
                            <div class="fw-semibold">Sin conversaciones</div>
                            <div class="small">Inicia una conversación desde la libreta SLEP.</div>
                        </div>
                    @else
                        <div class="conversation-list">
                            @foreach($conversations as $conversation)
                                <a href="{{ route('messages.show', $conversation->id) }}" class="conversation-item {{ $conversation->has_unread ? 'is-unread' : '' }}">
                                    <span class="presence-dot {{ $conversation->online ? 'is-online' : '' }}"></span>
                                    <span class="avatar-pill {{ $conversation->avatarClass }}">{{ $conversation->initial }}</span>
                                    <div class="min-w-0 flex-grow-1">
                                        <div class="d-flex justify-content-between gap-2 align-items-start">
                                            <div class="conversation-title text-truncate">{{ $conversation->name }}</div>
                                            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                                @if($conversation->has_unread)
                                                    <span class="unread-pill">{{ $conversation->unread_count }}</span>
                                                @endif
                                                <div class="small text-muted">{{ $conversation->last_at ?: $conversation->updated_at }}</div>
                                            </div>
                                        </div>
                                        <div class="small text-muted mb-1">{{ $conversation->roleLabel }}</div>
                                        <div class="conversation-last">{{ $conversation->last ?: 'Sin mensajes todavía' }}</div>
                                        @if($conversation->has_unread)
                                            <div class="mt-2"><span class="info-chip is-primary"><i class="bi bi-envelope-exclamation"></i> Mensaje pendiente de lectura</span></div>
                                        @endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const csrf = '{{ csrf_token() }}';

        async function startConversation(userId, startUrl, showTemplate) {
            const response = await fetch(startUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ user_id: userId })
            });
            const data = await response.json().catch(() => ({ ok: false }));
            if (response.ok && data.ok && data.id) {
                window.location.href = showTemplate.replace('__ID__', data.id);
                return;
            }
            alert(data.error || 'No fue posible iniciar la conversación.');
        }

        document.querySelectorAll('.js-start-conversation').forEach((button) => {
            button.addEventListener('click', () => {
                startConversation(button.dataset.userId, button.dataset.startUrl, button.dataset.showTemplate);
            });
        });

        const input = document.getElementById('userSearch');
        const list = document.getElementById('searchList');
        const btn = document.getElementById('btnStart');
        if (!input || !list || !btn) {
            return;
        }

        const qUrl = input.dataset.searchUrl;
        const startUrl = input.dataset.startUrl;
        const showTemplate = input.dataset.showTemplate;
        let pickedId = null;
        let timer = null;

        if (!list.classList.contains('messages-user-search-results')) {
            list.classList.add('messages-user-search-results');
        }
        list.setAttribute('role', 'listbox');
        if (list.parentElement !== document.body) {
            document.body.appendChild(list);
        }

        function positionSearchList() {
            const anchor = input.closest('.input-group') || input;
            const rect = anchor.getBoundingClientRect();
            const margin = 10;
            const top = Math.min(rect.bottom + 8, window.innerHeight - margin);
            const availableHeight = Math.max(160, window.innerHeight - top - margin);

            list.style.left = Math.max(margin, rect.left) + 'px';
            list.style.top = top + 'px';
            list.style.width = Math.max(280, Math.min(rect.width, window.innerWidth - (margin * 2))) + 'px';
            list.style.maxHeight = Math.min(360, availableHeight) + 'px';
        }

        function showList() {
            positionSearchList();
            list.style.display = 'block';
        }

        function clearList() {
            list.innerHTML = '';
            list.style.display = 'none';
        }

        function text(value) {
            return document.createTextNode(value || '');
        }

        function render(items) {
            list.innerHTML = '';
            pickedId = null;
            btn.disabled = true;

            if (!items || !items.length) {
                const empty = document.createElement('div');
                empty.className = 'search-empty-row';
                empty.appendChild(text('No se encontraron usuarios para la búsqueda ingresada.'));
                list.appendChild(empty);
                showList();
                return;
            }

            items.forEach((user) => {
                const row = document.createElement('div');
                row.className = 'search-result-item';
                row.setAttribute('role', 'option');

                const dot = document.createElement('span');
                dot.className = 'presence-dot ' + (user.online ? 'is-online' : '');

                const avatar = document.createElement('span');
                avatar.className = 'avatar-pill role-' + (user.role || 'muted');
                avatar.appendChild(text(user.initial || 'U'));

                const body = document.createElement('div');
                body.className = 'min-w-0 flex-grow-1';
                const name = document.createElement('div');
                name.className = 'fw-semibold text-truncate';
                name.appendChild(text(user.name));
                const role = document.createElement('div');
                role.className = 'small text-muted';
                role.appendChild(text(user.role_label));
                body.appendChild(name);
                body.appendChild(role);

                row.appendChild(dot);
                row.appendChild(avatar);
                row.appendChild(body);
                row.addEventListener('click', () => {
                    pickedId = user.id;
                    input.value = user.name;
                    list.style.display = 'none';
                    btn.disabled = false;
                });

                list.appendChild(row);
            });

            showList();
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            const q = this.value.trim();
            if (q.length < 2) {
                clearList();
                return;
            }
            timer = setTimeout(async () => {
                try {
                    const url = new URL(qUrl, window.location.origin);
                    url.searchParams.set('q', q);
                    const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    const data = response.ok ? await response.json() : { items: [] };
                    render(data.items || []);
                } catch (error) {
                    clearList();
                }
            }, 250);
        });

        input.addEventListener('focus', function () {
            if (list.childElementCount > 0 && this.value.trim().length >= 2) {
                showList();
            }
        });

        window.addEventListener('resize', () => {
            if (list.style.display === 'block') {
                positionSearchList();
            }
        });

        window.addEventListener('scroll', () => {
            if (list.style.display === 'block') {
                positionSearchList();
            }
        }, true);

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                clearList();
            }
        });

        document.addEventListener('click', (event) => {
            if (event.target !== input && !list.contains(event.target) && event.target !== btn) {
                clearList();
            }
        });

        btn.addEventListener('click', () => {
            if (!pickedId) {
                return;
            }
            startConversation(pickedId, startUrl, showTemplate);
        });
    })();
</script>
@endpush
