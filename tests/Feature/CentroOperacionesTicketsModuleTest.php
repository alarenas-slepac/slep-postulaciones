<?php

namespace Tests\Feature;

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
        $this->assertStringContainsString("name('tickets.resolver')", $routes);
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
