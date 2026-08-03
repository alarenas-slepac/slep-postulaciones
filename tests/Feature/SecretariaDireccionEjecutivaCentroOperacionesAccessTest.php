<?php

namespace Tests\Feature;

use App\Support\SlepUiRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SecretariaDireccionEjecutivaCentroOperacionesAccessTest extends TestCase
{
    public function test_role_has_territorial_routes_and_navigation_without_report_editing(): void
    {
        $role = 'secretaria_direccion_ejecutiva';

        $this->assertContains($role, config('centro_operaciones.roles_visualizacion'));

        foreach ([
            'centro-operaciones.index',
            'centro-operaciones.datos',
            'centro-operaciones.tv',
            'centro-operaciones.reportes.history',
            'centro-operaciones.reportes.show',
        ] as $routeName) {
            $route = app('router')->getRoutes()->getByName($routeName);
            $middleware = $route?->gatherMiddleware() ?? [];
            $roleMiddleware = collect($middleware)->first(fn (string $item) => str_starts_with($item, 'ensure.role:'));

            $this->assertNotNull($route, "No se encontró la ruta {$routeName}.");
            $this->assertNotNull($roleMiddleware, "La ruta {$routeName} no tiene middleware de rol.");
            $this->assertStringContainsString($role, $roleMiddleware);
        }

        foreach (['centro-operaciones.reportes.create', 'centro-operaciones.reportes.store', 'centro-operaciones.reportes.edit', 'centro-operaciones.reportes.update'] as $routeName) {
            $route = app('router')->getRoutes()->getByName($routeName);
            $roleMiddleware = collect($route?->gatherMiddleware() ?? [])
                ->first(fn (string $item) => str_starts_with($item, 'ensure.role:'));

            $this->assertNotNull($route, "No se encontró la ruta {$routeName}.");
            $this->assertNotNull($roleMiddleware, "La ruta {$routeName} no tiene middleware de rol.");
            $this->assertStringNotContainsString($role, $roleMiddleware);
        }

        $user = new class
        {
            public function canModule(string $module, ?string $role = null): bool
            {
                return true;
            }
        };

        $labels = collect(SlepUiRegistry::menuGroups($user, $role))->flatten(1)->pluck('label');

        $this->assertContains('Panel territorial', $labels);
        $this->assertContains('Historial de reportes', $labels);
        $this->assertNotContains('Reporte diario', $labels);
    }

    public function test_migration_grants_module_access_idempotently(): void
    {
        $this->createPrerequisites();
        $migration = require database_path('migrations/2026_08_03_150000_grant_operations_center_to_secretaria_direccion_ejecutiva.php');

        try {
            $migration->up();
            $migration->up();

            $moduleId = DB::table('modules')->where('key', 'centro-operaciones')->value('id');
            $roleId = DB::table('roles')->where('name', 'secretaria_direccion_ejecutiva')->value('id');

            $this->assertNotNull($roleId);
            $this->assertDatabaseHas('module_role', ['module_id' => $moduleId, 'role_id' => $roleId]);
            $this->assertSame(1, DB::table('module_role')->count());
        } finally {
            $migration->down();
            Schema::dropIfExists('module_role');
            Schema::dropIfExists('roles');
            Schema::dropIfExists('modules');
        }
    }

    private function createPrerequisites(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('section');
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort')->default(100);
            $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });
        Schema::create('module_role', function (Blueprint $table) {
            $table->foreignId('module_id');
            $table->foreignId('role_id');
            $table->timestamps();
            $table->unique(['module_id', 'role_id']);
        });

        DB::table('modules')->insert([
            'key' => 'centro-operaciones',
            'name' => 'Centro de Operaciones',
            'section' => 'Operación',
            'icon' => 'bi-broadcast-pin',
            'sort' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
