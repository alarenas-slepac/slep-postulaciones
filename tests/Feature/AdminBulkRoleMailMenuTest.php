<?php

namespace Tests\Feature;

use App\Support\ModuleRegistry;
use App\Support\SlepUiRegistry;
use Tests\TestCase;

class AdminBulkRoleMailMenuTest extends TestCase
{
    public function test_barra_lateral_ubica_correos_masivos_junto_a_los_accesos_administrativos(): void
    {
        $labels = collect(SlepUiRegistry::menuGroups(null, 'admin')['Administración'] ?? [])
            ->pluck('label')
            ->values();

        $usuarios = $labels->search('Usuarios y roles');
        $correos = $labels->search('Correos masivos por rol');
        $historial = $labels->search('Historial de notificaciones');

        $this->assertIsInt($usuarios);
        $this->assertSame($usuarios + 1, $correos);
        $this->assertSame($correos + 1, $historial);
        $this->assertNotContains(
            'Correos masivos por rol',
            collect(SlepUiRegistry::menuGroups(null, 'funcionario_slep'))->flatten(1)->pluck('label')
        );
    }

    public function test_modulo_y_ruta_conservan_acceso_exclusivo_para_administrador(): void
    {
        $route = app('router')->getRoutes()->getByName('admin.bulk-role-mail.index');
        $middleware = $route?->gatherMiddleware() ?? [];

        $this->assertNotNull($route);
        $this->assertContains('ensure.role:admin', $middleware);
        $this->assertSame('Administración', ModuleRegistry::defaultMeta('admin.bulk-role-mail')['section']);
        $this->assertSame('Correos masivos por rol', ModuleRegistry::defaultMeta('admin.bulk-role-mail')['name']);
    }

    public function test_navegacion_antigua_incluye_el_mismo_acceso(): void
    {
        $navbar = file_get_contents(resource_path('views/partials/navbar.blade.php'));

        $this->assertStringContainsString("Route::has('admin.bulk-role-mail.index')", $navbar);
        $this->assertStringContainsString("route('admin.bulk-role-mail.index')", $navbar);
        $this->assertStringContainsString('Correos masivos por rol', $navbar);
    }
}
