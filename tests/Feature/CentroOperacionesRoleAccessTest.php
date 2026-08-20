<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SlepUiRegistry;
use Tests\TestCase;

class CentroOperacionesRoleAccessTest extends TestCase
{
    public function test_comunicaciones_is_viewer_and_gabinete_has_total_management(): void
    {
        $roles = config('centro_operaciones.roles_visualizacion');

        $this->assertContains('comunicaciones', $roles);
        $this->assertContains('gabinete_slep', $roles);
        $this->assertContains('gabinete_slep', config('centro_operaciones.roles_gestion_total'));
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
        $this->assertContains('Reporte diario', $labels);
        $this->assertContains('Historial de reportes', $labels);
        $this->assertContains('Riesgo por establecimiento', $labels);
        $this->assertContains('Tickets de incidencias', $labels);
        $this->assertContains('Mantenedor de incidencias', $labels);
        $this->assertContains('Mantenedor de riesgo IRTE', $labels);
        $this->assertContains('Mensajes', $labels);
        $this->assertSame('Gabinete SLEP', User::roleContextLabels()['gabinete_slep']);
    }

    public function test_gabinete_can_access_every_centro_operaciones_route(): void
    {
        $routeNames = [
            'centro-operaciones.index',
            'centro-operaciones.datos',
            'centro-operaciones.tv',
            'centro-operaciones.tickets.index',
            'centro-operaciones.tickets.show',
            'centro-operaciones.tickets.pdf',
            'centro-operaciones.tickets.imagenes.store',
            'centro-operaciones.tickets.imagenes.show',
            'centro-operaciones.tickets.resolver',
            'centro-operaciones.configuraciones.index',
            'centro-operaciones.configuraciones.store',
            'centro-operaciones.configuraciones.update',
            'centro-operaciones.riesgos.index',
            'centro-operaciones.riesgos.evaluar',
            'centro-operaciones.riesgos.evaluaciones.store',
            'centro-operaciones.riesgos.configuracion',
            'centro-operaciones.riesgos.configuracion.versiones.store',
            'centro-operaciones.riesgos.configuracion.update',
            'centro-operaciones.riesgos.configuracion.publicar',
            'centro-operaciones.reportes.create',
            'centro-operaciones.reportes.store',
            'centro-operaciones.reportes.history',
            'centro-operaciones.reportes.show',
            'centro-operaciones.reportes.edit',
            'centro-operaciones.reportes.update',
        ];

        foreach ($routeNames as $routeName) {
            $route = app('router')->getRoutes()->getByName($routeName);
            $roleMiddleware = collect($route?->gatherMiddleware() ?? [])
                ->first(fn (string $item) => str_starts_with($item, 'ensure.role:'));

            $this->assertNotNull($route, "No se encontrÃ³ la ruta {$routeName}.");
            $this->assertNotNull($roleMiddleware, "La ruta {$routeName} no tiene middleware de rol.");
            $this->assertStringContainsString('gabinete_slep', $roleMiddleware, "Gabinete no accede a {$routeName}.");
        }
    }

    public function test_risk_routes_separate_viewers_evaluators_and_maintainer(): void
    {
        $index = app('router')->getRoutes()->getByName('centro-operaciones.riesgos.index');
        $evaluar = app('router')->getRoutes()->getByName('centro-operaciones.riesgos.evaluar');
        $configurar = app('router')->getRoutes()->getByName('centro-operaciones.riesgos.configuracion');

        $this->assertNotNull($index);
        $this->assertNotNull($evaluar);
        $this->assertNotNull($configurar);
        $this->assertStringContainsString('comunicaciones', implode('|', $index->gatherMiddleware()));
        $this->assertStringContainsString('gabinete_slep', implode('|', $evaluar->gatherMiddleware()));
        $this->assertStringNotContainsString('comunicaciones', implode('|', $evaluar->gatherMiddleware()));
        $this->assertStringContainsString('ensure.role:admin|gabinete_slep', implode('|', $configurar->gatherMiddleware()));
    }
}
