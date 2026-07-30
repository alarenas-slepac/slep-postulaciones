@extends('layouts.app')

@section('content')
    @php
        $u = auth()->user();
        $activeRole = $activeRole ?? $u->activeRoleName();
        $cargasHabilitadas = in_array($activeRole, (array) config('cargas_familiares.acceso_solicitantes.roles_habilitados', ['funcionario_ac']), true);
    @endphp

    <div class="dashboard dashboard--funcionario-ac">
        <div class="dashboard-hero mb-4">
            <div class="hero-icon"><i class="bi bi-person-badge"></i></div>
            <div>
                <h2 class="hero-title h4 m-0">Panel Funcionario Administracion Central</h2>
                <p class="hero-subtitle m-0">Acceso personal para acreditar y consultar cargas familiares.</p>
            </div>
        </div>

        @include('partials.role-switcher', ['user' => $u, 'activeRole' => $activeRole])

        @if ($cargasHabilitadas && Route::has('tramites.cargas-familiares.index'))
            <div class="dashboard-section mb-4">
                <h3 class="h6 text-uppercase text-muted mb-2">Cargas familiares</h3>
                <div class="dashboard-grid">
                    <a href="{{ route('tramites.cargas-familiares.index') }}" class="text-decoration-none">
                        <div class="tile tile-role">
                            <div class="d-flex align-items-center gap-3">
                                <i class="bi bi-people tile-icon"></i>
                                <div>
                                    <h3 class="h6 m-0 text-dark">Mis Cargas Familiares</h3>
                                    <p class="text-muted small m-0">Ver cargas vigentes, nuevas acreditaciones y estado de solicitudes.</p>
                                </div>
                            </div>
                        </div>
                    </a>

                    @if (Route::has('tramites.cargas-familiares.create'))
                        <a href="{{ route('tramites.cargas-familiares.create') }}" class="text-decoration-none">
                            <div class="tile tile-role">
                                <div class="d-flex align-items-center gap-3">
                                    <i class="bi bi-file-earmark-plus tile-icon"></i>
                                    <div>
                                        <h3 class="h6 m-0 text-dark">Nueva acreditacion</h3>
                                        <p class="text-muted small m-0">Ingresar nuevo causante o actualizar una carga familiar.</p>
                                    </div>
                                </div>
                            </div>
                        </a>
                    @endif
                </div>
            </div>
        @else
            <div class="alert alert-warning">
                {{ config('cargas_familiares.acceso_solicitantes.mensaje_bloqueo', 'El acceso a Mis Cargas Familiares se encuentra temporalmente bloqueado.') }}
            </div>
        @endif
    </div>
@endsection
