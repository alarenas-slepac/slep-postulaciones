<?php

namespace Tests\Feature;

use App\Support\SlepUiRegistry;
use Tests\TestCase;

class CentroOperacionesTicketsModuleTest extends TestCase
{
    public function test_catalogo_excluye_otro_y_conserva_plazo_por_defecto(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_08_06_120000_create_centro_operaciones_tickets_tables.php'));
        $this->assertStringContainsString("\$tipo === 'otro'", $migration);
        $this->assertStringContainsString("->default(4)", $migration);
    }

    public function test_clave_foranea_de_responsable_usa_un_nombre_compatible_con_mysql(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_08_06_120000_create_centro_operaciones_tickets_tables.php'));

        $this->assertStringContainsString("'co_inc_cfg_responsable_fk'", $migration);
        $this->assertLessThanOrEqual(64, strlen('co_inc_cfg_responsable_fk'));
        $this->assertStringNotContainsString(
            'centro_operaciones_incidente_configuraciones_responsable_funcionario_ac_id_foreign',
            $migration
        );
    }

    public function test_rutas_de_tickets_mantenedor_y_resolucion_estan_registradas(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));
        $this->assertStringContainsString("name('tickets.index')", $routes);
        $this->assertStringContainsString("name('configuraciones.index')", $routes);
        $this->assertStringContainsString("name('configuraciones.store')", $routes);
        $this->assertStringContainsString("name('tickets.resolver')", $routes);
    }

    public function test_mantenedor_permite_crear_y_asignar_por_subdireccion(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/CentroOperaciones/IncidenteConfiguracionController.php'));
        $view = file_get_contents(resource_path('views/centro-operaciones/configuraciones/index.blade.php'));

        $this->assertStringContainsString('public function store(Request $request)', $controller);
        $this->assertStringContainsString("->where('subdireccion_dependencia', \$datos['subdireccion_dependencia'])", $controller);
        $this->assertStringContainsString('48 - strlen($terminacion)', $controller);
        $this->assertStringContainsString('Nueva incidencia', $view);
        $this->assertStringContainsString('1. Subdirección', $view);
        $this->assertStringContainsString('2. Responsable de subdirección', $view);
        $this->assertStringContainsString('Subdirector(a) (Jefatura)', $view);
        $this->assertStringContainsString('data-subdireccion', $view);
    }

    public function test_mantenedor_y_tickets_comparten_la_linea_visual_del_centro_de_operaciones(): void
    {
        $mantenedor = file_get_contents(resource_path('views/centro-operaciones/configuraciones/index.blade.php'));
        $tickets = file_get_contents(resource_path('views/centro-operaciones/tickets/index.blade.php'));
        $detalle = file_get_contents(resource_path('views/centro-operaciones/tickets/show.blade.php'));
        $estilos = file_get_contents(resource_path('css/centro-operaciones.css'));

        foreach ([$mantenedor, $tickets, $detalle] as $vista) {
            $this->assertStringContainsString("@vite('resources/css/centro-operaciones.css')", $vista);
            $this->assertStringContainsString('class="co-shell', $vista);
            $this->assertStringContainsString('class="co-hero', $vista);
            $this->assertStringContainsString('class="co-card', $vista);
        }

        $this->assertStringContainsString('co-config-grid', $mantenedor);
        $this->assertStringContainsString('co-ticket-status', $tickets);
        $this->assertStringContainsString('co-resolution-card', $detalle);
        $this->assertStringContainsString('.co-config-grid', $estilos);
        $this->assertStringContainsString('.co-ticket-status', $estilos);
    }

    public function test_catalogo_dinamico_conserva_nombre_severidad_y_estado(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_08_06_150000_add_catalog_fields_to_centro_operaciones_incidente_configuraciones.php'));
        $catalogo = file_get_contents(app_path('Services/CentroOperaciones/IncidenciaCatalogo.php'));

        $this->assertStringContainsString("->string('nombre', 120)", $migration);
        $this->assertStringContainsString("->string('severidad', 20)", $migration);
        $this->assertStringContainsString("'active' => \$configuracion->activo", $catalogo);
        $this->assertStringContainsString('public function activos(): Collection', $catalogo);
    }

    public function test_menu_de_tickets_considera_los_roles_asignados_al_usuario(): void
    {
        $navbar = file_get_contents(resource_path('views/partials/navbar.blade.php'));

        $this->assertStringContainsString(
            '$canAccessCentroOperacionesTickets = $u->hasAnyRole($ticketsRoles);',
            $navbar
        );
        $this->assertStringContainsString(
            '@if ($canAccessCentroOperacionesTickets && Route::has(\'centro-operaciones.tickets.index\'))',
            $navbar
        );
        $this->assertStringContainsString(
            '$canManageCentroOperacionesTickets = $u->hasRole(\'admin\');',
            $navbar
        );
        $this->assertStringContainsString(
            '@if ($canManageCentroOperacionesTickets && Route::has(\'centro-operaciones.configuraciones.index\'))',
            $navbar
        );
        $this->assertStringNotContainsString(
            "in_array(\$activeRole, ['admin', 'director_ejecutivo', 'secretaria_direccion_ejecutiva', 'comunicaciones', 'funcionario_ac', 'funcionario_directivo_estab'], true) && Route::has('centro-operaciones.tickets.index')",
            $navbar
        );
    }

    public function test_barra_lateral_moderna_incluye_los_accesos_de_tickets(): void
    {
        $menuDirector = collect(SlepUiRegistry::menuGroups(null, 'director_ejecutivo'))
            ->flatten(1)
            ->pluck('label');
        $menuAdmin = collect(SlepUiRegistry::menuGroups(null, 'admin'))
            ->flatten(1)
            ->pluck('label');

        $this->assertContains('Tickets de incidencias', $menuDirector);
        $this->assertNotContains('Mantenedor de incidencias', $menuDirector);
        $this->assertContains('Tickets de incidencias', $menuAdmin);
        $this->assertContains('Mantenedor de incidencias', $menuAdmin);
    }

    public function test_reporte_resuelve_el_ticket_y_conserva_la_incidencia(): void
    {
        $servicio = file_get_contents(app_path('Services/CentroOperaciones/ReporteService.php'));
        $formulario = file_get_contents(resource_path('views/centro-operaciones/reportes/form.blade.php'));

        $this->assertStringContainsString('private function resolverIncidencias(', $servicio);
        $this->assertStringContainsString('CentroOperacionesTicket::query()', $servicio);
        $this->assertStringContainsString("'resolucion' => \$resolucion", $servicio);
        $this->assertStringNotContainsString("->whereNotIn('tipo', \$tiposIncidencia)\n            ->delete();", $servicio);
        $this->assertStringContainsString("->where('estado', 'activa')", $formulario);
    }

    public function test_escalamiento_esta_programado(): void
    {
        $console = file_get_contents(base_path('routes/console.php'));
        $this->assertStringContainsString("Schedule::command('incidencias:escalar-tickets')->hourly()", $console);
    }
}
