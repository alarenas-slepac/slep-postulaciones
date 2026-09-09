<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\EstablecimientoController;
use App\Http\Controllers\Admin\FuncionarioViaticoAnexoController;
use App\Http\Controllers\Admin\PermisoSinGoceExcepcionController;
use App\Http\Controllers\FuncionarioEstab\SolicitudReemplazoController;
use App\Http\Controllers\ReemplazosController;
use App\Models\Establecimiento;
use App\Models\FuncionarioViaticoAnexo;
use App\Models\PermisoSinGoceExcepcion;
use App\Models\ReemplazoPersonal;
use App\Models\User;
use App\Services\Padron\PadronBloqueoService;
use App\Services\Remuneraciones\ReemplazoPersonalRutService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PadronPendientesConsumidoresTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('establecimientos', function (Blueprint $t) {
            $t->id(); $t->integer('rbd'); $t->string('nombre_establecimiento'); $t->string('comuna');
        });
        Schema::create('reemplazos_personal', function (Blueprint $t) {
            $t->id(); $t->integer('establecimiento_id')->nullable(); $t->integer('rbd');
            foreach (['rut', 'nombre', 'estatuto', 'escalafon', 'tipocontrato', 'financiamiento', 'source_filename'] as $field) { $t->string($field)->nullable(); }
            $t->integer('anio'); $t->integer('mes'); $t->boolean('vigente')->default(true);
            foreach (['jornada', 'jornada_basica', 'jornada_media'] as $field) { $t->integer($field)->default(0); }
            $t->timestamps();
        });
        Schema::create('padron_revisiones', function (Blueprint $t) {
            $t->id(); $t->integer('anio'); $t->integer('mes'); $t->timestamp('aplicada_at')->nullable();
        });
        Schema::create('reemplazos_personal_bloqueos', function (Blueprint $t) {
            $t->id(); $t->integer('reemplazo_personal_id'); $t->integer('establecimiento_id')->nullable(); $t->integer('rbd')->nullable();
            foreach (['rut', 'nombre', 'motivo', 'observacion'] as $field) { $t->string($field)->nullable(); }
            $t->boolean('activo')->default(true); $t->integer('bloqueado_por')->nullable();
            $t->integer('desbloqueado_por')->nullable(); $t->timestamp('desbloqueado_at')->nullable(); $t->timestamps();
        });
        Schema::create('funcionarios_viatico_anexo', function (Blueprint $t) {
            $t->id();
            foreach (['rut', 'rut_body', 'rut_dv', 'nombre_completo', 'establecimiento_nombre', 'estamento', 'cargo_funcion', 'observacion'] as $field) { $t->string($field)->nullable(); }
            $t->integer('establecimiento_id')->nullable(); $t->boolean('activo'); $t->integer('registrado_por')->nullable();
            $t->timestamp('validado_at')->nullable(); $t->timestamps();
        });
        Schema::create('permiso_sin_goce_excepciones', function (Blueprint $t) {
            $t->id();
            foreach (['rut_normalizado', 'rut_original', 'nombre_titular', 'observacion'] as $field) { $t->string($field)->nullable(); }
            $t->boolean('activo'); $t->integer('created_by')->nullable(); $t->integer('updated_by')->nullable(); $t->timestamps();
        });
        DB::table('establecimientos')->insert([
            ['id' => 1, 'rbd' => 99999, 'nombre_establecimiento' => 'Escuela sintética A', 'comuna' => 'Comuna A'],
            ['id' => 2, 'rbd' => 99998, 'nombre_establecimiento' => 'Escuela sintética B', 'comuna' => 'Comuna B'],
        ]);
    }

    private function personal(int $id = 101, array $changes = []): ReemplazoPersonal
    {
        DB::table('reemplazos_personal')->insert(array_replace([
            'id' => $id, 'rut' => '11.111.111-1', 'nombre' => 'Persona sintética', 'establecimiento_id' => 1, 'rbd' => 99999,
            'estatuto' => 'DOCENTE', 'escalafon' => 'AULA', 'tipocontrato' => 'PLANTA', 'anio' => 2026, 'mes' => 8,
            'jornada' => 20, 'jornada_basica' => 20, 'jornada_media' => 0,
        ], $changes));
        return ReemplazoPersonal::findOrFail($id);
    }

    private function request(array $data = []): Request
    {
        $request = Request::create('/prueba', 'POST', $data);
        $request->setUserResolver(fn () => (new User)->forceFill(['id' => 1]));
        $request->setLaravelSession(app('session')->driver());
        return $request;
    }

    private function lookupAnexo(string $rut = '11111111-1'): ?ReemplazoPersonal
    {
        return (new \ReflectionMethod(FuncionarioViaticoAnexoController::class, 'funcionarioActivoPorRut'))
            ->invoke(app(FuncionarioViaticoAnexoController::class), $rut);
    }

    public function test_viatico_does_not_resurrect_older_active_month(): void
    {
        $this->personal(); $this->personal(102, ['mes' => 9, 'vigente' => false]);
        $this->assertNull($this->lookupAnexo());
    }

    public function test_viatico_keeps_partial_legacy_period_by_school_but_respects_full_load_floor(): void
    {
        $this->personal(); $this->personal(102, ['rut' => '22.222.222-2', 'establecimiento_id' => 2, 'rbd' => 99998, 'mes' => 9]);
        $this->assertSame(101, $this->lookupAnexo()->id);
        DB::table('padron_revisiones')->insert(['anio' => 2026, 'mes' => 9, 'aplicada_at' => now()]);
        $this->assertNull($this->lookupAnexo());
    }

    public function test_viatico_requires_exact_rut_and_rejects_multiple_schools(): void
    {
        $this->personal(101, ['rut' => '111111112-3']);
        $this->assertNull($this->lookupAnexo());
        $this->personal(102); $this->personal(103, ['establecimiento_id' => 2, 'rbd' => 99998]);
        $this->expectException(ValidationException::class);
        $this->lookupAnexo();
    }

    public function test_real_viatico_creation_stores_school_name_and_can_deactivate_after_departure(): void
    {
        $this->personal();
        $controller = app(FuncionarioViaticoAnexoController::class);
        $controller->store($this->request(['rut' => '11111111-1', 'activo' => true]));
        $record = FuncionarioViaticoAnexo::firstOrFail();
        $this->assertSame('Escuela sintética A', $record->establecimiento_nombre);
        DB::table('reemplazos_personal')->update(['vigente' => false]);
        $controller->update($this->request(['rut' => '11111111-1', 'activo' => false]), $record);
        $this->assertFalse($record->fresh()->activo);
        $this->assertSame('Escuela sintética A', $record->fresh()->establecimiento_nombre);
        $this->expectException(ValidationException::class);
        $controller->toggle($record->fresh());
    }

    public function test_excepcion_requires_current_regular_teacher_but_allows_deactivation(): void
    {
        $this->personal(101, ['tipocontrato' => 'SUPLENCIA']);
        $controller = app(PermisoSinGoceExcepcionController::class);
        $controller->store($this->request(['rut' => '11111111-1']));
        $this->assertDatabaseCount('permiso_sin_goce_excepciones', 0);
        DB::table('reemplazos_personal')->update(['tipocontrato' => 'PLANTA']);
        $controller->store($this->request(['rut' => '11111111-1']));
        $record = PermisoSinGoceExcepcion::firstOrFail();
        DB::table('reemplazos_personal')->update(['vigente' => false]);
        $controller->toggle($this->request(), $record);
        $this->assertFalse($record->fresh()->activo);
        $this->expectException(ValidationException::class);
        $controller->toggle($this->request(), $record->fresh());
    }

    public function test_school_summary_does_not_add_previous_months(): void
    {
        $this->personal(); $this->personal(102, ['mes' => 9, 'jornada' => 30]);
        $view = app(EstablecimientoController::class)->show(Establecimiento::findOrFail(1));
        $this->assertSame(30, $view->getData()['totalJornada']);
        $this->assertSame([102], $view->getData()['registros']->pluck('id')->all());
    }

    public function test_cgr_identification_still_accepts_historical_personnel(): void
    {
        $this->personal(101, ['vigente' => false]);
        $this->assertSame('Persona sintética', app(ReemplazoPersonalRutService::class)->buscar('11111111-1')['nombre']);
    }

    private function block(int $id = 101, array $changes = []): void
    {
        DB::table('reemplazos_personal_bloqueos')->insert(array_replace([
            'reemplazo_personal_id' => $id, 'establecimiento_id' => 1, 'rbd' => 99999, 'rut' => '111111111',
            'nombre' => 'Persona sintética', 'motivo' => 'Revisión administrativa sintética', 'activo' => true,
        ], $changes));
    }

    public function test_block_follows_transfer_and_new_contract_without_rewriting_origin(): void
    {
        $this->personal(); $this->block();
        $before = DB::table('reemplazos_personal_bloqueos')->get()->toJson();
        DB::table('reemplazos_personal')->where('id', 101)->update(['establecimiento_id' => 2, 'rbd' => 99998]);
        $this->personal(102, ['rut' => '11111111-1', 'establecimiento_id' => 2, 'rbd' => 99998]);
        $this->personal(103, ['rut' => '222222222']);
        $service = app(PadronBloqueoService::class);
        $rows = ReemplazoPersonal::orderBy('id')->get(); $service->cargar($rows);
        $this->assertTrue($service->bloqueado($rows[0])); $this->assertTrue($service->bloqueado($rows[1]));
        $this->assertFalse($service->bloqueado($rows[2]));
        $this->assertSame([101, 102], $service->filtrarBloqueados(ReemplazoPersonal::query())->orderBy('id')->pluck('id')->all());
        $this->assertSame($before, DB::table('reemplazos_personal_bloqueos')->get()->toJson());
    }

    public function test_old_block_without_rut_is_resolved_through_its_contract(): void
    {
        $this->personal(); $this->block(101, ['rut' => null]); $p = $this->personal(102, ['rut' => '111111111']);
        $this->assertTrue(app(PadronBloqueoService::class)->bloqueado($p));
        $this->assertSame(2, app(PadronBloqueoService::class)->filtrarBloqueados(ReemplazoPersonal::query())->count());
    }

    public function test_unblock_from_new_contract_closes_all_active_blocks_with_audit(): void
    {
        $this->personal(); $p = $this->personal(102, ['establecimiento_id' => 2, 'rbd' => 99998]);
        $this->block(); $this->block(102);
        app(ReemplazosController::class)->desbloquearPersonal($this->request(), $p);
        $this->assertSame(0, DB::table('reemplazos_personal_bloqueos')->where('activo', true)->count());
        $this->assertSame(2, DB::table('reemplazos_personal_bloqueos')->where('desbloqueado_por', 1)->whereNotNull('desbloqueado_at')->count());
        $this->assertFalse(app(PadronBloqueoService::class)->bloqueado($p->fresh()));
    }

    public function test_block_cannot_be_duplicated_from_another_contract(): void
    {
        $this->personal(); $this->block(); $p = $this->personal(102);
        app(ReemplazosController::class)->bloquearPersonal($this->request(['motivo' => 'Motivo sintético']), $p);
        $this->assertDatabaseCount('reemplazos_personal_bloqueos', 1);
    }

    public function test_assistant_is_also_blocked_for_replacement_requests(): void
    {
        $this->personal(); $this->block(); $p = $this->personal(102, ['estatuto' => 'AAEE']);
        $method = new \ReflectionMethod(SolicitudReemplazoController::class, 'titularTieneBloqueoIndividualActivo');
        $this->assertTrue($method->invoke(app(SolicitudReemplazoController::class), $p));
    }

    public function test_ajax_marks_contract_at_new_school_disabled_and_returns_original_reason(): void
    {
        $this->personal(); $this->block();
        $this->personal(102, ['establecimiento_id' => 2, 'rbd' => 99998, 'estatuto' => 'AAEE']);
        $user = (new User)->forceFill(['id' => 1]);
        $user->setRelation('establecimiento', Establecimiento::findOrFail(2));
        auth()->setUser($user);
        $response = app(SolicitudReemplazoController::class)->ajaxFuncionarios(Request::create('/ajax', 'GET'));
        $rows = $response->getData(true)['results'];
        $this->assertCount(1, $rows);
        $this->assertSame(102, $rows[0]['id']);
        $this->assertTrue($rows[0]['disabled']);
        $this->assertSame('Revisión administrativa sintética', $rows[0]['bloqueo_motivo']);
    }

    public function test_legacy_transfer_route_now_verifies_rut_across_schools_without_copying(): void
    {
        $this->personal(); $this->block();
        $this->personal(102, ['mes' => 9, 'establecimiento_id' => 2, 'rbd' => 99998]);
        $before = DB::table('reemplazos_personal_bloqueos')->get()->toJson();
        $response = app(ReemplazosController::class)->traspasarBloqueosPersonal($this->request([
            'periodo_origen' => '2026-08', 'periodo_destino' => '2026-09',
        ]));
        $summary = $response->getSession()->get('traspaso_bloqueos_resumen');
        $this->assertSame(1, $summary['ya_existian']);
        $this->assertSame(0, $summary['traspasados']);
        $this->assertSame($before, DB::table('reemplazos_personal_bloqueos')->get()->toJson());
    }

    public function test_empty_ruts_never_match_other_people(): void
    {
        $this->personal(101, ['rut' => '']); $this->block(101, ['rut' => '']);
        $other = $this->personal(102, ['rut' => '']);
        $this->assertFalse(app(PadronBloqueoService::class)->bloqueado($other));
        $this->assertSame([101], app(PadronBloqueoService::class)->filtrarBloqueados(ReemplazoPersonal::query())->pluck('id')->all());
    }
}
