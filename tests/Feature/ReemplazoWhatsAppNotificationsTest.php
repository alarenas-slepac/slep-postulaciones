<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppMessage;
use App\Models\FuncionarioAcAutorizado;
use App\Models\SolicitudReemplazo;
use App\Models\User;
use App\Models\WhatsAppDispatch;
use App\Services\SolicitudReemplazoAutorizacionDocenteService;
use App\Services\WhatsApp\ReemplazoWhatsAppNotifier;
use App\Services\WhatsApp\ReemplazoWhatsAppRecipients;
use App\Services\WhatsApp\WhatsAppClient;
use App\Services\WhatsApp\WhatsAppException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\IsolatedSecurityTestCase;

class ReemplazoWhatsAppNotificationsTest extends IsolatedSecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests(); Queue::fake(); Mail::fake(); $this->withoutVite();
        config(['app.url' => 'https://sga.example.test', 'whatsapp.enabled' => true, 'whatsapp.reemplazos.enabled' => true,
            'whatsapp.url' => 'http://127.0.0.1:3210', 'whatsapp.token' => str_repeat('synthetic_', 8),
            'whatsapp.queue_connection' => 'database', 'whatsapp.queue' => 'whatsapp']);
        foreach (['roles', 'permissions'] as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->timestamps());
        }
        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id'); $table->unsignedBigInteger('role_id');
        });
        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id'); $table->string('model_type'); $table->unsignedBigInteger('model_id');
        });
        foreach (ReemplazoWhatsAppRecipients::ROLES as $index => $role) {
            DB::table('roles')->insert(['id' => $index + 4, 'name' => $role, 'guard_name' => 'web']);
        }
        (require database_path('migrations/2026_10_10_120000_create_whatsapp_dispatches.php'))->up();
        (require database_path('migrations/2026_04_30_210000_create_funcionarios_ac_autorizados_table.php'))->up();
        (require database_path('migrations/2026_10_11_150000_add_whatsapp_reemplazos_preference.php'))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Schema::create('modules', function (Blueprint $table): void { $table->id(); $table->string('key'); });
        Schema::create('module_role', function (Blueprint $table): void { $table->unsignedBigInteger('module_id'); $table->unsignedBigInteger('role_id'); });
        DB::table('modules')->insert(['id' => 1, 'key' => 'gestion.solicitudes-reemplazo']);
        foreach ([4, 5, 6, 7] as $role) DB::table('module_role')->insert(['module_id' => 1, 'role_id' => $role]);
        Schema::create('establecimientos', function (Blueprint $table): void {
            $table->id(); $table->string('nombre_establecimiento');
        });
        DB::table('establecimientos')->insert(['id' => 1, 'nombre_establecimiento' => 'Establecimiento de prueba']);
        Schema::create('solicitudes_reemplazo', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('establecimiento_id')->default(1);
            foreach (['numero_solicitud', 'estado', 'contacto_email', 'motivo_rechazo', 'justificacion_tecnica_uatp',
                'plani_motivo_rechazo', 'uatp_rechazo_reabierto_motivo', 'uatp_reapertura_motivo',
                'plani_rechazo_reabierto_motivo', 'plani_reapertura_motivo'] as $field) {
                $table->string($field)->nullable();
            }
            foreach (['uatp_decision_user_id', 'plani_decision_user_id', 'derivada_a_user_id',
                'derivada_por_user_id', 'uatp_reapertura_user_id', 'plani_reapertura_user_id'] as $field) {
                $table->unsignedBigInteger($field)->nullable();
            }
            foreach (['fecha_inicio', 'fecha_termino', 'uatp_decision_at', 'plani_decision_at',
                'derivada_at', 'uatp_reapertura_at', 'plani_reapertura_at'] as $field) {
                $table->dateTime($field)->nullable();
            }
            $table->boolean('continuidad')->nullable(); $table->boolean('es_continuidad')->nullable(); $table->timestamps();
        });
    }

    private function recipient(int $role = 4, array $attributes = []): FuncionarioAcAutorizado
    {
        $number = User::count() + 1;
        $user = $this->testUser($number, $role);
        return FuncionarioAcAutorizado::create($attributes + [
            'run' => '9900000'.$number, 'dv' => 'K', 'rut_normalizado' => $user->rut,
            'nombres' => 'Cuenta de prueba', 'apellido_paterno' => 'Prueba',
            'registered_user_id' => $user->id, 'estado_autorizacion' => 'activo',
            'telefono' => '+9990000000'.$number, 'notificar_whatsapp_reemplazos' => true,
        ]);
    }

    private function solicitud(string $state = 'pendiente_uatp'): SolicitudReemplazo
    {
        return SolicitudReemplazo::create(['establecimiento_id' => 1, 'estado' => $state, 'numero_solicitud' => '00001-2026',
            'fecha_inicio' => '2026-10-12', 'fecha_termino' => '2026-10-30', 'es_continuidad' => true]);
    }

    private function notify(SolicitudReemplazo $solicitud, string $event = 'created', ?string $occurrence = null): void
    {
        app(ReemplazoWhatsAppNotifier::class)->notify($solicitud, $event, $occurrence);
    }

    public function test_default_flags_are_off_and_old_generic_preference_does_not_opt_in(): void
    {
        $this->assertFalse((require config_path('whatsapp.php'))['reemplazos']['enabled']);
        $record = $this->recipient(attributes: ['notificar_whatsapp_reemplazos' => false, 'enviar_notificacion' => true]);
        $this->notify($this->solicitud());
        Queue::assertNothingPushed(); $this->assertSame(0, WhatsAppDispatch::count());
        $record->update(['notificar_whatsapp_reemplazos' => true]);
        config(['whatsapp.reemplazos.enabled' => false]); $this->notify(SolicitudReemplazo::first());
        Queue::assertNothingPushed(); Http::assertNothingSent();
    }

    #[DataProvider('phones')]
    public function test_phone_is_normalized_and_validated_before_enqueue(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, app(ReemplazoWhatsAppRecipients::class)->phone($input));
        $this->recipient(attributes: ['telefono' => $input]); $this->notify($this->solicitud());
        $this->assertSame($expected ? 1 : 0, WhatsAppDispatch::count());
        if ($expected) $this->assertSame($expected, WhatsAppDispatch::first()->payload['to']);
        Http::assertNothingSent();
    }

    public static function phones(): array
    {
        return [
            'móvil sin prefijo' => ['900000000', '+56900000000'],
            '56 sin signo' => ['56900000000', '+56900000000'],
            'con formato' => ['+56 (9) 0000-0000', '+56900000000'],
            'prefijo 00' => ['0056900000000', '+56900000000'],
            'internacional explícito' => ['+99900000001', '+99900000001'],
            'vacío' => [null, null], 'corto' => ['12345', null],
            'Chile incompleto' => ['+569000000', null], 'Chile largo' => ['+569000000000', null],
            'anexo' => ['900000000 anexo 1', null], 'dos números' => ['900000000 / 900000001', null],
            'internacional sin país explícito' => ['99900000001', null],
        ];
    }

    public function test_message_contains_summary_and_canonical_link_without_personal_or_medical_details(): void
    {
        $this->recipient(); $solicitud = $this->solicitud();
        $this->notify($solicitud);
        $dispatch = WhatsAppDispatch::sole(); $text = $dispatch->payload['text'];
        foreach (['00001-2026', 'Establecimiento de prueba', '12-10-2026', '30-10-2026', 'Sí (validada)',
            'https://sga.example.test/gestion/solicitudes-reemplazo/'.$solicitud->id] as $part) {
            $this->assertStringContainsString($part, $text);
        }
        $raw = DB::table('whatsapp_dispatches')->value('payload');
        $this->assertStringNotContainsString($text, $raw);
        $this->assertStringNotContainsString($dispatch->payload['to'], $dispatch->idempotency_key);
        $this->assertStringNotContainsString('policy', serialize(new SendWhatsAppMessage($dispatch->id)));
        Http::assertNothingSent();
    }

    public function test_inactive_expired_future_unverified_and_wrong_role_are_excluded(): void
    {
        $this->recipient(attributes: ['estado_autorizacion' => 'inactivo']);
        $this->recipient(attributes: ['fecha_fin_autorizacion' => now()->subDay()]);
        $this->recipient(attributes: ['fecha_inicio_autorizacion' => now()->addDay()]);
        $unverified = $this->recipient(); $unverified->registeredUser->forceFill(['email_verified_at' => null])->save();
        $this->recipient(role: 3); $this->recipient(role: 2);
        $this->notify($this->solicitud());
        $this->assertSame(0, WhatsAppDispatch::count()); Queue::assertNothingPushed();
    }

    public function test_unique_historical_rut_is_supported_but_conflicting_link_and_duplicate_phone_are_safe(): void
    {
        $first = $this->recipient(attributes: ['registered_user_id' => null]);
        $this->recipient(attributes: ['telefono' => $first->telefono]);
        $conflict = $this->recipient(); $conflict->update(['registered_user_id' => $first->registered_user_id ?: User::first()->id]);
        $this->notify($this->solicitud());
        $this->assertSame(1, WhatsAppDispatch::count());
    }

    public function test_absent_module_access_prevents_delivery(): void
    {
        $this->recipient();
        DB::table('module_role')->delete();
        $this->notify($this->solicitud()); $this->assertSame(0, WhatsAppDispatch::count());
    }

    public function test_ambiguous_historical_rut_and_multiple_active_fiches_are_not_guessed(): void
    {
        $record = $this->recipient(attributes: ['registered_user_id' => null]);
        $duplicate = $this->testUser(20, 1); DB::table('users')->where('id', $duplicate->id)->update(['rut' => $record->rut_normalizado]);
        $this->notify($this->solicitud()); $this->assertSame(0, WhatsAppDispatch::count());
        $duplicate->delete();
        $linked = $this->recipient();
        $extra = $this->recipient();
        $extra->update(['registered_user_id' => $linked->registered_user_id]);
        // Corregir el vínculo contradictorio mantiene dos fichas de la misma cuenta:
        // la normalización del RUT en ambas debe coincidir para llegar a esa comprobación.
        DB::table('funcionarios_ac_autorizados')->where('id', $extra->id)->update(['rut_normalizado' => $linked->run.'-K']);
        $extra->forceFill(['run' => $linked->run, 'dv' => $linked->dv])->save();
        $this->assertCount(1, app(ReemplazoWhatsAppRecipients::class)->forStage(['coordinador_uatp']));
    }

    public function test_editor_renders_accessible_explicit_opt_in_and_does_not_call_bridge(): void
    {
        $record = $this->recipient(attributes: ['notificar_whatsapp_reemplazos' => false]);
        $this->actingAs($this->testUser(20, 3));
        $response = $this->get(route('tramites.cargas-familiares.admin.funcionarios-ac.edit', $record));
        $response->assertOk()->assertSee('Recibir WhatsApp de solicitudes de reemplazo')->assertSee('9XXXXXXXX');
        $document = new \DOMDocument(); @$document->loadHTML($response->getContent());
        $checkbox = $document->getElementById('notificar_whatsapp_reemplazos');
        $this->assertSame('checkbox', $checkbox->getAttribute('type')); $this->assertFalse($checkbox->hasAttribute('checked'));
        $this->assertStringContainsString('whatsapp-reemplazos-help', $checkbox->getAttribute('aria-describedby'));
        $this->assertSame(1, (new \DOMXPath($document))->query('//label[@for="notificar_whatsapp_reemplazos"]')->length);
        Http::assertNothingSent(); Queue::assertNothingPushed();
    }

    public function test_occurrence_is_deduplicated_and_rollback_discards_dispatch_and_job(): void
    {
        $this->recipient(); $solicitud = $this->solicitud();
        DB::beginTransaction(); $this->notify($solicitud); Queue::assertNothingPushed(); DB::rollBack();
        $this->assertSame(0, WhatsAppDispatch::count());
        $this->notify($solicitud); $this->notify($solicitud);
        $this->assertSame(1, WhatsAppDispatch::count()); Queue::assertPushed(SendWhatsAppMessage::class, 1);
        $this->notify($solicitud, 'reabierta_uatp', 'second-action');
        $this->notify($solicitud, 'reabierta_uatp', 'second-action');
        $this->assertSame(2, WhatsAppDispatch::count()); Queue::assertPushed(SendWhatsAppMessage::class, 2);
    }

    public function test_real_routes_notify_next_stage_and_only_assigned_user_without_repeated_transitions(): void
    {
        $uatp = $this->recipient(); $plani = $this->recipient(5); $gdp = $this->recipient(6);
        $slep = $this->recipient(7); $other = $this->recipient(7);
        $this->mock(SolicitudReemplazoAutorizacionDocenteService::class, fn ($mock) =>
            $mock->shouldReceive('cumpleRegistroParaAprobacionUatp')->andReturn(true));
        $solicitud = $this->solicitud();
        $this->actingAs($uatp->registeredUser)->post(route('gestion.solicitudes-reemplazo.uatp.aprobar', $solicitud),
            ['justificacion_tecnica_uatp' => 'Justificación sintética de prueba'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($plani->telefono, WhatsAppDispatch::sole()->payload['to']);
        $this->post(route('gestion.solicitudes-reemplazo.uatp.aprobar', $solicitud),
            ['justificacion_tecnica_uatp' => 'Justificación sintética de prueba'])->assertSessionHasErrors('estado');
        $this->actingAs($plani->registeredUser)->post(route('gestion.solicitudes-reemplazo.plani.validar', $solicitud))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($gdp->telefono, WhatsAppDispatch::where('event_key', 'solicitud_reemplazo.validada_plani')->sole()->payload['to']);
        $this->actingAs($gdp->registeredUser)->post(route('gestion.solicitudes-reemplazo.gdp.derivar'),
            ['selected' => [$solicitud->id], 'derivaciones' => [$solicitud->id => $slep->registered_user_id]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($slep->telefono, WhatsAppDispatch::where('event_key', 'solicitud_reemplazo.derivada_slep')->sole()->payload['to']);
        $this->post(route('gestion.solicitudes-reemplazo.gdp.reasignar', $solicitud),
            ['derivada_a_user_id' => $slep->registered_user_id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(3, WhatsAppDispatch::count());
        $this->post(route('gestion.solicitudes-reemplazo.gdp.reasignar', $solicitud),
            ['derivada_a_user_id' => $other->registered_user_id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($other->telefono, WhatsAppDispatch::where('event_key', 'solicitud_reemplazo.reasignada_slep')->sole()->payload['to']);
        Http::assertNothingSent();
    }

    public function test_nonresponsible_cannot_approve_or_change_preference(): void
    {
        $record = $this->recipient(5); $solicitud = $this->solicitud();
        $this->actingAs($record->registeredUser)->post(route('gestion.solicitudes-reemplazo.uatp.aprobar', $solicitud))->assertForbidden();
        $this->patch(route('tramites.cargas-familiares.admin.funcionarios-ac.update', $record), ['notificar_whatsapp_reemplazos' => true])->assertForbidden();
        Queue::assertNothingPushed(); Http::assertNothingSent();
    }

    #[DataProvider('reopenedStages')]
    public function test_administrative_reopen_notifies_next_responsible_once(string $stage, string $rejected, int $role, string $event): void
    {
        $record = $this->recipient($role); $solicitud = $this->solicitud($rejected);
        $this->actingAs($this->testUser(20, 3));
        $url = route('gestion.solicitudes-reemplazo.'.$stage.'.reabrir', $solicitud);
        $data = [$stage.'_reapertura_motivo' => 'Motivo sintético de reapertura'];
        $this->post($url, $data)->assertRedirect()->assertSessionHasNoErrors();
        $dispatch = WhatsAppDispatch::sole(); $this->assertSame($record->telefono, $dispatch->payload['to']);
        $this->assertSame('solicitud_reemplazo.'.$event, $dispatch->event_key);
        $this->post($url, $data)->assertSessionHasErrors('estado'); $this->assertSame(1, WhatsAppDispatch::count());
        Http::assertNothingSent();
    }

    public static function reopenedStages(): array
    {
        return [['uatp', 'rechazada_uatp', 4, 'reabierta_uatp'], ['plani', 'rechazada_plani', 5, 'reabierta_plani']];
    }

    public function test_assigned_recipient_is_cancelled_when_reassigned_before_send(): void
    {
        $old = $this->recipient(7); $new = $this->recipient(7); $solicitud = $this->solicitud('derivada_slep');
        $solicitud->update(['derivada_a_user_id' => $old->registered_user_id]); $this->notify($solicitud, 'derivada_slep');
        $solicitud->update(['derivada_a_user_id' => $new->registered_user_id]);
        $dispatch = WhatsAppDispatch::sole(); (new SendWhatsAppMessage($dispatch->id))->handle(app(WhatsAppClient::class));
        $this->assertSame('stage_changed', $dispatch->fresh()->error_code); Http::assertNothingSent();
    }

    public function test_nonexistent_whatsapp_number_is_terminal_and_not_retried(): void
    {
        $this->recipient(); $this->notify($this->solicitud()); $dispatch = WhatsAppDispatch::sole();
        Http::fake(['*/v1/messages' => Http::response(['error' => 'invalid_recipient'], 422)]);
        $job = new SendWhatsAppMessage($dispatch->id); $job->handle(app(WhatsAppClient::class)); $job->handle(app(WhatsAppClient::class));
        $this->assertSame('failed', $dispatch->fresh()->status); $this->assertSame('invalid_recipient', $dispatch->fresh()->error_code);
        $this->assertNull($dispatch->fresh()->payload); Http::assertSentCount(1);
    }

    public function test_preference_editor_validates_roles_phone_and_preserves_old_form_saves(): void
    {
        $record = $this->recipient(attributes: ['notificar_whatsapp_reemplazos' => false]);
        $this->actingAs($this->testUser(20, 3));
        $url = route('tramites.cargas-familiares.admin.funcionarios-ac.update', $record);
        $data = ['run' => $record->run, 'dv' => 'K', 'nombres' => 'Cuenta de prueba', 'apellido_paterno' => 'Prueba',
            'telefono' => '900000000', 'estado_autorizacion' => 'activo'];
        $this->patch($url, $data + ['notificar_whatsapp_reemplazos' => '1'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($record->fresh()->notificar_whatsapp_reemplazos);
        $this->patch($url, $data)->assertSessionHasNoErrors(); $this->assertTrue($record->fresh()->notificar_whatsapp_reemplazos);
        $this->patch($url, array_replace($data, ['telefono' => '123', 'notificar_whatsapp_reemplazos' => '1']))
            ->assertSessionHasErrors('notificar_whatsapp_reemplazos');
        DB::table('model_has_roles')->where('model_id', $record->registered_user_id)->delete();
        $this->patch($url, $data + ['notificar_whatsapp_reemplazos' => '1'])->assertSessionHasErrors('notificar_whatsapp_reemplazos');
        $this->patch($url, $data + ['notificar_whatsapp_reemplazos' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse($record->fresh()->notificar_whatsapp_reemplazos);
    }

    #[DataProvider('revocations')]
    public function test_worker_cancels_stale_or_revoked_recipients_without_contacting_node(string $change, string $code): void
    {
        $record = $this->recipient(); $solicitud = $this->solicitud(); $this->notify($solicitud);
        match ($change) {
            'flag' => $record->update(['notificar_whatsapp_reemplazos' => false]),
            'phone' => $record->update(['telefono' => '+99900000009']),
            'expired' => $record->update(['fecha_fin_autorizacion' => now()->subDay()]),
            'role' => DB::table('model_has_roles')->where('model_id', $record->registered_user_id)->delete(),
            'deleted' => $record->registeredUser->delete(),
            'state' => $solicitud->update(['estado' => 'pendiente_gdp']),
            'module' => config(['whatsapp.reemplazos.enabled' => false]),
        };
        $dispatch = WhatsAppDispatch::sole(); (new SendWhatsAppMessage($dispatch->id))->handle(app(WhatsAppClient::class));
        $this->assertSame('cancelled', $dispatch->fresh()->status); $this->assertSame($code, $dispatch->fresh()->error_code);
        $this->assertNull($dispatch->fresh()->payload); Http::assertNothingSent();
    }

    public static function revocations(): array
    {
        $cases = [];
        foreach (['flag', 'phone', 'expired', 'role', 'deleted'] as $change) $cases[$change] = [$change, 'recipient_ineligible'];
        $cases['state'] = ['state', 'stage_changed']; $cases['module'] = ['module', 'event_disabled'];
        return $cases;
    }

    public function test_retry_keeps_identity_authentication_and_internal_policy_does_not_leave_laravel(): void
    {
        $this->recipient(); $this->notify($this->solicitud()); $dispatch = WhatsAppDispatch::sole();
        Http::fakeSequence()->push(['error' => 'disconnected'], 503)->push(['status' => 'sent']);
        $job = new SendWhatsAppMessage($dispatch->id);
        try { $job->handle(app(WhatsAppClient::class)); $this->fail('Se esperaba reintento'); }
        catch (WhatsAppException $error) { $this->assertTrue($error->retryable); }
        $job->handle(app(WhatsAppClient::class));
        foreach (Http::recorded() as [$request]) {
            $this->assertSame($dispatch->id, $request['id']); $this->assertArrayNotHasKey('policy', $request->data());
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer '.config('whatsapp.token')));
        }
        $job->handle(app(WhatsAppClient::class)); Http::assertSentCount(2);
        $this->assertSame('sent', $dispatch->fresh()->status);
    }

    public function test_unknown_events_and_unsafe_link_are_not_enqueued_and_queue_failure_does_not_break_workflow(): void
    {
        $uatp = $this->recipient(); $this->recipient(5); $solicitud = $this->solicitud();
        $this->notify($solicitud, 'rechazada_uatp'); config(['app.url' => 'http://untrusted.example.test']);
        $this->notify($solicitud); Queue::assertNothingPushed();
        config(['app.url' => 'https://sga.example.test', 'whatsapp.queue_connection' => 'sync']);
        $this->mock(SolicitudReemplazoAutorizacionDocenteService::class, fn ($mock) =>
            $mock->shouldReceive('cumpleRegistroParaAprobacionUatp')->andReturn(true));
        $this->actingAs($uatp->registeredUser)->post(route('gestion.solicitudes-reemplazo.uatp.aprobar', $solicitud),
            ['justificacion_tecnica_uatp' => 'Justificación sintética de prueba'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('pendiente_validacion', $solicitud->fresh()->estado); Http::assertNothingSent();
    }

    public function test_migration_preserves_preferences_on_rollback_and_has_false_default(): void
    {
        $record = $this->recipient();
        $migration = require database_path('migrations/2026_10_11_150000_add_whatsapp_reemplazos_preference.php');
        $migration->down(); $migration->up(); $this->assertTrue($record->fresh()->notificar_whatsapp_reemplazos);
        $attributes = $record->getAttributes(); unset($attributes['id'], $attributes['notificar_whatsapp_reemplazos']);
        $attributes['rut_normalizado'] = '990000099K'; $id = DB::table('funcionarios_ac_autorizados')->insertGetId($attributes);
        $this->assertFalse(FuncionarioAcAutorizado::find($id)->notificar_whatsapp_reemplazos);
    }
}
