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

    public function test_rutas_de_tickets_mantenedor_y_resolucion_estan_registradas(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));
        $this->assertStringContainsString("name('tickets.index')", $routes);
        $this->assertStringContainsString("name('configuraciones.index')", $routes);
        $this->assertStringContainsString("name('tickets.resolver')", $routes);
    }

    public function test_escalamiento_esta_programado(): void
    {
        $console = file_get_contents(base_path('routes/console.php'));
        $this->assertStringContainsString("Schedule::command('incidencias:escalar-tickets')->hourly()", $console);
    }
}
