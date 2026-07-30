@extends('layouts.app')

@section('content')
    @php $u = auth()->user(); $activeRole = $activeRole ?? $u->activeRoleName(); @endphp

    <div class="dashboard dashboard--coordinador">
        <div class="dashboard-hero mb-4">
            <div class="hero-icon"><i class="bi bi-diagram-3"></i></div>
            <div>
                <h2 class="hero-title h4 m-0">Panel Coordinador UATP</h2>
                <p class="hero-subtitle m-0">Accesos para revisión, validación y consulta de informes</p>
            </div>
        </div>

        @include('partials.role-switcher', ['user' => $u, 'activeRole' => $activeRole])

        @if ($u->canAnyModule(['gestion.solicitudes-reemplazo', 'gestion.estadisticas', 'gestion.informes'], $activeRole))
            <div class="dashboard-section mb-4">
                <h3 class="h6 text-uppercase text-muted mb-2">Gestión</h3>
                <div class="dashboard-grid">
                    @if ($u->canModule('gestion.solicitudes-reemplazo', $activeRole) && Route::has('gestion.solicitudes-reemplazo.index'))
                        <a href="{{ route('gestion.solicitudes-reemplazo.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-clipboard2-data tile-icon"></i><div><h3 class="h6 m-0 text-dark">Solicitudes de reemplazo</h3><p class="text-muted small m-0">Revisión y validación UATP</p></div></div></div></a>
                    @endif
                    @if ($u->canModule('gestion.estadisticas', $activeRole) && Route::has('gestion.estadisticas.index'))
                        <a href="{{ route('gestion.estadisticas.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-bar-chart-line tile-icon"></i><div><h3 class="h6 m-0 text-dark">Estadísticas</h3><p class="text-muted small m-0">Resumen global de solicitudes</p></div></div></div></a>
                    @endif
                    @if ($u->canModule('gestion.informes', $activeRole) && Route::has('gestion.informes.index'))
                        <a href="{{ route('gestion.informes.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-file-earmark-spreadsheet tile-icon"></i><div><h3 class="h6 m-0 text-dark">Informes</h3><p class="text-muted small m-0">Exportación de planillas e informes</p></div></div></div></a>
                    @endif
                </div>
            </div>
        @endif

        @if (($u->canModule('reemplazos', $activeRole) && Route::has('reemplazos.index')) || Route::has('reemplazos.buscador-postulantes.index'))
            <div class="dashboard-section mb-4">
                <h3 class="h6 text-uppercase text-muted mb-2">Reemplazos</h3>
                <div class="dashboard-grid">
                    @if ($u->canModule('reemplazos', $activeRole) && Route::has('reemplazos.index'))
                        <a href="{{ route('reemplazos.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-person-plus tile-icon"></i><div><h3 class="h6 m-0 text-dark">Reemplazos</h3><p class="text-muted small m-0">Consulta del padrón mensual</p></div></div></div></a>
                    @endif
                    @if (Route::has('reemplazos.buscador-postulantes.index'))
                        <a href="{{ route('reemplazos.buscador-postulantes.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-search tile-icon"></i><div><h3 class="h6 m-0 text-dark">Buscador de postulantes</h3><p class="text-muted small m-0">Búsqueda y ficha</p></div></div></div></a>
                    @endif
                </div>
            </div>
        @endif

        @if ($u->canModule('messages', $activeRole))
            <div class="dashboard-section mb-4">
                <h3 class="h6 text-uppercase text-muted mb-2">Comunicación</h3>
                <div class="dashboard-grid">
                    <a href="{{ route('messages.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-chat-dots tile-icon"></i><div><h3 class="h6 m-0 text-dark">Mensajes</h3><p class="text-muted small m-0">Comunicación interna</p></div></div></div></a>
                </div>
            </div>
        @endif
    </div>
@endsection
