<?php

namespace Tests\Feature;

use App\Support\ModuleRegistry;
use App\Support\SlepUiRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CoordinadorUatpDotacionAccessTest extends TestCase
{
    private const MODULE_KEYS = [
        'admin.cursos',
        'admin.planes-estudio',
        'admin.establecimiento-cursos',
        'admin.establecimiento-curso-pie',
        'admin.establecimiento-planes',
        'admin.asignaturas',
        'admin.asignaturas-personalizadas',
        'admin.dotacion-funciones',
        'admin.dotacion-establecimiento',
    ];

    private const MENU_LABELS = [
        'Cursos',
        'Planes de estudio',
        'Cursos por establecimiento',
        'Estudiantes PIE por curso',
        'Configurar planes EE',
        'Asignaturas',
        'Asignaturas personalizadas',
        'Dotación funciones y planes',
        'Dotación establecimiento',
    ];

    public function test_all_dotacion_routes_allow_coordinador_uatp(): void
    {
        $checked = [];

        foreach (app('router')->getRoutes() as $route) {
            $routeName = $route->getName();
            if (! $routeName || ! in_array(ModuleRegistry::moduleKeyFromRouteName($routeName), self::MODULE_KEYS, true)) {
                continue;
            }

            $roleMiddleware = collect($route->gatherMiddleware())
                ->first(fn (string $middleware) => str_starts_with($middleware, 'ensure.role:'));

            $this->assertNotNull($roleMiddleware, "La ruta {$routeName} no tiene middleware de rol.");
            $this->assertStringContainsString(
                'coordinador_uatp',
                $roleMiddleware,
                "La ruta {$routeName} no permite acceso a coordinador_uatp."
            );

            $checked[] = $routeName;
        }

        $this->assertGreaterThanOrEqual(70, count($checked));
    }

    public function test_navigation_exposes_all_dotacion_entries_for_coordinador_uatp(): void
    {
        $user = new class
        {
            public function canModule(string $module, ?string $role = null): bool
            {
                return true;
            }
        };

        $menuLabels = collect(SlepUiRegistry::menuGroups($user, 'coordinador_uatp'))
            ->flatten(1)
            ->pluck('label');
        $quickLabels = collect(SlepUiRegistry::quickModules($user, 'coordinador_uatp'))
            ->pluck('label');

        foreach (self::MENU_LABELS as $label) {
            $this->assertContains($label, $menuLabels);
            $this->assertContains($label, $quickLabels);
        }
    }

    public function test_migration_grants_every_dotacion_module_idempotently(): void
    {
        $this->createPrerequisites();
        $migration = require database_path('migrations/2026_08_21_150000_grant_dotacion_access_to_coordinador_uatp.php');

        try {
            $migration->up();
            $migration->up();

            $roleId = DB::table('roles')->where('name', 'coordinador_uatp')->value('id');
            $moduleIds = DB::table('modules')->whereIn('key', self::MODULE_KEYS)->pluck('id');

            $this->assertCount(count(self::MODULE_KEYS), $moduleIds);
            $this->assertSame(
                count(self::MODULE_KEYS),
                DB::table('module_role')
                    ->where('role_id', $roleId)
                    ->whereIn('module_id', $moduleIds)
                    ->count()
            );
            $this->assertDatabaseHas('modules', [
                'key' => 'admin.cursos',
                'name' => 'Catálogo histórico de cursos',
                'section' => 'Configuración pedagógica',
                'icon' => 'bi-safe',
                'sort' => 7,
            ]);

            $migration->down();

            $this->assertSame(0, DB::table('module_role')->where('role_id', $roleId)->count());
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
            'name' => 'coordinador_uatp',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('modules')->insert([
            'key' => 'admin.cursos',
            'name' => 'Catálogo histórico de cursos',
            'section' => 'Configuración pedagógica',
            'icon' => 'bi-safe',
            'sort' => 7,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
