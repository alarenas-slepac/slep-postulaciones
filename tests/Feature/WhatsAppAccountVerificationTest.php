<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppMessage;
use App\Models\AccountWhatsAppVerification as Proof;
use App\Models\NotificationEventSetting;
use App\Models\User;
use App\Models\WhatsAppDispatch;
use App\Services\Auth\WhatsAppAccountVerification as Verification;
use App\Services\Notifications\NotificationEventCatalog;
use App\Services\Notifications\NotificationEventPolicy;
use App\Services\Notifications\NotificationWhatsAppRecipients;
use App\Services\WhatsApp\WhatsAppClient;
use App\Support\ChileanMobilePhone;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedSecurityTestCase;

class WhatsAppAccountVerificationTest extends IsolatedSecurityTestCase
{
    protected User $account;
    protected Verification $verification;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite(); Http::preventStrayRequests(); Queue::fake();
        $this->travelTo(now()->startOfSecond());
        config(['app.url' => 'https://sga.example.test', 'whatsapp.enabled' => true, 'whatsapp.queue_connection' => 'database',
            'whatsapp.url' => 'http://127.0.0.1:3210', 'whatsapp.token' => str_repeat('synthetic_token_', 4)]);
        Schema::table('roles', fn (Blueprint $t) => $t->timestamps());
        Schema::table('permissions', fn (Blueprint $t) => $t->timestamps());
        foreach (['role_has_permissions', 'model_has_permissions'] as $name) {
            Schema::create($name, function (Blueprint $t) use ($name): void {
                $t->unsignedBigInteger('permission_id');
                if ($name === 'role_has_permissions') { $t->unsignedBigInteger('role_id'); }
                else { $t->string('model_type'); $t->unsignedBigInteger('model_id'); }
            });
        }
        foreach (['2026_10_10_120000_create_whatsapp_dispatches.php', '2026_10_11_170000_create_notification_event_settings.php',
            '2026_11_12_160000_create_account_whatsapp_verifications.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        Schema::create('postulant_profiles', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('user_id')->unique('test_profile_user_uq');
            $t->date('fecha_nacimiento'); $t->string('telefono1')->nullable(); $t->string('direccion')->nullable(); $t->timestamps();
        });
        $this->account = $this->unverifiedUser(1);
        $this->actingAs($this->account)->withSession(['active_role' => 'postulante']);
        NotificationEventSetting::create(['event_key' => Verification::EVENT, 'channel' => 'whatsapp', 'enabled' => true, 'recipient_mode' => 'all']);
        $this->verification = app(Verification::class);
        Route::middleware(['web', 'auth', 'verified'])->get('/__tests/account-verified', fn () => 'Cuenta habilitada');
        Route::middleware(['web', 'auth', 'verified', 'ensure.role:admin'])->get('/__tests/account-admin', fn () => 'Administración');
        $path = Storage::disk('local')->path('auth-verification-views');
        File::ensureDirectoryExists($path.'/layouts');
        File::put($path.'/layouts/app.blade.php', '<!doctype html><html lang="es"><head><meta name="viewport" content="width=device-width, initial-scale=1">@stack("styles")</head><body><main>@yield("content")</main>@stack("scripts")</body></html>');
        view()->getFinder()->prependLocation($path);
    }

    protected function unverifiedUser(int $id): User
    {
        return tap($this->testUser($id), fn (User $u) => $u->forceFill(['email_verified_at' => null])->save());
    }

    protected function requestCode(string $phone = '900000001', ?User $user = null): array
    {
        $user ??= $this->account;
        $this->verification->request($user, $phone);
        $record = Proof::where('user_id', $user->id)->sole();
        $dispatch = WhatsAppDispatch::findOrFail($record->dispatch_id);
        preg_match('/código para validar tu cuenta es: ([0-9]{6})/u', $dispatch->payload['text'], $matches);
        $this->assertCount(2, $matches);
        return [$record, $matches[1], $dispatch];
    }

    private function confirm(Proof $record, string $code, ?User $user = null): void
    {
        $this->verification->confirm($user ?? $this->account, $record->challenge_id, $code);
    }

    public static function phones(): array
    {
        return ['nacional' => ['900000001'], 'pais' => ['56900000001'], 'internacional' => ['+56900000001'],
            'espacios' => ['+56 9 0000 0001'], 'separadores' => ['(56) 9-0000-0001']];
    }

    #[DataProvider('phones')]
    public function test_normaliza_y_encola_codigo_personal_cifrado_sin_conectar_whatsapp(string $phone): void
    {
        [$record, $code, $dispatch] = $this->requestCode($phone);
        $this->assertSame('+56900000001', $record->pending_phone);
        $this->assertSame(300, $record->expires_at->timestamp - now()->timestamp);
        $this->assertSame(60, $record->resend_at->timestamp - now()->timestamp);
        $this->assertNotSame($code, $record->code_hash);
        $this->assertStringNotContainsString($code, DB::table('whatsapp_dispatches')->value('payload'));
        $this->assertStringContainsString('https://sga.example.test/verify-email?channel=whatsapp', $dispatch->payload['text']);
        $this->assertFalse($this->account->hasVerifiedAccount());
        $this->assertSame(0, DB::table('postulant_profiles')->count());
        Queue::assertPushed(SendWhatsAppMessage::class, fn ($job) => $job->dispatchId === $dispatch->id);
        Http::assertNothingSent();
    }

    public static function invalidPhones(): array
    {
        return array_map(fn ($p) => [$p], ['', '123', '800000001', '229000001', '+56229000001', '+54900000001', '00900000001',
            '+569000000001', '5690000000', '+56+900000001', '900000001/900000002', '900000001abc', '９０００００００１', ['900000001']]);
    }

    #[DataProvider('invalidPhones')]
    public function test_formato_invalido_no_crea_desafios_ni_envios(mixed $phone): void
    {
        $this->assertNull(ChileanMobilePhone::normalize($phone));
        $this->post(route('verification.whatsapp.send'), ['whatsapp_phone' => $phone])->assertSessionHasErrors('whatsapp_phone');
        $this->assertSame(0, Proof::count()); Queue::assertNothingPushed(); Http::assertNothingSent();
    }

    public function test_confirma_cuenta_sin_verificar_correo_ni_elevar_permisos_y_no_reutiliza_codigo(): void
    {
        Event::fake([Verified::class]);
        $this->get('/__tests/account-verified')->assertRedirect(route('verification.notice'));
        [$record, $code] = $this->requestCode();
        $this->post(route('verification.whatsapp.confirm'), ['challenge_id' => $record->challenge_id, 'whatsapp_code' => $code])->assertRedirect(route('dashboard'));
        $this->assertNull($this->account->fresh()->email_verified_at);
        $this->assertTrue($this->account->fresh()->hasVerifiedAccount());
        $this->assertFalse($this->account->fresh()->hasVerifiedEmail());
        $this->assertSame('+56900000001', $record->fresh()->verified_phone);
        $this->assertNull($record->fresh()->code_hash);
        $this->get('/__tests/account-verified')->assertOk();
        $this->get('/__tests/account-admin')->assertForbidden();
        $this->assertSame(['postulante'], $this->account->getRoleNames()->all());
        Event::assertNotDispatched(Verified::class);
        try { $this->confirm($record, $code); $this->fail('No se reutiliza un código consumido.'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('whatsapp_code', $e->errors()); }
    }

    public function test_cooldown_persistente_59_segundos_y_nuevo_codigo_a_los_60_cancela_el_anterior(): void
    {
        [$old, $code, $dispatch] = $this->requestCode();
        $this->travel(59)->seconds();
        try { $this->requestCode(); $this->fail('No puede reenviar antes de 60 segundos.'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('whatsapp_phone', $e->errors()); }
        $this->travel(1)->seconds();
        [$new, $newCode] = $this->requestCode('56900000001');
        $this->assertNotSame($old->challenge_id, $new->challenge_id);
        try { $this->confirm($old, $code); $this->fail('El desafío anterior quedó invalidado.'); }
        catch (ValidationException) { $this->assertNull($new->fresh()->verified_at); }
        (new SendWhatsAppMessage($dispatch->id))->handle(app(WhatsAppClient::class));
        $this->assertSame('cancelled', $dispatch->fresh()->status);
        Http::assertNothingSent();
        $this->confirm($new, $newCode);
        $this->assertTrue($this->account->fresh()->hasVerifiedAccount());
    }

    public function test_vence_exactamente_a_los_cinco_minutos_y_el_worker_no_envia_codigos_vencidos(): void
    {
        [$record, $code, $dispatch] = $this->requestCode();
        $this->travel(300)->seconds();
        try { $this->confirm($record, $code); $this->fail('El código ya venció.'); }
        catch (ValidationException) { $this->assertNull($record->fresh()->verified_at); }
        (new SendWhatsAppMessage($dispatch->id))->handle(app(WhatsAppClient::class));
        $this->assertSame('verification_expired', $dispatch->fresh()->error_code);
        Http::assertNothingSent();
    }

    public function test_cinco_intentos_se_guardan_aunque_la_respuesta_sea_error(): void
    {
        [$record, $code] = $this->requestCode();
        $wrong = $code === '000000' ? '111111' : '000000';
        foreach (range(1, 5) as $attempt) {
            $this->post(route('verification.whatsapp.confirm'), ['challenge_id' => $record->challenge_id, 'whatsapp_code' => $wrong])
                ->assertSessionHasErrors('whatsapp_code')->assertSessionMissing('_old_input.whatsapp_code');
            $this->assertSame($attempt, $record->fresh()->attempts);
        }
        $this->assertNull($record->fresh()->code_hash);
        try { $this->confirm($record, $code); $this->fail('El código agotado no puede usarse.'); }
        catch (ValidationException) { $this->assertFalse($this->account->fresh()->hasVerifiedAccount()); }
    }

    public function test_no_permite_reutilizar_numero_confirmado_incluso_si_la_cuenta_anterior_fue_eliminada(): void
    {
        [$record, $code] = $this->requestCode(); $this->confirm($record, $code);
        $other = $this->unverifiedUser(2);
        $this->account->forceFill(['deleted_at' => now()])->save();
        foreach (['900000001', '56900000001', '+56900000001'] as $phone) {
            try { $this->requestCode($phone, $other); $this->fail('El número confirmado es exclusivo.'); }
            catch (ValidationException $e) { $this->assertArrayHasKey('whatsapp_phone', $e->errors()); }
        }
        $this->assertSame(1, Proof::count());
    }

    public function test_dos_desafios_pendientes_no_pueden_confirmar_el_mismo_numero_en_dos_cuentas(): void
    {
        [$first, $code1] = $this->requestCode();
        $other = $this->unverifiedUser(2);
        [$second, $code2] = $this->requestCode('900000001', $other);
        $this->confirm($first, $code1);
        try { $this->confirm($second, $code2, $other); $this->fail('La segunda confirmación debe rechazarse.'); }
        catch (ValidationException) { $this->assertNull($second->fresh()->verified_at); }
        $this->assertSame(1, Proof::whereNotNull('verified_at')->count());
    }

    public function test_actualiza_telefono_del_perfil_existente_sin_cambiar_otros_datos(): void
    {
        DB::table('postulant_profiles')->insert(['user_id' => $this->account->id, 'fecha_nacimiento' => '1990-01-01', 'direccion' => 'Dirección sintética', 'telefono1' => '900000009']);
        [$record, $code] = $this->requestCode(); $this->confirm($record, $code);
        $profile = DB::table('postulant_profiles')->sole();
        $this->assertSame('+56900000001', $profile->telefono1);
        $this->assertSame('Dirección sintética', $profile->direccion);
        $this->assertSame('1990-01-01', $profile->fecha_nacimiento);
        $this->assertSame('+56900000001', $this->account->fresh()->verifiedWhatsAppPhone());
        $this->assertSame($this->account->id, User::verifiedAccount()->sole()->id);
        $this->assertSame($this->account->id, app(NotificationWhatsAppRecipients::class)->eligible()->sole()['user_id']);
    }

    public function test_visitante_no_puede_consultar_solicitar_o_confirmar_y_un_usuario_no_puede_usar_desafio_ajeno(): void
    {
        [$record, $code] = $this->requestCode();
        auth()->logout();
        $this->get(route('verification.notice'))->assertRedirect('/login');
        $this->post(route('verification.whatsapp.send'), ['whatsapp_phone' => '900000002'])->assertRedirect('/login');
        $this->post(route('verification.whatsapp.confirm'), ['challenge_id' => $record->challenge_id, 'whatsapp_code' => $code])->assertRedirect('/login');
        $this->getJson(route('verification.whatsapp.status'))->assertUnauthorized();
        $this->actingAs($this->unverifiedUser(2));
        $this->post(route('verification.whatsapp.confirm'), ['user_id' => $this->account->id, 'challenge_id' => $record->challenge_id, 'whatsapp_code' => $code])->assertSessionHasErrors('whatsapp_code');
        $this->assertNull($record->fresh()->verified_at);
        $state = $this->getJson(route('verification.whatsapp.status'))->assertOk()->json();
        $this->assertSame('', $state['phone']); $this->assertNull($state['challenge_id']);
    }

    public function test_se_conserva_el_enlace_firmado_de_correo_y_no_se_crea_confirmacion_whatsapp(): void
    {
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), ['id' => $this->account->id, 'hash' => sha1($this->account->email)]);
        $this->get($url)->assertRedirect(route('dashboard'));
        $this->assertTrue($this->account->fresh()->hasVerifiedEmail());
        $this->assertSame(0, Proof::count());
        $this->get('/__tests/account-verified')->assertOk();
    }

    public function test_evento_aparece_sin_preferencia_y_comienza_desactivado(): void
    {
        NotificationEventSetting::query()->delete();
        $event = app(NotificationEventCatalog::class)->find(Verification::EVENT);
        $this->assertSame(['whatsapp'], $event['channels']);
        $this->assertSame('Acceso', $event['module']);
        $this->assertFalse(app(NotificationEventPolicy::class)->enabled(Verification::EVENT, 'whatsapp'));
        $this->get(route('verification.notice'))->assertOk()->assertSee('no está disponible');
        $this->post(route('verification.whatsapp.send'), ['whatsapp_phone' => '900000001'])->assertSessionHasErrors('whatsapp');
        Queue::assertNothingPushed();
    }

    public function test_pantalla_y_estado_son_privados_no_exponen_digest_codigo_o_clave(): void
    {
        [$record, $code] = $this->requestCode();
        $this->get(route('verification.notice', ['channel' => 'whatsapp']))->assertOk()->assertSee('Código recibido por WhatsApp')
            ->assertSee('data-resend-at', false)->assertDontSee($record->code_hash)->assertHeader('Cache-Control', 'no-store, private');
        $response = $this->getJson(route('verification.whatsapp.status'))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertArrayNotHasKey('code_hash', $response->json());
        $this->assertArrayNotHasKey('code', $response->json());
        $this->post(route('verification.whatsapp.confirm'), ['challenge_id' => $record->challenge_id, 'whatsapp_code' => 'no-code'])
            ->assertSessionHasErrors('whatsapp_code')->assertSessionMissing('_old_input.whatsapp_code');
    }

    public function test_limites_http_no_se_evaden_cambiando_formato_del_mismo_numero(): void
    {
        $this->post(route('verification.whatsapp.send'), ['whatsapp_phone' => '900000001'])->assertSessionHasNoErrors();
        $this->post(route('verification.whatsapp.send'), ['whatsapp_phone' => '+56900000001'])->assertStatus(429);
        $this->travel(60)->seconds();
        $this->post(route('verification.whatsapp.send'), ['whatsapp_phone' => '56900000001'])->assertSessionHasNoErrors();
        $this->assertSame(2, WhatsAppDispatch::count());
    }

    public static function staleStates(): array
    {
        return array_map(fn ($state) => [$state], ['disabled', 'service-off', 'email-changed', 'email-verified', 'deleted', 'phone-changed', 'policy-missing']);
    }

    #[DataProvider('staleStates')]
    public function test_worker_revalida_condiciones_antes_de_enviar(string $state): void
    {
        [$record, , $dispatch] = $this->requestCode();
        match ($state) {
            'disabled' => NotificationEventSetting::query()->update(['enabled' => false]),
            'service-off' => config(['whatsapp.enabled' => false]),
            'email-changed' => $this->account->update(['email' => 'cambio@example.test']),
            'email-verified' => $this->account->forceFill(['email_verified_at' => now()])->save(),
            'deleted' => $this->account->forceFill(['deleted_at' => now()])->save(),
            'phone-changed' => $record->update(['pending_phone' => '+56900000002']),
            'policy-missing' => $dispatch->update(['payload' => ['to' => '+56900000001', 'text' => 'Código sintético sin política']]),
        };
        (new SendWhatsAppMessage($dispatch->id))->handle(app(WhatsAppClient::class));
        $this->assertSame('cancelled', $dispatch->fresh()->status); Http::assertNothingSent();
    }

    public function test_worker_envia_solo_el_codigo_actual_y_borra_payload_cifrado_tras_aceptacion(): void
    {
        [$record, $code, $dispatch] = $this->requestCode();
        Http::fake(['127.0.0.1:3210/*' => Http::response(['status' => 'sent'])]);
        (new SendWhatsAppMessage($dispatch->id))->handle(app(WhatsAppClient::class));
        Http::assertSent(fn ($r) => $r['to'] === '+56900000001' && str_contains($r['text'], $code) && ! isset($r['policy']));
        $this->assertSame('sent', $dispatch->fresh()->status); $this->assertNull($dispatch->fresh()->payload);
        $this->confirm($record, $code); $this->assertTrue($this->account->fresh()->hasVerifiedAccount());
    }

    public function test_telefono_se_prefija_en_mi_perfil_sin_crear_registro_incompleto(): void
    {
        [$record, $code] = $this->requestCode(); $this->confirm($record, $code);
        Schema::create('communes', function (Blueprint $t): void { $t->id(); $t->string('name'); $t->string('region_code'); });
        Schema::create('commune_user', function (Blueprint $t): void { $t->unsignedBigInteger('user_id'); $t->unsignedBigInteger('commune_id'); });
        Schema::create('areas_desempeno', function (Blueprint $t): void { $t->id(); $t->string('nombre'); $t->string('estamento'); $t->boolean('activo'); });
        Schema::create('menciones', function (Blueprint $t): void { $t->id(); $t->string('nombre'); $t->unsignedBigInteger('subsector_id')->nullable(); });
        $request = Request::create('/mi-perfil'); $request->setUserResolver(fn () => $this->account->fresh());
        $view = app(\App\Http\Controllers\PostulantProfileController::class)->edit($request);
        $this->assertSame('+56900000001', $view->getData()['profile']->telefono1);
        $this->assertFalse($view->getData()['profile']->exists);
        $this->assertSame(0, DB::table('postulant_profiles')->count());
    }

    public function test_evento_se_configura_solo_por_administrador_sin_copias_ni_destinatarios_arbitrarios(): void
    {
        NotificationEventSetting::query()->delete();
        $path = '/admin/eventos-notificaciones/'.Verification::EVENT;
        $data = ['revision' => 0, 'enabled' => true, 'subject_template' => null, 'body_template' => null,
            'recipient_mode' => 'all', 'recipient_ids' => [], 'additional_emails' => null];
        $this->put($path.'/whatsapp', $data)->assertRedirect(route('verification.notice'));
        $this->assertSame(0, NotificationEventSetting::count());
        $this->actingAs($this->testUser(3, 3))->get($path.'/editar')->assertOk()->assertSee('Cuenta autenticada')->assertSee('Guardar WhatsApp');
        $this->post($path.'/whatsapp/preview', $data)->assertOk()->assertSee('Código de ejemplo: 000000');
        $this->put($path.'/whatsapp', $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue(app(NotificationEventPolicy::class)->enabled(Verification::EVENT, 'whatsapp'));
        $this->putJson($path.'/mail', $data)->assertNotFound();
        $this->putJson($path.'/whatsapp', array_replace($data, ['revision' => 1, 'recipient_mode' => 'selected', 'recipient_ids' => [$this->account->id]]))
            ->assertUnprocessable();
        $this->putJson($path.'/whatsapp', array_replace($data, ['revision' => 1, 'additional_emails' => 'copia@example.test']))->assertUnprocessable();
        $this->post($path.'/whatsapp/restablecer', ['revision' => 1])->assertRedirect();
        $this->assertFalse(app(NotificationEventPolicy::class)->enabled(Verification::EVENT, 'whatsapp'));
        Queue::assertNothingPushed(); Http::assertNothingSent();
    }

    public function test_solicitud_en_transaccion_externa_solo_encola_despues_del_commit(): void
    {
        DB::beginTransaction();
        $this->requestCode(); Queue::assertNothingPushed();
        DB::rollBack();
        $this->assertSame(0, Proof::count()); $this->assertSame(0, WhatsAppDispatch::count()); Queue::assertNothingPushed();
        DB::beginTransaction(); $this->requestCode(); Queue::assertNothingPushed(); DB::commit();
        Queue::assertPushed(SendWhatsAppMessage::class, 1);
    }

    public function test_fallo_de_encolado_no_deja_codigo_inutilizable_ni_valida_cuenta(): void
    {
        config(['whatsapp.queue_connection' => 'sync']);
        $this->post(route('verification.whatsapp.send'), ['whatsapp_phone' => '900000001'])->assertSessionHasErrors('whatsapp_phone');
        $this->assertSame(0, Proof::count()); $this->assertSame(0, WhatsAppDispatch::count()); Queue::assertNothingPushed();
        $this->assertFalse($this->account->fresh()->hasVerifiedAccount());
    }

    public function test_presentacion_configurable_conserva_codigo_enlace_y_pie_al_final(): void
    {
        NotificationEventSetting::query()->update(['body_template' => "Información del SGA\n{{contenido_original}}\nCuida tu cuenta."]);
        [, $code, $dispatch] = $this->requestCode();
        $this->assertSame(1, substr_count($dispatch->payload['text'], $code));
        $this->assertStringEndsWith('Por favor, no respondas a este mensaje.', $dispatch->payload['text']);
        $this->assertStringContainsString('https://sga.example.test/verify-email?channel=whatsapp', $dispatch->payload['text']);
    }

    public function test_migracion_pendiente_y_enlace_inseguro_no_habilitan_whatsapp_y_conservan_correo(): void
    {
        Schema::drop('account_whatsapp_verifications');
        $this->get(route('verification.notice'))->assertOk()->assertSee('no está disponible');
        $this->assertFalse($this->account->fresh()->hasVerifiedAccount());
        $this->account->forceFill(['email_verified_at' => now()])->save();
        $this->get('/__tests/account-verified')->assertOk();
        config(['app.url' => 'http://sga.example.test']);
        $this->assertFalse($this->verification->available());
    }

    public function test_una_migracion_parcial_sin_exclusividad_no_permite_emitir_codigos(): void
    {
        Schema::table('account_whatsapp_verifications', fn (Blueprint $t) => $t->dropUnique('awv_phone_uq'));
        $this->assertFalse($this->verification->available());
        $this->post(route('verification.whatsapp.send'), ['whatsapp_phone' => '900000001'])->assertSessionHasErrors('whatsapp');
        $this->assertSame(0, Proof::count()); Queue::assertNothingPushed();
    }

    public function test_configuracion_cacheada_sin_evento_no_activa_el_canal_por_compatibilidad(): void
    {
        config(['notification_events.account_whatsapp' => []]);
        $this->assertFalse($this->verification->available());
        $this->post(route('verification.whatsapp.send'), ['whatsapp_phone' => '900000001'])->assertSessionHasErrors('whatsapp');
        Queue::assertNothingPushed();
    }
}
