<?php

namespace App\Support;

use Illuminate\Support\Str;

class SlepSidebarNavigation
{
    private const PRIORITY = [
        'admin' => ['operaciones', 'seleccion'],
        'funcionario_slep' => ['seleccion', 'remuneraciones'],
        'coordinador_gdp' => ['personal', 'seleccion'],
        'coordinador_uatp' => ['dotacion'],
    ];

    private const SECTIONS = [
        'operaciones' => ['Centro de Operaciones', 'bi-broadcast-pin'],
        'seleccion' => ['Reemplazos y selección', 'bi-people'],
        'dotacion' => ['Dotación y gestión educativa', 'bi-mortarboard'],
        'personal' => ['Trámites de personal', 'bi-folder-check'],
        'cometidos' => ['Cometidos y recursos', 'bi-calendar-event'],
        'remuneraciones' => ['Remuneraciones', 'bi-receipt-cutoff'],
        'votaciones' => ['Votaciones', 'bi-clipboard-check'],
        'reportes' => ['Reportes y cumplimiento', 'bi-bar-chart-line'],
        'administracion' => ['Administración del sistema', 'bi-gear'],
    ];

    private const ROUTES_BY_SECTION = [
        'seleccion' => [
            'gestion.solicitudes-reemplazo.index',
            'gestion.deudas-pension-alimentos.index',
            'gestion.autorizaciones-docentes.index',
            'gestion.solicitudes-reemplazo.finiquitos.index',
            'gestion.solicitudes-reemplazo.prorrogas.index',
            'reemplazos.index',
            'reemplazos.buscador-postulantes.index',
            'gestion.bolsa-trabajo.index',
            'admin.documents.index',
            'admin.solicitudes-reemplazo-configuracion.edit',
            'admin.restricted-ruts.index',
        ],
        'dotacion' => [
            'admin.dotacion-establecimiento.index',
            'admin.dotacion-funciones.index',
            'admin.establecimiento-curso-pie.index',
            'admin.establecimiento-planes.index',
            'admin.planes-estudio.index',
            'admin.establecimiento-cursos.index',
            'admin.cursos.index',
            'admin.asignaturas.index',
            'admin.asignaturas-personalizadas.index',
            'admin.subsectores.index',
            'admin.menciones.index',
            'admin.establecimientos.index',
            'admin.admision-escolar.index',
            'admin.alumnos-prioritarios.index',
        ],
        'personal' => ['admin.permiso-sin-goce-excepciones.index'],
        'cometidos' => [
            'tramites.cometidos-funcionarios.index',
            'gestion.agendamientos-recursos.index',
            'admin.viaticos-reembolsos.index',
            'admin.viaticos-disponibilidad.index',
            'admin.funcionarios-viatico-anexo.index',
            'admin.cometidos-notificaciones.index',
        ],
        'remuneraciones' => ['admin.aaee-valores-hora.index'],
        'votaciones' => ['votaciones.admin.dashboard', 'votaciones.operacion.index'],
        'reportes' => ['declaracion.index'],
        'administracion' => ['gestion.postulante-tutorial.index'],
    ];

    private const ORIGINAL_SECTIONS = [
        'Centro de Operaciones' => 'operaciones',
        'Trámites y operación' => 'personal',
        'Remuneraciones' => 'remuneraciones',
        'Gestión y control' => 'reportes',
    ];

    private const ROUTE_ALIASES = [
        'reemplazos.documents' => 'reemplazos.buscador-postulantes.index',
        'centro-operaciones.riesgos.configuracion' => 'centro-operaciones.riesgos.configuracion',
    ];

    public static function appliesTo(?string $activeRole): bool
    {
        return isset(self::PRIORITY[$activeRole]);
    }

    /**
     * Reordena enlaces previamente autorizados por SlepUiRegistry.
     */
    public static function forRole(array $menuGroups, ?string $activeRole, ?string $currentRoute): ?array
    {
        if (! self::appliesTo($activeRole)) {
            return null;
        }

        $links = [];
        foreach ($menuGroups as $original => $entries) {
            foreach ($entries as $entry) {
                $route = $entry['route'];
                $links[$route] ??= $entry + ['section' => self::sectionFor($route, $original)];
            }
        }
        $activeLink = self::activeLink($currentRoute, $links);
        $direct = [];
        $grouped = [];
        foreach ($links as $route => $entry) {
            $entry['active'] = $route === $activeLink;
            if ($route === 'dashboard' || $route === 'messages.index') {
                if ($route === 'dashboard') {
                    $entry['label'] = 'Inicio';
                }
                $direct[] = $entry;
                continue;
            }

            $grouped[$entry['section']][$route] = $entry;
        }

        $sections = [];
        $order = array_unique(array_merge(self::PRIORITY[$activeRole], array_keys(self::SECTIONS)));
        foreach ($order as $key) {
            if (empty($grouped[$key])) {
                continue;
            }
            [$label, $icon] = self::SECTIONS[$key];
            $sections[] = [
                'key' => $key,
                'label' => $label,
                'icon' => $icon,
                'active' => isset($grouped[$key][$activeLink]),
                'links' => array_values($grouped[$key]),
            ];
        }

        return compact('direct', 'sections');
    }

    private static function sectionFor(string $route, string $original): string
    {
        foreach (self::ROUTES_BY_SECTION as $section => $routes) {
            if (in_array($route, $routes, true)) {
                return $section;
            }
        }

        return self::ORIGINAL_SECTIONS[$original] ?? 'administracion';
    }

    private static function activeLink(?string $route, array $links): ?string
    {
        if ($route === null || isset($links[$route])) {
            return $route;
        }

        foreach (self::ROUTE_ALIASES as $prefix => $link) {
            if (isset($links[$link]) && str_starts_with($route, $prefix.'.')) {
                return $link;
            }
        }

        // El prefijo más específico resuelve páginas de detalle y módulos anidados.
        $match = null;
        $length = 0;
        foreach (array_keys($links) as $link) {
            $prefix = Str::beforeLast($link, '.');
            if ($prefix !== $link && strlen($prefix) > $length && str_starts_with($route, $prefix.'.')) {
                $match = $link;
                $length = strlen($prefix);
            }
        }

        return $match;
    }
}
