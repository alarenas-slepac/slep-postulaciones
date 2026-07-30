@extends('layouts.app')

@section('content')
@php
    $modeloNotificacion = \App\Models\CometidoNotificacionConfiguracion::class;
    $totalCatalogo = $modeloNotificacion::query()->count();
    $totalActivas = $modeloNotificacion::query()->where('activo', true)->count();
    $totalPorRol = $modeloNotificacion::query()->where('tipo_destinatario', 'rol_configurable')->count();
    $totalConfigurables = $modeloNotificacion::query()->whereIn('tipo_destinatario', [
        'rol_configurable',
        'correo_configurable',
        'dinamico_con_copia_configurable',
        'dinamico_establecimiento_con_copia_configurable',
    ])->count();

    $categoriaActual = request('categoria');
    $busquedaActual = request('q');
@endphp

<div class="container-fluid py-4 cometidos-notificaciones-view">
    <style>
        .cometidos-notificaciones-view .module-shell { border: 1px solid #d7dee8; border-radius: 1.15rem; background: #fff; box-shadow: 0 .55rem 1.6rem rgba(15,23,42,.06); overflow: hidden; }
        .cometidos-notificaciones-view .module-hero { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 1rem; padding: 1.35rem 1.45rem; background: linear-gradient(135deg, #f8fbff 0%, #ffffff 100%); border-bottom: 1px solid #e5edf6; }
        .cometidos-notificaciones-view .module-title-wrap { display: flex; align-items: flex-start; gap: .95rem; min-width: 0; }
        .cometidos-notificaciones-view .module-icon { width: 2.85rem; height: 2.85rem; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; color: #fff; background: linear-gradient(135deg, #0d6efd 0%, #1d4ed8 100%); box-shadow: 0 .4rem 1rem rgba(13,110,253,.25); flex: 0 0 auto; }
        .cometidos-notificaciones-view .module-kicker { color: #64748b; font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; margin-bottom: .12rem; }
        .cometidos-notificaciones-view .module-title { color: #0f172a; font-size: clamp(1.45rem, 2vw, 2rem); font-weight: 850; line-height: 1.1; margin-bottom: .35rem; }
        .cometidos-notificaciones-view .module-help { max-width: 820px; color: #475569; line-height: 1.45; margin-bottom: 0; }
        .cometidos-notificaciones-view .module-badge { display: inline-flex; align-items: center; gap: .4rem; padding: .48rem .75rem; border-radius: 999px; background: #eef6ff; border: 1px solid #b9d9ff; color: #0d47a1; font-size: .8rem; font-weight: 800; }

        .cometidos-notificaciones-view .metric-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .9rem; padding: 1rem 1.45rem 1.25rem; background: #fff; }
        .cometidos-notificaciones-view .metric-card { border: 1px solid #dbe4f0; border-radius: 1rem; background: #fff; padding: .95rem 1rem; min-height: 6.2rem; box-shadow: 0 .25rem .8rem rgba(15,23,42,.035); }
        .cometidos-notificaciones-view .metric-label { display: flex; align-items: center; gap: .45rem; color: #64748b; font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .025em; margin-bottom: .35rem; }
        .cometidos-notificaciones-view .metric-number { color: #0f172a; font-size: 1.55rem; line-height: 1; font-weight: 900; margin-bottom: .45rem; }
        .cometidos-notificaciones-view .metric-help { color: #64748b; font-size: .78rem; line-height: 1.35; margin-bottom: 0; }

        .cometidos-notificaciones-view .stage-panel-card { border: 1px solid #d7dee8; border-radius: 1rem; background: #fff; box-shadow: 0 .35rem 1.2rem rgba(15,23,42,.045); overflow: hidden; }
        .cometidos-notificaciones-view .stage-panel-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: .9rem; padding: 1rem; background: linear-gradient(135deg, #f8fbff 0%, #ffffff 100%); border-bottom: 1px solid #e5edf6; }
        .cometidos-notificaciones-view .stage-panel-title-wrap { display: flex; align-items: flex-start; gap: .8rem; min-width: 0; }
        .cometidos-notificaciones-view .stage-panel-icon { width: 2.55rem; height: 2.55rem; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 1.1rem; box-shadow: 0 .35rem .8rem rgba(15,23,42,.12); flex: 0 0 auto; }
        .cometidos-notificaciones-view .stage-panel-icon.is-filter { background: #475569; }
        .cometidos-notificaciones-view .stage-panel-icon.is-list { background: #0d6efd; }
        .cometidos-notificaciones-view .stage-panel-icon.is-info { background: #0f8f4d; }
        .cometidos-notificaciones-view .stage-panel-kicker { color: #64748b; font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .035em; margin-bottom: .1rem; }
        .cometidos-notificaciones-view .stage-panel-help { color: #64748b; font-size: .84rem; margin-top: .18rem; line-height: 1.35; }
        .cometidos-notificaciones-view .stage-panel-body { padding: 1rem; }

        .cometidos-notificaciones-view .form-label { color: #0f172a; font-weight: 750; }
        .cometidos-notificaciones-view .form-control,
        .cometidos-notificaciones-view .form-select { border-radius: .85rem; border-color: #d7dee8; padding: .7rem .85rem; box-shadow: none; }
        .cometidos-notificaciones-view .form-control:focus,
        .cometidos-notificaciones-view .form-select:focus { border-color: #93c5fd; box-shadow: 0 0 0 .18rem rgba(13,110,253,.12); }

        .cometidos-notificaciones-view .cometido-btn { display: inline-flex; align-items: center; justify-content: center; gap: .45rem; border-radius: .85rem; padding: .68rem .95rem; font-weight: 800; border: 1px solid transparent; text-decoration: none; transition: all .18s ease; }
        .cometidos-notificaciones-view .cometido-btn.is-primary { background: linear-gradient(135deg, #0d6efd 0%, #1d4ed8 100%); color: #fff; }
        .cometidos-notificaciones-view .cometido-btn.is-secondary { background: #fff; color: #334155; border-color: #d7dee8; }
        .cometidos-notificaciones-view .cometido-btn.is-danger { background: #fff; color: #dc3545; border-color: #ffc9cf; }
        .cometidos-notificaciones-view .cometido-btn.is-primary:hover { box-shadow: 0 .45rem 1rem rgba(13,110,253,.2); }
        .cometidos-notificaciones-view .cometido-btn.is-secondary:hover { background: #f8fafc; color: #0f172a; }
        .cometidos-notificaciones-view .cometido-btn.is-danger:hover { background: #fff8f8; }

        .cometidos-notificaciones-view .criteria-box { border: 1px solid #cfe1ff; border-radius: 1rem; background: #f8fbff; padding: 1rem; color: #334155; line-height: 1.45; }
        .cometidos-notificaciones-view .criteria-title { display: flex; align-items: center; gap: .45rem; color: #0f172a; font-weight: 850; margin-bottom: .35rem; }

        .cometidos-notificaciones-view .notifications-list { display: grid; gap: .85rem; }
        .cometidos-notificaciones-view .notification-card { border: 1px solid #e3eaf3; border-radius: 1rem; background: #fff; box-shadow: 0 .25rem .8rem rgba(15,23,42,.035); overflow: hidden; }
        .cometidos-notificaciones-view .notification-card.is-inactive { opacity: .78; }
        .cometidos-notificaciones-view .notification-main { display: grid; grid-template-columns: minmax(0, 1.45fr) minmax(180px, .75fr) minmax(220px, .9fr) minmax(170px, .65fr); gap: .9rem; padding: 1rem; align-items: start; }
        .cometidos-notificaciones-view .notification-title { color: #0f172a; font-weight: 850; line-height: 1.25; margin-bottom: .25rem; }
        .cometidos-notificaciones-view .notification-description { color: #64748b; font-size: .84rem; line-height: 1.38; margin-bottom: .65rem; }
        .cometidos-notificaciones-view .notification-label { color: #64748b; font-size: .7rem; font-weight: 850; text-transform: uppercase; letter-spacing: .025em; margin-bottom: .35rem; }
        .cometidos-notificaciones-view .chip-wrap { display: flex; flex-wrap: wrap; gap: .35rem; }
        .cometidos-notificaciones-view .info-chip { display: inline-flex; align-items: center; gap: .32rem; padding: .25rem .55rem; border-radius: 999px; background: #f1f5f9; border: 1px solid #dbe4f0; color: #475569; font-size: .76rem; font-weight: 800; max-width: 100%; word-break: break-word; }
        .cometidos-notificaciones-view .info-chip.is-primary { background: #eef6ff; border-color: #b9d9ff; color: #0d47a1; }
        .cometidos-notificaciones-view .info-chip.is-success { background: #ecfdf3; border-color: #bcebd0; color: #0f5132; }
        .cometidos-notificaciones-view .info-chip.is-warning { background: #fff8e1; border-color: #f5d58b; color: #8a4b00; }
        .cometidos-notificaciones-view .info-chip.is-danger { background: #fff1f2; border-color: #fecdd3; color: #b42318; }
        .cometidos-notificaciones-view .notification-actions { display: flex; flex-direction: column; gap: .55rem; align-items: stretch; }
        .cometidos-notificaciones-view .empty-state { text-align: center; padding: 2.5rem 1rem; color: #64748b; }
        .cometidos-notificaciones-view .empty-icon { width: 3rem; height: 3rem; border-radius: 999px; background: #f1f5f9; display: inline-flex; align-items: center; justify-content: center; color: #475569; font-size: 1.35rem; margin-bottom: .8rem; }

        @media (max-width: 1199.98px) {
            .cometidos-notificaciones-view .notification-main { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 991.98px) {
            .cometidos-notificaciones-view .metric-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 767.98px) {
            .cometidos-notificaciones-view .metric-grid { grid-template-columns: 1fr; padding: 1rem; }
            .cometidos-notificaciones-view .module-hero { padding: 1rem; }
            .cometidos-notificaciones-view .notification-main { grid-template-columns: 1fr; }
            .cometidos-notificaciones-view .notification-actions { flex-direction: row; flex-wrap: wrap; }
            .cometidos-notificaciones-view .notification-actions .cometido-btn { flex: 1 1 160px; }
        }
    </style>

    <div class="module-shell mb-4">
        <div class="module-hero">
            <div class="module-title-wrap">
                <span class="module-icon"><i class="bi bi-bell"></i></span>
                <div>
                    <div class="module-kicker">Administración · Cometidos funcionarios</div>
                    <h1 class="module-title">Notificaciones de cometidos</h1>
                    <p class="module-help">
                        Mantenedor de destinatarios del flujo completo de cometidos funcionarios. Permite configurar roles destinatarios, correos directos y copias adicionales por cada evento del proceso.
                    </p>
                </div>
            </div>
            <span class="module-badge"><i class="bi bi-sliders"></i> Configuración de correo</span>
        </div>

        <div class="metric-grid">
            <div class="metric-card">
                <div class="metric-label"><i class="bi bi-list-check"></i> Catálogo</div>
                <div class="metric-number">{{ number_format($totalCatalogo, 0, ',', '.') }}</div>
                <p class="metric-help">Notificaciones registradas del proceso.</p>
            </div>
            <div class="metric-card">
                <div class="metric-label"><i class="bi bi-toggle-on"></i> Activas</div>
                <div class="metric-number">{{ number_format($totalActivas, 0, ',', '.') }}</div>
                <p class="metric-help">Correos habilitados para envío.</p>
            </div>
            <div class="metric-card">
                <div class="metric-label"><i class="bi bi-people"></i> Por rol</div>
                <div class="metric-number">{{ number_format($totalPorRol, 0, ',', '.') }}</div>
                <p class="metric-help">Eventos que usan destinatarios por rol.</p>
            </div>
            <div class="metric-card">
                <div class="metric-label"><i class="bi bi-envelope-check"></i> Configurables</div>
                <div class="metric-number">{{ number_format($totalConfigurables, 0, ',', '.') }}</div>
                <p class="metric-help">Eventos administrables desde esta vista.</p>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-4">
            <i class="bi bi-check-circle-fill me-1"></i> {{ session('success') }}
        </div>
    @endif

    <div class="stage-panel-card mb-4">
        <div class="stage-panel-header">
            <div class="stage-panel-title-wrap">
                <span class="stage-panel-icon is-filter"><i class="bi bi-funnel"></i></span>
                <div>
                    <div class="stage-panel-kicker">Búsqueda y filtrado</div>
                    <h2 class="h5 mb-0">Filtrar notificaciones</h2>
                    <div class="stage-panel-help">Busque por nombre, clave técnica, rol, correo o categoría del proceso.</div>
                </div>
            </div>
        </div>
        <div class="stage-panel-body">
            <form method="GET" action="{{ route('admin.cometidos-notificaciones.index') }}" class="row g-3 align-items-end">
                <div class="col-lg-5">
                    <label class="form-label">Buscar</label>
                    <input type="text" name="q" value="{{ $busquedaActual }}" class="form-control" placeholder="Nombre, clave, rol o correo">
                </div>
                <div class="col-lg-4">
                    <label class="form-label">Categoría</label>
                    <select name="categoria" class="form-select">
                        <option value="">Todas las categorías</option>
                        @foreach($categorias as $categoria)
                            <option value="{{ $categoria }}" @selected($categoriaActual === $categoria)>{{ $categoria }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3 d-flex flex-wrap gap-2">
                    <button class="cometido-btn is-primary flex-fill" type="submit"><i class="bi bi-search"></i> Filtrar</button>
                    <a href="{{ route('admin.cometidos-notificaciones.index') }}" class="cometido-btn is-danger"><i class="bi bi-x-circle"></i> Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="stage-panel-card mb-4">
        <div class="stage-panel-header">
            <div class="stage-panel-title-wrap">
                <span class="stage-panel-icon is-info"><i class="bi bi-info-circle"></i></span>
                <div>
                    <div class="stage-panel-kicker">Criterio de envío</div>
                    <h2 class="h5 mb-0">Funcionamiento de destinatarios</h2>
                </div>
            </div>
        </div>
        <div class="stage-panel-body">
            <div class="criteria-box">
                <div class="criteria-title"><i class="bi bi-envelope-paper"></i> Regla aplicada al proceso</div>
                <div class="small">
                    Las notificaciones de tipo <strong>rol configurable</strong> se envían a usuarios activos que tengan los roles seleccionados y, además, a correos directos registrados. Las notificaciones dinámicas mantienen al destinatario natural del trámite y los correos configurados funcionan como copia adicional. La notificación de <strong>Servicios Generales</strong> se mantiene sólo por correo configurable.
                </div>
            </div>
        </div>
    </div>

    <div class="stage-panel-card">
        <div class="stage-panel-header">
            <div class="stage-panel-title-wrap">
                <span class="stage-panel-icon is-list"><i class="bi bi-bell"></i></span>
                <div>
                    <div class="stage-panel-kicker">Catálogo del proceso</div>
                    <h2 class="h5 mb-0">Notificaciones configuradas</h2>
                    <div class="stage-panel-help">Se muestran {{ $configuraciones->count() }} registro(s) en esta página y {{ $configuraciones->total() }} en total.</div>
                </div>
            </div>
        </div>

        <div class="stage-panel-body">
            <div class="notifications-list">
                @forelse($configuraciones as $configuracion)
                    @php
                        $roles = \App\Models\CometidoNotificacionConfiguracion::parseRoles($configuracion->roles);
                        $correos = \App\Models\CometidoNotificacionConfiguracion::parseCorreos($configuracion->correos);
                        $tipoLabel = match ($configuracion->tipo_destinatario) {
                            'rol_configurable' => 'Rol configurable',
                            'correo_configurable' => 'Correo configurable',
                            'dinamico_con_copia_configurable' => 'Dinámico + copia',
                            'dinamico_establecimiento_con_copia_configurable' => 'Establecimiento + copia',
                            default => ucfirst(str_replace('_', ' ', (string) $configuracion->tipo_destinatario)),
                        };
                    @endphp
                    <div class="notification-card {{ $configuracion->activo ? '' : 'is-inactive' }}">
                        <div class="notification-main">
                            <div>
                                <div class="notification-title">{{ $configuracion->nombre }}</div>
                                <div class="notification-description">{{ $configuracion->descripcion }}</div>
                                <div class="chip-wrap">
                                    <span class="info-chip"><i class="bi bi-folder2-open"></i> {{ $configuracion->categoria ?: 'Sin categoría' }}</span>
                                    <span class="info-chip is-primary"><i class="bi bi-code-slash"></i> {{ $configuracion->clave }}</span>
                                </div>
                            </div>

                            <div>
                                <div class="notification-label">Tipo</div>
                                <span class="info-chip is-primary"><i class="bi bi-diagram-3"></i> {{ $tipoLabel }}</span>
                                <div class="notification-label mt-3">Estado</div>
                                @if($configuracion->activo)
                                    <span class="info-chip is-success"><i class="bi bi-check-circle"></i> Activo</span>
                                @else
                                    <span class="info-chip"><i class="bi bi-slash-circle"></i> Inactivo</span>
                                @endif
                            </div>

                            <div>
                                <div class="notification-label">Roles destinatarios</div>
                                <div class="chip-wrap">
                                    @forelse($roles as $rol)
                                        <span class="info-chip"><i class="bi bi-person-badge"></i> {{ $rol }}</span>
                                    @empty
                                        <span class="info-chip"><i class="bi bi-dash-circle"></i> No aplica</span>
                                    @endforelse
                                </div>

                                <div class="notification-label mt-3">Correos configurados</div>
                                <div class="chip-wrap">
                                    @forelse($correos as $correo)
                                        <span class="info-chip"><i class="bi bi-envelope"></i> {{ $correo }}</span>
                                    @empty
                                        <span class="info-chip"><i class="bi bi-inbox"></i> Sin correos directos</span>
                                    @endforelse
                                </div>
                            </div>

                            <div class="notification-actions">
                                <div>
                                    <div class="notification-label">Actualización</div>
                                    <span class="info-chip"><i class="bi bi-clock-history"></i> {{ optional($configuracion->updated_at)->format('d-m-Y H:i') ?: 'Sin registro' }}</span>
                                </div>
                                <a href="{{ route('admin.cometidos-notificaciones.edit', $configuracion) }}" class="cometido-btn is-primary">
                                    <i class="bi bi-pencil-square"></i> Editar
                                </a>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-bell-slash"></i></div>
                        <div class="fw-semibold">No existen configuraciones de notificación.</div>
                        <div class="small">Ejecute la migración o sincronización del catálogo de cometidos.</div>
                    </div>
                @endforelse
            </div>
        </div>

        @if($configuraciones->hasPages())
            <div class="card-footer bg-white border-0 px-3 pb-3">
                {{ $configuraciones->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
