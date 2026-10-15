<?php

namespace Tests\Feature;

use App\Models\DotacionEstablecimientoConfiguracion;
use App\Models\DotacionFuncionEstablecimiento;
use App\Models\DotacionFuncionRegla;
use App\Models\Establecimiento;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionFuncionesCalculator;
use App\Support\DotacionProceso2027Calculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionCoordinacionPieHorasTest extends IsolatedSecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->app['request']->setLaravelSession($this->app['session.store']);
        Storage::disk('local')->put('pie-views/layouts/app.blade.php', '<html><body>@yield(\'content\')</body></html>');
        $this->app['view']->getFinder()->prependLocation(Storage::disk('local')->path('pie-views'));
        Schema::create('establecimientos', function (Blueprint $table): void {
            $table->id(); $table->integer('rbd'); $table->string('nombre_establecimiento');
            $table->string('comuna')->nullable(); $table->boolean('sala_cuna')->default(false);
        });
        DB::table('establecimientos')->insert([
            ['id' => 1, 'rbd' => 99999, 'nombre_establecimiento' => 'Establecimiento sintético uno'],
            ['id' => 2, 'rbd' => 99998, 'nombre_establecimiento' => 'Establecimiento sintético dos'],
        ]);
        Schema::create('establecimiento_cursos', function (Blueprint $table): void {
            $table->id(); $table->integer('establecimiento_id'); $table->integer('anio');
            $table->integer('curso_id')->nullable(); $table->integer('plan_estudio_id')->nullable();
            $table->boolean('activo')->default(true); $table->integer('matricula')->default(20);
        });
        Schema::create('establecimiento_curso_pie', function (Blueprint $table): void {
            $table->id(); $table->integer('establecimiento_id'); $table->integer('establecimiento_curso_id');
            $table->integer('anio'); $table->integer('total_pie')->default(1);
        });
        foreach ([1, 2] as $ee) {
            foreach ([2026, 2027] as $year) {
                for ($i = 0; $i < 3; $i++) {
                    $id = DB::table('establecimiento_cursos')->insertGetId(['establecimiento_id' => $ee, 'anio' => $year]);
                    DB::table('establecimiento_curso_pie')->insert(['establecimiento_id' => $ee, 'anio' => $year, 'establecimiento_curso_id' => $id]);
                }
            }
        }
        (require database_path('migrations/2026_05_26_190000_create_dotacion_funciones_tables.php'))->up();
        (require database_path('migrations/2026_05_25_183000_create_docente_horas_proporciones_table.php'))->up();
        (require database_path('migrations/2026_10_15_170000_add_horas_coordinacion_pie_to_dotacion_configuraciones.php'))->up();
        DB::table('dotacion_funciones_reglas')->whereNotIn('codigo', ['coordinador_pie', 'pise'])->update(['vigente' => false]);
        Schema::create('modules', function (Blueprint $table): void { $table->id(); $table->string('key'); });
        Schema::table('users', function (Blueprint $table): void { $table->integer('establecimiento_id')->nullable(); });
        $this->actingAs($this->testUser(role: 3))->withSession(['active_role' => 'admin']);
    }

    public function test_sin_ajuste_conserva_calculo_automatico_y_datos_historicos(): void
    {
        $this->assertSame(6, $this->coordinacion()['horas_sugeridas']);
        $this->assertFalse($this->coordinacion()['ajuste_administrativo']);
        DotacionEstablecimientoConfiguracion::create(['establecimiento_id' => 1, 'anio' => 2027]);
        $this->assertSame(6, $this->coordinacion()['horas_sugeridas']);
        $this->assertSame(6, $this->coordinacion(2026)['horas_sugeridas']);
    }

    public static function ajustes(): array
    {
        return ['reducir' => [2], 'aumentar' => [50], 'sin horas' => [0]];
    }

    #[DataProvider('ajustes')]
    public function test_administrador_modifica_horas_del_establecimiento_y_anio(int $hours): void
    {
        $this->guardar(['horas_coordinacion_pie' => $hours])->assertRedirect()->assertSessionHasNoErrors();
        $config = DotacionEstablecimientoConfiguracion::sole();
        $this->assertSame($hours, $config->horas_coordinacion_pie);
        $this->assertSame(1, $config->updated_by);
        $this->assertSame(2027, $config->anio);
        $this->assertSame($hours, $this->coordinacion()['horas_sugeridas']);
        $this->assertSame(6, $this->coordinacion()['horas_potenciales']);
        $this->assertTrue($this->coordinacion()['ajuste_administrativo']);
        $this->assertSame(6, $this->coordinacion(2026)['horas_sugeridas']);
        $this->assertSame(6, $this->coordinacion(2027, 2)['horas_sugeridas']);
        $this->assertSame(3, DotacionFuncionesCalculator::sugerencias($this->ee(), 2027)->firstWhere('codigo', 'pise')['horas_sugeridas']);
    }

    public function test_campo_vacio_restablece_calculo_que_sigue_los_cursos_nee(): void
    {
        $this->guardar(['horas_coordinacion_pie' => 0])->assertSessionHasNoErrors();
        $this->guardar(['horas_coordinacion_pie' => ''])->assertSessionHasNoErrors();
        $this->assertNull(DotacionEstablecimientoConfiguracion::sole()->horas_coordinacion_pie);
        $this->assertSame(6, $this->coordinacion()['horas_sugeridas']);
        DB::table('establecimiento_curso_pie')->where('establecimiento_id', 1)->where('anio', 2027)->limit(1)->update(['total_pie' => 0]);
        $this->assertSame(4, $this->coordinacion()['horas_sugeridas']);
    }

    public static function otrosRoles(): array
    {
        return [['funcionario_directivo_estab'], ['coordinador_uatp'], ['supervisor_plani'], ['coordinador_gdp']];
    }

    #[DataProvider('otrosRoles')]
    public function test_otro_rol_activo_no_puede_modificar_ni_borrar_ajuste_aunque_usuario_sea_admin(string $role): void
    {
        $this->guardar(['horas_coordinacion_pie' => 8])->assertSessionHasNoErrors();
        $this->usarRol($role);
        foreach ([2, null] as $hours) {
            $this->guardar(['horas_coordinacion_pie' => $hours])->assertForbidden();
            $this->assertSame(8, DotacionEstablecimientoConfiguracion::sole()->horas_coordinacion_pie);
        }
        $this->assertStringNotContainsString('name="horas_coordinacion_pie"', $this->vista($role));
    }

    public function test_guardar_otros_parametros_conserva_ajuste_y_creador(): void
    {
        $this->guardar(['horas_coordinacion_pie' => 12, 'director_adp' => 1, 'observacion' => 'Parámetro sintético'])->assertSessionHasNoErrors();
        $this->usarRol('coordinador_uatp');
        $this->guardar(['observacion' => 'Observación actualizada'])->assertRedirect()->assertSessionHasNoErrors();
        $config = DotacionEstablecimientoConfiguracion::sole();
        $this->assertSame(12, $config->horas_coordinacion_pie);
        $this->assertTrue($config->director_adp);
        $this->assertSame('Observación actualizada', $config->observacion);
        $this->assertSame(1, $config->created_by);
    }

    public static function valoresInvalidos(): array
    {
        return [[-1], [2.5], [10000], ['no-numérico']];
    }

    #[DataProvider('valoresInvalidos')]
    public function test_rechaza_valores_invalidos_sin_alterar_parametros(mixed $hours): void
    {
        $this->guardar(['horas_coordinacion_pie' => 8, 'observacion' => 'Parámetro original'])->assertSessionHasNoErrors();
        $before = DotacionEstablecimientoConfiguracion::sole()->getAttributes();
        $this->from('/configurar-pie')->guardar(['horas_coordinacion_pie' => $hours, 'observacion' => 'Cambio inválido'])
            ->assertRedirect('/configurar-pie')->assertSessionHasErrors('horas_coordinacion_pie')
            ->assertSessionHasInput('horas_coordinacion_pie', $hours);
        $this->assertSame($before, DotacionEstablecimientoConfiguracion::sole()->getAttributes());
    }

    public function test_necesidad_pie_y_proceso_guiado_usan_ajuste_sin_modificar_asignaciones(): void
    {
        $bloques = DotacionEstablecimientoCalculator::bloquesDotacion($this->ee(), 2027);
        $this->assertEquals(6, $bloques['pie']['total']);
        $ruleId = DotacionFuncionRegla::where('codigo', 'coordinador_pie')->sole()->id;
        $assignment = (object) ['id' => 91, 'estado' => 'activa', 'tipo_asignacion' => 'funcion_tecnico_pedagogica',
            'subtipo_asignacion' => 'pie', 'dotacion_funcion_regla_id' => $ruleId, 'horas_contrato' => 6,
            'docente_rut_normalizado' => '99000001K', 'estamento_cobertura' => 'docente'];
        $before = clone $assignment;
        $this->guardar(['horas_coordinacion_pie' => 2])->assertSessionHasNoErrors();
        $bloques = DotacionEstablecimientoCalculator::bloquesDotacion($this->ee(), 2027);
        $this->assertEquals(2, $bloques['pie']['total']);
        $needs = (new ReflectionMethod(DotacionAsignacionCalculator::class, 'necesidadesFunciones'))
            ->invoke(null, $bloques, collect([$assignment]));
        $need = $needs->firstWhere('codigo', 'coordinador_pie');
        $this->assertSame(2.0, $need['horas_contrato_requeridas']);
        $this->assertSame(6.0, $need['horas_contrato_asignadas']);
        $this->assertSame('excedida', $need['estado']['key']);
        $this->assertStringContainsString('Administración para 2027: 2 h', $need['fuente']);
        $process = DotacionProceso2027Calculator::resumen($this->ee(), 2027, [
            'asignacion' => ['necesidades' => ['funciones' => $needs], 'asignaciones' => collect([$assignment])],
        ]);
        $this->assertSame(2.0, $process['bloques']['bloque_3']['requeridas']);
        $this->assertEquals($before, $assignment);
    }

    public function test_formulario_admin_distingue_automatico_ajuste_y_cero(): void
    {
        $automatic = $this->vista('admin');
        $this->assertStringContainsString('name="horas_coordinacion_pie"', $automatic);
        $this->assertStringContainsString('placeholder="Automático: 6 h"', $automatic);
        $this->guardar(['horas_coordinacion_pie' => 0])->assertSessionHasNoErrors();
        $html = $this->vista('admin');
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $field = $xpath->query('//input[@name="horas_coordinacion_pie"]')->item(0);
        $this->assertSame('0', $field->getAttribute('value'));
        $this->assertSame(1, $xpath->query('//label[@for="'.$field->getAttribute('id').'"]')->length);
        $this->assertStringContainsString('Definidas por Administración: 0 h para 2027.', $html);
        $this->assertStringContainsString('Deje el campo vacío', $html);
    }

    public function test_proceso_guiado_ofrece_acceso_al_ajuste_solo_al_administrador(): void
    {
        $process = DotacionProceso2027Calculator::resumen($this->ee(), 2027, ['asignacion' => ['necesidades' => []]]);
        foreach (['admin', 'funcionario_directivo_estab', 'coordinador_uatp', 'supervisor_plani', 'coordinador_gdp'] as $role) {
            $html = view('admin.dotacion-establecimiento.partials._proceso_2027', [
                'establecimiento' => $this->ee(), 'anio' => 2027, 'tab' => 'sobredotacion',
                'activeRole' => $role, 'proceso2027' => $process,
            ])->render();
            if ($role === 'admin') {
                $this->assertStringContainsString('Ajustar coordinación PIE', $html);
                $this->assertStringContainsString('tab_origen=sobredotacion#coordinacion-pie-config', $html);
            } else {
                $this->assertStringNotContainsString('Ajustar coordinación PIE', $html);
            }
        }
    }

    public function test_migracion_es_idempotente_y_conserva_configuraciones_previas(): void
    {
        $migration = require database_path('migrations/2026_10_15_170000_add_horas_coordinacion_pie_to_dotacion_configuraciones.php');
        $migration->down();
        $config = DotacionEstablecimientoConfiguracion::create(['establecimiento_id' => 1, 'anio' => 2026, 'director_adp' => true, 'observacion' => 'Histórico sintético']);
        $before = $config->fresh()->getAttributes();
        $migration->up(); $migration->up();
        $after = $config->fresh()->getAttributes();
        $this->assertNull($after['horas_coordinacion_pie']);
        unset($after['horas_coordinacion_pie']);
        $this->assertSame($before, $after);
    }

    public function test_sin_migracion_lectura_conserva_automatico_y_escritura_informa_dependencia(): void
    {
        (require database_path('migrations/2026_10_15_170000_add_horas_coordinacion_pie_to_dotacion_configuraciones.php'))->down();
        DotacionEstablecimientoConfiguracion::create(['establecimiento_id' => 1, 'anio' => 2027]);
        $this->assertSame(6, $this->coordinacion()['horas_sugeridas']);
        $this->guardar(['horas_coordinacion_pie' => 8])->assertStatus(503);
        $this->assertSame(1, DotacionEstablecimientoConfiguracion::count());
    }

    private function guardar(array $fields): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('admin.dotacion-funciones.config', 1), ['anio' => 2027, 'formulario' => 'parametros'] + $fields);
    }

    private function ee(int $id = 1): Establecimiento
    {
        return Establecimiento::findOrFail($id);
    }

    private function coordinacion(int $year = 2027, int $ee = 1): array
    {
        return DotacionFuncionesCalculator::sugerencias($this->ee($ee), $year)->firstWhere('codigo', 'coordinador_pie');
    }

    private function usarRol(string $role): void
    {
        $roleId = DB::table('roles')->insertGetId(['name' => $role, 'guard_name' => 'web']);
        $user = auth()->user();
        $user->forceFill(['establecimiento_id' => 1])->save();
        DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => $user::class, 'model_id' => $user->id]);
        $user->unsetRelation('roles');
        $this->withSession(['active_role' => $role]);
    }

    private function vista(string $role): string
    {
        return view('admin.dotacion-funciones.show', [
            'establecimiento' => $this->ee(), 'anio' => 2027, 'activeRole' => $role, 'errors' => new ViewErrorBag,
            'volverUrl' => '/prueba', 'accionesContexto' => [], 'contexto' => DotacionFuncionesCalculator::contexto($this->ee(), 2027),
            'config' => DotacionEstablecimientoConfiguracion::first() ?? new DotacionEstablecimientoConfiguracion,
            'resumen' => ['horas_totales' => $this->coordinacion()['horas_sugeridas'] + 3],
            'categorias' => DotacionFuncionEstablecimiento::CATEGORIAS, 'bloquesConsolidados' => [], 'manuales' => [],
            'sugerencias' => DotacionFuncionesCalculator::sugerencias($this->ee(), 2027)->groupBy('categoria'),
            'canEdit' => true, 'canValidate' => false, 'canConfigureDirectorAdp' => $role === 'admin',
            'canConfigureCoordinacionPie' => $role === 'admin', 'proceso2027' => ['aplica' => true, 'funciones_no_normativas_habilitadas' => false],
        ])->render();
    }
}
