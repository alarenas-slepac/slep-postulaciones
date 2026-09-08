<?php

namespace Tests\Feature;

use App\Http\Controllers\Gestion\InformesController;
use App\Http\Controllers\Gestion\SolicitudReemplazoGestionController;
use App\Models\SolicitudReemplazo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PadronFiltrosHistoricosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('reemplazos_personal', function (Blueprint $t) {
            $t->id(); $t->string('rut'); $t->string('nombre'); $t->string('estatuto');
        });
        Schema::create('solicitudes_reemplazo', function (Blueprint $t) {
            $t->id(); $t->integer('reemplazo_personal_id')->nullable();
            $t->integer('establecimiento_id')->default(1);
            $t->string('estado')->default('aceptada'); $t->string('tipo_reemplazo')->default('licencia_medica');
            $t->string('rut_titular_normalizado')->nullable(); $t->string('rut_reemplazo_normalizado')->nullable();
            $t->integer('postulant_profile_id')->nullable(); $t->integer('contrato_trabajo_postulant_profile_id')->nullable();
            $t->date('fecha_inicio')->default('2026-08-01');
            $t->date('fecha_inicio_trabajo')->default('2026-08-01');
            $t->date('fecha_termino')->default('2026-08-30');
            $t->timestamp('uatp_decision_at')->nullable(); $t->timestamps();
            $t->json('padron_personal_snapshot')->nullable();
        });
        Schema::create('establecimientos', function (Blueprint $t) {
            $t->id(); $t->integer('rbd'); $t->string('nombre_establecimiento');
            $t->boolean('sala_cuna')->default(false); $t->string('comuna')->nullable();
        });
        Schema::create('solicitud_reemplazo_jornadas', function (Blueprint $t) {
            $t->id(); $t->integer('solicitud_reemplazo_id'); $t->decimal('reemplazo_total');
        });
        DB::table('establecimientos')->insert(['id' => 1, 'rbd' => 99999, 'nombre_establecimiento' => 'Establecimiento sintético']);
        DB::table('reemplazos_personal')->insert([
            ['id' => 101, 'rut' => '222222222', 'nombre' => 'Actual Alfa', 'estatuto' => 'ASISTENTE'],
            ['id' => 102, 'rut' => '333333333', 'nombre' => 'Actual Beta', 'estatuto' => 'DOCENTE'],
        ]);
        $this->document(1, 101, ['rut' => '11.111.111-1', 'nombre' => 'Original Alfa', 'estatuto' => 'DOCENTE']);
        $this->document(2, 102, ['rut' => '44.444.444-4', 'nombre' => 'Original Beta', 'estatuto' => 'ASISTENTE']);
        $this->document(3, 101); // Legado: usa el padrón actual.
        $this->document(4, 102);
    }

    private function document(int $id, ?int $personal, ?array $historical = null, array $attributes = []): void
    {
        DB::table('solicitudes_reemplazo')->insert(array_merge([
            'id' => $id, 'reemplazo_personal_id' => $personal,
            'padron_personal_snapshot' => $historical === null ? null : json_encode([
                'version' => 1, 'personal' => array_merge(['id' => $personal], $historical),
            ]),
        ], $attributes));
    }

    public function test_search_uses_each_document_copy_and_only_falls_back_for_legacy(): void
    {
        $this->assertSame([1], SolicitudReemplazo::titularCoincide('%ORIGINAL ALFA%')->pluck('id')->all());
        $this->assertSame([3], SolicitudReemplazo::titularCoincide('%Actual Alfa%')->pluck('id')->all());
        $this->assertSame([1], SolicitudReemplazo::titularCoincide('%11.111.111-1%')->pluck('id')->all());
        $this->assertSame([3], SolicitudReemplazo::titularCoincide('%222222222%')->pluck('id')->all());
        $this->document(5, 101, ['rut' => '555555555', 'nombre' => 'Otra copia Alfa', 'estatuto' => 'DOCENTE']);
        $this->assertSame([5], SolicitudReemplazo::titularCoincide('%Otra copia%')->pluck('id')->all());
        $this->assertSame([1], SolicitudReemplazo::titularCoincide('%Original Alfa%')->pluck('id')->all());
    }

    public function test_teacher_filter_uses_previous_statute_not_new_one(): void
    {
        $this->assertSame([1, 4], SolicitudReemplazo::titularDocente()->orderBy('id')->pluck('id')->all());
        foreach (['profesora', ' PROFESOR ', 'Estatuto docente'] as $index => $estatuto) {
            $this->document(10 + $index, 101, ['estatuto' => $estatuto]);
        }
        $this->assertSame([1, 4, 10, 11, 12], SolicitudReemplazo::titularDocente()->orderBy('id')->pluck('id')->all());
    }

    public function test_grouped_filters_preserve_establishment_status_and_pagination(): void
    {
        $this->document(5, 102, null, ['establecimiento_id' => 2]);
        $this->document(6, 102, null, ['estado' => 'anulada']);
        $page = SolicitudReemplazo::where('establecimiento_id', 1)->where('estado', 'aceptada')
            ->titularCoincide('%al%')->orderBy('id')->paginate(1, ['*'], 'page', 2);
        $this->assertSame(4, $page->total()); // Las dos copias originales y los dos registros sin copia.
        $this->assertSame(2, $page->items()[0]->id);
        $this->assertSame([1, 4], SolicitudReemplazo::where('establecimiento_id', 1)->where('estado', 'aceptada')
            ->titularDocente()->orderBy('id')->pluck('id')->all());
    }

    public function test_invalid_or_detached_copy_does_not_fall_back_to_current_roster(): void
    {
        foreach ([['version' => 2, 'personal' => ['id' => 102]], ['version' => 1, 'personal' => ['id' => 999]], ['version' => 1, 'personal' => null]] as $index => $copy) {
            $this->document(10 + $index, 102, null, ['padron_personal_snapshot' => json_encode($copy)]);
        }
        $this->document(20, null, ['id' => 102, 'estatuto' => 'DOCENTE', 'nombre' => 'Actual Beta']);
        $this->assertSame([4], SolicitudReemplazo::titularCoincide('%Actual Beta%')->pluck('id')->all());
        $this->assertSame([1, 4], SolicitudReemplazo::titularDocente()->orderBy('id')->pluck('id')->all());
    }

    public function test_bound_search_does_not_allow_sql_injection(): void
    {
        $query = SolicitudReemplazo::titularCoincide("%' OR 1=1 --%");
        $this->assertSame(0, $query->count());
        $this->assertStringNotContainsString('OR 1=1', $query->toSql());
    }

    public function test_normalized_rut_uses_legacy_stored_value_only_without_copy(): void
    {
        DB::table('solicitudes_reemplazo')->whereIn('id', [1, 3])->update(['rut_titular_normalizado' => '999999999']);
        $this->assertSame([1], SolicitudReemplazo::titularRutComparable('11111111-1')->pluck('id')->all());
        $this->assertSame([3], SolicitudReemplazo::titularRutComparable('22.222.222-2')->pluck('id')->all());
        $this->assertSame([3], SolicitudReemplazo::titularRutComparable('999999999')->pluck('id')->all());
    }

    public function test_filters_work_before_snapshot_migration(): void
    {
        Schema::table('solicitudes_reemplazo', fn (Blueprint $t) => $t->dropColumn('padron_personal_snapshot'));
        $this->assertSame([1, 3], SolicitudReemplazo::titularCoincide('%Actual Alfa%')->orderBy('id')->pluck('id')->all());
        $this->assertSame([2, 4], SolicitudReemplazo::titularDocente()->orderBy('id')->pluck('id')->all());
        $this->assertSame([1, 3], SolicitudReemplazo::titularRutComparable('222222222')->orderBy('id')->pluck('id')->all());
    }

    public function test_report_rows_retain_historical_teacher_and_saved_replacement_hours(): void
    {
        DB::table('solicitud_reemplazo_jornadas')->insert(['solicitud_reemplazo_id' => 1, 'reemplazo_total' => 30]);
        $method = new \ReflectionMethod(InformesController::class, 'buildRows');
        $rows = $method->invoke(app(InformesController::class), '2026-08-01', '2026-08-31');
        $this->assertSame([1, 4], $rows->pluck('solicitud_id')->all());
        $this->assertSame('Original Alfa', $rows[0]['nombre_funcionario_a_reemplazar']);
        $this->assertSame('11.111.111-1', $rows[0]['rut_funcionario_a_reemplazar']);
        $this->assertSame('30,00', $rows[0]['horas_efectivamente_reemplazadas']);
        $this->assertSame('Actual Beta', $rows[1]['nombre_funcionario_a_reemplazar']);
        $this->assertCount(0, $method->invoke(app(InformesController::class), '2026-09-01', '2026-09-30'));
        $this->assertCount(0, $method->invoke(app(InformesController::class), '2026-08-01', '2026-08-31', ['otro']));
    }

    public function test_all_export_scopes_filter_historical_titular_before_pagination(): void
    {
        $method = new \ReflectionMethod(SolicitudReemplazoGestionController::class, 'buildSolicitudesExportQuery');
        foreach (['uatp' => ['p', 'pendiente_uatp'], 'validacion' => ['v', 'pendiente_validacion'], 'gdp' => ['o', 'aceptada']] as $scope => [$prefix, $estado]) {
            DB::table('solicitudes_reemplazo')->update(['estado' => $estado]);
            $request = Request::create('/', 'GET', [$prefix.'_titular' => 'Original Alfa']);
            $query = $method->invoke(app(SolicitudReemplazoGestionController::class), $request, $scope);
            $this->assertSame([1], $query->setEagerLoads([])->pluck('id')->all());
        }
    }

    public function test_previous_requests_follow_historical_rut_even_after_current_rut_changes(): void
    {
        $this->document(10, 101, ['rut' => '11.111.111-1', 'nombre' => 'Original Alfa', 'estatuto' => 'DOCENTE']);
        // Un RUT alternativo contradictorio no reemplaza la copia del documento.
        DB::table('solicitudes_reemplazo')->where('id', 2)->update(['rut_titular_normalizado' => '111111111']);
        $method = new \ReflectionMethod(SolicitudReemplazoGestionController::class, 'solicitudesAnterioresRelacionadas');
        $rows = $method->invoke(app(SolicitudReemplazoGestionController::class), SolicitudReemplazo::findOrFail(10));
        $this->assertSame([1], $rows->pluck('id')->all());
        $this->assertSame('Original Alfa', $rows->first()->funcionarioTitular->nombre);
    }
}
