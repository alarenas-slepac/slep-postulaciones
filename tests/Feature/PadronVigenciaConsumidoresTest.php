<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\RegisterRutLookupController;
use App\Models\Establecimiento;
use App\Models\PostulantProfile;
use App\Models\SolicitudReemplazo;
use App\Models\User;
use App\Services\CentroOperaciones\DatosBaseService;
use App\Services\FuncionarioRegisterLookupService;
use App\Services\Padron\PadronHistorialService;
use App\Services\TramiteAutofillService;
use App\Support\RutChile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PadronVigenciaConsumidoresTest extends TestCase
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
            foreach (['rut', 'nombre', 'estatuto', 'escalafon', 'tipocontrato'] as $field) { $t->string($field); }
            $t->integer('anio'); $t->integer('mes'); $t->boolean('vigente')->default(true);
            $t->date('fecha_nacimiento')->nullable(); $t->integer('jornada')->default(44); $t->timestamps();
        });
        Schema::create('padron_revisiones', function (Blueprint $t) {
            $t->id(); $t->integer('anio'); $t->integer('mes'); $t->timestamp('aplicada_at')->nullable();
        });
        Schema::create('solicitudes_reemplazo', function (Blueprint $t) {
            $t->id(); $t->integer('reemplazo_personal_id')->nullable(); $t->integer('establecimiento_id');
            $t->integer('postulant_profile_id'); $t->string('estado'); $t->string('numero_solicitud');
            $t->date('fecha_inicio_trabajo')->nullable(); $t->date('fecha_inicio')->nullable();
            $t->json('padron_personal_snapshot')->nullable(); $t->timestamps();
        });
        DB::table('establecimientos')->insert([
            ['id' => 1, 'rbd' => 99999, 'nombre_establecimiento' => 'Escuela sintética A', 'comuna' => 'Comuna A'],
            ['id' => 2, 'rbd' => 99998, 'nombre_establecimiento' => 'Escuela sintética B', 'comuna' => 'Comuna B'],
        ]);
    }

    private function rut(int $body = 11111111): string { return $body.'-'.RutChile::dv($body); }

    private function personal(int $id = 101, array $changes = []): void
    {
        DB::table('reemplazos_personal')->insert(array_replace([
            'id' => $id, 'establecimiento_id' => 1, 'rbd' => 99999, 'rut' => $this->rut(),
            'nombre' => 'Sintético Prueba Docente', 'estatuto' => 'DOCENTE', 'escalafon' => 'AULA',
            'tipocontrato' => 'CONTRATA', 'anio' => 2026, 'mes' => 8, 'vigente' => true,
            'fecha_nacimiento' => '1980-01-01',
        ], $changes));
    }

    private function user(): User
    {
        $user = new User;
        $user->forceFill(['id' => 1, 'rut' => $this->rut(), 'email' => 'sintetico@example.test',
            'nombres' => 'Persona', 'apellido_paterno' => 'Sintética', 'apellido_materno' => 'Prueba']);
        $user->setRelation('postulantProfile', (new PostulantProfile)->forceFill(['id' => 7]));
        return $user;
    }

    private function aplicada(int $anio = 2026, int $mes = 9): void
    {
        DB::table('padron_revisiones')->insert(['anio' => $anio, 'mes' => $mes, 'aplicada_at' => now()]);
    }

    private function lookup(): array { return app(FuncionarioRegisterLookupService::class)->lookup($this->rut()); }
    private function autofill(): array { return app(TramiteAutofillService::class)->forUser($this->user()); }
    private function centro(): array { return app(DatosBaseService::class)->dotacionesPara(Establecimiento::all()); }

    private function solicitud(string $estado = 'cerrado', int $profile = 7): void
    {
        DB::table('solicitudes_reemplazo')->insert([
            'reemplazo_personal_id' => 501, 'establecimiento_id' => 1, 'postulant_profile_id' => $profile,
            'estado' => $estado, 'numero_solicitud' => 'SINTETICA', 'fecha_inicio' => '2026-07-01',
        ]);
    }

    public function test_inactive_latest_month_does_not_resurrect_older_active_contract(): void
    {
        $this->personal();
        $this->personal(102, ['mes' => 9, 'vigente' => false]);
        $this->assertSame(['docentes' => 0, 'asistentes' => 0, 'periodo' => null], $this->centro()[1]);
        $this->assertFalse($this->lookup()['is_funcionario']);
        $this->assertTrue($this->lookup()['tiene_antecedentes']);
        $this->assertFalse($this->autofill()['ok']);
    }

    public function test_absent_rut_is_not_current_just_because_its_own_last_row_is_active(): void
    {
        $this->personal();
        $this->personal(102, ['rut' => $this->rut(22222222), 'mes' => 9]);
        $this->assertSame(1, $this->centro()[1]['docentes']);
        $this->assertFalse($this->lookup()['is_funcionario']);
        $this->assertFalse($this->autofill()['ok']);
    }

    public function test_applied_full_period_leaves_missing_establishment_empty(): void
    {
        $this->personal();
        $this->aplicada();
        $this->assertSame(0, $this->centro()[1]['docentes']);
        $this->assertFalse($this->lookup()['is_funcionario']);
        $this->assertFalse($this->autofill()['ok']);
    }

    public function test_pending_preview_does_not_change_eligibility(): void
    {
        $this->personal();
        DB::table('padron_revisiones')->insert(['anio' => 2027, 'mes' => 1, 'aplicada_at' => null]);
        $this->assertTrue($this->lookup()['is_funcionario']);
        $this->assertTrue($this->autofill()['ok']);
        $this->assertSame(1, $this->centro()[1]['docentes']);
    }

    public function test_partial_legacy_load_of_other_school_does_not_hide_current_school(): void
    {
        $this->personal();
        $this->personal(102, ['rut' => $this->rut(22222222), 'establecimiento_id' => 2, 'rbd' => 99998, 'mes' => 9]);
        $this->assertTrue($this->lookup()['is_funcionario']);
        $this->assertSame('08/2026', $this->autofill()['periodo']);
        $this->assertSame(1, $this->centro()[1]['docentes']);
    }

    public function test_transfer_keeps_id_and_uses_new_school_without_altering_document_copy(): void
    {
        $this->personal(501, ['rut' => $this->rut(22222222)]);
        $this->personal();
        $this->solicitud();
        $copy = app(PadronHistorialService::class)->capturar(101, 'prueba_sintetica');
        DB::table('solicitudes_reemplazo')->update(['reemplazo_personal_id' => 101, 'padron_personal_snapshot' => json_encode($copy)]);
        $before = DB::table('solicitudes_reemplazo')->get()->toJson();
        DB::table('reemplazos_personal')->where('id', 101)->update(['mes' => 9, 'establecimiento_id' => 2, 'rbd' => 99998]);
        $this->aplicada();
        $this->assertSame(2, $this->lookup()['establecimiento_id']);
        $this->assertSame(2, $this->autofill()['establecimiento_id']);
        $this->assertSame(0, $this->centro()[1]['docentes']);
        $this->assertSame(1, $this->centro()[2]['docentes']);
        $this->assertSame(1, SolicitudReemplazo::first()->funcionarioTitular->establecimiento_id);
        $this->assertSame(101, SolicitudReemplazo::first()->funcionarioTitular->id);
        $this->assertSame($before, DB::table('solicitudes_reemplazo')->get()->toJson());
        $this->assertDatabaseCount('reemplazos_personal', 2);
    }

    public function test_reincorporation_is_recognized_with_same_id_in_new_year(): void
    {
        $this->personal(101, ['vigente' => false]);
        $this->aplicada(2027, 1);
        $this->assertFalse($this->lookup()['is_funcionario']);
        DB::table('reemplazos_personal')->where('id', 101)->update(['vigente' => true, 'anio' => 2027, 'mes' => 1]);
        $this->assertTrue($this->lookup()['is_funcionario']);
        $this->assertSame('01/2027', $this->autofill()['periodo']);
        $this->assertSame(1, $this->centro()[1]['docentes']);
        $this->assertDatabaseCount('reemplazos_personal', 1);
    }

    public function test_multiple_current_schools_remain_ambiguous_even_with_different_months(): void
    {
        $this->personal();
        $this->personal(102, ['establecimiento_id' => 2, 'rbd' => 99998, 'mes' => 9]);
        $this->assertSame('error', $this->lookup()['status']);
        $this->assertFalse($this->autofill()['ok']);
        DB::table('reemplazos_personal')->where('id', 101)->update(['vigente' => false]);
        $this->assertSame(2, $this->lookup()['establecimiento_id']);
        $this->assertSame(2, $this->autofill()['establecimiento_id']);
    }

    public function test_old_orphan_is_ignored_but_current_orphan_requires_regularization(): void
    {
        $this->personal(101, ['establecimiento_id' => null, 'mes' => 7]);
        $this->personal(102);
        $this->assertTrue($this->lookup()['is_funcionario']);
        $this->assertTrue($this->autofill()['ok']);
        DB::table('reemplazos_personal')->where('id', 101)->update(['mes' => 8]);
        $this->assertSame('error', $this->lookup()['status']);
        $this->assertFalse($this->autofill()['ok']);
        DB::table('reemplazos_personal')->where('id', 101)->update(['vigente' => false]);
        $this->assertTrue($this->lookup()['is_funcionario']);
    }

    public function test_school_reference_missing_from_catalog_is_not_accepted(): void
    {
        $this->personal(101, ['establecimiento_id' => 99]);
        $this->assertSame('error', $this->lookup()['status']);
        $this->assertFalse($this->autofill()['ok']);
    }

    public function test_center_deduplicates_ruts_and_keeps_existing_replacement_and_assistant_counts(): void
    {
        $this->personal(101, ['rut' => '11.111.111-1', 'tipocontrato' => 'REEMPLAZO']);
        $this->personal(102, ['rut' => '111111111', 'jornada' => 10]);
        $this->personal(103, ['rut' => $this->rut(22222222), 'estatuto' => 'ASISTENTE', 'tipocontrato' => 'SUPLENCIA']);
        $this->assertSame(['docentes' => 1, 'asistentes' => 1, 'periodo' => '202608'], $this->centro()[1]);
        $this->assertTrue($this->lookup()['is_funcionario']);
        $this->assertTrue($this->autofill()['ok']);
    }

    public function test_legacy_schema_without_vigente_or_revision_table_keeps_latest_period_rule(): void
    {
        $this->personal();
        Schema::table('reemplazos_personal', fn (Blueprint $t) => $t->dropColumn('vigente'));
        Schema::drop('padron_revisiones');
        $this->assertTrue($this->lookup()['is_funcionario']);
        $this->assertTrue($this->autofill()['ok']);
        $this->assertSame(1, $this->centro()[1]['docentes']);
    }

    public function test_birth_date_confirmation_is_still_required_and_old_identity_is_not_exposed(): void
    {
        $this->personal();
        $controller = app(RegisterRutLookupController::class);
        $service = app(FuncionarioRegisterLookupService::class);
        $data = $controller(Request::create('/lookup', 'GET', ['rut' => $this->rut()]), $service)->getData(true);
        $this->assertSame('funcionario_requires_birth_date', $data['status']);
        $this->assertArrayNotHasKey('nombres', $data);
        $data = $controller(Request::create('/lookup', 'GET', ['rut' => $this->rut(), 'fecha_nacimiento' => '1980-01-01']), $service)->getData(true);
        $this->assertSame('funcionario_prefill', $data['status']);
        DB::table('reemplazos_personal')->update(['vigente' => false]);
        $data = $controller(Request::create('/lookup', 'GET', ['rut' => $this->rut()]), $service)->getData(true);
        $this->assertSame('postulante_available', $data['status']);
        $this->assertArrayNotHasKey('nombres', $data);
    }

    public function test_fallback_preserves_accepted_closed_requests_when_person_has_no_roster_record(): void
    {
        $this->personal(501, ['rut' => $this->rut(22222222)]);
        $this->solicitud();
        $this->assertSame('solicitudes_reemplazo', $this->autofill()['source']);
        DB::table('solicitudes_reemplazo')->update(['estado' => 'aceptada']);
        $this->assertTrue($this->autofill()['ok']);
        DB::table('solicitudes_reemplazo')->update(['estado' => 'rechazada']);
        $this->assertFalse($this->autofill()['ok']);
        DB::table('solicitudes_reemplazo')->update(['estado' => 'cerrado', 'postulant_profile_id' => 99]);
        $this->assertFalse($this->autofill()['ok']);
    }

    public function test_historical_roster_cannot_be_bypassed_with_old_accepted_request(): void
    {
        $this->personal(501, ['rut' => $this->rut(22222222)]);
        $this->solicitud('aceptada');
        $this->personal(101, ['vigente' => false]);
        $before = DB::table('solicitudes_reemplazo')->get()->toJson();
        $result = $this->autofill();
        $this->assertFalse($result['ok']);
        $this->assertTrue($result['tiene_antecedentes']);
        $this->assertSame($before, DB::table('solicitudes_reemplazo')->get()->toJson());
    }

    public function test_invalid_rut_does_not_query_roster(): void
    {
        $this->assertSame('invalid', app(FuncionarioRegisterLookupService::class)->lookup('1-0')['status']);
        $user = $this->user(); $user->rut = '1-0';
        $this->assertFalse(app(TramiteAutofillService::class)->forUser($user)['ok']);
    }
}
