@extends('layouts.app')

@section('content')
    @php $u = auth()->user(); $activeRole = $activeRole ?? $u->activeRoleName(); @endphp

    <div class="dashboard dashboard--funcionario">
        <div class="dashboard-hero mb-4">
            <div class="hero-icon"><i class="bi bi-briefcase"></i></div>
            <div>
                <h2 class="hero-title h4 m-0">Panel Funcionario SLEP</h2>
                <p class="hero-subtitle m-0">Accesos para gestión operativa de solicitudes y reemplazos</p>
            </div>
        </div>

        @include('partials.role-switcher', ['user' => $u, 'activeRole' => $activeRole])

        @if ($u->canAnyModule(['gestion.solicitudes-reemplazo', 'gestion.bolsa-trabajo'], $activeRole))
            <div class="dashboard-section mb-4">
                <h3 class="h6 text-uppercase text-muted mb-2">Gestión</h3>
                <div class="dashboard-grid">
                    @if ($u->canModule('gestion.solicitudes-reemplazo', $activeRole) && Route::has('gestion.solicitudes-reemplazo.index'))
                        <a href="{{ route('gestion.solicitudes-reemplazo.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-clipboard2-data tile-icon"></i><div><h3 class="h6 m-0 text-dark">Solicitudes de reemplazo</h3><p class="text-muted small m-0">Solicitudes derivadas y seguimiento</p></div></div></div></a>
                    @endif
                    @if ($u->canModule('gestion.bolsa-trabajo', $activeRole) && Route::has('gestion.bolsa-trabajo.index'))
                        <a href="{{ route('gestion.bolsa-trabajo.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-briefcase tile-icon"></i><div><h3 class="h6 m-0 text-dark">Bolsa de Trabajo</h3><p class="text-muted small m-0">Publicación y edición de ofertas laborales</p></div></div></div></a>
                    @endif
                </div>
            </div>
        @endif

        @if ($u->canAnyModule(['reemplazos', 'incumplimientos'], $activeRole))
            <div class="dashboard-section mb-4">
                <h3 class="h6 text-uppercase text-muted mb-2">Reemplazos</h3>
                <div class="dashboard-grid">
                    @if ($u->canModule('reemplazos', $activeRole) && Route::has('reemplazos.index'))
                        <a href="{{ route('reemplazos.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-person-plus tile-icon"></i><div><h3 class="h6 m-0 text-dark">Reemplazos</h3><p class="text-muted small m-0">Padrón mensual y consultas operativas</p></div></div></div></a>
                    @endif
                    @if ($u->canModule('reemplazos', $activeRole) && Route::has('reemplazos.buscador-postulantes.index'))
                        <a href="{{ route('reemplazos.buscador-postulantes.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-search tile-icon"></i><div><h3 class="h6 m-0 text-dark">Buscador de postulantes</h3><p class="text-muted small m-0">Búsqueda y ficha del postulante</p></div></div></div></a>
                    @endif
                    @if ($u->canModule('incumplimientos', $activeRole) && Route::has('incumplimientos.index'))
                        <a href="{{ route('incumplimientos.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-exclamation-octagon tile-icon"></i><div><h3 class="h6 m-0 text-dark">Incumplimiento laboral</h3><p class="text-muted small m-0">Consulta y seguimiento por fecha de registro</p></div></div></div></a>
                    @endif
                </div>
            </div>
        @endif

        @if ($u->canModule('admin.documents', $activeRole) && Route::has('admin.documents.index'))
            <div class="dashboard-section mb-4">
                <h3 class="h6 text-uppercase text-muted mb-2">Revisión documental</h3>
                <div class="dashboard-grid">
                    <a href="{{ route('admin.documents.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-inboxes tile-icon"></i><div><h3 class="h6 m-0 text-dark">Revisión documental</h3><p class="text-muted small m-0">Revisión y estados de documentos de postulantes</p></div></div></div></a>
                </div>
            </div>
        @endif

        @if (Route::has('tramites.index'))
            <div class="dashboard-section mb-4">
                <h3 class="h6 text-uppercase text-muted mb-2">Trámites</h3>
                <div class="dashboard-grid">
                    <a href="{{ route('tramites.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-folder-check tile-icon"></i><div><h3 class="h6 m-0 text-dark">Revisión de trámites</h3><p class="text-muted small m-0">Bandeja de solicitudes de postulantes y funcionarios</p></div></div></div></a>
                    @if (Route::has('tramites.licencias-medicas.index') && in_array($activeRole, ['admin', 'funcionario_slep', 'coordinador_gdp'], true))
                        <a href="{{ route('tramites.licencias-medicas.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-heart-pulse tile-icon"></i><div><h3 class="h6 m-0 text-dark">Licencias médicas</h3><p class="text-muted small m-0">Ingreso, seguimiento y control COMPIN/SMC</p></div></div></div></a>
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
