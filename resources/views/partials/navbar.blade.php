<nav class="navbar bg-white border-bottom">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('home') }}">
            <img src="{{ asset(config('brand.logo_sidebar', 'branding/05_logo_sidebar.png')) }}" alt="{{ config('brand.platform_name', config('app.name')) }}" height="28"
                onerror="this.onerror=null;this.src='{{ asset(config('brand.logo_principal', 'branding/01_logo_principal.png')) }}'">
            <span class="fw-bold brand-wordmark">{{ config('brand.platform_name', config('app.name')) }}</span>
        </a>

        <button class="navbar-toggler d-inline-flex align-items-center gap-2 px-3" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Abrir menú de navegación">
            <span class="navbar-toggler-icon"></span>
            <span class="fw-semibold small">Menú</span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                @auth
                    @php
                        $u = auth()->user();
                        $activeRole = $u->activeRoleName();
                        $availableRoles = $u->availableRoleContexts();
                        $hasRoleSwitcher = $availableRoles->count() > 1;
                        $isAdmin = $activeRole === 'admin';
                        $isGdp = $activeRole === 'coordinador_gdp';
                        $isUatp = $activeRole === 'coordinador_uatp';
                        $isSlep = $activeRole === 'funcionario_slep';
                        $isEstab = $activeRole === 'funcionario_estab';
                        $isPlani = $activeRole === 'supervisor_plani';
                        $isDaf = $activeRole === 'funcionario_daf';
                        $isFuncionarioAc = $activeRole === 'funcionario_ac';
                        $isSecretariaDe = $activeRole === 'secretaria_direccion_ejecutiva';
                        $isPostulante = in_array($activeRole, ['postulante', 'funcionario'], true);
                        $isFuncionarioRegistro = $activeRole === 'funcionario';
                        $isTramiteReviewer = in_array($activeRole, ['admin', 'coordinador_gdp', 'funcionario_slep'], true);
                        $canGestionBolsa = in_array($activeRole, ['admin', 'funcionario_slep'], true) && Route::has('gestion.bolsa-trabajo.index');
                        $canAgendamientoRecursos = in_array($activeRole, ['admin', 'coordinador_gdp', 'funcionario_slep', 'funcionario_ac', 'secretaria_direccion_ejecutiva'], true) && Route::has('gestion.agendamientos-recursos.index');
                        $cargasSolicitanteRoles = (array) config('cargas_familiares.acceso_solicitantes.roles_habilitados', ['funcionario_ac']);
                        $isCargasApplicant = in_array($activeRole, $cargasSolicitanteRoles, true);
                    @endphp

                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>

                    @if ($isPostulante && ($u->canAnyModule(['postulant.profile', 'postulant.documents', 'postulant.reemplazos', 'postulant.ofertas-laborales', 'liquidaciones'], $activeRole) || Route::has('liquidaciones.mis.index')))
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle {{ (request()->routeIs('postulant.*') || request()->routeIs('liquidaciones.mis.*')) ? 'active' : '' }}"
                                href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-badge"></i> {{ $isFuncionarioRegistro ? 'Mi cuenta' : 'Postulación' }}
                            </a>
                            <ul class="dropdown-menu">
                                @if ($u->canModule('postulant.profile', $activeRole))
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('postulant.profile.*') ? 'active' : '' }}"
                                            href="{{ route('postulant.profile.edit') }}">
                                            <i class="bi bi-person-badge"></i> Mi Perfil
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="{{ route('postulant.profile.pdf') }}" target="_blank" rel="noopener">
                                            <i class="bi bi-filetype-pdf"></i> Mi perfil (PDF)
                                        </a>
                                    </li>
                                @endif
                                @if ($u->canModule('postulant.documents', $activeRole))
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('postulant.documents.*') ? 'active' : '' }}"
                                            href="{{ route('postulant.documents.index') }}">
                                            <i class="bi bi-folder2"></i> Mis documentos
                                        </a>
                                    </li>
                                @endif
                                @if ($u->canModule('postulant.reemplazos', $activeRole) && Route::has('postulant.reemplazos.index'))
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('postulant.reemplazos.*') ? 'active' : '' }}"
                                            href="{{ route('postulant.reemplazos.index') }}">
                                            <i class="bi bi-briefcase"></i> Mis Reemplazos
                                        </a>
                                    </li>
                                @endif
                                @if ($u->canModule('postulant.ofertas-laborales', $activeRole) && Route::has('postulant.ofertas-laborales.index'))
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('postulant.ofertas-laborales.*') ? 'active' : '' }}"
                                            href="{{ route('postulant.ofertas-laborales.index') }}">
                                            <i class="bi bi-megaphone"></i> Ofertas laborales
                                        </a>
                                    </li>
                                @endif
                                @if (Route::has('liquidaciones.mis.index'))
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('liquidaciones.mis.*') ? 'active' : '' }}"
                                            href="{{ route('liquidaciones.mis.index') }}">
                                            <i class="bi bi-file-earmark-pdf"></i> Mis liquidaciones
                                        </a>
                                    </li>
                                @endif
                            </ul>
                        </li>
                    @endif

                    @if ($isCargasApplicant && Route::has('tramites.cargas-familiares.index'))
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('tramites.cargas-familiares.*') ? 'active' : '' }}" href="{{ route('tramites.cargas-familiares.index') }}">
                                <i class="bi bi-people"></i> Mis Cargas Familiares
                            </a>
                        </li>
                    @endif

                    @if ($isTramiteReviewer && Route::has('tramites.cargas-familiares.admin.index'))
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle {{ request()->routeIs('tramites.cargas-familiares.*') ? 'active' : '' }}" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-people-fill"></i> Cargas Familiares
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ route('tramites.cargas-familiares.admin.index') }}"><i class="bi bi-clipboard-data"></i> Administracion</a></li>
                                @if (Route::has('tramites.cargas-familiares.review.index'))
                                    <li><a class="dropdown-item" href="{{ route('tramites.cargas-familiares.review.index') }}"><i class="bi bi-check2-square"></i> Revision de solicitudes</a></li>
                                @endif
                                @if (in_array($activeRole, ['admin', 'funcionario_slep'], true) && Route::has('tramites.cargas-familiares.import'))
                                    <li><a class="dropdown-item" href="{{ route('tramites.cargas-familiares.import') }}"><i class="bi bi-upload"></i> Carga masiva</a></li>
                                @endif
                                @if (Route::has('tramites.cargas-familiares.admin.funcionarios-ac.import'))
                                    <li><a class="dropdown-item" href="{{ route('tramites.cargas-familiares.admin.funcionarios-ac.import') }}"><i class="bi bi-person-badge"></i> Funcionarios AC</a></li>
                                @endif
                            </ul>
                        </li>
                    @endif


                    @if (in_array($activeRole, ['funcionario_estab', 'coordinador_uatp', 'admin', 'funcionario_slep', 'coordinador_gdp', 'supervisor_plani', 'coordinador_plani', 'funcionario_daf', 'funcionario_juridica'], true) && Route::has('tramites.cometidos-funcionarios.index'))
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('tramites.cometidos-funcionarios.*') ? 'active' : '' }}" href="{{ route('tramites.cometidos-funcionarios.index') }}">
                                <i class="bi bi-file-earmark-person"></i> Cometidos funcionarios
                            </a>
                        </li>
                    @endif

                    @if ((( $isPostulante && $u->canModule('tramites', $activeRole)) || $isTramiteReviewer) && Route::has('tramites.index'))
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('tramites.*') && !request()->routeIs('tramites.cargas-familiares.*') ? 'active' : '' }}" href="{{ route('tramites.index') }}">
                                <i class="bi bi-folder-check"></i> Tramites
                            </a>
                        </li>
                    @endif

                    @if (($isAdmin || $isGdp || $isUatp || $isSlep || $isPlani || $isFuncionarioAc || $isSecretariaDe) &&
                            ($u->canAnyModule(['gestion.solicitudes-reemplazo', 'gestion.estadisticas', 'gestion.informes', 'gestion.bolsa-trabajo'], $activeRole) || $canGestionBolsa || $canAgendamientoRecursos))
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle {{ request()->routeIs('gestion.solicitudes-reemplazo.*') || request()->routeIs('gestion.estadisticas.*') || request()->routeIs('gestion.informes.*') || request()->routeIs('gestion.bolsa-trabajo.*') || request()->routeIs('gestion.agendamientos-recursos.*') ? 'active' : '' }}"
                                href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-clipboard-data"></i> Gestión
                            </a>
                            <ul class="dropdown-menu">
                                @if ($u->canModule('gestion.solicitudes-reemplazo', $activeRole) && Route::has('gestion.solicitudes-reemplazo.index'))
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('gestion.solicitudes-reemplazo.*') ? 'active' : '' }}"
                                            href="{{ route('gestion.solicitudes-reemplazo.index') }}">
                                            <i class="bi bi-clipboard2-data"></i> Solicitudes de reemplazo
                                        </a>
                                    </li>
                                @endif
                                @if ($u->canModule('gestion.estadisticas', $activeRole) && Route::has('gestion.estadisticas.index'))
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('gestion.estadisticas.*') ? 'active' : '' }}"
                                            href="{{ route('gestion.estadisticas.index') }}">
                                            <i class="bi bi-bar-chart-line"></i> Estadísticas
                                        </a>
                                    </li>
                                @endif
                                @if ($u->canModule('gestion.informes', $activeRole) && Route::has('gestion.informes.index'))
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('gestion.informes.*') ? 'active' : '' }}"
                                            href="{{ route('gestion.informes.index') }}">
                                            <i class="bi bi-file-earmark-spreadsheet"></i> Informes
                                        </a>
                                    </li>
                                @endif
                                @if ($canAgendamientoRecursos)
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('gestion.agendamientos-recursos.*') ? 'active' : '' }}"
                                            href="{{ route('gestion.agendamientos-recursos.index') }}">
                                            <i class="bi bi-calendar-event"></i> Agenda Proyector y Salas
                                        </a>
                                    </li>
                                @endif
                                @if (($canGestionBolsa || $u->canModule('gestion.bolsa-trabajo', $activeRole)) && Route::has('gestion.bolsa-trabajo.index'))
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('gestion.bolsa-trabajo.*') ? 'active' : '' }}"
                                            href="{{ route('gestion.bolsa-trabajo.index') }}">
                                            <i class="bi bi-briefcase"></i> Bolsa de Trabajo
                                        </a>
                                    </li>
                                @endif
                                @if ($isAdmin && $u->canModule('declaracion', $activeRole) && Route::has('declaracion.index'))
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('declaracion.*') ? 'active' : '' }}" href="{{ route('declaracion.index') }}">
                                            <i class="bi bi-file-earmark-text"></i> Declaración de sostenedores
                                        </a>
                                    </li>
                                @endif
                            </ul>
                        </li>
                    @endif



                    @php
                        $canLiquidacionesAdmin = ($isAdmin || $isSlep) && Route::has('liquidaciones.cargas.index');
                        $canEndeudamientoAdmin = ($isAdmin || $isSlep) && Route::has('endeudamiento.cargas.index');
                    @endphp

                    @if ($canLiquidacionesAdmin || $canEndeudamientoAdmin)
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle {{ request()->routeIs('liquidaciones.cargas.*') || request()->routeIs('endeudamiento.*') ? 'active' : '' }}" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-cash-stack"></i> Remuneraciones
                            </a>
                            <ul class="dropdown-menu">
                                @if ($canLiquidacionesAdmin)
                                    <li><h6 class="dropdown-header">Liquidaciones</h6></li>
                                    <li><a class="dropdown-item {{ request()->routeIs('liquidaciones.cargas.index') ? 'active' : '' }}" href="{{ route('liquidaciones.cargas.index') }}"><i class="bi bi-list-ul"></i> Cargas de liquidaciones</a></li>
                                    <li><a class="dropdown-item {{ request()->routeIs('liquidaciones.cargas.create') ? 'active' : '' }}" href="{{ route('liquidaciones.cargas.create') }}"><i class="bi bi-upload"></i> Nueva carga</a></li>
                                @endif

                                @if ($canLiquidacionesAdmin && $canEndeudamientoAdmin)
                                    <li><hr class="dropdown-divider"></li>
                                @endif

                                @if ($canEndeudamientoAdmin)
                                    <li><h6 class="dropdown-header">Endeudamiento</h6></li>
                                    <li><a class="dropdown-item {{ request()->routeIs('endeudamiento.cargas.*') ? 'active' : '' }}" href="{{ route('endeudamiento.cargas.index') }}"><i class="bi bi-upload"></i> Cargas MAE</a></li>
                                    <li><a class="dropdown-item {{ request()->routeIs('endeudamiento.registros.*') ? 'active' : '' }}" href="{{ route('endeudamiento.registros.index') }}"><i class="bi bi-table"></i> Registros</a></li>
                                    <li><a class="dropdown-item {{ request()->routeIs('endeudamiento.topes.*') ? 'active' : '' }}" href="{{ route('endeudamiento.topes.index') }}"><i class="bi bi-calculator"></i> Cálculo de topes</a></li>
                                    <li><a class="dropdown-item {{ request()->routeIs('endeudamiento.normativa.*') ? 'active' : '' }}" href="{{ route('endeudamiento.normativa.index') }}"><i class="bi bi-sliders"></i> Topes normativos</a></li>
                                @endif
                            </ul>
                        </li>
                    @endif

                    @if (($isAdmin || $isGdp || $isUatp || $isSlep || $isEstab) &&
                            ($u->canAnyModule(['reemplazos', 'reemplazos.personal', 'incumplimientos'], $activeRole) || $isUatp))
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle {{ request()->routeIs('reemplazos.*') || request()->routeIs('incumplimientos.*') ? 'active' : '' }}"
                                href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-people"></i> Reemplazos
                            </a>
                            <ul class="dropdown-menu">
                                @if ($u->canModule('reemplazos', $activeRole) && Route::has('reemplazos.index'))
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('reemplazos.index') ? 'active' : '' }}"
                                            href="{{ route('reemplazos.index') }}">
                                            <i class="bi bi-person-plus"></i> Gestión de reemplazos
                                        </a>
                                    </li>
                                @endif
                                @if (($u->canModule('reemplazos', $activeRole) || $isUatp) && Route::has('reemplazos.buscador-postulantes.index'))
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('reemplazos.buscador-postulantes.*') ? 'active' : '' }}"
                                            href="{{ route('reemplazos.buscador-postulantes.index') }}">
                                            <i class="bi bi-search"></i> Buscador de postulantes
                                        </a>
                                    </li>
                                @endif
                                @if ($isAdmin && $u->canModule('reemplazos.personal', $activeRole) && Route::has('reemplazos.personal.import'))
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('reemplazos.personal.*') ? 'active' : '' }}"
                                            href="{{ route('reemplazos.personal.import') }}">
                                            <i class="bi bi-upload"></i> Carga masiva personal
                                        </a>
                                    </li>
                                @endif
                                @if (($isAdmin || $isSlep || $isEstab) && $u->canModule('incumplimientos', $activeRole) && Route::has('incumplimientos.index'))
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('incumplimientos.*') ? 'active' : '' }}"
                                            href="{{ route('incumplimientos.index') }}">
                                            <i class="bi bi-exclamation-octagon"></i> Incumplimiento laboral
                                        </a>
                                    </li>
                                @endif
                            </ul>
                        </li>
                    @endif

                    @if ($isEstab && $u->canModule('funcionario.solicitudes-reemplazo', $activeRole) && Route::has('funcionario.solicitudes-reemplazo.index'))
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle {{ request()->routeIs('funcionario.solicitudes-reemplazo.*') ? 'active' : '' }}"
                                href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-building"></i> Establecimiento
                            </a>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item {{ request()->routeIs('funcionario.solicitudes-reemplazo.index') ? 'active' : '' }}"
                                        href="{{ route('funcionario.solicitudes-reemplazo.index') }}">
                                        <i class="bi bi-clipboard-check"></i> Solicitudes de reemplazo
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item {{ request()->routeIs('funcionario.solicitudes-reemplazo.create') ? 'active' : '' }}"
                                        href="{{ route('funcionario.solicitudes-reemplazo.create') }}">
                                        <i class="bi bi-plus-circle"></i> Nueva solicitud
                                    </a>
                                </li>
                                @if ($u->canModule('declaracion', $activeRole) && Route::has('declaracion.index'))
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('declaracion.*') ? 'active' : '' }}" href="{{ route('declaracion.index') }}">
                                            <i class="bi bi-file-earmark-text"></i> Declaración Establecimiento 2026
                                        </a>
                                    </li>
                                @endif
                            </ul>
                        </li>
                    @endif

                    @if (in_array($activeRole, ['admin', 'coordinador_gdp', 'funcionario_daf', 'supervisor_plani', 'coordinador_plani'], true) && ($activeRole === 'admin' || $u->canAnyModule(['admin.establecimientos', 'admin.areas-desempeno', 'admin.aaee-valores-hora', 'admin.viaticos-reembolsos', 'admin.funcionarios-viatico-anexo', 'admin.menciones', 'admin.subsectores', 'declaracion'], $activeRole)))
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle {{ request()->routeIs('admin.establecimientos.*') || request()->routeIs('admin.areas-desempeno.*') || request()->routeIs('admin.menciones.*') || request()->routeIs('admin.subsectores.*') || request()->routeIs('admin.aaee-valores-hora.*') || request()->routeIs('admin.viaticos-reembolsos.*') || request()->routeIs('admin.funcionarios-viatico-anexo.*') || request()->routeIs('admin.titulos-catalogo.*') || request()->routeIs('admin.funciones-catalogo.*') || request()->routeIs('admin.instituciones-catalogo.*') ? 'active' : '' }}"
                                href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-collection"></i> Catálogos
                            </a>
                            <ul class="dropdown-menu">
                                @if ($u->canModule('admin.establecimientos', $activeRole))
                                    <li><a class="dropdown-item" href="{{ route('admin.establecimientos.index') }}"><i class="bi bi-buildings"></i> Establecimientos</a></li>
                                @endif
                                @if ($u->canModule('admin.areas-desempeno', $activeRole))
                                    <li><a class="dropdown-item" href="{{ route('admin.areas-desempeno.index') }}"><i class="bi bi-list-check"></i> Áreas de desempeño</a></li>
                                @endif
                                @if ($activeRole === 'admin' || ($u->canModule('admin.aaee-valores-hora', $activeRole) && Route::has('admin.aaee-valores-hora.index')))
                                    <li><a class="dropdown-item" href="{{ Route::has('admin.aaee-valores-hora.index') ? route('admin.aaee-valores-hora.index') : url('admin/aaee-valores-hora') }}"><i class="bi bi-currency-dollar"></i> Valores hora AAEE</a></li>
                                @endif
                                @if ($u->canModule('admin.viaticos-reembolsos', $activeRole) && Route::has('admin.viaticos-reembolsos.index'))
                                    <li><a class="dropdown-item" href="{{ route('admin.viaticos-reembolsos.index') }}"><i class="bi bi-cash-coin"></i> Viáticos y Reembolsos</a></li>
                                @endif
                                @if (in_array($activeRole, ['admin', 'supervisor_plani', 'coordinador_plani'], true) && Route::has('admin.funcionarios-viatico-anexo.index'))
                                    <li><a class="dropdown-item" href="{{ route('admin.funcionarios-viatico-anexo.index') }}"><i class="bi bi-person-check"></i> Funcionarios viático por anexo</a></li>
                                @endif
                                @if ($u->canModule('admin.menciones', $activeRole))
                                    <li><a class="dropdown-item" href="{{ route('admin.menciones.index') }}"><i class="bi bi-award"></i> Menciones</a></li>
                                @endif
                                @if ($u->canModule('declaracion', $activeRole) && Route::has('admin.titulos-catalogo.index'))
                                    <li><a class="dropdown-item" href="{{ route('admin.titulos-catalogo.index') }}"><i class="bi bi-journal-text"></i> Títulos catálogo</a></li>
                                @endif
                                @if ($u->canModule('declaracion', $activeRole) && Route::has('admin.funciones-catalogo.index'))
                                    <li><a class="dropdown-item" href="{{ route('admin.funciones-catalogo.index') }}"><i class="bi bi-diagram-2"></i> Funciones catálogo</a></li>
                                @endif
                                @if ($u->canModule('declaracion', $activeRole) && Route::has('admin.instituciones-catalogo.index'))
                                    <li><a class="dropdown-item" href="{{ route('admin.instituciones-catalogo.index') }}"><i class="bi bi-building"></i> Instituciones catálogo</a></li>
                                @endif
                                @if ($u->canModule('admin.subsectores', $activeRole))
                                    <li><a class="dropdown-item" href="{{ route('admin.subsectores.index') }}"><i class="bi bi-diagram-3"></i> Subsectores</a></li>
                                @endif
                            </ul>
                        </li>
                    @endif

                    @if (in_array($activeRole, ['admin', 'funcionario_slep', 'coordinador_gdp'], true) && $u->canModule('admin.documents', $activeRole) && Route::has('admin.documents.index'))
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.documents.*') ? 'active' : '' }}"
                                href="{{ route('admin.documents.index') }}">
                                <i class="bi bi-inboxes"></i> Revisión documental
                            </a>
                        </li>
                    @endif

                    @if ($isAdmin && ($u->canAnyModule(['admin.users', 'admin.roles', 'admin.restricted-ruts', 'admin.notification-logs'], $activeRole) || Route::has('admin.postulaciones.index')))
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle {{ request()->routeIs('admin.users.*') || request()->routeIs('admin.roles.*') || request()->routeIs('admin.restricted-ruts.*') || request()->routeIs('admin.notification-logs.*') || request()->routeIs('admin.postulaciones.*') ? 'active' : '' }}"
                                href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-shield-lock"></i> Administración
                            </a>
                            <ul class="dropdown-menu">
                                @if (Route::has('admin.postulaciones.index'))
                                    <li><a class="dropdown-item" href="{{ route('admin.postulaciones.index') }}"><i class="bi bi-person-vcard"></i> Postulaciones</a></li>
                                @endif
                                @if ($u->canModule('admin.users', $activeRole) && Route::has('admin.users.index'))
                                    <li><a class="dropdown-item" href="{{ route('admin.users.index') }}"><i class="bi bi-people"></i> Usuarios</a></li>
                                @endif
                                @if ($u->canModule('admin.roles', $activeRole) && Route::has('admin.roles.index'))
                                    <li><a class="dropdown-item" href="{{ route('admin.roles.index') }}"><i class="bi bi-key"></i> Roles (módulos)</a></li>
                                @endif
                                @if ($u->canModule('admin.restricted-ruts', $activeRole) && Route::has('admin.restricted-ruts.index'))
                                    <li><a class="dropdown-item" href="{{ route('admin.restricted-ruts.index') }}"><i class="bi bi-shield-exclamation"></i> Restricciones para ejercer</a></li>
                                @endif
                                @if ($u->canModule('admin.notification-logs', $activeRole) && Route::has('admin.notification-logs.index'))
                                    <li><a class="dropdown-item" href="{{ route('admin.notification-logs.index') }}"><i class="bi bi-envelope-check"></i> Historial de notificaciones</a></li>
                                @endif
                            </ul>
                        </li>
                    @endif

                    @if ($u->canModule('messages', $activeRole))
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('messages.*') ? 'active' : '' }}" href="{{ route('messages.index') }}">
                                <i class="bi bi-chat-dots"></i> Mensajes
                            </a>
                        </li>
                    @endif
                @endauth
            </ul>

            <ul class="navbar-nav ms-auto">
                @guest
                    <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Ingresar</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('register') }}">Registrarme</a></li>
                @endguest

                @auth
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown">
                            <span class="rounded-circle bg-secondary-subtle d-inline-flex align-items-center justify-content-center" style="width:28px;height:28px;">
                                {{ strtoupper(substr(auth()->user()->nombres ?? auth()->user()->email, 0, 1)) }}
                            </span>
                            <span class="d-none d-sm-inline">{{ auth()->user()->nombres ?? auth()->user()->email }}</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li class="dropdown-item-text small text-muted">
                                Rol activo: {{ $u->roleContextLabel($activeRole) }}
                            </li>
                            <li class="dropdown-item-text small text-muted pt-0">
                                Roles asignados:
                                @if (method_exists($u, 'getRoleNames'))
                                    {{ $u->getRoleNames()->map(fn($role) => $u->roleContextLabel($role))->implode(', ') }}
                                @else
                                    —
                                @endif
                            </li>
                            <li><hr class="dropdown-divider"></li>

                            @include('partials.role-switcher', ['user' => $u, 'activeRole' => $activeRole, 'variant' => 'navbar'])

                            @if (auth()->user()->hasVerifiedEmail() === false && Route::has('verification.notice'))
                                <li><a class="dropdown-item text-warning" href="{{ route('verification.notice') }}">Verificar correo</a></li>
                            @endif

                            <li>
                                <form method="POST" action="{{ route('logout') }}" class="m-0">
                                    @csrf
                                    <button type="submit" class="dropdown-item">Cerrar sesión</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                @endauth
            </ul>
        </div>
    </div>
</nav>
