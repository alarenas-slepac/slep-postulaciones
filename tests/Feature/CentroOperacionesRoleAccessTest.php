<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SlepUiRegistry;
use Tests\TestCase;

class CentroOperacionesRoleAccessTest extends TestCase
{
    public function test_comunicaciones_and_gabinete_are_territorial_viewer_roles(): void
    {
        $roles = config('centro_operaciones.roles_visualizacion');

        $this->assertContains('comunicaciones', $roles);
        $this->assertContains('gabinete_slep', $roles);
    }

    public function test_panel_history_and_detail_routes_allow_both_roles(): void
    {
        foreach (['centro-operaciones.index', 'centro-operaciones.reportes.history', 'centro-operaciones.reportes.show'] as $routeName) {
            $route = app('router')->getRoutes()->getByName($routeName);
            $middleware = $route?->gatherMiddleware() ?? [];
            $roleMiddleware = collect($middleware)->first(fn (string $item) => str_starts_with($item, 'ensure.role:'));

            $this->assertNotNull($route, "No se encontró la ruta {$routeName}.");
            $this->assertNotNull($roleMiddleware, "La ruta {$routeName} no tiene middleware de rol.");
            $this->assertStringContainsString('comunicaciones', $roleMiddleware);
            $this->assertStringContainsString('gabinete_slep', $roleMiddleware);
        }
    }

    public function test_navigation_exposes_requested_modules_for_gabinete(): void
    {
        $user = new class
        {
            public function canModule(string $module, ?string $role = null): bool
            {
                return true;
            }
        };

        $labels = collect(SlepUiRegistry::menuGroups($user, 'gabinete_slep'))
            ->flatten(1)
            ->pluck('label');

        $this->assertContains('Panel territorial', $labels);
        $this->assertContains('Historial de reportes', $labels);
        $this->assertContains('Mensajes', $labels);
        $this->assertSame('Gabinete SLEP', User::roleContextLabels()['gabinete_slep']);
    }
}
