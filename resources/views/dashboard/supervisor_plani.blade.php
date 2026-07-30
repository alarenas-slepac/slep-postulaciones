@extends('layouts.app')

@section('content')
    @php $u = auth()->user(); $activeRole = $activeRole ?? $u->activeRoleName(); @endphp

    <div class="dashboard dashboard--coordinador">
        <div class="dashboard-hero mb-4">
            <div class="hero-icon"><i class="bi bi-clipboard2-pulse"></i></div>
            <div>
                <h2 class="hero-title h4 m-0">Panel Supervisor Planificación</h2>
                <p class="hero-subtitle m-0">Accesos para validación técnica y seguimiento del flujo de solicitudes</p>
            </div>
        </div>

        @include('partials.role-switcher', ['user' => $u, 'activeRole' => $activeRole])

        @if ($u->canModule('gestion.solicitudes-reemplazo', $activeRole) && Route::has('gestion.solicitudes-reemplazo.index'))
            <div class="dashboard-section mb-4">
                <h3 class="h6 text-uppercase text-muted mb-2">Gestión</h3>
                <div class="dashboard-grid">
                    <a href="{{ route('gestion.solicitudes-reemplazo.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-clipboard2-data tile-icon"></i><div><h3 class="h6 m-0 text-dark">Solicitudes de reemplazo</h3><p class="text-muted small m-0">Pendientes UATP y validación Planificación</p></div></div></div></a>
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
