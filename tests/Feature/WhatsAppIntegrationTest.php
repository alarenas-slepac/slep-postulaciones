<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppMessage;
use App\Models\WhatsAppDispatch;
use App\Models\User;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Messages\WhatsAppText;
use App\Services\WhatsApp\WhatsAppClient;
use App\Services\WhatsApp\WhatsAppDispatcher;
use App\Services\WhatsApp\WhatsAppException;
use App\Support\SlepUiRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\IsolatedSecurityTestCase;

class WhatsAppIntegrationTest extends IsolatedSecurityTestCase
{
    private const TO = '+99900000000';
    private const TEXT = 'Contenido sintético de prueba';
    private const REQUEST_ID = '10000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
        Queue::fake();
        config(['whatsapp.enabled' => true, 'whatsapp.url' => 'http://127.0.0.1:3210',
            'whatsapp.token' => str_repeat('synthetic_token_', 4), 'whatsapp.queue_connection' => 'database', 'whatsapp.queue' => 'whatsapp']);
        Schema::table('permissions', fn (Blueprint $table) => $table->timestamps());
        Schema::table('roles', fn (Blueprint $table) => $table->timestamps());
        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id'); $table->unsignedBigInteger('role_id');
        });
        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id'); $table->string('model_type'); $table->unsignedBigInteger('model_id');
        });
        (require database_path('migrations/2026_10_10_120000_create_whatsapp_dispatches.php'))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function administrator(): User
    {
        return $this->testUser(role: 3);
    }

    private function enqueue(): WhatsAppDispatch
    {
        return app(WhatsAppDispatcher::class)->enqueue(self::TO, self::TEXT, 'synthetic-event-1', 'test.event');
    }

    public function test_guest_and_nonadministrator_cannot_access_any_endpoint_even_with_permission(): void
    {
        $this->get('/admin/whatsapp')->assertRedirect('/login');
        $user = $this->testUser(); $user->givePermissionTo('whatsapp.manage'); $this->actingAs($user);
        foreach ([['GET', '/admin/whatsapp'], ['GET', '/admin/whatsapp/status'], ['POST', '/admin/whatsapp/qr'],
            ['POST', '/admin/whatsapp/unlink'], ['POST', '/admin/whatsapp/test']] as [$method, $uri]) {
            $this->json($method, $uri)->assertForbidden();
        }
        Http::assertNothingSent(); Queue::assertNothingPushed();
    }

    public function test_administrator_requires_permission_and_menu_respects_it(): void
    {
        $user = $this->administrator();
        Role::findByName('admin', 'web')->revokePermissionTo('whatsapp.manage');
        $user->unsetRelation('roles'); app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($user)->getJson('/admin/whatsapp/status')->assertForbidden();
        $this->assertNotContains('admin.whatsapp.index', collect(SlepUiRegistry::menuGroups($user, 'admin'))->flatten(1)->pluck('route')->all());
        Http::assertNothingSent();
    }

    public function test_authorized_page_uses_existing_layout_and_messenger_instructions_with_private_responses(): void
    {
        $user = $this->administrator();
        $response = $this->actingAs($user)->get('/admin/whatsapp');
        $response->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('WhatsApp Messenger')->assertSee('WhatsApp Business')->assertSee('Dispositivos vinculados')
            ->assertSee('Mensaje de prueba')->assertSee('eventos automáticos');
        $this->assertContains('admin.whatsapp.index', collect(SlepUiRegistry::menuGroups($user, 'admin'))->flatten(1)->pluck('route')->all());
        Http::assertNothingSent();
    }

    public function test_disabled_integration_never_contacts_service_or_queues_messages(): void
    {
        config(['whatsapp.enabled' => false]);
        $this->actingAs($this->administrator())->getJson('/admin/whatsapp/status')->assertOk()->assertJsonPath('state', 'disabled');
        $this->assertNull(app(WhatsAppDispatcher::class)->enqueue(self::TO, self::TEXT, 'key', 'event'));
        $this->postJson('/admin/whatsapp/qr')->assertStatus(409)->assertJsonPath('error', 'disabled');
        Http::assertNothingSent(); Queue::assertNothingPushed(); $this->assertSame(0, WhatsAppDispatch::count());
    }

    public function test_admin_test_is_enqueued_once_per_request_with_encrypted_payload_and_job_contains_only_id(): void
    {
        $this->actingAs($this->administrator());
        $data = ['to' => self::TO, 'text' => self::TEXT, 'request_id' => self::REQUEST_ID];
        $first = $this->postJson('/admin/whatsapp/test', $data)->assertAccepted();
        $this->postJson('/admin/whatsapp/test', $data)->assertAccepted()->assertJsonPath('id', $first->json('id'));
        $this->assertSame(1, WhatsAppDispatch::count());
        $dispatch = WhatsAppDispatch::first();
        $raw = DB::table('whatsapp_dispatches')->value('payload');
        $this->assertStringNotContainsString(self::TEXT, $raw); $this->assertStringNotContainsString(self::TO, $raw);
        $this->assertSame(self::TO, $dispatch->payload['to']);
        $this->assertArrayNotHasKey('payload', $dispatch->toArray());
        Queue::assertPushed(SendWhatsAppMessage::class, 1);
        Queue::assertPushed(SendWhatsAppMessage::class, fn ($job) => $job->dispatchId === $dispatch->id && $job->connection === 'database' && $job->queue === 'whatsapp');
        $serialized = serialize(new SendWhatsAppMessage($dispatch->id));
        $this->assertStringNotContainsString(self::TO, $serialized); $this->assertStringNotContainsString(self::TEXT, $serialized);
        Http::assertNothingSent();
    }

    public function test_validation_and_conflicting_idempotency_do_not_send_or_overwrite(): void
    {
        $this->actingAs($this->administrator());
        $this->postJson('/admin/whatsapp/test', ['to' => '123', 'text' => '', 'request_id' => 'invalid'])->assertUnprocessable();
        $this->enqueue();
        try { app(WhatsAppDispatcher::class)->enqueue(self::TO, 'Otro texto', 'synthetic-event-1', 'test.event'); $this->fail('Conflicto esperado'); }
        catch (WhatsAppException $exception) { $this->assertSame('idempotency_conflict', $exception->errorCode); }
        $this->assertSame(self::TEXT, WhatsAppDispatch::first()->payload['text']); Queue::assertPushed(SendWhatsAppMessage::class, 1);
    }

    public function test_client_authenticates_all_actions_and_ignores_sensitive_service_fields(): void
    {
        Http::fake(['*/v1/status' => Http::response(['state' => 'connected', 'credentials' => 'sensitive']),
            '*/v1/session/qr' => Http::response(['state' => 'connecting'], 202), '*/v1/session/unlink' => Http::response(['state' => 'unlinked'])]);
        $this->actingAs($this->administrator())->getJson('/admin/whatsapp/status')->assertOk()->assertJsonPath('state', 'connected')->assertDontSee('sensitive');
        $this->postJson('/admin/whatsapp/qr')->assertOk(); $this->postJson('/admin/whatsapp/unlink')->assertOk();
        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer '.config('whatsapp.token')));
        foreach (Http::recorded() as [$request]) $this->assertTrue($request->hasHeader('Authorization', 'Bearer '.config('whatsapp.token')));
    }

    public function test_client_refuses_non_loopback_urls_credentials_and_unsafe_qr(): void
    {
        foreach (['https://example.test', 'http://user@127.0.0.1:3210', 'http://127.0.0.1:3210?token=secret'] as $url) {
            config(['whatsapp.url' => $url]);
            try { app(WhatsAppClient::class)->status(); $this->fail('Configuración rechazada'); }
            catch (WhatsAppException $exception) { $this->assertSame('configuration', $exception->errorCode); }
        }
        Http::assertNothingSent(); config(['whatsapp.url' => 'http://127.0.0.1:3210']);
        Http::fake(['*/status' => Http::response(['state' => 'qr']), '*/qr' => Http::response(['image' => 'data:image/svg+xml;base64,c2VjcmV0', 'expires_at' => '2030-01-01'])]);
        $this->assertNull(app(WhatsAppClient::class)->status()['qr']);
    }

    public function test_disconnection_and_lost_http_response_retry_with_same_id_without_exposing_exception_content(): void
    {
        $dispatch = $this->enqueue(); $job = new SendWhatsAppMessage($dispatch->id);
        Http::fakeSequence()->push(['error' => 'disconnected'], 503)->push(['status' => 'sent']);
        try { $job->handle(app(WhatsAppClient::class)); $this->fail('Reintento esperado'); }
        catch (WhatsAppException $exception) { $this->assertTrue($exception->retryable); }
        $this->assertSame('retrying', $dispatch->fresh()->status);
        $job->handle(app(WhatsAppClient::class));
        $this->assertSame('sent', $dispatch->fresh()->status); $this->assertNull($dispatch->fresh()->payload);
        foreach (Http::recorded() as [$request]) $this->assertSame($dispatch->id, $request['id']);
        Http::assertSentCount(2); $job->handle(app(WhatsAppClient::class)); Http::assertSentCount(2);
    }

    public function test_transport_timeout_is_sanitized_and_keeps_retry_identity(): void
    {
        $dispatch = $this->enqueue();
        Http::fake(fn () => throw new ConnectionException('SECRET '.self::TO.' '.self::TEXT));
        try { (new SendWhatsAppMessage($dispatch->id))->handle(app(WhatsAppClient::class)); $this->fail('Timeout esperado'); }
        catch (WhatsAppException $exception) {
            $this->assertSame('WhatsApp: service_unavailable', $exception->getMessage());
            $this->assertNull($exception->getPrevious()); $this->assertTrue($exception->retryable);
        }
        $this->assertSame('retrying', $dispatch->fresh()->status);
    }

    public function test_unknown_result_and_auth_failure_are_terminal_and_remove_sensitive_payload(): void
    {
        $dispatch = $this->enqueue(); Http::fakeSequence()->push(['error' => 'delivery_unknown'], 409)->push(['error' => 'TOKEN_SECRET'], 401);
        $job = new SendWhatsAppMessage($dispatch->id); $job->handle(app(WhatsAppClient::class));
        $this->assertSame('unknown', $dispatch->fresh()->status); $this->assertNull($dispatch->fresh()->payload);
        $job->handle(app(WhatsAppClient::class)); Http::assertSentCount(1);
        $other = app(WhatsAppDispatcher::class)->enqueue(self::TO, self::TEXT, 'synthetic-event-2', 'test.event');
        (new SendWhatsAppMessage($other->id))->handle(app(WhatsAppClient::class));
        $this->assertSame('failed', $other->fresh()->status); $this->assertSame('authentication', $other->fresh()->error_code);
        $this->assertNull($other->fresh()->payload);
    }

    public function test_disabling_cancels_pending_job_and_sync_queue_is_rejected(): void
    {
        $dispatch = $this->enqueue(); config(['whatsapp.enabled' => false]);
        (new SendWhatsAppMessage($dispatch->id))->handle(app(WhatsAppClient::class));
        $this->assertSame('cancelled', $dispatch->fresh()->status); $this->assertNull($dispatch->fresh()->payload); Http::assertNothingSent();
        config(['whatsapp.enabled' => true, 'whatsapp.queue_connection' => 'sync']);
        $this->expectException(WhatsAppException::class);
        app(WhatsAppDispatcher::class)->enqueue(self::TO, self::TEXT, 'another', 'test.event');
    }

    public function test_channel_uses_only_explicit_recipient_and_isolated_failure_never_interrupts_main_operation(): void
    {
        $notification = new class extends Notification {
            public function toWhatsApp($notifiable): WhatsAppText { return new WhatsAppText('Contenido sintético de prueba', 'channel-event', 'test.event'); }
        };
        $user = $this->testUser(); $user->telefono = self::TO;
        app(WhatsAppChannel::class)->send($user, $notification); Queue::assertNothingPushed();
        $recipient = (new AnonymousNotifiable)->route('whatsapp', self::TO);
        app(WhatsAppChannel::class)->send($recipient, $notification); Queue::assertPushed(SendWhatsAppMessage::class, 1);
        config(['whatsapp.queue_connection' => 'sync']);
        app(WhatsAppChannel::class)->send($recipient, $notification); // No arroja excepción a la operación principal.
        Queue::assertPushed(SendWhatsAppMessage::class, 1);
    }

    public function test_exhausted_retries_mark_uncertain_without_retaining_sensitive_data(): void
    {
        $dispatch = $this->enqueue(); (new SendWhatsAppMessage($dispatch->id))->failed(new \RuntimeException('SECRET'));
        $this->assertSame('unknown', $dispatch->fresh()->status); $this->assertNull($dispatch->fresh()->payload);
        $this->assertSame('retry_exhausted', $dispatch->fresh()->error_code);
    }

    public function test_mutations_require_csrf_and_status_polling_does_not_consume_action_limit(): void
    {
        $this->actingAs($this->administrator());
        Http::fake(['*/v1/status' => Http::response(['state' => 'idle']), '*/v1/session/qr' => Http::response(['state' => 'connecting'])]);
        foreach (range(1, 10) as $i) $this->getJson('/admin/whatsapp/status')->assertOk();
        $this->postJson('/admin/whatsapp/qr')->assertOk();
        // Laravel omite CSRF durante tests; comprobar también su ejecución fuera de ese bypass.
        $this->app['env'] = 'local';
        $this->postJson('/admin/whatsapp/test', ['to' => self::TO, 'text' => self::TEXT, 'request_id' => self::REQUEST_ID])->assertStatus(419);
        Queue::assertNothingPushed();
    }

    public function test_conservative_migration_rollback_and_reapplication_preserve_history_and_permissions(): void
    {
        $dispatch = $this->enqueue();
        $migration = require database_path('migrations/2026_10_10_120000_create_whatsapp_dispatches.php');
        $migration->down(); $migration->up();
        $this->assertSame($dispatch->id, WhatsAppDispatch::first()->id);
        $this->assertSame(self::TEXT, WhatsAppDispatch::first()->payload['text']);
        $this->assertTrue(Role::findByName('admin', 'web')->hasPermissionTo('whatsapp.manage'));
        Queue::assertPushed(SendWhatsAppMessage::class, 1);
    }

    public function test_queue_waits_for_outer_transaction_and_discards_a_rolled_back_dispatch(): void
    {
        DB::beginTransaction();
        $this->enqueue(); Queue::assertNothingPushed();
        DB::rollBack();
        $this->assertSame(0, WhatsAppDispatch::count()); Queue::assertNothingPushed();
        DB::beginTransaction();
        $this->enqueue(); Queue::assertNothingPushed();
        DB::commit(); Queue::assertPushed(SendWhatsAppMessage::class, 1);
    }

    public function test_queue_failure_at_outer_commit_does_not_interrupt_main_transaction(): void
    {
        Queue::shouldReceive('connection')->with('database')->andThrow(new \RuntimeException('SECRET '.self::TEXT));
        $result = DB::transaction(function (): string {
            $this->enqueue();
            return 'main_operation_completed';
        });
        $this->assertSame('main_operation_completed', $result);
        $dispatch = WhatsAppDispatch::first();
        $this->assertSame('failed', $dispatch->status);
        $this->assertSame('queue_unavailable', $dispatch->error_code);
        $this->assertNull($dispatch->payload); Http::assertNothingSent();
    }
}
