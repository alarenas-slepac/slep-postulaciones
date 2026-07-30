@extends('layouts.app')

@section('content')
    @php $u = auth()->user(); $activeRole = $activeRole ?? $u->activeRoleName(); @endphp

    <div class="dashboard dashboard--admin">
        <div class="dashboard-hero mb-4">
            <div class="hero-icon"><i class="bi bi-shield-lock"></i></div>
            <div>
                <h2 class="hero-title h4 m-0">Panel de Administración</h2>
                <p class="hero-subtitle m-0">Accesos consolidados para operación, administración y soporte del sistema</p>
            </div>
        </div>

        @include('partials.role-switcher', ['user' => $u, 'activeRole' => $activeRole])

        @if ($u->canAnyModule(['gestion.solicitudes-reemplazo', 'gestion.estadisticas', 'gestion.informes', 'gestion.bolsa-trabajo'], $activeRole))
            <div class="dashboard-section mb-4">
                <h3 class="h6 text-uppercase text-muted mb-2">Gestión</h3>
                <div class="dashboard-grid">
                    @if ($u->canModule('gestion.solicitudes-reemplazo', $activeRole) && Route::has('gestion.solicitudes-reemplazo.index'))
                        <a href="{{ route('gestion.solicitudes-reemplazo.index') }}" class="text-decoration-none">
                            <div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-clipboard2-data tile-icon"></i><div><h3 class="h6 m-0 text-dark">Solicitudes de reemplazo</h3><p class="text-muted small m-0">Bandeja operativa y seguimiento</p></div></div></div>
                        </a>
                    @endif
                    @if ($u->canModule('gestion.estadisticas', $activeRole) && Route::has('gestion.estadisticas.index'))
                        <a href="{{ route('gestion.estadisticas.index') }}" class="text-decoration-none">
                            <div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-bar-chart-line tile-icon"></i><div><h3 class="h6 m-0 text-dark">Estadísticas</h3><p class="text-muted small m-0">Indicadores generales de solicitudes</p></div></div></div>
                        </a>
                    @endif
                    @if ($u->canModule('gestion.informes', $activeRole) && Route::has('gestion.informes.index'))
                        <a href="{{ route('gestion.informes.index') }}" class="text-decoration-none">
                            <div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-file-earmark-spreadsheet tile-icon"></i><div><h3 class="h6 m-0 text-dark">Informes</h3><p class="text-muted small m-0">Planillas BRP, DIPRES y reportes</p></div></div></div>
                        </a>
                    @endif
                    @if ($u->canModule('declaracion', $activeRole) && Route::has('declaracion.index'))
                        <a href="{{ route('declaracion.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-file-earmark-text tile-icon"></i><div><h3 class="h6 m-0 text-dark">Declaración de sostenedores</h3><p class="text-muted small m-0">Gestión, importación y revisión de registros por establecimiento</p></div></div></div></a>
                    @endif
                    @if ($u->canModule('gestion.bolsa-trabajo', $activeRole) && Route::has('gestion.bolsa-trabajo.index'))
                        <a href="{{ route('gestion.bolsa-trabajo.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-briefcase tile-icon"></i><div><h3 class="h6 m-0 text-dark">Bolsa de Trabajo</h3><p class="text-muted small m-0">CRUD de ofertas laborales y difusión</p></div></div></div></a>
                    @endif
                </div>
            </div>
        @endif



        @if ($u->canModule('endeudamiento', $activeRole) && Route::has('endeudamiento.cargas.index'))
            <div class="dashboard-section mb-4">
                <h3 class="h6 text-uppercase text-muted mb-2">Endeudamiento</h3>
                <div class="dashboard-grid">
                    <a href="{{ route('endeudamiento.cargas.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-cash-coin tile-icon"></i><div><h3 class="h6 m-0 text-dark">Cargas MAE</h3><p class="text-muted small m-0">Importación versionada por mes, año y dominio</p></div></div></div></a>
                    @if (Route::has('endeudamiento.registros.index'))
                        <a href="{{ route('endeudamiento.registros.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-table tile-icon"></i><div><h3 class="h6 m-0 text-dark">Registros MAE</h3><p class="text-muted small m-0">Consulta de descuentos, aportes y otros descuentos</p></div></div></div></a>
                    @endif
                    @if (Route::has('endeudamiento.topes.index'))
                        <a href="{{ route('endeudamiento.topes.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-calculator tile-icon"></i><div><h3 class="h6 m-0 text-dark">Cálculo de topes</h3><p class="text-muted small m-0">Análisis individual, detalle y exportaciones del cálculo</p></div></div></div></a>
                    @endif
                    @if (Route::has('endeudamiento.normativa.index'))
                        <a href="{{ route('endeudamiento.normativa.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-sliders tile-icon"></i><div><h3 class="h6 m-0 text-dark">Topes normativos</h3><p class="text-muted small m-0">Parámetros, prioridad y reglas para descuentos homologados</p></div></div></div></a>
                    @endif
                </div>
            </div>
        @endif

        @if ($u->canAnyModule(['reemplazos', 'reemplazos.personal', 'incumplimientos'], $activeRole))
            <div class="dashboard-section mb-4">
                <h3 class="h6 text-uppercase text-muted mb-2">Reemplazos y control</h3>
                <div class="dashboard-grid">
                    @if ($u->canModule('reemplazos', $activeRole) && Route::has('reemplazos.index'))
                        <a href="{{ route('reemplazos.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-person-plus tile-icon"></i><div><h3 class="h6 m-0 text-dark">Reemplazos</h3><p class="text-muted small m-0">Padrón mensual y gestión operativa</p></div></div></div></a>
                    @endif
                    @if ($u->canModule('reemplazos', $activeRole) && Route::has('reemplazos.buscador-postulantes.index'))
                        <a href="{{ route('reemplazos.buscador-postulantes.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-search tile-icon"></i><div><h3 class="h6 m-0 text-dark">Buscador de postulantes</h3><p class="text-muted small m-0">Consulta de postulantes y fichas</p></div></div></div></a>
                    @endif
                    @if ($u->canModule('reemplazos.personal', $activeRole) && Route::has('reemplazos.personal.import'))
                        <a href="{{ route('reemplazos.personal.import') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-upload tile-icon"></i><div><h3 class="h6 m-0 text-dark">Carga masiva personal</h3><p class="text-muted small m-0">Importar padrón mensual</p></div></div></div></a>
                    @endif
                    @if ($u->canModule('incumplimientos', $activeRole) && Route::has('incumplimientos.index'))
                        <a href="{{ route('incumplimientos.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-exclamation-octagon tile-icon"></i><div><h3 class="h6 m-0 text-dark">Incumplimiento laboral</h3><p class="text-muted small m-0">Registro, edición y seguimiento</p></div></div></div></a>
                    @endif
                </div>
            </div>
        @endif

        @if ($u->canModule('admin.documents', $activeRole))
            <div class="dashboard-section mb-4">
                <h3 class="h6 text-uppercase text-muted mb-2">Revisión documental</h3>
                <div class="dashboard-grid">
                    <a href="{{ route('admin.documents.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-inboxes tile-icon"></i><div><h3 class="h6 m-0 text-dark">Documentos</h3><p class="text-muted small m-0">Revisión y estados de postulantes</p></div></div></div></a>
                </div>
            </div>
        @endif

        @if ($u->canAnyModule(['admin.users', 'admin.roles', 'admin.restricted-ruts', 'admin.notification-logs'], $activeRole) || Route::has('admin.postulaciones.index'))
            <div class="dashboard-section mb-4">
                <h3 class="h6 text-uppercase text-muted mb-2">Administración del sistema</h3>
                <div class="dashboard-grid">
                    @if (Route::has('admin.postulaciones.index'))
                        <a href="{{ route('admin.postulaciones.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-person-vcard tile-icon"></i><div><h3 class="h6 m-0 text-dark">Postulaciones</h3><p class="text-muted small m-0">Listado y ficha completa de postulantes</p></div></div></div></a>
                    @endif
                    @if ($u->canModule('admin.users', $activeRole) && Route::has('admin.users.index'))
                        <a href="{{ route('admin.users.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-people tile-icon"></i><div><h3 class="h6 m-0 text-dark">Usuarios</h3><p class="text-muted small m-0">Gestión de cuentas y roles</p></div></div></div></a>
                    @endif
                    @if ($u->canModule('admin.roles', $activeRole) && Route::has('admin.roles.index'))
                        <a href="{{ route('admin.roles.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-key tile-icon"></i><div><h3 class="h6 m-0 text-dark">Roles (módulos)</h3><p class="text-muted small m-0">Asignación de accesos</p></div></div></div></a>
                    @endif
                    @if ($u->canModule('admin.restricted-ruts', $activeRole) && Route::has('admin.restricted-ruts.index'))
                        <a href="{{ route('admin.restricted-ruts.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-shield-exclamation tile-icon"></i><div><h3 class="h6 m-0 text-dark">Restricciones para ejercer</h3><p class="text-muted small m-0">Bloqueos judiciales y manuales</p></div></div></div></a>
                    @endif
                    @if ($u->canModule('admin.notification-logs', $activeRole) && Route::has('admin.notification-logs.index'))
                        <a href="{{ route('admin.notification-logs.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-envelope-check tile-icon"></i><div><h3 class="h6 m-0 text-dark">Historial de notificaciones</h3><p class="text-muted small m-0">Seguimiento de correos enviados y fallidos</p></div></div></div></a>
                    @endif
                </div>
            </div>
        @endif

        @if ($activeRole === 'admin' || $u->canAnyModule(['admin.establecimientos', 'admin.areas-desempeno', 'admin.menciones', 'admin.subsectores', 'admin.aaee-valores-hora'], $activeRole))
            <div class="dashboard-section mb-4">
                <h3 class="h6 text-uppercase text-muted mb-2">Catálogos</h3>
                <div class="dashboard-grid">
                    @if ($u->canModule('admin.establecimientos', $activeRole))
                        <a href="{{ route('admin.establecimientos.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-buildings tile-icon"></i><div><h3 class="h6 m-0 text-dark">Establecimientos</h3><p class="text-muted small m-0">Mantención del catálogo</p></div></div></div></a>
                    @endif
                    @if ($u->canModule('admin.areas-desempeno', $activeRole))
                        <a href="{{ route('admin.areas-desempeno.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-list-check tile-icon"></i><div><h3 class="h6 m-0 text-dark">Áreas de desempeño</h3><p class="text-muted small m-0">Configuración por estamento</p></div></div></div></a>
                    @endif
                    @if ($u->canModule('admin.menciones', $activeRole))
                        <a href="{{ route('admin.menciones.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-award tile-icon"></i><div><h3 class="h6 m-0 text-dark">Menciones</h3><p class="text-muted small m-0">Administración del catálogo</p></div></div></div></a>
                    @endif
                    @if ($u->canModule('declaracion', $activeRole) && Route::has('admin.titulos-catalogo.index'))
                        <a href="{{ route('admin.titulos-catalogo.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-journal-text tile-icon"></i><div><h3 class="h6 m-0 text-dark">Títulos catálogo</h3><p class="text-muted small m-0">CRUD e importación masiva de títulos</p></div></div></div></a>
                    @endif
                    @if ($u->canModule('declaracion', $activeRole) && Route::has('admin.funciones-catalogo.index'))
                        <a href="{{ route('admin.funciones-catalogo.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-diagram-2 tile-icon"></i><div><h3 class="h6 m-0 text-dark">Funciones catálogo</h3><p class="text-muted small m-0">CRUD e importación masiva de funciones</p></div></div></div></a>
                    @endif
                    @if ($u->canModule('declaracion', $activeRole) && Route::has('admin.instituciones-catalogo.index'))
                        <a href="{{ route('admin.instituciones-catalogo.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-building tile-icon"></i><div><h3 class="h6 m-0 text-dark">Instituciones catálogo</h3><p class="text-muted small m-0">CRUD e importación masiva de instituciones</p></div></div></div></a>
                    @endif
                    @if ($u->canModule('admin.subsectores', $activeRole))
                        <a href="{{ route('admin.subsectores.index') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-diagram-3 tile-icon"></i><div><h3 class="h6 m-0 text-dark">Subsectores</h3><p class="text-muted small m-0">Administración del catálogo</p></div></div></div></a>
                    @endif
                    @if ($activeRole === 'admin' || ($u->canModule('admin.aaee-valores-hora', $activeRole) && Route::has('admin.aaee-valores-hora.index')))
                        <a href="{{ Route::has('admin.aaee-valores-hora.index') ? route('admin.aaee-valores-hora.index') : url('admin/aaee-valores-hora') }}" class="text-decoration-none"><div class="tile tile-role"><div class="d-flex align-items-center gap-3"><i class="bi bi-currency-dollar tile-icon"></i><div><h3 class="h6 m-0 text-dark">Valores hora AAEE</h3><p class="text-muted small m-0">Mantención de valores referenciales</p></div></div></div></a>
                    @endif
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
