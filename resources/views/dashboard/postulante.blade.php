@extends('layouts.app')

@section('content')
    @php
        $u = auth()->user();
        $activeRole = $activeRole ?? $u->activeRoleName();
        $accountRole = $accountRole ?? ($activeRole === 'funcionario' ? 'funcionario' : 'postulante');
        $isFuncionarioAccount = $accountRole === 'funcionario';

        $percent = isset($check['percent']) ? (int) $check['percent'] : 0;
        $perfilOk = $percent >= 100 || (!empty($check['ok']) && $check['ok'] === true);
        $est = optional($user->postulantProfile)->estamento;

        $docsHref = $perfilOk ? route('postulant.documents.index') : route('postulant.profile.edit');
        $docsHelp = $perfilOk
            ? 'Requeridos para ' . ($est ?: 'tu perfil') . '.'
            : 'Completa tu perfil para habilitar documentos.';

        $heroSubtitle = $isFuncionarioAccount
            ? 'Bienvenido(a) a tu panel personal'
            : 'Bienvenido(a) a tu panel de postulante';

        $sectionTitle = $isFuncionarioAccount ? 'Mi cuenta' : 'Postulación';
    @endphp

    <div class="dashboard dashboard--postulante">
        <div class="dashboard-hero mb-4">
            <div class="hero-icon"><i class="bi bi-person-badge"></i></div>
            <div>
                <h2 class="hero-title h4 m-0">Hola, {{ $user->display_name }}</h2>
                <p class="hero-subtitle m-0">{{ $heroSubtitle }}</p>
            </div>
        </div>

        @include('partials.role-switcher', ['user' => $u, 'activeRole' => $activeRole])

        <div class="row g-3 mb-3">
            <div class="col-12 col-md-4">
                <div class="stat-card">
                    <div class="stat-label">Avance de perfil</div>
                    @if (isset($check['percent']))
                        <div class="progress" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0"
                            aria-valuemax="100">
                            <div class="progress-bar" style="width: {{ $percent }}%">{{ $percent }}%</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if (($u->canAnyModule(['postulant.profile', 'postulant.documents', 'postulant.reemplazos', 'postulant.ofertas-laborales', 'liquidaciones'], $activeRole) || Route::has('liquidaciones.mis.index')))
            <div class="dashboard-section mb-4">
                <h3 class="h6 text-uppercase text-muted mb-2">{{ $sectionTitle }}</h3>
                <div class="dashboard-grid">
                    @if ($u->canModule('postulant.profile', $activeRole))
                        <a href="{{ route('postulant.profile.edit') }}" class="text-decoration-none">
                            <div class="tile tile-role">
                                <div class="d-flex align-items-center gap-3">
                                    <i class="bi bi-person-badge tile-icon"></i>
                                    <div>
                                        <h3 class="h6 m-0 text-dark">Mi Perfil</h3>
                                        <p class="text-muted small m-0">Completa/actualiza tus datos</p>
                                    </div>
                                </div>
                            </div>
                        </a>

                        <a href="{{ route('postulant.profile.pdf') }}" class="text-decoration-none" target="_blank"
                            rel="noopener">
                            <div class="tile tile-role">
                                <div class="d-flex align-items-center gap-3">
                                    <i class="bi bi-filetype-pdf tile-icon"></i>
                                    <div>
                                        <h3 class="h6 m-0 text-dark">Mi perfil (PDF)</h3>
                                        <p class="text-muted small m-0">Descargar/visualizar</p>
                                    </div>
                                </div>
                            </div>
                        </a>
                    @endif

                    @if ($u->canModule('postulant.documents', $activeRole))
                        <a href="{{ $docsHref }}" class="text-decoration-none">
                            <div class="tile tile-role">
                                <div class="d-flex align-items-center gap-3">
                                    <i class="bi bi-folder2 tile-icon"></i>
                                    <div>
                                        <h3 class="h6 m-0 text-dark">Mis documentos</h3>
                                        <p class="text-muted small m-0">{{ $docsHelp }}</p>
                                    </div>
                                </div>
                            </div>
                        </a>
                    @endif

                    @if ($u->canModule('postulant.reemplazos', $activeRole) && Route::has('postulant.reemplazos.index'))
                        <a href="{{ route('postulant.reemplazos.index') }}" class="text-decoration-none">
                            <div class="tile tile-role">
                                <div class="d-flex align-items-center gap-3">
                                    <i class="bi bi-briefcase tile-icon"></i>
                                    <div>
                                        <h3 class="h6 m-0 text-dark">Mis Reemplazos</h3>
                                        <p class="text-muted small m-0">Activos, futuros e historial</p>
                                    </div>
                                </div>
                            </div>
                        </a>
                    @endif

                    @if ($u->canModule('postulant.ofertas-laborales', $activeRole) && Route::has('postulant.ofertas-laborales.index'))
                        <a href="{{ route('postulant.ofertas-laborales.index') }}" class="text-decoration-none">
                            <div class="tile tile-role">
                                <div class="d-flex align-items-center gap-3">
                                    <i class="bi bi-megaphone tile-icon"></i>
                                    <div>
                                        <h3 class="h6 m-0 text-dark">Ofertas Laborales</h3>
                                        <p class="text-muted small m-0">Consulta y postulación a ofertas vigentes</p>
                                    </div>
                                </div>
                            </div>
                        </a>
                    @endif



                    @if (Route::has('liquidaciones.mis.index'))
                        <a href="{{ route('liquidaciones.mis.index') }}" class="text-decoration-none">
                            <div class="tile tile-role">
                                <div class="d-flex align-items-center gap-3">
                                    <i class="bi bi-file-earmark-pdf tile-icon"></i>
                                    <div>
                                        <h3 class="h6 m-0 text-dark">Mis liquidaciones</h3>
                                        <p class="text-muted small m-0">Consulta y descarga tus liquidaciones publicadas</p>
                                    </div>
                                </div>
                            </div>
                        </a>
                    @endif
                </div>

                @if (!$perfilOk)
                    <div class="alert alert-warning mt-3" role="alert">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-exclamation-triangle"></i>
                            <div>
                                <strong>Perfil incompleto:</strong> completa los campos pendientes en
                                <a href="{{ route('postulant.profile.edit') }}">Mi Perfil</a>.
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif


        @if ($u->canModule('tramites', $activeRole) && Route::has('tramites.index'))
            <div class="dashboard-section mb-4">
                <h3 class="h6 text-uppercase text-muted mb-2">Trámites</h3>
                <div class="dashboard-grid">
                    <a href="{{ route('tramites.index') }}" class="text-decoration-none">
                        <div class="tile tile-role">
                            <div class="d-flex align-items-center gap-3">
                                <i class="bi bi-folder-check tile-icon"></i>
                                <div>
                                    <h3 class="h6 m-0 text-dark">Mis trámites</h3>
                                    <p class="text-muted small m-0">Revisa el estado e historial de tus solicitudes</p>
                                </div>
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('tramites.create') }}" class="text-decoration-none">
                        <div class="tile tile-role">
                            <div class="d-flex align-items-center gap-3">
                                <i class="bi bi-file-earmark-plus tile-icon"></i>
                                <div>
                                    <h3 class="h6 m-0 text-dark">Nuevo trámite</h3>
                                    <p class="text-muted small m-0">Inicia una nueva solicitud de trámite</p>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        @endif

        @if ($u->canModule('messages', $activeRole))
            <div class="dashboard-section mb-4">
                <h3 class="h6 text-uppercase text-muted mb-2">Comunicación</h3>
                <div class="dashboard-grid">
                    <a href="{{ route('messages.index') }}" class="text-decoration-none">
                        <div class="tile tile-role">
                            <div class="d-flex align-items-center gap-3">
                                <i class="bi bi-chat-dots tile-icon"></i>
                                <div>
                                    <h3 class="h6 m-0 text-dark">Mensajes</h3>
                                    <p class="text-muted small m-0">Revisa tu bandeja</p>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        @endif
    </div>
@endsection
