<?php

namespace Tests\Feature;

use App\Models\DotacionFuncionEstablecimiento;
use App\Support\SlepUiRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedSecurityTestCase;

class SupervisorPlaniDotacionActionsTest extends IsolatedSecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::table('roles')->insert([
            ['id' => 4, 'name' => 'supervisor_plani', 'guard_name' => 'web'],
            ['id' => 5, 'name' => 'coordinador_uatp', 'guard_name' => 'web'],
            ['id' => 6, 'name' => 'coordinador_plani', 'guard_name' => 'web'],
        ]);
        Schema::create('modules', function (Blueprint $table): void {
            $table->id(); $table->string('key')->unique(); $table->string('name');
            $table->string('section'); $table->string('icon')->nullable();
            $table->unsignedInteger('sort')->default(100); $table->timestamps();
        });
        Schema::create('module_role', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('module_id'); $table->unsignedBigInteger('role_id');
            $table->timestamps(); $table->unique(['module_id', 'role_id']);
        });
        Schema::create('establecimientos', function (Blueprint $table): void {
            $table->id(); $table->string('nombre_establecimiento');
            $table->boolean('sala_cuna')->default(false);
        });
        DB::table('establecimientos')->insert(['id' => 1, 'nombre_establecimiento' => 'Establecimiento sintético']);
        Schema::create('dotacion_funciones_establecimiento', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('establecimiento_id'); $table->integer('anio');
            $table->string('nombre_funcion'); $table->string('estado');
            $table->string('tipo_coordinacion')->nullable(); $table->text('descripcion_funcion')->nullable();
            $table->integer('horas_declaradas')->nullable(); $table->integer('horas_aprobadas')->nullable();
            $table->text('fundamento')->nullable(); $table->text('observacion')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('validated_by')->nullable(); $table->timestamp('validated_at')->nullable();
            $table->timestamps();
        });
        foreach (['dotacion_contrata_habilitaciones', 'dotacion_sobredotacion_justificaciones'] as $name) {
            Schema::create($name, fn (Blueprint $table) => $table->id());
        }
        Schema::create('establecimiento_curso_pie', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('establecimiento_id'); $table->integer('anio');
        });
    }

    private function grantModules(): object
    {
        $migration = require database_path('migrations/2026_10_02_180000_grant_dotacion_full_access_to_supervisor_plani.php');
        $migration->up();

        return $migration;
    }

    public function test_migracion_completa_nueve_modulos_sin_alterar_permisos_ni_metadatos_historicos(): void
    {
        $historic = '2020-01-01 00:00:00';
        $moduleId = DB::table('modules')->insertGetId([
            'key' => 'admin.cursos', 'name' => 'Nombre histórico', 'section' => 'Sección original',
            'icon' => 'bi-safe', 'sort' => 7, 'created_at' => $historic, 'updated_at' => $historic,
        ]);
        foreach ([4, 5] as $roleId) {
            DB::table('module_role')->insert([
                'module_id' => $moduleId, 'role_id' => $roleId,
                'created_at' => $historic, 'updated_at' => $historic,
            ]);
        }

        $migration = $this->grantModules();
        $migration->up();
        $this->assertSame(9, DB::table('module_role')->where('role_id', 4)->count());
        $this->assertSame(1, DB::table('module_role')->where('role_id', 5)->count());
        $this->assertDatabaseHas('modules', ['id' => $moduleId, 'name' => 'Nombre histórico', 'sort' => 7, 'updated_at' => $historic]);
        $this->assertDatabaseHas('module_role', ['role_id' => 4, 'module_id' => $moduleId, 'created_at' => $historic, 'updated_at' => $historic]);
        $this->assertDatabaseMissing('modules', ['key' => 'admin.users']);
        $migration->down();
        $this->assertSame(9, DB::table('module_role')->where('role_id', 4)->count());
    }

    public function test_usuario_real_tiene_menu_y_accesos_rapidos_de_dotacion(): void
    {
        $this->grantModules();
        $user = $this->testUser(1, 4);
        $this->assertCount(9, $user->allowedModuleKeys('supervisor_plani'));
        $this->assertFalse($user->canModule('admin.users', 'supervisor_plani'));
        $menu = collect(SlepUiRegistry::menuGroups($user, 'supervisor_plani'))->flatten(1)->pluck('label');
        $quick = collect(SlepUiRegistry::quickModules($user, 'supervisor_plani'))->pluck('label');
        foreach (['Cursos', 'Planes de estudio', 'Cursos por establecimiento', 'Estudiantes PIE por curso',
            'Configurar planes EE', 'Asignaturas', 'Asignaturas personalizadas',
            'Dotación funciones y planes', 'Dotación establecimiento'] as $label) {
            $this->assertContains($label, $menu);
            $this->assertContains($label, $quick);
        }
    }

    public function test_rutas_de_escritura_ejecutan_validacion_para_supervisor_con_permisos(): void
    {
        $this->grantModules();
        $this->actingAs($this->testUser(1, 4))->withSession(['active_role' => 'supervisor_plani']);

        foreach (['admin.dotacion-funciones.manual.store',
            'admin.dotacion-establecimiento.contrata-habilitaciones.store',
            'admin.dotacion-establecimiento.sobredotacion.justificaciones.store'] as $name) {
            $this->post(route($name, 1), [])->assertRedirect()->assertSessionHasErrors('anio');
        }
    }

    public function test_supervisor_edita_observa_valida_y_elimina_funcion(): void
    {
        $this->grantModules();
        $user = $this->testUser(1, 4);
        $this->actingAs($user)->withSession(['active_role' => 'supervisor_plani']);
        $function = DotacionFuncionEstablecimiento::create([
            'establecimiento_id' => 1, 'anio' => 2026, 'nombre_funcion' => 'Función sintética', 'estado' => 'en_revision',
        ]);
        $params = [1, $function->id];
        $this->put(route('admin.dotacion-funciones.manual.update', $params), [
            'anio' => 2026, 'nombre_funcion' => 'Función corregida', 'horas_declaradas' => 2,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Función corregida', $function->refresh()->nombre_funcion);
        $this->post(route('admin.dotacion-funciones.manual.observar', $params), [
            'observacion' => 'Revisar antecedente sintético.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('observado', $function->refresh()->estado);
        $this->post(route('admin.dotacion-funciones.manual.validar', $params), [
            'horas_aprobadas' => 2,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('validado_uatp', $function->refresh()->estado);
        $this->assertSame($user->id, $function->validated_by);
        $this->assertNotNull($function->validated_at);
        $this->delete(route('admin.dotacion-funciones.manual.destroy', $params))->assertRedirect();
        $this->assertDatabaseMissing('dotacion_funciones_establecimiento', ['id' => $function->id]);
    }

    public function test_supervisor_no_puede_operar_una_funcion_de_otro_establecimiento_por_url(): void
    {
        $this->grantModules();
        $this->actingAs($this->testUser(1, 4))->withSession(['active_role' => 'supervisor_plani']);
        $function = DotacionFuncionEstablecimiento::create([
            'establecimiento_id' => 2, 'anio' => 2026, 'nombre_funcion' => 'Función ajena a la URL', 'estado' => 'en_revision',
        ]);
        $this->post(route('admin.dotacion-funciones.manual.observar', [1, $function->id]), [
            'observacion' => 'No debe guardarse.',
        ])->assertNotFound();
        $this->assertSame('en_revision', $function->refresh()->estado);
    }

    public function test_supervisor_puede_eliminar_pie_sin_ampliar_permiso_de_uatp(): void
    {
        $this->grantModules();
        DB::table('establecimiento_curso_pie')->insert(['id' => 1, 'establecimiento_id' => 1, 'anio' => 2027]);
        $module = DB::table('modules')->where('key', 'admin.establecimiento-curso-pie')->value('id');
        DB::table('module_role')->insert(['module_id' => $module, 'role_id' => 5]);
        $this->actingAs($this->testUser(1, 5))->withSession(['active_role' => 'coordinador_uatp']);
        $this->delete(route('admin.establecimiento-curso-pie.destroy', 1))->assertForbidden();
        $this->assertDatabaseHas('establecimiento_curso_pie', ['id' => 1]);
        $this->actingAs($this->testUser(2, 4))->withSession(['active_role' => 'supervisor_plani']);
        $this->delete(route('admin.establecimiento-curso-pie.destroy', 1))->assertRedirect();
        $this->assertDatabaseMissing('establecimiento_curso_pie', ['id' => 1]);
    }

    public function test_otro_rol_no_obtiene_permisos_de_escritura_ni_por_url_directa(): void
    {
        $this->grantModules();
        $module = DB::table('modules')->where('key', 'admin.dotacion-funciones')->value('id');
        DB::table('module_role')->insert(['module_id' => $module, 'role_id' => 6]);
        $this->actingAs($this->testUser(1, 6))->withSession(['active_role' => 'coordinador_plani']);
        $this->post(route('admin.dotacion-funciones.manual.store', 1), [])->assertForbidden();
    }

    public function test_no_se_elude_el_permiso_del_modulo(): void
    {
        $this->grantModules();
        DB::table('module_role')->where('role_id', 4)->delete();
        $this->actingAs($this->testUser(1, 4))->withSession(['active_role' => 'supervisor_plani']);
        $this->post(route('admin.dotacion-funciones.manual.store', 1), [])->assertForbidden();
    }

    public static function rolesPlanesEe(): array
    {
        return [
            'administrador' => [3, 'admin'],
            'coordinador UATP' => [5, 'coordinador_uatp'],
            'supervisor planificación' => [4, 'supervisor_plani'],
        ];
    }

    #[DataProvider('rolesPlanesEe')]
    public function test_configurar_planes_ee_permite_consultar_configurar_editar_enviar_y_eliminar(int $roleId, string $role): void
    {
        $this->grantModules();
        (require database_path('migrations/2026_08_21_150000_grant_dotacion_access_to_coordinador_uatp.php'))->up();
        $this->createPlanPrerequisites();
        $user = $this->testUser(1, $roleId);
        $this->actingAs($user)->withSession(['active_role' => $role]);

        $this->get(route('admin.establecimiento-planes.index', ['anio' => 2027]))
            ->assertOk()->assertSee('Plan sintético');
        $this->get(route('admin.establecimiento-planes.configure', 1))->assertRedirect();
        $plan = \App\Models\EstablecimientoPlanEstudio::firstOrFail();
        $this->assertSame($user->id, $plan->created_by);
        $this->assertSame('borrador', $plan->estado);
        $this->get(route('admin.establecimiento-planes.edit', $plan))->assertOk();
        $this->put(route('admin.establecimiento-planes.update', $plan), [
            'action' => 'enviar', 'observacion' => 'Configuración sintética revisada.',
            'detalles' => [[
                'plan_estudio_bloque_id' => 1, 'nombre_asignatura_personalizada' => 'Taller sintético',
                'horas_semanales' => 2,
            ]],
        ])->assertRedirect(route('admin.establecimiento-planes.show', $plan))->assertSessionHasNoErrors();
        $this->assertSame('enviado', $plan->refresh()->estado);
        $this->assertNotNull($plan->submitted_at);
        $this->assertDatabaseHas('establecimiento_planes_estudio_asignaturas', [
            'establecimiento_plan_estudio_id' => $plan->id,
            'nombre_asignatura_personalizada' => 'Taller sintético', 'horas_semanales' => 2,
        ]);
        $this->get(route('admin.establecimiento-planes.show', $plan))->assertOk()->assertSee('Taller sintético');
        $this->delete(route('admin.establecimiento-planes.destroy', $plan))->assertRedirect();
        $this->assertDatabaseMissing('establecimiento_planes_estudio', ['id' => $plan->id]);
    }

    private function createPlanPrerequisites(): void
    {
        Schema::table('establecimientos', function (Blueprint $table): void {
            $table->integer('rbd')->default(99999); $table->string('comuna')->default('Comuna sintética');
        });
        Schema::create('cursos', function (Blueprint $table): void {
            $table->id(); $table->string('nombre'); $table->boolean('activo')->default(true);
            $table->integer('orden')->default(1);
        });
        Schema::create('planes_estudio', function (Blueprint $table): void {
            $table->id(); $table->string('nombre_plan'); $table->decimal('horas_semanales_total')->default(2);
        });
        Schema::create('establecimiento_cursos', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('establecimiento_id'); $table->integer('rbd')->default(99999);
            $table->unsignedBigInteger('curso_id'); $table->unsignedBigInteger('plan_estudio_id');
            $table->integer('anio'); $table->string('letra')->default('A');
            $table->string('nombre_seccion')->nullable(); $table->integer('matricula')->default(10);
            $table->string('regimen_jec')->default('con_jec'); $table->boolean('activo')->default(true);
        });
        Schema::create('establecimiento_planes_estudio', function (Blueprint $table): void {
            $table->id();
            foreach (['establecimiento_id', 'establecimiento_curso_id', 'plan_estudio_id', 'curso_id', 'created_by'] as $field) {
                $table->unsignedBigInteger($field);
            }
            $table->integer('anio'); $table->string('estado'); $table->text('observacion')->nullable();
            $table->timestamp('submitted_at')->nullable(); $table->timestamps();
        });
        Schema::create('planes_estudio_bloques', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('plan_estudio_id'); $table->string('nombre');
            $table->string('tipo_bloque'); $table->decimal('horas_semanales')->default(2);
            $table->boolean('permite_asignaturas_establecimiento')->default(true);
            $table->boolean('permite_asignaturas_personalizadas')->default(true);
            $table->boolean('activo')->default(true); $table->integer('orden')->default(1);
        });
        Schema::create('planes_estudio_asignaturas', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('plan_estudio_id'); $table->integer('orden')->default(1);
        });
        Schema::create('asignaturas', function (Blueprint $table): void {
            $table->id(); $table->string('nombre'); $table->string('codigo')->nullable();
            $table->string('tipo_asignatura'); $table->string('nivel_educativo'); $table->string('area');
            $table->boolean('activo')->default(true);
        });
        Schema::create('establecimiento_planes_estudio_asignaturas', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('establecimiento_plan_estudio_id');
            $table->unsignedBigInteger('plan_estudio_bloque_id');
            $table->unsignedBigInteger('asignatura_id')->nullable(); $table->unsignedBigInteger('asignatura_plan_comun_id')->nullable();
            $table->string('nombre_asignatura_personalizada')->nullable(); $table->decimal('horas_semanales');
            $table->decimal('horas_anuales')->nullable(); $table->string('origen');
            $table->text('observacion')->nullable(); $table->integer('orden'); $table->timestamps();
        });
        DB::table('cursos')->insert(['id' => 1, 'nombre' => 'Curso sintético']);
        DB::table('planes_estudio')->insert(['id' => 1, 'nombre_plan' => 'Plan sintético']);
        DB::table('establecimiento_cursos')->insert([
            'id' => 1, 'establecimiento_id' => 1, 'curso_id' => 1, 'plan_estudio_id' => 1, 'anio' => 2027,
        ]);
        DB::table('planes_estudio_bloques')->insert([
            'id' => 1, 'plan_estudio_id' => 1, 'nombre' => 'Libre disposición', 'tipo_bloque' => 'libre_disposicion',
        ]);
    }
}
