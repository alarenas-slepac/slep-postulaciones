<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DotacionFuncionesController;
use App\Support\SlepUiRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionProperty;
use Tests\TestCase;

class DirectorAdpNormativaAccessTest extends TestCase
{
    public function test_solo_los_roles_autorizados_pueden_habilitar_director_adp(): void
    {
        $controller = app(DotacionFuncionesController::class);
        $roles = (new ReflectionProperty(DotacionFuncionesController::class, 'directorAdpRoles'))->getValue($controller);

        $this->assertSame(['admin', 'coordinador_uatp', 'supervisor_plani'], $roles);
        $this->assertContains('supervisor_plani', (new ReflectionProperty(DotacionFuncionesController::class, 'allowedRoles'))->getValue($controller));

        foreach ([
            'admin.dotacion-funciones.index',
            'admin.dotacion-funciones.show',
            'admin.dotacion-funciones.config',
        ] as $routeName) {
            $middleware = collect(app('router')->getRoutes()->getByName($routeName)->gatherMiddleware())
                ->first(fn (string $item) => str_starts_with($item, 'ensure.role:'));

            $this->assertStringContainsString('supervisor_plani', (string) $middleware);
        }
    }

    public function test_navegacion_expone_dotacion_funciones_a_supervisor_plani(): void
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

        $this->assertContains('Dotación funciones y planes', $menuLabels);
        $this->assertContains('Dotación funciones y planes', $quickLabels);
    }

    public function test_migracion_registra_la_regla_y_el_acceso_del_supervisor_de_forma_idempotente(): void
    {
        $this->createPrerequisites();
        $migration = require database_path('migrations/2026_09_21_120000_add_director_adp_normativa_rule.php');

        try {
            $migration->up();
            $migration->up();

            $moduleId = DB::table('modules')->where('key', 'admin.dotacion-funciones')->value('id');
            $roleId = DB::table('roles')->where('name', 'supervisor_plani')->value('id');

            $this->assertDatabaseHas('dotacion_funciones_reglas', [
                'codigo' => 'director_adp',
                'categoria' => 'directiva',
                'tipo_regla' => 'director_adp',
                'horas_fijas' => 44,
                'declarable' => false,
                'vigente' => true,
            ]);
            $this->assertSame(1, DB::table('module_role')
                ->where('module_id', $moduleId)
                ->where('role_id', $roleId)
                ->count());
        } finally {
            Schema::dropIfExists('module_role');
            Schema::dropIfExists('roles');
            Schema::dropIfExists('modules');
            Schema::dropIfExists('dotacion_funciones_reglas');
        }
    }

    private function createPrerequisites(): void
    {
        Schema::create('modules', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('section');
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort')->default(100);
            $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });
        Schema::create('module_role', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('module_id');
            $table->foreignId('role_id');
            $table->unique(['module_id', 'role_id']);
        });
        Schema::create('dotacion_funciones_reglas', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('categoria');
            $table->string('nombre');
            $table->string('tipo_regla');
            $table->unsignedSmallInteger('horas_fijas')->nullable();
            $table->unsignedSmallInteger('horas_minimas')->nullable();
            $table->unsignedSmallInteger('horas_maximas')->nullable();
            $table->unsignedSmallInteger('umbral_matricula')->nullable();
            $table->unsignedSmallInteger('horas_bajo_umbral')->nullable();
            $table->unsignedSmallInteger('horas_sobre_umbral')->nullable();
            $table->boolean('permite_multiples')->default(false);
            $table->boolean('declarable')->default(false);
            $table->boolean('obligatoria')->default(false);
            $table->boolean('requiere_validacion')->default(true);
            $table->text('fundamento')->nullable();
            $table->boolean('vigente')->default(true);
            $table->timestamps();
        });

        DB::table('roles')->insert([
            'name' => 'supervisor_plani',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('modules')->insert([
            'key' => 'admin.dotacion-funciones',
            'name' => 'Dotación funciones y planes',
            'section' => 'Catálogos',
            'icon' => 'bi-diagram-3-fill',
            'sort' => 32,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
