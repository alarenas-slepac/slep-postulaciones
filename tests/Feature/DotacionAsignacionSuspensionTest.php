<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DotacionAsignacionController;
use App\Http\Controllers\Admin\DotacionEstablecimientoController;
use App\Http\Controllers\Admin\DotacionFuncionesController;
use App\Http\Controllers\Admin\DotacionProceso2027Controller;
use App\Http\Controllers\Admin\EstablecimientoPlanEstudioController;
use App\Http\Middleware\CoordinarEscrituraPadron;
use App\Models\DotacionDocenteAsignacion;
use App\Models\Establecimiento;
use App\Models\User;
use App\Support\DotacionAsignacionSuspension;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionAsignacionSuspensionTest extends IsolatedSecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-09 12:00:00', 'America/Santiago'));
        $this->withoutMiddleware(CoordinarEscrituraPadron::class); // El bloqueo MySQL se prueba en su propia suite.
        $this->app['request']->setLaravelSession($this->app['session.store']);
        Schema::table('users', fn (Blueprint $t) => $t->integer('establecimiento_id')->nullable());
        DB::table('roles')->insert([
            ['id' => 4, 'name' => 'funcionario_directivo_estab', 'guard_name' => 'web'],
            ['id' => 5, 'name' => 'coordinador_uatp', 'guard_name' => 'web'],
            ['id' => 6, 'name' => 'coordinador_gdp', 'guard_name' => 'web'],
            ['id' => 7, 'name' => 'supervisor_plani', 'guard_name' => 'web'],
        ]);
        Schema::create('modules', function (Blueprint $t): void { $t->id(); $t->string('key'); });
        Schema::create('module_role', function (Blueprint $t): void { $t->integer('module_id'); $t->integer('role_id'); });
        // Sin entrada de módulo, conserva el comportamiento habitual de EnsureModuleAccess.
        Schema::create('establecimientos', function (Blueprint $t): void { $t->id(); });
        DB::table('establecimientos')->insert(['id' => 1]);
        Schema::create('dotacion_docente_asignaciones', function (Blueprint $t): void {
            $t->id(); $t->integer('anio'); $t->integer('establecimiento_id');
            $t->string('docente_nombre'); $t->string('docente_rut');
            $t->string('tipo_asignacion'); $t->string('subtipo_asignacion')->nullable();
            $t->string('asignatura_nombre'); $t->string('subvencion');
            $t->decimal('horas_contrato', 8, 2); $t->decimal('horas_plan_pedagogicas', 8, 2)->nullable();
        });
        DB::table('dotacion_docente_asignaciones')->insert([
            'id' => 1, 'anio' => 2027, 'establecimiento_id' => 1, 'docente_nombre' => 'Docente de prueba',
            'docente_rut' => '99000001K', 'tipo_asignacion' => 'plan_estudio', 'asignatura_nombre' => 'Matemática',
            'subvencion' => 'General', 'horas_contrato' => 4, 'horas_plan_pedagogicas' => 3,
        ]);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    public static function escrituras(): array
    {
        return [
            ['POST', 'store', 'store', false],
            ['POST', 'reservas.store', 'storeReserva', false],
            ['POST', 'reservas.vincular', 'vincularReserva', true],
            ['PUT', 'update', 'update', true],
            ['DELETE', 'destroy', 'destroy', true],
            ['DELETE', 'curso-bloque.destroy', 'destroyCourseBlock', false],
            ['DELETE', 'fantasmas.destroy', 'destroyGhostAssignments', false],
        ];
    }

    #[DataProvider('escrituras')]
    public function test_bloquea_todas_las_acciones_http_sin_alterar_asignaciones(string $method, string $route, string $action, bool $record): void
    {
        $this->entrar();
        $antes = DB::table('dotacion_docente_asignaciones')->get()->toJson();
        $this->partialMock(DotacionAsignacionController::class)->shouldReceive($action)->never();
        $url = route('admin.dotacion-establecimiento.asignaciones.'.$route, $record ? [1, 1] : [1]);
        // Un año falso no elude la suspensión de un registro del año 2027.
        $this->json($method, $url, ['anio' => $record ? 2026 : 2027])
            ->assertUnprocessable()->assertJsonValidationErrors('asignaciones');
        $this->assertSame($antes, DB::table('dotacion_docente_asignaciones')->get()->toJson());
    }

    public function test_formulario_antiguo_redirige_con_mensaje_y_cuenta_admin_con_rol_directivo_no_elude_bloqueo(): void
    {
        $user = $this->entrar();
        DB::table('model_has_roles')->insert(['role_id' => 3, 'model_type' => User::class, 'model_id' => $user->id]);
        $user->unsetRelation('roles');
        $url = route('admin.dotacion-establecimiento.show', [1, 'anio' => 2027, 'tab' => 'asignacion']);
        $this->from($url)->post(route('admin.dotacion-establecimiento.asignaciones.store', 1), ['anio' => 2027])
            ->assertRedirect($url)->assertSessionHasErrors('asignaciones');
        $this->assertSame(4.0, (float) DotacionDocenteAsignacion::findOrFail(1)->horas_contrato);
    }

    public static function roles(): array
    {
        return [[3, 'admin'], [5, 'coordinador_uatp'], [6, 'coordinador_gdp'], [7, 'supervisor_plani']];
    }

    #[DataProvider('roles')]
    public function test_otros_roles_conservan_edicion(int $id, string $rol): void
    {
        $this->entrar($id, $rol);
        $this->partialMock(DotacionAsignacionController::class)->shouldReceive('update')->once()->andReturn(redirect('/disponible'));
        $this->put(route('admin.dotacion-establecimiento.asignaciones.update', [1, 1]), [])->assertRedirect('/disponible');
        $this->assertFalse(DotacionAsignacionSuspension::bloqueada(2027, $rol));
    }

    public function test_directivo_conserva_edicion_2026_y_reabre_2027_a_medianoche_de_chile(): void
    {
        $this->entrar();
        $this->partialMock(DotacionAsignacionController::class)->shouldReceive('update')->twice()->andReturn(redirect('/disponible'));
        DB::table('dotacion_docente_asignaciones')->where('id', 1)->update(['anio' => 2026]);
        $this->put(route('admin.dotacion-establecimiento.asignaciones.update', [1, 1]))->assertRedirect('/disponible');
        DB::table('dotacion_docente_asignaciones')->where('id', 1)->update(['anio' => 2027]);
        // Servidor con reloj UTC: corresponde a las 00:00 en Santiago.
        $this->travelTo(Carbon::parse('2026-10-13 03:00:00', 'UTC'));
        $this->put(route('admin.dotacion-establecimiento.asignaciones.update', [1, 1]))->assertRedirect('/disponible');
    }

    public function test_limites_temporales_son_inclusivo_al_iniciar_y_exclusivo_al_reabrir_y_configuracion_es_controlable(): void
    {
        foreach ([
            ['2026-10-08 23:59:59', false], ['2026-10-09 00:00:00', true],
            ['2026-10-12 23:59:59', true], ['2026-10-13 00:00:00', false],
        ] as [$fecha, $bloqueada]) {
            $this->travelTo(Carbon::parse($fecha, 'America/Santiago'));
            $this->assertSame($bloqueada, DotacionAsignacionSuspension::bloqueada(2027, 'funcionario_directivo_estab'));
            foreach ([2026, 2028] as $anio) {
                $this->assertFalse(DotacionAsignacionSuspension::bloqueada($anio, 'funcionario_directivo_estab'));
            }
        }
        $this->travelTo(Carbon::parse('2026-10-10 12:00:00', 'America/Santiago'));
        config(['dotacion_asignacion.suspension_temporal.habilitada' => false]);
        $this->assertFalse(DotacionAsignacionSuspension::bloqueada(2027, 'funcionario_directivo_estab'));
    }

    public function test_lectura_y_otras_etapas_no_son_bloqueadas(): void
    {
        $this->entrar();
        $this->partialMock(DotacionEstablecimientoController::class)->shouldReceive('show')->once()->andReturn(response('Consulta'));
        $this->get(route('admin.dotacion-establecimiento.show', [1, 'anio' => 2027]))->assertOk();
        $this->partialMock(DotacionProceso2027Controller::class)->shouldReceive('update')->once()->andReturn(redirect('/etapas'));
        $this->post(route('admin.dotacion-establecimiento.proceso-2027.update', 1), ['anio' => 2027])->assertRedirect('/etapas');
        $this->partialMock(DotacionFuncionesController::class)->shouldReceive('updateConfig')->once()->andReturn(response('Funciones'));
        $this->post(route('admin.dotacion-funciones.config', 1), ['anio' => 2027])->assertOk();
        $this->partialMock(EstablecimientoPlanEstudioController::class)->shouldReceive('index')->once()->andReturn(response('Planes'));
        $this->get(route('admin.establecimiento-planes.index', ['anio' => 2027]))->assertOk();
    }

    public function test_vista_conserva_datos_filtros_y_totales_sin_formularios_y_los_restituye_al_reabrir(): void
    {
        $data = $this->datosVista();
        $html = view('admin.dotacion-establecimiento.partials._asignacion', $data)->render();
        $xpath = $this->xpath($html);
        $this->assertSame(0, $xpath->query('//form')->length);
        $this->assertSame(0, $xpath->query('//details[@data-dotacion-editor]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-asignaciones-solo-consulta]')->length);
        $this->assertGreaterThan(0, $xpath->query('//*[@data-dotacion-filters] | //*[@data-dotacion-filter]')->length);
        foreach (['Matemática', 'Trabajo colaborativo de prueba', 'Dirección de prueba', 'Función de prueba', 'Docente de prueba', 'Horas fantasmas', 'Horas reservadas sin función', '13/10/2026 00:00'] as $texto) {
            $this->assertStringContainsString($texto, $html);
        }
        foreach (self::roles() as [, $rol]) {
            $xpath = $this->xpath(view('admin.dotacion-establecimiento.partials._asignacion', array_replace($data, ['activeRole' => $rol]))->render());
            $this->assertGreaterThan(0, $xpath->query('//form')->length);
            $this->assertSame(0, $xpath->query('//*[@data-asignaciones-solo-consulta]')->length);
        }
        $this->travelTo(Carbon::parse('2026-10-13 00:00:00', 'America/Santiago'));
        $xpath = $this->xpath(view('admin.dotacion-establecimiento.partials._asignacion', $data)->render());
        $this->assertGreaterThan(0, $xpath->query('//form')->length);
        $this->assertSame(0, $xpath->query('//*[@data-asignaciones-solo-consulta]')->length);
    }

    private function entrar(int $id = 4, string $rol = 'funcionario_directivo_estab'): User
    {
        $user = $this->testUser($id, $id);
        $user->forceFill(['establecimiento_id' => 1])->save();
        $this->actingAs($user)->withSession(['active_role' => $rol]);

        return $user;
    }

    private function datosVista(): array
    {
        $asig = DotacionDocenteAsignacion::findOrFail(1);
        $reserva = (new DotacionDocenteAsignacion)->forceFill($asig->getAttributes());
        $reserva->forceFill(['id' => 2, 'tipo_asignacion' => 'reserva_no_normativa', 'subtipo_asignacion' => 'titular']);
        $fantasma = clone $asig;
        $fantasma->forceFill(['id' => 3, 'motivo_huerfana' => 'Necesidad sin vínculo vigente']);
        $item = [
            'key' => 'plan:prueba', 'titulo' => 'Matemática', 'tipo_asignacion' => 'plan_estudio',
            'subtipo_asignacion' => 'plan_comun', 'curso_label' => '1° Básico A', 'bloque' => 'Plan común',
            'horas_plan_requeridas' => 5, 'horas_plan_asignadas' => 3, 'horas_plan_pendientes' => 2,
            'horas_contrato_requeridas' => 6, 'asignaciones' => [$asig],
        ];
        $funcion = [
            'key' => 'funcion:prueba', 'titulo' => 'Función de prueba', 'tipo_asignacion' => 'otra_funcion',
            'subtipo_asignacion' => 'otra', 'curso_label' => 'Establecimiento',
            'horas_plan_requeridas' => null, 'horas_contrato_requeridas' => 3,
            'horas_contrato_asignadas' => 0, 'asignaciones' => [$asig],
        ];

        return [
            'anio' => 2027, 'activeRole' => 'funcionario_directivo_estab', 'establecimiento' => Establecimiento::findOrFail(1),
            'docentes' => collect(), 'errors' => new ViewErrorBag,
            'asignacion' => ['asignaciones' => collect([$asig, $reserva]), 'asignaciones_huerfanas' => collect([$fantasma]),
                'necesidades' => [
                    'plan_estudio' => [$item],
                    'pie_colaborativo' => [array_replace($funcion, [
                        'key' => 'pie:prueba', 'titulo' => 'Trabajo colaborativo de prueba',
                        'tipo_asignacion' => 'pie_colaborativo', 'subtipo_asignacion' => 'pie',
                    ])],
                    'funciones' => [$funcion, array_replace($funcion, [
                        'key' => 'directivo:prueba', 'titulo' => 'Dirección de prueba',
                        'tipo_asignacion' => 'funcion_directiva', 'asignacion_automatica' => true,
                        'dotacion_funcion_regla_id' => 1,
                    ])],
                ]],
            'proceso2027' => ['aplica' => true, 'asignacion_habilitada' => true, 'funciones_no_normativas_habilitadas' => true],
        ];
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new \DOMXPath($dom);
    }
}
