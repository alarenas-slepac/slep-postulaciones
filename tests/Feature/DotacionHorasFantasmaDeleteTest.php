<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DotacionAsignacionController;
use App\Models\DotacionDocenteAsignacion;
use App\Models\Establecimiento;
use App\Support\DocenteHorasNoLectivasCalculator;
use App\Support\DotacionAsignacionCalculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DotacionHorasFantasmaDeleteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        (new ReflectionProperty(DocenteHorasNoLectivasCalculator::class, 'proportionRowsCache'))->setValue(null, []);
        Schema::create('establecimientos', function (Blueprint $t): void { $t->id(); });
        DB::table('establecimientos')->insert([['id' => 1], ['id' => 2]]);
        (require database_path('migrations/2026_05_25_183000_create_docente_horas_proporciones_table.php'))->up();
        (require database_path('migrations/2026_07_23_170000_sync_docente_horas_proporciones_cpeip.php'))->up();
        Schema::create('dotacion_docente_asignaciones', function (Blueprint $t): void {
            $t->id(); $t->integer('establecimiento_id'); $t->integer('anio');
            $t->string('docente_rut_normalizado'); $t->string('docente_nombre')->default('Docente sintético');
            $t->string('tipo_asignacion'); $t->string('subtipo_asignacion')->nullable();
            $t->string('asignatura_nombre')->default('Asignatura sintética'); $t->string('necesidad_key')->nullable();
            $t->integer('dotacion_funcion_id')->nullable(); $t->integer('dotacion_funcion_regla_id')->nullable();
            $t->string('estado')->default('activa'); $t->string('estamento_cobertura')->default('docente');
            $t->string('proporcion_aplicada')->default('65/35');
            $t->decimal('horas_plan_pedagogicas', 8, 2)->nullable(); $t->decimal('horas_contrato', 8, 2);
            $t->timestamps();
        });
        foreach ([
            ['id' => 1, 'tipo_asignacion' => 'plan_estudio', 'necesidad_key' => 'plan:eliminado', 'horas_plan_pedagogicas' => 15, 'horas_contrato' => 17],
            ['id' => 2, 'tipo_asignacion' => 'otra_funcion', 'dotacion_funcion_id' => 999, 'horas_contrato' => 3],
            ['id' => 3, 'tipo_asignacion' => 'plan_estudio', 'necesidad_key' => 'plan:vigente', 'horas_plan_pedagogicas' => 15, 'horas_contrato' => 18],
            ['id' => 4, 'tipo_asignacion' => 'reserva_no_normativa', 'horas_contrato' => 2],
            ['id' => 5, 'tipo_asignacion' => 'otra_funcion', 'establecimiento_id' => 2, 'horas_contrato' => 3],
            ['id' => 6, 'tipo_asignacion' => 'otra_funcion', 'anio' => 2026, 'horas_contrato' => 3],
            ['id' => 7, 'tipo_asignacion' => 'otra_funcion', 'estado' => 'inactiva', 'horas_contrato' => 3],
            ['id' => 8, 'tipo_asignacion' => 'plan_normativo', 'necesidad_key' => 'plan:normativo:eliminado', 'horas_contrato' => 3],
            ['id' => 9, 'tipo_asignacion' => 'acompanamiento_parvularia', 'necesidad_key' => 'plan:eliminado', 'horas_plan_pedagogicas' => 2, 'horas_contrato' => 2],
        ] as $fila) {
            DB::table('dotacion_docente_asignaciones')->insert(array_merge([
                'establecimiento_id' => 1, 'anio' => 2027, 'docente_rut_normalizado' => '111111111',
            ], $fila));
        }
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(DocenteHorasNoLectivasCalculator::class, 'proportionRowsCache'))->setValue(null, []);
        parent::tearDown();
    }

    private function controller(): DotacionAsignacionController
    {
        // Ejecuta el detector real sobre un contexto de necesidades controlado,
        // sin construir el resto de indicadores de la pantalla en esta prueba.
        return new class extends DotacionAsignacionController {
            protected function ghostAssignments(Establecimiento $establecimiento, int $anio): Collection
            {
                $rows = DotacionDocenteAsignacion::query()->where('establecimiento_id', $establecimiento->id)
                    ->where('anio', $anio)->where('estado', 'activa')->get();

                return (new ReflectionMethod(DotacionAsignacionCalculator::class, 'asignacionesHuerfanas'))
                    ->invoke(null, $rows, ['plan_estudio' => [['key' => 'plan:vigente']]]);
            }
        };
    }

    private function request(array $ids, string $rol = 'admin', int $establecimientoUsuario = 1): Request
    {
        $request = Request::create('/horas-fantasmas', 'DELETE', ['anio' => 2027, 'asignaciones' => $ids]);
        $request->setUserResolver(fn () => new class($rol, $establecimientoUsuario) {
            public function __construct(private string $rol, public int $establecimiento_id) {}
            public function activeRoleName(): string { return $this->rol; }
        });
        $request->setLaravelSession(app('session')->driver());

        return $request;
    }

    public function test_elimina_fantasmas_de_todos_los_tipos_y_recalcula_sin_tocar_reservas_ni_otro_anio_o_rbd(): void
    {
        $response = $this->controller()->destroyGhostAssignments($this->request([1, 2, 8, 9]), Establecimiento::findOrFail(1));
        $this->assertSame([3, 4, 5, 6, 7], DB::table('dotacion_docente_asignaciones')->orderBy('id')->pluck('id')->all());
        $this->assertSame(17.0, (float) DotacionDocenteAsignacion::findOrFail(3)->horas_contrato);
        $this->assertSame(2.0, (float) DotacionDocenteAsignacion::findOrFail(4)->horas_contrato);
        $this->assertStringContainsString('anio=2027', $response->getTargetUrl());
        $this->assertStringContainsString('tab=asignacion', $response->getTargetUrl());
    }

    public function test_rechaza_lote_completo_con_asignacion_vigente_reserva_otro_rbd_anio_inactiva_o_inexistente(): void
    {
        foreach ([3, 4, 5, 6, 7, 999] as $id) {
            try {
                $this->controller()->destroyGhostAssignments($this->request([1, $id]), Establecimiento::findOrFail(1));
                $this->fail('Debe cancelar el lote sin eliminar parcialmente.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('asignaciones', $exception->errors());
            }
            $this->assertSame(9, DotacionDocenteAsignacion::count());
        }
    }

    public function test_revalida_si_la_necesidad_se_restablecio_tras_mostrar_el_boton(): void
    {
        DotacionDocenteAsignacion::findOrFail(1)->update(['necesidad_key' => 'plan:vigente']);
        $this->expectException(ValidationException::class);
        $this->controller()->destroyGhostAssignments($this->request([1, 2]), Establecimiento::findOrFail(1));
    }

    public function test_rechaza_roles_no_autorizados_y_directivos_de_otro_establecimiento(): void
    {
        foreach ([['funcionario_slep', 1], ['funcionario_directivo_estab', 2]] as [$rol, $id]) {
            try {
                $this->controller()->destroyGhostAssignments($this->request([1], $rol, $id), Establecimiento::findOrFail(1));
                $this->fail('Debe rechazar la eliminación.');
            } catch (HttpException $exception) { $this->assertSame(403, $exception->getStatusCode()); }
        }
        $this->assertSame(9, DotacionDocenteAsignacion::count());
    }

    public function test_boton_muestra_anio_y_confirmacion_y_solo_aparece_a_roles_autorizados(): void
    {
        $row = DotacionDocenteAsignacion::findOrFail(1);
        $row->setAttribute('motivo_huerfana', 'Plan eliminado');
        $vars = ['asignacionesHuerfanas' => collect([$row]), 'establecimiento' => Establecimiento::findOrFail(1),
            'anio' => 2027, 'resumenAsignacion' => [], 'fmt' => fn ($value) => (string) $value, 'activeRole' => 'admin'];
        $html = Blade::render("@include('admin.dotacion-establecimiento.partials._asignaciones_huerfanas')", $vars);
        $this->assertStringContainsString('Eliminar todas las horas fantasmas', $html);
        $this->assertStringContainsString('name="anio" value="2027"', $html);
        $this->assertStringContainsString('name="asignaciones[]" value="1"', $html);
        $this->assertStringContainsString('Esta acción no se puede deshacer', $html);
        $html = Blade::render("@include('admin.dotacion-establecimiento.partials._asignaciones_huerfanas')", array_merge($vars, ['activeRole' => 'funcionario_slep']));
        $this->assertStringNotContainsString('Eliminar todas las horas fantasmas', $html);
        $route = app('router')->getRoutes()->getByName('admin.dotacion-establecimiento.asignaciones.fantasmas.destroy');
        $this->assertSame(['DELETE'], $route->methods());
        $this->assertContains('ensure.role:admin|funcionario_directivo_estab|coordinador_uatp|coordinador_gdp|supervisor_plani', $route->gatherMiddleware());
    }
}
