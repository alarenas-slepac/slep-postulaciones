<?php

namespace Tests\Feature;

use App\Models\FuncionarioAcAutorizado;
use App\Models\FuncionarioApiIntegration;
use App\Models\ReemplazoPersonal;
use App\Services\Funcionarios\FuncionarioConsultaService;
use App\Support\RutChile;
use App\Support\SlepUiRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\IsolatedSecurityTestCase;

class FuncionarioConsultaApiTest extends IsolatedSecurityTestCase
{
    private const API = '/api/v1/funcionarios/consultar';
    private const WEB = '/consultas/funcionarios';
    private const ADMIN = '/admin/integraciones-funcionarios';
    private const RUT = '990000018';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 10, 22)->startOfDay());
        Http::preventStrayRequests(); Mail::fake(); Queue::fake();
        Schema::table('permissions', fn (Blueprint $t) => $t->timestamps());
        Schema::table('roles', fn (Blueprint $t) => $t->timestamps());
        Schema::create('role_has_permissions', function (Blueprint $t) { $t->unsignedBigInteger('permission_id'); $t->unsignedBigInteger('role_id'); });
        Schema::create('model_has_permissions', function (Blueprint $t) { $t->unsignedBigInteger('permission_id'); $t->string('model_type'); $t->unsignedBigInteger('model_id'); });
        Role::create(['name' => 'funcionario_estab', 'guard_name' => 'web']);
        Schema::create('establecimientos', function (Blueprint $t) {
            $t->id(); $t->unsignedInteger('rbd'); $t->string('nombre_establecimiento'); $t->string('comuna')->nullable();
        });
        DB::table('establecimientos')->insert([
            ['id' => 1, 'rbd' => 99991, 'nombre_establecimiento' => 'Escuela de prueba uno'],
            ['id' => 2, 'rbd' => 99992, 'nombre_establecimiento' => 'Escuela de prueba dos'],
        ]);
        foreach (['2026_01_29_000001_create_reemplazos_personal_table', '2026_03_25_210000_add_vigencia_and_bienios_to_reemplazos_personal_table',
            '2026_04_30_210000_create_funcionarios_ac_autorizados_table', '2026_10_22_220000_create_funcionario_consulta_api',
            '2026_10_22_221000_index_rut_for_funcionario_consulta'] as $file) {
            (require database_path('migrations/'.$file.'.php'))->up();
        }
        Schema::table('funcionarios_ac_autorizados', function (Blueprint $t) {
            $t->string('subdireccion_dependencia')->nullable(); $t->boolean('jefatura')->default(false);
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function employee(array $attrs = []): ReemplazoPersonal
    {
        $row = new ReemplazoPersonal;
        $row->forceFill(array_replace(['rut' => self::RUT, 'nombre' => 'Persona sintética', 'rbd' => 99991,
            'establecimiento_id' => 1, 'anio' => 2026, 'mes' => 10, 'vigente' => true,
            'estatuto' => 'Estatuto sintético', 'escalafon' => 'Escalafón sintético', 'row_hash' => hash('sha256', random_bytes(16))], $attrs))->save();
        return $row;
    }

    private function ac(array $attrs = []): FuncionarioAcAutorizado
    {
        return FuncionarioAcAutorizado::create(array_replace([
            'run' => '99000001', 'dv' => '8', 'rut_normalizado' => self::RUT,
            'nombres' => 'Persona sintética', 'apellido_paterno' => 'Prueba', 'apellido_materno' => 'Ejemplo',
            'unidad_departamento' => 'Unidad sintética', 'subdireccion_dependencia' => 'Subdirección sintética',
            'estado_autorizacion' => 'activo', 'jefatura' => true,
        ], $attrs));
    }

    private function integration(array $attrs = []): FuncionarioApiIntegration
    {
        return FuncionarioApiIntegration::create($attrs + ['name' => 'Sistema sintético', 'enabled' => true, 'requests_per_minute' => 30]);
    }

    private function callApi(?string $token, array $data = ['rut' => self::RUT])
    {
        Auth::forgetGuards();
        return $this->postJson(self::API, $data, $token ? ['Authorization' => 'Bearer '.$token] : []);
    }

    private function token(FuncionarioApiIntegration $integration, ?array $abilities = null, int $days = 1): string
    {
        return $integration->createToken('sintético', $abilities ?? [FuncionarioApiIntegration::ABILITY], now()->addDays($days))->plainTextToken;
    }

    public function test_api_requires_a_valid_token_and_returns_private_json(): void
    {
        foreach ([null, 'invalid-synthetic-token'] as $token) {
            $this->callApi($token)->assertUnauthorized()->assertJsonPath('error', 'no_autenticado')
                ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer');
        }
        $this->getJson(self::API)->assertNotFound(); // El fallback web existente responde 404 a GET.
    }

    public function test_tokens_are_scoped_expiring_and_isolated_from_session_users(): void
    {
        $client = $this->integration();
        $this->callApi($this->token($client, ['other']))->assertForbidden();
        $this->callApi($this->token($client, days: -1))->assertUnauthorized();
        $this->callApi($client->createToken('sin-vencimiento', [FuncionarioApiIntegration::ABILITY])->plainTextToken)->assertForbidden();
        $this->actingAs($this->testUser(role: 3))->postJson(self::API, ['rut' => self::RUT])->assertForbidden();
    }

    public function test_api_enforces_https_in_production(): void
    {
        $client = $this->integration(); $token = $this->token($client);
        $this->app->instance('env', 'production');
        $this->callApi($token)->assertForbidden();
        $this->postJson('https://localhost'.self::API, ['rut' => self::RUT], ['Authorization' => 'Bearer '.$token])->assertOk();
    }

    public function test_school_result_contains_each_distinct_contract_and_only_allowed_fields(): void
    {
        $this->employee(['fecha_nacimiento' => '1980-01-01', 'source_filename' => 'sintetico-privado.xlsx']);
        $this->employee(['establecimiento_id' => 2, 'rbd' => 99992, 'estatuto' => 'Otro estatuto']);
        $this->employee(['escalafon' => 'Otro escalafón']);
        $this->employee(); // Igual vínculo: no duplicar la salida.
        $client = $this->integration(); $token = $this->token($client);
        $response = $this->callApi($token)->assertOk()->assertJsonPath('estado', 'padron_actual')
            ->assertJsonPath('clasificacion', 'establecimiento')->assertJsonCount(3, 'funcionario.vinculos')
            ->assertJsonPath('funcionario.rut_normalizado', self::RUT);
        $this->assertSame(['rut_normalizado', 'nombre', 'vinculos'], array_keys($response->json('funcionario')));
        $this->assertSame(['rbd', 'establecimiento', 'estatuto', 'escalafon', 'periodo'], array_keys($response->json('funcionario.vinculos.0')));
        $response->assertDontSee('1980-01-01')->assertDontSee('sintetico-privado.xlsx')->assertDontSee($token);
        $audit = DB::table('funcionario_consulta_audits')->sole();
        $this->assertSame($client->id, $audit->integration_id);
        $this->assertSame(hash_hmac('sha256', self::RUT, config('app.key')), $audit->rut_fingerprint);
        $this->assertStringNotContainsString(self::RUT, json_encode($audit));
        $response->assertHeader('X-Request-ID', $audit->request_id);
        Mail::assertNothingSent(); Queue::assertNothingPushed();
    }

    public function test_ac_fields_and_integer_jefatura_are_exact_and_nulls_preserved(): void
    {
        $record = $this->ac(['apellido_materno' => null]);
        $token = $this->token($this->integration());
        $response = $this->callApi($token)->assertOk()->assertJsonPath('clasificacion', 'administracion_central')
            ->assertJsonPath('estado', 'autorizacion_activa')->assertJsonPath('funcionario.jefatura', 1)
            ->assertJsonPath('funcionario.apellido_materno', null);
        $this->assertSame(['rut_normalizado', 'apellido_paterno', 'apellido_materno', 'nombres', 'unidad_departamento', 'subdireccion_dependencia', 'jefatura'], array_keys($response->json('funcionario')));
        $record->update(['jefatura' => false]);
        $this->callApi($token)->assertJsonPath('funcionario.jefatura', 0);
    }

    #[DataProvider('invalidRuts')]
    public function test_invalid_ruts_are_rejected_without_inventing_a_dv(mixed $rut): void
    {
        $this->callApi($this->token($this->integration()), ['rut' => $rut])->assertUnprocessable()->assertJsonPath('error', 'rut_invalido');
        $this->assertNull(DB::table('funcionario_consulta_audits')->sole()->rut_fingerprint);
    }

    public static function invalidRuts(): array
    {
        return [[null], [''], ['99000001'], ['990000019'], ['99.000.001-8'], ['99000001-8'], ['0K'], ['000'], [['rut']], [990000018], ["' OR 1=1 --"]];
    }

    public function test_lowercase_k_and_legacy_formats_are_found_by_generated_index(): void
    {
        $body = 99000001;
        while (RutChile::dv($body) !== 'K') $body++;
        $this->employee(['rut' => number_format($body, 0, '', '.').'-k']);
        $this->callApi($this->token($this->integration()), ['rut' => $body.'k'])->assertOk()
            ->assertJsonPath('estado', 'padron_actual')->assertJsonPath('funcionario.rut_normalizado', $body.'K');
    }

    public function test_absence_and_history_are_distinguished_from_current_employment(): void
    {
        $token = $this->token($this->integration());
        $this->callApi($token)->assertOk()->assertJsonPath('encontrado', false)->assertJsonPath('estado', 'sin_antecedentes');
        $this->employee(['mes' => 9]);
        $this->employee(['rut' => '990000026']); // El padrón actual del establecimiento es octubre.
        $this->callApi($token)->assertJsonPath('encontrado', true)->assertJsonPath('estado', 'sin_vinculo_actual_confirmado')->assertJsonPath('funcionario', null);
    }

    public function test_inactive_expired_and_future_ac_authorizations_are_not_current(): void
    {
        $ac = $this->ac(['estado_autorizacion' => 'inactivo']); $token = $this->token($this->integration());
        $this->callApi($token)->assertJsonPath('estado', 'sin_vinculo_actual_confirmado');
        $ac->update(['estado_autorizacion' => 'activo', 'fecha_inicio_autorizacion' => now()->addDay()]);
        $this->callApi($token)->assertJsonPath('estado', 'sin_vinculo_actual_confirmado');
        $ac->update(['fecha_inicio_autorizacion' => null, 'fecha_fin_autorizacion' => now()->subDay()]);
        $this->callApi($token)->assertJsonPath('estado', 'sin_vinculo_actual_confirmado');
    }

    public function test_dual_source_match_and_orphans_require_review(): void
    {
        $school = $this->employee(); $ac = $this->ac(); $token = $this->token($this->integration());
        $this->callApi($token)->assertJsonPath('estado', 'revision_requerida')
            ->assertJsonCount(1, 'coincidencias.establecimiento.vinculos')->assertJsonCount(1, 'coincidencias.administracion_central');
        $ac->delete(); $school->update(['establecimiento_id' => null]);
        $this->callApi($token)->assertJsonPath('estado', 'revision_requerida')->assertJsonPath('funcionario.vinculos.0.establecimiento', null);
    }

    public function test_failure_in_either_source_or_audit_returns_generic_503_without_logging_payload(): void
    {
        $this->ac(); $token = $this->token($this->integration());
        Schema::drop('reemplazos_personal');
        $response = $this->callApi($token)->assertStatus(503)->assertJsonPath('error', 'servicio_no_disponible');
        $response->assertDontSee(self::RUT)->assertDontSee('SQLSTATE')->assertDontSee('Persona sintética');
        Log::shouldNotHaveReceived('error');
        Schema::drop('funcionario_consulta_audits');
        $this->callApi($token)->assertStatus(503);
    }

    public function test_missing_audit_never_discloses_a_successful_result(): void
    {
        $this->employee(); $token = $this->token($this->integration());
        Schema::drop('funcionario_consulta_audits');
        $this->callApi($token)->assertStatus(503)->assertDontSee('Persona sintética');
    }

    public function test_quota_suspension_and_revocation_take_effect_immediately(): void
    {
        $client = $this->integration(['requests_per_minute' => 1]); $token = $this->token($client);
        $this->callApi($token)->assertOk();
        $this->callApi($token)->assertStatus(429)->assertHeader('Retry-After')->assertHeader('Cache-Control', 'no-store, private');
        $client->update(['enabled' => false]);
        $this->callApi($token)->assertForbidden();
        $client->tokens()->delete();
        $this->callApi($token)->assertUnauthorized();
    }

    public function test_query_string_is_not_an_alternative_to_the_json_body(): void
    {
        $token = $this->token($this->integration());
        $this->postJson(self::API.'?rut='.self::RUT, [], ['Authorization' => 'Bearer '.$token])->assertUnprocessable();
    }

    public function test_web_requires_role_permission_active_context_and_uses_same_result(): void
    {
        $this->get(self::WEB)->assertRedirect('/login');
        $outsider = $this->testUser(); $outsider->givePermissionTo('consultar_funcionarios');
        $this->actingAs($outsider)->getJson(self::WEB)->assertForbidden();
        $user = $this->testUser(id: 2, role: Role::findByName('funcionario_estab')->id);
        $this->employee();
        $this->actingAs($user)->get(self::WEB)->assertOk()->assertSee('Consulta de funcionarios')->assertSee('fc-rut');
        $this->postJson(self::WEB, ['rut' => self::RUT])->assertJsonPath('estado', 'padron_actual');
        $this->assertSame($user->id, DB::table('funcionario_consulta_audits')->sole()->user_id);
        $routes = collect(SlepUiRegistry::menuGroups($user, 'funcionario_estab'))->flatten(1)->pluck('route');
        $this->assertContains('funcionarios.consulta.index', $routes);
        $user->assignRole('postulante');
        $this->withSession(['active_role' => 'postulante'])->postJson(self::WEB, ['rut' => self::RUT])->assertForbidden();
        Role::findByName('funcionario_estab')->revokePermissionTo('consultar_funcionarios');
        app(PermissionRegistrar::class)->forgetCachedPermissions(); $user->unsetRelation('roles');
        $this->withSession(['active_role' => 'funcionario_estab'])->getJson(self::WEB)->assertForbidden();
        $this->assertNotContains('funcionarios.consulta.index', collect(SlepUiRegistry::menuGroups($user, 'funcionario_estab'))->flatten(1)->pluck('route'));
    }

    public function test_only_admin_with_management_permission_can_manage_integrations(): void
    {
        $user = $this->testUser(role: Role::findByName('funcionario_estab')->id);
        $user->givePermissionTo('funcionarios_api.manage');
        $this->actingAs($user)->getJson(self::ADMIN)->assertForbidden();
        $this->postJson(self::ADMIN, ['name' => 'Rechazado', 'requests_per_minute' => 30, 'token_days' => 90])->assertForbidden();
        $this->assertSame(0, FuncionarioApiIntegration::count());
        $admin = $this->testUser(id: 2, role: 3);
        Role::findByName('admin')->revokePermissionTo('funcionarios_api.manage');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($admin)->getJson(self::ADMIN)->assertForbidden();
    }

    public function test_admin_can_create_update_renew_revoke_and_view_activity_without_secret_gets(): void
    {
        $admin = $this->testUser(role: 3);
        $this->actingAs($admin)->get(self::ADMIN)->assertOk()->assertSee('Integraciones API de funcionarios');
        $created = $this->post(self::ADMIN, ['name' => 'Consumidor de prueba', 'requests_per_minute' => 15, 'token_days' => 10])->assertOk()
            ->assertViewIs('admin.funcionarios-api.token')->assertHeader('Cache-Control', 'no-store, private');
        $plain = $created->viewData('plainToken'); $client = FuncionarioApiIntegration::sole();
        $stored = PersonalAccessToken::sole();
        $this->assertNotSame($plain, $stored->token);
        $this->assertSame(hash('sha256', explode('|', $plain, 2)[1]), $stored->token);
        $this->assertEquals(now()->addDays(10), $stored->expires_at);
        $this->assertStringNotContainsString($plain, json_encode(session()->all()));
        $this->get(self::ADMIN)->assertOk()->assertDontSee($plain)->assertDontSee($stored->token);
        $this->patch(self::ADMIN.'/'.$client->id, ['name' => $client->name, 'enabled' => 0, 'requests_per_minute' => 5])->assertRedirect(self::ADMIN);
        $this->assertFalse($client->fresh()->enabled);
        $renewed = $this->post(self::ADMIN.'/'.$client->id.'/token', ['token_days' => 20])->assertOk()->viewData('plainToken');
        $this->assertNull(PersonalAccessToken::findToken($plain));
        $this->assertNotNull(PersonalAccessToken::findToken($renewed));
        $this->delete(self::ADMIN.'/'.$client->id.'/token')->assertRedirect(self::ADMIN);
        $this->assertNull(PersonalAccessToken::findToken($renewed));
    }

    public function test_admin_input_is_validated_and_foreign_integrations_are_bound_safely(): void
    {
        $this->actingAs($this->testUser(role: 3));
        $this->postJson(self::ADMIN, ['name' => 'Prueba', 'requests_per_minute' => 0, 'token_days' => 9999])->assertUnprocessable();
        $this->patchJson(self::ADMIN.'/99999', ['enabled' => 0])->assertNotFound();
        $this->assertSame(0, FuncionarioApiIntegration::count());
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_derived_index_updates_after_source_edits_and_preserves_source_data(): void
    {
        $row = $this->employee(['rut' => '99.000.001-8']);
        $this->assertSame(self::RUT, $row->fresh()->rut_consulta_api);
        $row->update(['rut' => '99.000.002-6']);
        $this->assertSame('990000026', $row->fresh()->rut_consulta_api);
        $this->assertSame('99.000.002-6', $row->fresh()->rut);
        $this->assertTrue(Schema::hasIndex('reemplazos_personal', 'reemplazos_personal_rut_consulta_api_idx'));
    }

    public function test_existing_rows_are_preserved_when_index_migration_is_applied(): void
    {
        foreach (['reemplazos_personal', 'funcionarios_ac_autorizados'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropIndex($table.'_rut_consulta_api_idx');
                $t->dropColumn('rut_consulta_api');
            });
        }
        $this->employee(['rut' => '99.000.001-8']); $this->ac();
        (require database_path('migrations/2026_10_22_221000_index_rut_for_funcionario_consulta.php'))->up();
        $this->assertSame(1, ReemplazoPersonal::count());
        $this->assertSame(1, FuncionarioAcAutorizado::count());
        $this->assertSame('99.000.001-8', ReemplazoPersonal::sole()->rut);
        $this->assertSame(self::RUT, ReemplazoPersonal::sole()->rut_consulta_api);
        $this->assertSame(self::RUT, FuncionarioAcAutorizado::sole()->rut_consulta_api);
    }

    public function test_migrations_can_resume_without_deleting_existing_tokens_audits_or_sources(): void
    {
        $this->employee(); $client = $this->integration(); $token = $this->token($client);
        $this->callApi($token)->assertOk();
        Schema::table('reemplazos_personal', fn (Blueprint $t) => $t->dropIndex('reemplazos_personal_rut_consulta_api_idx'));
        (require database_path('migrations/2026_10_22_220000_create_funcionario_consulta_api.php'))->up();
        (require database_path('migrations/2026_10_22_221000_index_rut_for_funcionario_consulta.php'))->up();
        $this->assertSame(1, FuncionarioApiIntegration::count());
        $this->assertSame(1, DB::table('funcionario_consulta_audits')->count());
        $this->assertNotNull(PersonalAccessToken::findToken($token));
        $this->assertTrue(Schema::hasIndex('reemplazos_personal', 'reemplazos_personal_rut_consulta_api_idx'));
        $this->callApi($token)->assertJsonPath('estado', 'padron_actual');
    }

    public function test_csrf_is_required_for_web_consultation_and_token_management(): void
    {
        $this->actingAs($this->testUser(role: 3));
        $this->app->instance('env', 'local'); // Activa la comprobación CSRF real del framework.
        $this->postJson(self::WEB, ['rut' => self::RUT])->assertStatus(419);
        $this->post(self::ADMIN, ['name' => 'Rechazada', 'requests_per_minute' => 30, 'token_days' => 10])->assertStatus(419);
        $this->assertSame(0, FuncionarioApiIntegration::count());
    }

    public function test_multiple_ac_matches_require_review_and_inactive_ac_does_not_hide_a_current_school(): void
    {
        $this->ac(); $this->ac(['rut_normalizado' => '99.000.001-8']);
        $token = $this->token($this->integration());
        $this->callApi($token)->assertJsonPath('estado', 'revision_requerida')->assertJsonCount(2, 'coincidencias.administracion_central');
        FuncionarioAcAutorizado::query()->update(['estado_autorizacion' => 'inactivo']);
        $this->employee();
        $this->callApi($token)->assertJsonPath('estado', 'padron_actual')->assertJsonPath('clasificacion', 'establecimiento');
    }
}
