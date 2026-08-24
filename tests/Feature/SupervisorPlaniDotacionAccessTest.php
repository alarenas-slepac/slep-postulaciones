<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DotacionAsignacionController;
use App\Http\Controllers\Admin\DotacionCursoCombinadoController;
use App\Http\Controllers\Admin\DotacionDocenteExclusionController;
use App\Http\Controllers\Admin\DotacionEstablecimientoController;
use App\Http\Controllers\Admin\DotacionProporcionExcepcionController;
use App\Support\ModuleRegistry;
use App\Support\DotacionSobredotacionCalculator;
use App\Support\SlepUiRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionProperty;
use Tests\TestCase;

class SupervisorPlaniDotacionAccessTest extends TestCase
{
    public function test_todas_las_rutas_de_dotacion_establecimiento_permiten_supervisor_plani(): void
    {
        $checked = [];

        foreach (app('router')->getRoutes() as $route) {
            $routeName = $route->getName();
            if (! $routeName || ModuleRegistry::moduleKeyFromRouteName($routeName) !== 'admin.dotacion-establecimiento') {
                continue;
            }

            $roleMiddleware = collect($route->gatherMiddleware())
                ->first(fn (string $middleware) => str_starts_with($middleware, 'ensure.role:'));

            $this->assertNotNull($roleMiddleware, "La ruta {$routeName} no tiene middleware de rol.");
            $this->assertStringContainsString(
                'supervisor_plani',
                $roleMiddleware,
                "La ruta {$routeName} no permite acceso a supervisor_plani."
            );
            $checked[] = $routeName;
        }

        $this->assertGreaterThanOrEqual(14, count($checked));
    }

    public function test_navegacion_expone_dotacion_establecimiento_a_supervisor_plani(): void
    {
        $user = new class
        {
            public function canModule(string $module, ?string $role = null): bool
            {
                return true;
            }
        };

        $menuLabels = collect(SlepUiRegistry::menuGroups($user, 'supervisor_plani'))
            ->flatten(1)
            ->pluck('label');
        $quickLabels = collect(SlepUiRegistry::quickModules($user, 'supervisor_plani'))
            ->pluck('label');

        $this->assertContains('Dotación establecimiento', $menuLabels);
        $this->assertContains('Dotación establecimiento', $quickLabels);
    }

    public function test_controladores_de_dotacion_establecimiento_autorizan_supervisor_plani(): void
    {
        foreach ([
            DotacionEstablecimientoController::class,
            DotacionAsignacionController::class,
            DotacionDocenteExclusionController::class,
            DotacionCursoCombinadoController::class,
            DotacionProporcionExcepcionController::class,
        ] as $controllerClass) {
            $property = new ReflectionProperty($controllerClass, 'allowedRoles');
            $roles = $property->getValue(app($controllerClass));

            $this->assertContains('supervisor_plani', $roles, "{$controllerClass} no autoriza supervisor_plani.");
        }
    }

    public function test_detalle_sobredotacion_restringe_los_roles_autorizados(): void
    {
        foreach (['admin', 'coordinador_gdp', 'supervisor_plani', 'coordinador_uatp'] as $role) {
            $this->assertTrue(DotacionSobredotacionCalculator::canView($role));
        }

        foreach (['funcionario_directivo_estab', 'funcionario_slep', 'coordinador_plani', null] as $role) {
            $this->assertFalse(DotacionSobredotacionCalculator::canView($role));
        }
    }

    public function test_migracion_otorga_modulo_a_supervisor_plani_de_forma_idempotente(): void
    {
        $this->createPrerequisites();
        $migration = require database_path('migrations/2026_08_24_210000_grant_dotacion_establecimiento_access_to_supervisor_plani.php');

        try {
            $migration->up();
            $migration->up();

            $roleId = DB::table('roles')->where('name', 'supervisor_plani')->value('id');
            $moduleId = DB::table('modules')->where('key', 'admin.dotacion-establecimiento')->value('id');
            $this->assertSame(1, DB::table('module_role')
                ->where('role_id', $roleId)
                ->where('module_id', $moduleId)
                ->count());

            $migration->down();
            $this->assertSame(0, DB::table('module_role')
                ->where('role_id', $roleId)
                ->where('module_id', $moduleId)
                ->count());
        } finally {
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
            $table->id();
            $table->foreignId('module_id');
            $table->foreignId('role_id');
            $table->unique(['module_id', 'role_id']);
        });

        DB::table('roles')->insert([
            'name' => 'supervisor_plani',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('modules')->insert([
            'key' => 'admin.dotacion-establecimiento',
            'name' => 'Dotación establecimiento',
            'section' => 'Catálogos',
            'icon' => 'bi-building-check',
            'sort' => 33,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
