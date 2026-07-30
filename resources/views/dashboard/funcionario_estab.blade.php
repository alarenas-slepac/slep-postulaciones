@extends('layouts.app')

@section('content')
    @php $u = auth()->user(); $activeRole = $activeRole ?? $u->activeRoleName(); @endphp

    <div class="dashboard dashboard--funcionario">
        <div class="dashboard-hero mb-4">
            <div class="hero-icon"><i class="bi bi-building"></i></div>
            <div>
                <h2 class="hero-title h4 m-0">Panel Funcionario Establecimiento</h2>
                <p class="hero-subtitle m-0">Accesos del establecimiento para solicitudes y control operacional</p>
            </div>
        </div>

        @include('partials.role-switcher', ['user' => $u, 'activeRole' => $activeRole])

        @if ($u->canAnyModule(['funcionario.solicitudes-reemplazo', 'incumplimientos'], $activeRole))
            <div class="dashboard-section mb-4">
                <h3 class="h6 text-uppercase text-muted mb-2">Establecimiento</h3>
                <div class="dashboard-grid">
                    @if ($u->canModule('funcionario.solicitudes-reemplazo', $activeRole) && Route::has('funcionario.solicitudes-reemplazo.index'))
                        <a href="{{ route('funcionario.solicitudes-reemplazo.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-clipboard-check tile-icon"></i><div><h3 class="h6 m-0 text-dark">Solicitudes de reemplazo</h3><p class="text-muted small m-0">Listado y seguimiento</p></div></div></div></a>
                        <a href="{{ route('funcionario.solicitudes-reemplazo.create') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-plus-circle tile-icon"></i><div><h3 class="h6 m-0 text-dark">Nueva solicitud</h3><p class="text-muted small m-0">Crear solicitud de reemplazo</p></div></div></div></a>
                    @endif
                    @if ($u->canModule('incumplimientos', $activeRole) && Route::has('incumplimientos.index'))
                        <a href="{{ route('incumplimientos.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-exclamation-octagon tile-icon"></i><div><h3 class="h6 m-0 text-dark">Incumplimiento laboral</h3><p class="text-muted small m-0">Registrar y consultar atrasos e inasistencias</p></div></div></div></a>
                    @endif
                    @if ($u->canModule('declaracion', $activeRole) && Route::has('declaracion.index'))
                        <a href="{{ route('declaracion.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-file-earmark-text tile-icon"></i><div><h3 class="h6 m-0 text-dark">Declaración Establecimiento 2026</h3><p class="text-muted small m-0">Consulta y actualización de registros de su establecimiento</p></div></div></div></a>
                    @endif
                </div>
            </div>
        @endif

        @if ($u->canModule('reemplazos', $activeRole) && Route::has('reemplazos.buscador-postulantes.index'))
            <div class="dashboard-section mb-4">
                <h3 class="h6 text-uppercase text-muted mb-2">Consulta de reemplazos</h3>
                <div class="dashboard-grid">
                    <a href="{{ route('reemplazos.buscador-postulantes.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-search tile-icon"></i><div><h3 class="h6 m-0 text-dark">Buscador de postulantes</h3><p class="text-muted small m-0">Búsqueda y ver ficha</p></div></div></div></a>
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
