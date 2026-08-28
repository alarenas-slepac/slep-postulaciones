<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class SlepUiRegistry
{
    public static function roleLabel(?string $role): string
    {
        $role = strtolower(trim((string) $role));

        return [
            'admin' => 'Administrador',
            'coordinador_gdp' => 'Coordinador GDP',
            'coordinador_uatp' => 'Coordinador UATP',
            'comunicaciones' => 'Comunicaciones',
            'gabinete_slep' => 'Gabinete SLEP',
            'secretaria_direccion_ejecutiva' => 'Secretaría Dirección Ejecutiva',
            'supervisor_plani' => 'Supervisor Planificación',
            'coordinador_plani' => 'Coordinador Planificación',
            'funcionario_slep' => 'Funcionario SLEP',
            'funcionario_ac' => 'Funcionario Administración Central',
            'funcionario_estab' => 'Funcionario Establecimiento',
            'funcionario_directivo_estab' => 'Directivo de Establecimiento',
            'funcionario_daf' => 'Funcionario DAF',
            'funcionario_daf_compra' => 'Funcionario DAF Compra',
            'director_ejecutivo' => 'Director Ejecutivo',
            'funcionario_juridica' => 'Funcionario Jurídica',
            'digitador_licencias' => 'Digitador Licencias Médicas',
            'analista_licencias' => 'Analista Licencias Médicas',
            'analista_smc' => 'Analista SMC',
            'administrador_licencias' => 'Administrador Licencias Médicas',
            'funcionario' => 'Funcionario',
            'postulante' => 'Postulante',
        ][$role] ?? Str::headline(str_replace('_', ' ', $role ?: 'usuario'));
    }

    public static function roleTone(?string $role): string
    {
        $role = strtolower(trim((string) $role));

        return match ($role) {
            'admin' => 'primary',
            'funcionario_estab', 'funcionario_directivo_estab' => 'success',
            'coordinador_uatp' => 'info',
            'comunicaciones', 'gabinete_slep', 'secretaria_direccion_ejecutiva' => 'purple',
            'supervisor_plani', 'coordinador_plani' => 'warning',
            'coordinador_gdp', 'funcionario_slep', 'digitador_licencias', 'analista_licencias', 'analista_smc', 'administrador_licencias' => 'purple',
            'funcionario_daf', 'funcionario_daf_compra' => 'teal',
            'director_ejecutivo' => 'primary',
            'funcionario_juridica' => 'danger',
            default => 'muted',
        };
    }

    public static function initials($user): string
    {
        $name = trim((string) ($user->nombre_completo ?? $user->name ?? $user->email ?? 'Usuario'));
        $parts = preg_split('/\s+/', $name) ?: [];
        $first = mb_substr($parts[0] ?? 'U', 0, 1);
        $second = mb_substr($parts[1] ?? ($parts[0] ?? 'S'), 0, 1);

        return mb_strtoupper($first . $second);
    }

    public static function menuGroups($user, ?string $activeRole): array
    {
        $entries = [
            'Inicio' => [
                self::entry('Dashboard', 'dashboard', 'bi-speedometer2', ['*']),
            ],
            'Centro de Operaciones' => [
                self::entry('Panel territorial', 'centro-operaciones.index', 'bi-broadcast-pin', ['admin', 'director_ejecutivo', 'funcionario_slep', 'coordinador_gdp', 'coordinador_uatp', 'comunicaciones', 'gabinete_slep', 'secretaria_direccion_ejecutiva'], 'centro-operaciones'),
                self::entry('Reporte diario', 'centro-operaciones.reportes.create', 'bi-clipboard2-pulse', ['funcionario_directivo_estab', 'comunicaciones', 'gabinete_slep'], 'centro-operaciones'),
                self::entry('Historial de reportes', 'centro-operaciones.reportes.history', 'bi-clock-history', ['admin', 'director_ejecutivo', 'funcionario_slep', 'coordinador_gdp', 'coordinador_uatp', 'comunicaciones', 'gabinete_slep', 'secretaria_direccion_ejecutiva', 'funcionario_directivo_estab'], 'centro-operaciones'),
                self::entry('Riesgo por establecimiento', 'centro-operaciones.riesgos.index', 'bi-shield-check', ['admin', 'director_ejecutivo', 'funcionario_slep', 'coordinador_gdp', 'coordinador_uatp', 'comunicaciones', 'gabinete_slep', 'secretaria_direccion_ejecutiva'], 'centro-operaciones'),
                self::entry('Tickets de incidencias', 'centro-operaciones.tickets.index', 'bi-ticket-detailed', ['admin', 'director_ejecutivo', 'secretaria_direccion_ejecutiva', 'comunicaciones', 'gabinete_slep', 'funcionario_ac', 'funcionario_directivo_estab']),
                self::entry('Mantenedor de incidencias', 'centro-operaciones.configuraciones.index', 'bi-sliders', ['admin', 'comunicaciones', 'gabinete_slep']),
                self::entry('Mantenedor de riesgo IRTE', 'centro-operaciones.riesgos.configuracion', 'bi-ui-checks-grid', ['admin', 'comunicaciones', 'gabinete_slep']),
            ],
            'Trámites y operación' => [
                self::entry('Votaciones CCAF y Mutualidades', 'votaciones.admin.dashboard', 'bi-geo-alt-fill', ['admin', 'coordinador_gdp', 'funcionario_slep'], 'votaciones'),
                self::entry('Operación de votaciones', 'votaciones.operacion.index', 'bi-sign-turn-right-fill', ['admin', 'coordinador_gdp', 'funcionario_slep', 'funcionario_ac'], 'votaciones'),
                self::entry('Certificados laborales', 'certificados.index', 'bi-file-earmark-check', ['admin', 'coordinador_gdp', 'funcionario_slep', 'funcionario', 'funcionario_ac'], 'certificados'),
                self::entry('Cometidos funcionarios', 'tramites.cometidos-funcionarios.index', 'bi-briefcase', ['admin', 'director_ejecutivo', 'funcionario_estab', 'funcionario_ac', 'coordinador_uatp', 'supervisor_plani', 'coordinador_plani', 'coordinador_gdp', 'funcionario_slep', 'funcionario_daf', 'funcionario_daf_compra', 'funcionario_juridica']),
                self::entry('Licencias Médicas', 'tramites.licencias-medicas.index', 'bi-file-medical', ['admin', 'coordinador_gdp', 'funcionario_slep', 'digitador_licencias', 'analista_licencias', 'analista_smc', 'administrador_licencias']),
                self::entry('Agendamiento Proyector y Salas', 'gestion.agendamientos-recursos.index', 'bi-calendar-event', ['admin', 'coordinador_gdp', 'funcionario_slep', 'funcionario_ac', 'secretaria_direccion_ejecutiva']),
                self::entry('Solicitudes reemplazo', 'funcionario.solicitudes-reemplazo.index', 'bi-clipboard-check', ['funcionario_estab'], 'funcionario.solicitudes-reemplazo'),
                self::entry('Gestión reemplazos', 'gestion.solicitudes-reemplazo.index', 'bi-kanban', ['admin', 'coordinador_uatp', 'coordinador_gdp', 'funcionario_slep', 'supervisor_plani'], 'gestion.solicitudes-reemplazo'),
                self::entry('Deuda pensión de alimentos', 'gestion.deudas-pension-alimentos.index', 'bi-shield-exclamation', ['admin', 'funcionario_slep'], 'gestion.solicitudes-reemplazo'),
                self::entry('Autorizaciones docentes', 'gestion.autorizaciones-docentes.index', 'bi-patch-check', ['admin', 'coordinador_uatp']),
                self::entry('Finiquitos reemplazos', 'gestion.solicitudes-reemplazo.finiquitos.index', 'bi-receipt-cutoff', ['admin', 'coordinador_gdp', 'funcionario_slep'], 'gestion.solicitudes-reemplazo'),
                self::entry('Padrón reemplazos', 'reemplazos.index', 'bi-person-lines-fill', ['admin', 'coordinador_gdp', 'funcionario_slep'], 'reemplazos'),
                self::entry('Buscador postulantes', 'reemplazos.buscador-postulantes.index', 'bi-search', ['admin', 'coordinador_gdp', 'coordinador_uatp', 'funcionario_slep', 'funcionario_estab']),
                self::entry('Vista temporal de usuarios', 'gestion.postulante-tutorial.index', 'bi-person-video2', ['admin', 'coordinador_gdp', 'funcionario_slep']),
                self::entry('Trámites generales', 'tramites.index', 'bi-folder-check', ['admin', 'coordinador_gdp', 'funcionario_slep', 'postulante', 'funcionario'], 'tramites'),
                self::entry('Incumplimiento laboral', 'incumplimientos.index', 'bi-exclamation-octagon', ['admin', 'funcionario_estab'], 'incumplimientos'),
            ],
            'Postulación y funcionarios' => [
                self::entry('Mi perfil', 'postulant.profile.edit', 'bi-person-badge', ['postulante', 'funcionario'], 'postulant.profile'),
                self::entry('Mis documentos', 'postulant.documents.index', 'bi-file-earmark-arrow-up', ['postulante', 'funcionario'], 'postulant.documents'),
                self::entry('Mis reemplazos', 'postulant.reemplazos.index', 'bi-calendar-check', ['postulante', 'funcionario'], 'postulant.reemplazos'),
                self::entry('Mi deuda de pensión', 'postulant.deudas-pension-alimentos.index', 'bi-file-earmark-lock', ['postulante', 'funcionario'], 'postulant.reemplazos'),
                self::entry('Mis Finiquitos', 'postulant.finiquitos.index', 'bi-file-earmark-pdf', ['postulante', 'funcionario'], 'postulant.reemplazos'),
                self::entry('Ofertas laborales', 'postulant.ofertas-laborales.index', 'bi-briefcase-fill', ['postulante', 'funcionario'], 'postulant.ofertas-laborales'),
                self::entry('Mis Cargas Familiares', 'tramites.cargas-familiares.index', 'bi-people', ['postulante', 'funcionario', 'funcionario_ac']),
                self::entry('Mis liquidaciones', 'liquidaciones.mis.index', 'bi-receipt', ['postulante', 'funcionario']),
            ],
            'Administración' => [
                self::entry('Usuarios y roles', 'admin.users.index', 'bi-people-fill', ['admin'], 'admin.users'),
                self::entry('Correos masivos por rol', 'admin.bulk-role-mail.index', 'bi-envelope-at', ['admin', 'coordinador_gdp']),
                self::entry('Historial de notificaciones', 'admin.notification-logs.index', 'bi-envelope-check', ['admin'], 'admin.notification-logs'),
                self::entry('Roles', 'admin.roles.index', 'bi-person-gear', ['admin'], 'admin.roles'),
                self::entry('Configuración reemplazos', 'admin.solicitudes-reemplazo-configuracion.edit', 'bi-gear', ['admin']),
                self::entry('Establecimientos', 'admin.establecimientos.index', 'bi-building', ['admin'], 'admin.establecimientos'),
                self::entry('Admisión Escolar', 'admin.admision-escolar.index', 'bi-buildings', ['admin', 'coordinador_uatp', 'comunicaciones'], 'admin.admision-escolar'),
                self::entry('Alumnos prioritarios', 'admin.alumnos-prioritarios.index', 'bi-percent', ['admin'], 'admin.alumnos-prioritarios'),
                self::entry('Cursos', 'admin.cursos.index', 'bi-layers', ['admin', 'coordinador_uatp'], 'admin.cursos'),
                self::entry('Planes de estudio', 'admin.planes-estudio.index', 'bi-journal-bookmark', ['admin', 'coordinador_uatp'], 'admin.planes-estudio'),
                self::entry('Cursos por establecimiento', 'admin.establecimiento-cursos.index', 'bi-mortarboard', ['admin', 'coordinador_uatp'], 'admin.establecimiento-cursos'),
                self::entry('Estudiantes PIE por curso', 'admin.establecimiento-curso-pie.index', 'bi-person-lines-fill', ['admin', 'funcionario_directivo_estab', 'coordinador_uatp', 'coordinador_gdp'], 'admin.establecimiento-curso-pie'),
                self::entry('Dotación funciones y planes', 'admin.dotacion-funciones.index', 'bi-diagram-3-fill', ['admin', 'funcionario_directivo_estab', 'coordinador_uatp', 'coordinador_gdp'], 'admin.dotacion-funciones'),
                self::entry('Dotación establecimiento', 'admin.dotacion-establecimiento.index', 'bi-building-check', ['admin', 'funcionario_directivo_estab', 'coordinador_uatp', 'coordinador_gdp', 'supervisor_plani'], 'admin.dotacion-establecimiento'),
                self::entry('Configurar planes EE', 'admin.establecimiento-planes.index', 'bi-ui-checks-grid', ['admin', 'funcionario_directivo_estab', 'coordinador_uatp'], 'admin.establecimiento-planes'),
                self::entry('Asignaturas', 'admin.asignaturas.index', 'bi-book', ['admin', 'coordinador_uatp'], 'admin.asignaturas'),
                self::entry('Asignaturas personalizadas', 'admin.asignaturas-personalizadas.index', 'bi-pencil-square', ['admin', 'coordinador_gdp', 'coordinador_uatp'], 'admin.asignaturas-personalizadas'),
                self::entry('Viáticos y reembolsos', 'admin.viaticos-reembolsos.index', 'bi-cash-coin', ['admin', 'supervisor_plani', 'coordinador_plani'], 'admin.viaticos-reembolsos'),
                self::entry('Viáticos disponibilidad', 'admin.viaticos-disponibilidad.index', 'bi-wallet2', ['admin', 'supervisor_plani', 'coordinador_plani'], 'admin.viaticos-disponibilidad'),
                self::entry('Notificaciones cometidos', 'admin.cometidos-notificaciones.index', 'bi-bell', ['admin', 'coordinador_gdp', 'funcionario_slep'], 'admin.cometidos-notificaciones'),
                self::entry('Funcionarios viático por anexo', 'admin.funcionarios-viatico-anexo.index', 'bi-person-check', ['admin', 'supervisor_plani', 'coordinador_plani'], 'admin.funcionarios-viatico-anexo'),
                self::entry('Valores hora AAEE', 'admin.aaee-valores-hora.index', 'bi-currency-dollar', ['admin']),
                self::entry('Áreas desempeño', 'admin.areas-desempeno.index', 'bi-diagram-3', ['admin'], 'admin.areas-desempeno'),
                self::entry('Subsectores', 'admin.subsectores.index', 'bi-diagram-2', ['admin'], 'admin.subsectores'),
                self::entry('Menciones', 'admin.menciones.index', 'bi-tags', ['admin'], 'admin.menciones'),
                self::entry('Restricciones RUT', 'admin.restricted-ruts.index', 'bi-shield-exclamation', ['admin'], 'admin.restricted-ruts'),
                self::entry('Excepciones permiso sin goce', 'admin.permiso-sin-goce-excepciones.index', 'bi-unlock', ['admin', 'coordinador_gdp'], 'admin.permiso-sin-goce-excepciones'),
            ],
            'Remuneraciones' => [
                self::entry('Liquidaciones', 'liquidaciones.cargas.index', 'bi-receipt-cutoff', ['admin', 'funcionario_slep'], 'liquidaciones'),
                self::entry('Endeudamiento', 'endeudamiento.cargas.index', 'bi-calculator', ['admin', 'funcionario_slep'], 'endeudamiento'),
                self::entry('Descuentos CGR', 'descuentos-cgr.index', 'bi-bank', ['admin', 'funcionario_slep']),
                self::entry('Valores UTM', 'descuentos-cgr.utm.index', 'bi-currency-exchange', ['admin', 'funcionario_slep']),
            ],
            'Gestión y control' => [
                self::entry('Estadísticas', 'gestion.estadisticas.index', 'bi-bar-chart-line', ['admin', 'coordinador_gdp', 'funcionario_slep', 'coordinador_uatp', 'supervisor_plani'], 'gestion.estadisticas'),
                self::entry('Informes', 'gestion.informes.index', 'bi-file-earmark-spreadsheet', ['admin', 'coordinador_gdp', 'funcionario_slep', 'supervisor_plani'], 'gestion.informes'),
                self::entry('Bolsa de Trabajo', 'gestion.bolsa-trabajo.index', 'bi-briefcase', ['admin', 'funcionario_slep'], 'gestion.bolsa-trabajo'),
                self::entry('Agendamiento Proyector y Salas', 'gestion.agendamientos-recursos.index', 'bi-calendar-event', ['admin', 'coordinador_gdp', 'funcionario_slep', 'funcionario_ac', 'secretaria_direccion_ejecutiva']),
                self::entry('Revisión documentos', 'admin.documents.index', 'bi-file-earmark-check', ['admin', 'funcionario_slep', 'coordinador_gdp'], 'admin.documents'),
            ],
            'Comunicación' => [
                self::entry('Mensajes', 'messages.index', 'bi-chat-square-dots', ['admin', 'director_ejecutivo', 'funcionario_estab', 'funcionario_establecimiento', 'funcionario_directivo_estab', 'funcionario_directivo_establecimiento', 'coordinador_uatp', 'comunicaciones', 'gabinete_slep', 'supervisor_plani', 'coordinador_plani', 'coordinador_gdp', 'funcionario_slep', 'funcionario_daf', 'funcionario_juridica', 'funcionario_daf_compra', 'funcionario_ac', 'funcionario', 'postulante'], 'messages'),
            ],
        ];

        $visible = [];
        foreach ($entries as $group => $items) {
            $items = array_values(array_filter($items, fn ($item) => self::entryVisible($item, $user, $activeRole)));
            if (!empty($items)) {
                $visible[$group] = $items;
            }
        }

        return $visible;
    }

    public static function quickModules($user, ?string $activeRole): array
    {
        $items = [
            self::entry('Centro de Operaciones', 'centro-operaciones.index', 'bi-broadcast-pin', ['admin', 'director_ejecutivo', 'funcionario_slep', 'coordinador_gdp', 'coordinador_uatp', 'comunicaciones', 'gabinete_slep', 'secretaria_direccion_ejecutiva'], 'centro-operaciones'),
            self::entry('Reporte diario', 'centro-operaciones.reportes.create', 'bi-clipboard2-pulse', ['funcionario_directivo_estab', 'comunicaciones', 'gabinete_slep'], 'centro-operaciones'),
            self::entry('Historial operacional', 'centro-operaciones.reportes.history', 'bi-clock-history', ['admin', 'director_ejecutivo', 'funcionario_slep', 'coordinador_gdp', 'coordinador_uatp', 'comunicaciones', 'gabinete_slep', 'secretaria_direccion_ejecutiva', 'funcionario_directivo_estab'], 'centro-operaciones'),
            self::entry('Certificados laborales', 'certificados.index', 'bi-file-earmark-check', ['admin', 'coordinador_gdp', 'funcionario_slep', 'funcionario', 'funcionario_ac'], 'certificados'),
            self::entry('Cometidos funcionarios', 'tramites.cometidos-funcionarios.index', 'bi-briefcase', ['admin', 'director_ejecutivo', 'funcionario_estab', 'funcionario_ac', 'coordinador_uatp', 'supervisor_plani', 'coordinador_plani', 'coordinador_gdp', 'funcionario_slep', 'funcionario_daf', 'funcionario_daf_compra', 'funcionario_juridica']),
            self::entry('Licencias Médicas', 'tramites.licencias-medicas.index', 'bi-file-medical', ['admin', 'coordinador_gdp', 'funcionario_slep', 'digitador_licencias', 'analista_licencias', 'analista_smc', 'administrador_licencias']),
                self::entry('Agendamiento Proyector y Salas', 'gestion.agendamientos-recursos.index', 'bi-calendar-event', ['admin', 'coordinador_gdp', 'funcionario_slep', 'funcionario_ac', 'secretaria_direccion_ejecutiva']),
            self::entry('Nueva solicitud de cometido', 'tramites.cometidos-funcionarios.create', 'bi-plus-circle', ['funcionario_estab', 'funcionario_ac', 'director_ejecutivo']),
            self::entry('Gestión de reemplazos', 'gestion.solicitudes-reemplazo.index', 'bi-kanban', ['admin', 'coordinador_uatp', 'coordinador_gdp', 'funcionario_slep', 'supervisor_plani'], 'gestion.solicitudes-reemplazo'),
            self::entry('Deuda pensión de alimentos', 'gestion.deudas-pension-alimentos.index', 'bi-shield-exclamation', ['admin', 'funcionario_slep'], 'gestion.solicitudes-reemplazo'),
            self::entry('Autorizaciones docentes', 'gestion.autorizaciones-docentes.index', 'bi-patch-check', ['admin', 'coordinador_uatp']),
            self::entry('Finiquitos reemplazos', 'gestion.solicitudes-reemplazo.finiquitos.index', 'bi-receipt-cutoff', ['admin', 'coordinador_gdp', 'funcionario_slep'], 'gestion.solicitudes-reemplazo'),
            self::entry('Buscador postulantes', 'reemplazos.buscador-postulantes.index', 'bi-search', ['admin', 'coordinador_gdp', 'coordinador_uatp', 'funcionario_slep', 'funcionario_estab']),
            self::entry('Bolsa de Trabajo', 'gestion.bolsa-trabajo.index', 'bi-briefcase', ['admin', 'funcionario_slep'], 'gestion.bolsa-trabajo'),
            self::entry('Solicitudes establecimiento', 'funcionario.solicitudes-reemplazo.index', 'bi-clipboard-check', ['funcionario_estab'], 'funcionario.solicitudes-reemplazo'),
            self::entry('Viáticos y reembolsos', 'admin.viaticos-reembolsos.index', 'bi-cash-coin', ['admin', 'supervisor_plani', 'coordinador_plani'], 'admin.viaticos-reembolsos'),
            self::entry('Funcionarios viático por anexo', 'admin.funcionarios-viatico-anexo.index', 'bi-person-check', ['admin', 'supervisor_plani', 'coordinador_plani'], 'admin.funcionarios-viatico-anexo'),
            self::entry('Valores hora AAEE', 'admin.aaee-valores-hora.index', 'bi-currency-dollar', ['admin']),
            self::entry('Descuentos CGR', 'descuentos-cgr.index', 'bi-bank', ['admin', 'funcionario_slep']),
            self::entry('Mis Cargas Familiares', 'tramites.cargas-familiares.index', 'bi-people', ['postulante', 'funcionario', 'funcionario_ac']),
            self::entry('Mis Finiquitos', 'postulant.finiquitos.index', 'bi-file-earmark-pdf', ['postulante', 'funcionario'], 'postulant.reemplazos'),
            self::entry('Mi deuda de pensión', 'postulant.deudas-pension-alimentos.index', 'bi-file-earmark-lock', ['postulante', 'funcionario'], 'postulant.reemplazos'),
            self::entry('Nueva acreditación', 'tramites.cargas-familiares.create', 'bi-file-earmark-plus', ['postulante', 'funcionario', 'funcionario_ac']),
            self::entry('Usuarios y roles', 'admin.users.index', 'bi-people-fill', ['admin'], 'admin.users'),
            self::entry('Admisión Escolar', 'admin.admision-escolar.index', 'bi-buildings', ['admin', 'coordinador_uatp', 'comunicaciones'], 'admin.admision-escolar'),
            self::entry('Cursos', 'admin.cursos.index', 'bi-layers', ['admin', 'coordinador_uatp'], 'admin.cursos'),
            self::entry('Planes de estudio', 'admin.planes-estudio.index', 'bi-journal-bookmark', ['admin', 'coordinador_uatp'], 'admin.planes-estudio'),
            self::entry('Cursos por establecimiento', 'admin.establecimiento-cursos.index', 'bi-mortarboard', ['admin', 'coordinador_uatp'], 'admin.establecimiento-cursos'),
            self::entry('Configurar planes EE', 'admin.establecimiento-planes.index', 'bi-ui-checks-grid', ['admin', 'funcionario_directivo_estab', 'coordinador_uatp'], 'admin.establecimiento-planes'),
            self::entry('Estudiantes PIE por curso', 'admin.establecimiento-curso-pie.index', 'bi-person-lines-fill', ['admin', 'funcionario_directivo_estab', 'coordinador_uatp', 'coordinador_gdp'], 'admin.establecimiento-curso-pie'),
            self::entry('Dotación funciones y planes', 'admin.dotacion-funciones.index', 'bi-diagram-3-fill', ['admin', 'funcionario_directivo_estab', 'coordinador_uatp', 'coordinador_gdp'], 'admin.dotacion-funciones'),
            self::entry('Dotación establecimiento', 'admin.dotacion-establecimiento.index', 'bi-building-check', ['admin', 'funcionario_directivo_estab', 'coordinador_uatp', 'coordinador_gdp', 'supervisor_plani'], 'admin.dotacion-establecimiento'),
            self::entry('Asignaturas', 'admin.asignaturas.index', 'bi-book', ['admin', 'coordinador_uatp'], 'admin.asignaturas'),
            self::entry('Asignaturas personalizadas', 'admin.asignaturas-personalizadas.index', 'bi-pencil-square', ['admin', 'coordinador_gdp', 'coordinador_uatp'], 'admin.asignaturas-personalizadas'),
            self::entry('Roles', 'admin.roles.index', 'bi-person-gear', ['admin'], 'admin.roles'),
            self::entry('Reportes', 'gestion.informes.index', 'bi-file-earmark-spreadsheet', ['admin', 'coordinador_gdp', 'funcionario_slep', 'supervisor_plani'], 'gestion.informes'),
            self::entry('Revisión documentos', 'admin.documents.index', 'bi-file-earmark-check', ['admin', 'funcionario_slep', 'coordinador_gdp'], 'admin.documents'),
            self::entry('Mensajes', 'messages.index', 'bi-chat-square-dots', ['admin', 'director_ejecutivo', 'funcionario_estab', 'funcionario_establecimiento', 'funcionario_directivo_estab', 'funcionario_directivo_establecimiento', 'coordinador_uatp', 'comunicaciones', 'gabinete_slep', 'supervisor_plani', 'coordinador_plani', 'coordinador_gdp', 'funcionario_slep', 'funcionario_daf', 'funcionario_juridica', 'funcionario_daf_compra', 'funcionario_ac', 'funcionario', 'postulante'], 'messages'),
        ];

        return array_values(array_filter($items, fn ($item) => self::entryVisible($item, $user, $activeRole)));
    }

    public static function dashboardTitle(?string $activeRole): array
    {
        return match ($activeRole) {
            'funcionario_estab' => ['title' => 'Panel de establecimiento', 'subtitle' => 'Solicitudes, correcciones y seguimiento del establecimiento.'],
            'funcionario_directivo_estab' => ['title' => 'Panel directivo de establecimiento', 'subtitle' => 'Gestión y revisión de antecedentes del establecimiento.'],
            'coordinador_uatp' => ['title' => 'Panel UATP', 'subtitle' => 'Revisión de pertinencia pedagógica y derivaciones.'],
            'comunicaciones' => ['title' => 'Panel de Comunicaciones', 'subtitle' => 'Gestión editorial y publicación de la vitrina de Admisión Escolar.'],
            'gabinete_slep' => ['title' => 'Panel Gabinete SLEP', 'subtitle' => 'Seguimiento territorial, coordinación institucional y comunicación interna.'],
            'secretaria_direccion_ejecutiva' => ['title' => 'Panel Secretaría Dirección Ejecutiva', 'subtitle' => 'Seguimiento territorial y coordinación de la Dirección Ejecutiva.'],
            'supervisor_plani', 'coordinador_plani' => ['title' => 'Panel Planificación', 'subtitle' => 'Revisión CDP, montos diarios y disponibilidad presupuestaria.'],
            'coordinador_gdp', 'funcionario_slep' => ['title' => 'Panel GDP / SLEP', 'subtitle' => 'Resoluciones, autorizaciones y gestión administrativa.'],
            'funcionario_daf' => ['title' => 'Panel DAF', 'subtitle' => 'Pagos, rendiciones y control financiero.'],
            'funcionario_daf_compra' => ['title' => 'Panel DAF Compra', 'subtitle' => 'Gestión de reserva, CDP de pasajes, compra y boleto aéreo.'],
            'director_ejecutivo' => ['title' => 'Panel Director Ejecutivo', 'subtitle' => 'Autorización de cometidos de Dirección Ejecutiva, jefaturas y resolución de reconversiones por falta de presupuesto.'],
            'funcionario_juridica' => ['title' => 'Panel Jurídica', 'subtitle' => 'Resoluciones jurídicas de reembolso y revisión de antecedentes.'],
            'funcionario_ac' => ['title' => 'Panel funcionario administración central', 'subtitle' => 'Gestión personal, solicitudes y documentos asociados.'],
            'admin' => ['title' => 'Dashboard global', 'subtitle' => 'Visión consolidada de módulos, usuarios, trámites y alertas.'],
            default => ['title' => 'Dashboard', 'subtitle' => 'Resumen general de tareas y accesos del sistema.'],
        };
    }

    private static function entry(string $label, string $route, string $icon, array $roles = ['*'], ?string $module = null): array
    {
        if ($route === 'messages.index') {
            $unread = self::unreadMessagesCount();
            if ($unread > 0) {
                $label .= ' (' . min($unread, 99) . ($unread > 99 ? '+' : '') . ')';
            }
        }

        return compact('label', 'route', 'icon', 'roles', 'module');
    }

    private static function unreadMessagesCount(): int
    {
        try {
            if (! auth()->check() || ! \Illuminate\Support\Facades\Schema::hasTable('message_reads')) {
                return 0;
            }

            $userId = auth()->id();
            $conversationIds = \Illuminate\Support\Facades\DB::table('conversation_participants')
                ->where('user_id', $userId)
                ->pluck('conversation_id');

            if ($conversationIds->isEmpty()) {
                return 0;
            }

            return (int) \Illuminate\Support\Facades\DB::table('messages')
                ->whereIn('conversation_id', $conversationIds)
                ->where('user_id', '!=', $userId)
                ->whereNotExists(function ($subquery) use ($userId) {
                    $subquery->selectRaw(1)
                        ->from('message_reads')
                        ->whereColumn('message_reads.conversation_id', 'messages.conversation_id')
                        ->where('message_reads.user_id', $userId)
                        ->whereColumn('message_reads.last_read_message_id', '>=', 'messages.id');
                })
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private static function entryVisible(array $item, $user, ?string $activeRole): bool
    {
        if (!Route::has($item['route'])) {
            return false;
        }

        $roles = $item['roles'] ?? ['*'];
        if (!in_array('*', $roles, true) && !in_array((string) $activeRole, $roles, true)) {
            return false;
        }

        $module = $item['module'] ?? null;
        if ($module && $user && method_exists($user, 'canModule') && !$user->canModule($module, $activeRole)) {
            return false;
        }

        return true;
    }
}
