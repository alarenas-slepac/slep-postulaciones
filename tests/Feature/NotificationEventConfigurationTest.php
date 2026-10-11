<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppMessage;
use App\Listeners\ConfigureNotificationMail;
use App\Mail\BaseMailable;
use App\Models\NotificationEventSetting;
use App\Services\Notifications\NotificationEventCatalog;
use App\Services\Notifications\NotificationEventPolicy;
use App\Services\WhatsApp\WhatsAppClient;
use App\Services\WhatsApp\WhatsAppDispatcher;
use App\Support\NotificationAudit;
use App\Support\SlepUiRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Mime\Email;
use Tests\Support\IsolatedSecurityTestCase;

class NotificationEventConfigurationTest extends IsolatedSecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite(); Queue::fake(); Http::preventStrayRequests();
        config(['app.url' => 'https://sga.example.test', 'whatsapp.enabled' => true, 'whatsapp.queue_connection' => 'database']);
        Schema::table('permissions', fn (Blueprint $t) => $t->timestamps());
        Schema::table('roles', fn (Blueprint $t) => $t->timestamps());
        Schema::create('role_has_permissions', function (Blueprint $t) { $t->unsignedBigInteger('permission_id'); $t->unsignedBigInteger('role_id'); });
        Schema::create('model_has_permissions', function (Blueprint $t) { $t->unsignedBigInteger('permission_id'); $t->string('model_type'); $t->unsignedBigInteger('model_id'); });
        (require database_path('migrations/2026_10_11_170000_create_notification_event_settings.php'))->up();
        (require database_path('migrations/2026_10_10_120000_create_whatsapp_dispatches.php'))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function data(array $changes = []): array
    {
        return array_replace(['revision' => 0, 'enabled' => true, 'subject_template' => null, 'body_template' => null,
            'additional_emails' => null, 'recipient_mode' => 'all', 'recipient_ids' => []], $changes);
    }

    private function setting(string $key, string $channel, array $changes = []): NotificationEventSetting
    {
        return NotificationEventSetting::create(['event_key' => $key, 'channel' => $channel, 'enabled' => true,
            'recipient_mode' => 'all', 'recipient_ids' => [], 'additional_emails' => [], 'revision' => 1] + $changes);
    }

    private function mail(): BaseMailable
    {
        return (new BaseMailable('Asunto original', lines: ['Datos originales del evento'], actionText: 'Ingresar al SGA', actionUrl: 'https://sga.example.test/'))
            ->to('original@example.test')->attachData('%PDF-synthetic', 'ejemplo.pdf', ['mime' => 'application/pdf']);
    }

    public function test_guests_and_other_roles_cannot_read_or_change_even_with_permission(): void
    {
        $this->get('/admin/eventos-notificaciones')->assertRedirect('/login');
        $user = $this->testUser(); $user->givePermissionTo('notifications.manage'); $this->actingAs($user);
        $this->getJson('/admin/eventos-notificaciones')->assertForbidden();
        $this->getJson('/admin/eventos-notificaciones/system.generic_mail/editar')->assertForbidden();
        $this->putJson('/admin/eventos-notificaciones/system.generic_mail/mail', $this->data())->assertForbidden();
        $this->postJson('/admin/eventos-notificaciones/system.generic_mail/mail/preview', $this->data())->assertForbidden();
        $this->postJson('/admin/eventos-notificaciones/system.generic_mail/mail/restablecer', ['revision' => 0])->assertForbidden();
        $this->assertSame(0, NotificationEventSetting::count()); Http::assertNothingSent(); Queue::assertNothingPushed();
    }

    public function test_administrator_requires_permission_and_menu_obeys_it(): void
    {
        $user = $this->testUser(role: 3);
        Role::findByName('admin')->revokePermissionTo('notifications.manage');
        app(PermissionRegistrar::class)->forgetCachedPermissions(); $user->unsetRelation('roles');
        $this->actingAs($user)->getJson('/admin/eventos-notificaciones')->assertForbidden();
        $this->assertNotContains('admin.notification-events.index', collect(SlepUiRegistry::menuGroups($user, 'admin'))->flatten(1)->pluck('route')->all());
    }

    public function test_admin_can_consult_catalog_and_edit_with_private_responses_without_get_writes(): void
    {
        $this->actingAs($this->testUser(role: 3))->get('/admin/eventos-notificaciones')->assertOk()
            ->assertSee('Eventos de notificaciones')->assertSee('No disponible')->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/admin/eventos-notificaciones/solicitud_reemplazo.created/editar')->assertOk()->assertSee('Guardar correo')->assertSee('Guardar WhatsApp');
        $this->assertSame(0, NotificationEventSetting::count());
    }

    public function test_unknown_event_and_unimplemented_channel_cannot_be_configured(): void
    {
        $this->actingAs($this->testUser(role: 3));
        $this->get('/admin/eventos-notificaciones/unknown/editar')->assertNotFound();
        $this->putJson('/admin/eventos-notificaciones/auth.password_reset/whatsapp', $this->data())->assertNotFound();
    }

    public function test_protected_access_events_ignore_settings_and_reject_mutations(): void
    {
        $this->actingAs($this->testUser(role: 3));
        foreach (['auth.email_verification', 'auth.password_reset'] as $key) {
            $this->putJson("/admin/eventos-notificaciones/$key/mail", $this->data(['enabled' => false]))->assertForbidden();
            $this->postJson("/admin/eventos-notificaciones/$key/mail/restablecer", ['revision' => 0])->assertForbidden();
            $this->setting($key, 'mail')->update(['enabled' => false, 'additional_emails' => ['extra@example.test']]);
            $email = (new Email)->to('owner@example.test')->subject('Acceso personal')->text('Enlace personal');
            $this->assertNull(app(ConfigureNotificationMail::class)->configure(new MessageSending($email, ['sga_event_key' => $key])));
            $this->assertSame([], $email->getBcc()); $this->assertSame('Acceso personal', $email->getSubject());
        }
    }

    public function test_save_encrypts_recipients_records_actor_and_revision_without_sending(): void
    {
        $user = $this->testUser(role: 3); $this->actingAs($user);
        $this->put('/admin/eventos-notificaciones/system.generic_mail/mail', $this->data([
            'additional_emails' => "COPIA@example.test\ncopia@example.test", 'body_template' => "Aviso del SGA\n{{contenido_original}}",
        ]))->assertRedirect();
        $setting = NotificationEventSetting::sole(); $this->assertSame(['copia@example.test'], $setting->additional_emails);
        $this->assertSame($user->id, $setting->updated_by); $this->assertSame(1, $setting->revision);
        $raw = DB::table('notification_event_settings')->first(); $this->assertStringNotContainsString('copia@example.test', $raw->additional_emails);
        $change = DB::table('notification_event_changes')->first(); $this->assertSame($user->id, $change->actor_id);
        $this->assertStringNotContainsString('copia@example.test', $change->current);
        Http::assertNothingSent(); Queue::assertNothingPushed();
    }

    public function test_stale_form_cannot_overwrite_and_reset_keeps_history(): void
    {
        $this->actingAs($this->testUser(role: 3));
        $this->put('/admin/eventos-notificaciones/system.generic_mail/mail', $this->data(['enabled' => false]))->assertRedirect();
        $this->putJson('/admin/eventos-notificaciones/system.generic_mail/mail', $this->data())->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->post('/admin/eventos-notificaciones/system.generic_mail/mail/restablecer', ['revision' => 1])->assertRedirect();
        $this->assertNull(NotificationEventSetting::sole()->enabled); $this->assertSame(2, NotificationEventSetting::sole()->revision);
        $this->assertSame(2, DB::table('notification_event_changes')->count());
    }

    #[DataProvider('invalidTemplates')]
    public function test_invalid_templates_are_rejected(string $field, string $value): void
    {
        $this->actingAs($this->testUser(role: 3))->putJson('/admin/eventos-notificaciones/system.generic_mail/mail', $this->data([$field => $value]))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame(0, NotificationEventSetting::count());
    }

    public static function invalidTemplates(): array
    {
        return [['body_template', 'Texto sin contenido original'], ['body_template', '{{contenido_original}}{{contenido_original}}'],
            ['body_template', '{{contenido_original}} {{secreto}}'], ['body_template', '<script>alert(1)</script>{{contenido_original}}'],
            ['body_template', '@php exit; @endphp {{contenido_original}}'], ['subject_template', "Asunto\r\nBcc: otro@example.test"],
            ['subject_template', '{{contenido_original}}']];
    }

    public function test_invalid_copies_and_noneligible_whatsapp_recipients_rejected(): void
    {
        $this->actingAs($this->testUser(role: 3));
        $this->putJson('/admin/eventos-notificaciones/system.generic_mail/mail', $this->data(['additional_emails' => 'incorrecto']))->assertUnprocessable();
        $this->putJson('/admin/eventos-notificaciones/solicitud_reemplazo.created/whatsapp', $this->data(['recipient_mode' => 'selected', 'recipient_ids' => [999]]))->assertUnprocessable();
        $this->putJson('/admin/eventos-notificaciones/solicitud_reemplazo.aceptada_reemplazante/whatsapp', $this->data(['recipient_mode' => 'selected', 'recipient_ids' => [1]]))->assertUnprocessable();
    }

    public function test_preview_uses_synthetic_data_without_saving_or_sending(): void
    {
        $this->actingAs($this->testUser(role: 3))->post('/admin/eventos-notificaciones/system.generic_mail/mail/preview',
            $this->data(['body_template' => "Aviso especial\n{{contenido_original}}"]))->assertOk()->assertSee('Aviso especial')->assertSee('Ejemplo ficticio');
        $this->assertSame(0, NotificationEventSetting::count()); Http::assertNothingSent(); Queue::assertNothingPushed();
    }

    public function test_real_mail_pipeline_applies_template_once_preserves_body_attachment_and_deduplicates_copies(): void
    {
        $this->setting('system.generic_mail', 'mail')->update(['subject_template' => 'SGA: {{asunto_original}}',
            'body_template' => "Presentación propia\n{{contenido_original}}\n".'Cierre propio $0', 'additional_emails' => ['ORIGINAL@example.test', 'copy@example.test']]);
        $sent = Mail::send($this->mail()); $email = $sent->getSymfonySentMessage()->getOriginalMessage();
        $this->assertSame('SGA: Asunto original', $email->getSubject());
        $this->assertSame(1, substr_count($email->getHtmlBody(), 'Presentación propia'));
        $this->assertStringContainsString('Datos originales del evento', $email->getHtmlBody());
        $this->assertStringContainsString('Cierre propio $0', $email->getHtmlBody());
        $this->assertStringContainsString('https://sga.example.test/', $email->getHtmlBody());
        $this->assertCount(1, $email->getAttachments()); $this->assertCount(1, $email->getBcc());
        $this->assertSame('copy@example.test', $email->getBcc()[0]->getAddress());
    }

    public function test_disabled_mail_is_suppressed_even_when_setting_changes_after_queueing(): void
    {
        $mail = $this->mail(); $this->setting('system.generic_mail', 'mail')->update(['enabled' => false]);
        $this->assertNull(Mail::send($mail));
        $this->assertCount(0, Mail::mailer()->getSymfonyTransport()->messages());
    }

    public function test_missing_configuration_table_preserves_historical_mail_and_whatsapp(): void
    {
        Schema::drop('notification_event_settings');
        $this->assertNotNull(Mail::send($this->mail()));
        $this->assertNotNull(app(WhatsAppDispatcher::class)->enqueue('+99900000000', 'Sintético', 'legacy', 'admin.test'));
    }

    public function test_whatsapp_disabled_before_enqueue_and_after_enqueue_makes_no_http_request(): void
    {
        $this->setting('admin.test', 'whatsapp')->update(['enabled' => false]);
        $dispatcher = app(WhatsAppDispatcher::class);
        $this->assertNull($dispatcher->enqueue('+99900000000', 'Sintético', 'disabled', 'admin.test'));
        NotificationEventSetting::sole()->update(['enabled' => true]);
        $dispatch = $dispatcher->enqueue('+99900000000', 'Sintético', 'pending', 'admin.test');
        NotificationEventSetting::sole()->update(['enabled' => false]);
        (new SendWhatsAppMessage($dispatch->id))->handle(app(WhatsAppClient::class));
        $this->assertSame('cancelled', $dispatch->fresh()->status); Http::assertNothingSent();
    }

    public function test_whatsapp_template_conserves_original_link_and_final_footer(): void
    {
        $key = 'solicitud_reemplazo.aceptada_reemplazante';
        $this->setting($key, 'whatsapp')->update(['body_template' => "Información de tu asignación\n{{contenido_original}}\nRevisa las fechas."]);
        $footer = "Esta es una notificación automática del SGA.\nPor favor, no respondas a este mensaje.";
        $text = app(NotificationEventPolicy::class)->whatsappText($key, "Datos: https://sga.example.test/\n\n".$footer, true);
        $this->assertStringStartsWith('Información de tu asignación', $text); $this->assertStringContainsString('https://sga.example.test/', $text);
        $this->assertStringEndsWith($footer, $text); $this->assertSame(1, substr_count($text, $footer));
    }

    public function test_catalog_covers_every_existing_mail_class_and_resolves_dynamic_variants(): void
    {
        $catalog = app(NotificationEventCatalog::class);
        foreach (glob(app_path('Mail/*.php')) as $file) {
            $class = 'App\\Mail\\'.basename($file, '.php');
            $this->assertNotNull($catalog->resolveMail(['__laravel_mailable' => $class]), $class);
        }
        foreach ($catalog->mailClasses() as $class) $this->assertTrue($catalog->registeredClass($class), 'Registrar el nuevo evento en notification_events.php: '.$class);
        $this->assertSame('auth.password_reset', $catalog->resolveMail(['__laravel_notification' => \App\Notifications\Auth\CustomResetPassword::class, 'sga_event_key' => 'system.generic_mail']));
        $this->assertSame('auth.email_verification', $catalog->resolveMail(['__laravel_notification' => \App\Notifications\Auth\CustomVerifyEmail::class, 'sga_event_key' => 'system.generic_mail']));
        $this->assertSame('centro_operaciones.escalamiento', $catalog->resolveMail(['__laravel_mailable' => \App\Mail\CentroOperacionesTicketMail::class, 'evento' => 'escalamiento']));
        $this->assertSame('gestion.bolsa-trabajo.etapas.avance', $catalog->resolveMail(['__laravel_mailable' => \App\Mail\BolsaTrabajoEtapaPostulacionMail::class, 'tipo' => 'avance']));
        foreach (\App\Models\CometidoNotificacionConfiguracion::catalogoProceso() as $key => $event) {
            foreach ($event['asuntos'] ?? [] as $subject) $this->assertSame('cometidos.'.$key, $catalog->resolveMail(['__laravel_mailable' => \App\Mail\CometidoFuncionarioNotificationMail::class], $subject));
        }
    }

    public function test_unverified_administrator_cannot_access_settings(): void
    {
        $user = $this->testUser(role: 3); $user->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($user)->getJson('/admin/eventos-notificaciones')->assertForbidden();
    }

    public function test_audit_records_suppression_without_marking_mail_sent(): void
    {
        (require database_path('migrations/2026_03_20_110000_create_notification_dispatch_logs_table.php'))->up();
        $this->setting('failed_job', 'mail')->update(['enabled' => false]);
        NotificationAudit::sendMail('technical@example.test', $this->mail(), ['event_key' => 'failed_job']);
        $log = DB::table('notification_dispatch_logs')->first();
        $this->assertSame('suppressed', $log->status); $this->assertNull($log->sent_at);
        $this->assertCount(0, Mail::mailer()->getSymfonyTransport()->messages());
    }

    public function test_selection_restricts_enqueue_and_cancels_previously_queued_recipient(): void
    {
        $key = 'solicitud_reemplazo.created'; $dispatcher = app(WhatsAppDispatcher::class);
        $this->setting($key, 'whatsapp')->update(['recipient_mode' => 'selected', 'recipient_ids' => [2]]);
        $this->assertNull($dispatcher->enqueue('+99900000000', 'Sintético', 'excluded', $key, deliveryPolicy: ['user_id' => 1]));
        $dispatch = $dispatcher->enqueue('+99900000000', 'Sintético', 'included', $key, deliveryPolicy: ['scope' => 'reemplazos', 'user_id' => 2]);
        $this->assertNotNull($dispatch);
        NotificationEventSetting::sole()->update(['recipient_ids' => [1]]);
        (new SendWhatsAppMessage($dispatch->id))->handle(app(WhatsAppClient::class));
        $this->assertSame('cancelled', $dispatch->fresh()->status); $this->assertSame('recipient_ineligible', $dispatch->fresh()->error_code);
        Http::assertNothingSent();
    }

    public function test_agendamientos_views_and_dynamic_cometido_close_are_resolved(): void
    {
        $catalog = app(NotificationEventCatalog::class); $booking = new \App\Models\AgendamientoRecurso;
        foreach (['pendiente' => [], 'aprobada' => ['aprobada' => true], 'rechazada' => ['aprobada' => false],
            'anulacion' => ['tipo' => 'anulacion'], 'edicion_horario' => ['tipo' => 'edicion_horario']] as $type => $data) {
            $this->assertSame('agendamientos.'.$type, $catalog->resolveMail(['agendamiento' => $booking] + $data));
        }
        $this->assertSame('cometidos.establecimiento_cierre_cometido', $catalog->resolveMail(['__laravel_mailable' => BaseMailable::class], 'Cometido funcionario cerrado - Persona de ejemplo'));
    }

    public function test_catalog_filters_and_read_only_page_work_before_migration(): void
    {
        $this->actingAs($this->testUser(role: 3))->get('/admin/eventos-notificaciones?channel=whatsapp&module=Reemplazos')
            ->assertOk()->assertSee('Nueva solicitud')->assertDontSee('Ticket asignado');
        Schema::drop('notification_event_settings');
        $this->get('/admin/eventos-notificaciones')->assertOk()->assertSee('instalación de la configuración está pendiente');
        $this->put('/admin/eventos-notificaciones/system.generic_mail/mail', $this->data())->assertStatus(503);
    }

    public function test_migration_can_be_repeated_without_replacing_saved_preferences(): void
    {
        $setting = $this->setting('system.generic_mail', 'mail'); $setting->update(['enabled' => false]);
        (require database_path('migrations/2026_10_11_170000_create_notification_event_settings.php'))->up();
        $this->assertFalse($setting->fresh()->enabled); $this->assertSame(1, NotificationEventSetting::count());
    }

    public function test_stale_form_preserves_old_revision_until_explicit_reload(): void
    {
        $this->actingAs($this->testUser(role: 3)); $this->setting('system.generic_mail', 'mail');
        $this->from('/admin/eventos-notificaciones/system.generic_mail/editar')
            ->put('/admin/eventos-notificaciones/system.generic_mail/mail', $this->data(['editing_channel' => 'mail']))
            ->assertRedirect()->assertSessionHasErrors('revision');
        $this->get('/admin/eventos-notificaciones/system.generic_mail/editar')->assertOk()->assertSee('name="revision" value="0"', false);
        $this->assertSame(1, NotificationEventSetting::sole()->revision);
    }

    private function phoneUser(int $id = 1, string $phone = '900000001'): \App\Models\User
    {
        if (!Schema::hasTable('postulant_profiles')) Schema::create('postulant_profiles', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('user_id'); $t->string('telefono1'); $t->timestamps();
        });
        $user = $this->testUser($id);
        DB::table('postulant_profiles')->insert(['user_id' => $user->id, 'telefono1' => $phone]);
        return $user;
    }

    private function eventMail(\App\Models\User $user, string $occurrence = 'synthetic-occurrence'): BaseMailable
    {
        return (new BaseMailable('Aviso de ejemplo', lines: ['Información reservada para la web'], actionUrl: 'https://sga.example.test/tramites'))
            ->to($user->email)->with(['sga_event_key' => 'system.generic_mail', 'sga_occurrence_id' => $occurrence]);
    }

    public function test_new_whatsapp_channels_are_available_but_disabled_by_default_and_reset(): void
    {
        $catalog = app(NotificationEventCatalog::class); $policy = app(NotificationEventPolicy::class);
        $this->assertCount(90, $catalog->all());
        $this->assertCount(88, array_filter($catalog->all(), fn ($e) => in_array('whatsapp', $e['channels'], true)));
        $this->assertCount(77, array_filter($catalog->all(), fn ($e) => $e['whatsapp_from_mail'] ?? false));
        foreach ($catalog->all() as $key => $event) {
            if (!($event['whatsapp_from_mail'] ?? false)) continue;
            $this->assertContains('whatsapp', $event['channels']); $this->assertFalse($policy->enabled($key, 'whatsapp'));
        }
        $user = $this->phoneUser(); Mail::send($this->eventMail($user));
        $this->assertSame(0, \App\Models\WhatsAppDispatch::count()); Queue::assertNothingPushed();
        $this->setting('system.generic_mail', 'whatsapp')->update(['enabled' => null]);
        $this->assertFalse($policy->enabled('system.generic_mail', 'whatsapp'));
    }

    public function test_whatsapp_queues_independently_when_mail_disabled_without_copying_private_content_or_attachment(): void
    {
        $user = $this->phoneUser();
        $this->setting('system.generic_mail', 'mail')->update(['enabled' => false]); $this->setting('system.generic_mail', 'whatsapp');
        $this->assertNull(Mail::send($this->eventMail($user)->attachData('%PDF-synthetic', 'private.pdf')));
        $dispatch = \App\Models\WhatsAppDispatch::sole();
        $this->assertSame('+56900000001', $dispatch->payload['to']);
        $this->assertSame('notification_event', $dispatch->payload['policy']['scope']);
        $this->assertStringContainsString('https://sga.example.test/tramites', $dispatch->payload['text']);
        $this->assertStringNotContainsString('Información reservada', $dispatch->payload['text']);
        $this->assertArrayNotHasKey('document', $dispatch->payload);
        $this->assertStringEndsWith('Por favor, no respondas a este mensaje.', $dispatch->payload['text']);
        Queue::assertPushed(SendWhatsAppMessage::class, 1); Http::assertNothingSent();
    }

    public function test_audited_disabled_mail_can_still_queue_whatsapp_and_records_suppression(): void
    {
        (require database_path('migrations/2026_03_20_110000_create_notification_dispatch_logs_table.php'))->up();
        $user = $this->phoneUser(); $this->setting('system.generic_mail', 'mail')->update(['enabled' => false]); $this->setting('system.generic_mail', 'whatsapp');
        NotificationAudit::sendMail($user->email, $this->eventMail($user), ['event_key' => 'system.generic_mail']);
        $this->assertSame('suppressed', DB::table('notification_dispatch_logs')->value('status'));
        $this->assertSame(1, \App\Models\WhatsAppDispatch::count());
    }

    public function test_mail_retry_uses_same_whatsapp_dispatch_after_template_or_phone_change(): void
    {
        $user = $this->phoneUser(); $this->setting('system.generic_mail', 'whatsapp');
        Mail::send($this->eventMail($user)); $first = \App\Models\WhatsAppDispatch::sole();
        NotificationEventSetting::sole()->update(['body_template' => "Nueva presentación\n{{contenido_original}}"]);
        DB::table('postulant_profiles')->update(['telefono1' => '900000002']);
        $retryMail = $this->eventMail($user); $retryMail->subjectText = 'Asunto actualizado durante el reintento';
        Mail::send($retryMail);
        $this->assertSame(1, \App\Models\WhatsAppDispatch::count()); $this->assertSame($first->payload, $first->fresh()->payload);
        Queue::assertPushed(SendWhatsAppMessage::class, 1);
        (new SendWhatsAppMessage($first->id))->handle(app(WhatsAppClient::class));
        $this->assertSame('recipient_ineligible', $first->fresh()->error_code); Http::assertNothingSent();
    }

    public function test_selected_user_does_not_receive_a_message_without_being_an_original_recipient(): void
    {
        $first = $this->phoneUser(); $second = $this->phoneUser(2, '900000002');
        $this->setting('system.generic_mail', 'whatsapp')->update(['recipient_mode' => 'selected', 'recipient_ids' => [$second->id]]);
        Mail::send($this->eventMail($first)); $this->assertSame(0, \App\Models\WhatsAppDispatch::count());
        $this->actingAs($this->testUser(3, 3))->put('/admin/eventos-notificaciones/system.generic_mail/whatsapp',
            $this->data(['revision' => 1, 'recipient_mode' => 'selected', 'recipient_ids' => [$first->id]]))->assertRedirect();
        Mail::send($this->eventMail($first)); $this->assertSame(1, \App\Models\WhatsAppDispatch::count());
    }

    public function test_mail_copies_added_by_admin_do_not_become_whatsapp_recipients(): void
    {
        $first = $this->phoneUser(); $second = $this->phoneUser(2, '900000002');
        $this->setting('system.generic_mail', 'whatsapp');
        $this->setting('system.generic_mail', 'mail')->update(['additional_emails' => [$second->email]]);
        Mail::send($this->eventMail($first)); $dispatch = \App\Models\WhatsAppDispatch::sole();
        $this->assertSame($first->id, $dispatch->payload['policy']['user_id']);
    }

    public function test_invalid_unverified_deleted_and_shared_phones_are_not_dispatched(): void
    {
        $first = $this->phoneUser(1, 'incorrecto'); $second = $this->phoneUser(2, '900000002');
        $second->forceFill(['email_verified_at' => null])->save();
        $third = $this->phoneUser(3, '900000003'); $third->delete();
        $fourth = $this->phoneUser(4, '900000004'); $fifth = $this->phoneUser(5, '56900000004');
        $this->setting('system.generic_mail', 'whatsapp');
        Mail::send($this->eventMail($first)->cc([$second->email, $third->email, $fourth->email, $fifth->email, 'unknown@example.test']));
        $this->assertSame(0, \App\Models\WhatsAppDispatch::count()); Queue::assertNothingPushed();
    }

    public function test_active_ac_record_is_used_and_inactive_or_ambiguous_record_cannot_fall_back_to_profile(): void
    {
        Schema::create('funcionarios_ac_autorizados', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('registered_user_id')->nullable(); $t->string('rut_normalizado')->nullable();
            $t->string('run')->nullable(); $t->string('dv')->nullable(); $t->string('telefono'); $t->string('estado_autorizacion');
            $t->date('fecha_inicio_autorizacion')->nullable(); $t->date('fecha_fin_autorizacion')->nullable();
        });
        $user = $this->phoneUser();
        DB::table('funcionarios_ac_autorizados')->insert(['registered_user_id' => $user->id, 'rut_normalizado' => $user->rut_normalized, 'telefono' => '56900000002', 'estado_autorizacion' => 'activo']);
        $recipients = app(\App\Services\Notifications\NotificationWhatsAppRecipients::class);
        $this->assertSame('+56900000002', $recipients->forEmails([$user->email])->sole()['phone']);
        DB::table('funcionarios_ac_autorizados')->update(['estado_autorizacion' => 'inactivo']);
        $this->assertCount(0, $recipients->forEmails([$user->email]));
        DB::table('funcionarios_ac_autorizados')->update(['estado_autorizacion' => 'activo', 'rut_normalizado' => '990000099K']);
        $this->assertCount(0, $recipients->forEmails([$user->email]));
        DB::table('funcionarios_ac_autorizados')->update(['rut_normalizado' => $user->rut_normalized]);
        DB::table('funcionarios_ac_autorizados')->update(['registered_user_id' => 999]);
        $this->assertCount(0, $recipients->forEmails([$user->email]));
        DB::table('funcionarios_ac_autorizados')->update(['registered_user_id' => $user->id]);
        DB::table('funcionarios_ac_autorizados')->insert(['registered_user_id' => $user->id, 'telefono' => '900000003', 'estado_autorizacion' => 'activo']);
        $this->assertCount(0, $recipients->forEmails([$user->email]));
    }

    public function test_generic_queued_message_retries_with_same_id_and_cancels_when_event_disabled(): void
    {
        $user = $this->phoneUser(); $setting = $this->setting('system.generic_mail', 'whatsapp'); Mail::send($this->eventMail($user));
        $dispatch = \App\Models\WhatsAppDispatch::sole(); $job = new SendWhatsAppMessage($dispatch->id); $ids = [];
        $client = $this->mock(WhatsAppClient::class);
        $client->shouldReceive('request')->twice()->andReturnUsing(function ($method, $path, $data) use (&$ids) {
            $ids[] = $data['id']; throw new \App\Services\WhatsApp\WhatsAppException('disconnected', true);
        });
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try { $job->handle($client); $this->fail('El envío debe reintentarse.'); }
            catch (\App\Services\WhatsApp\WhatsAppException $e) { $this->assertTrue($e->retryable); }
        }
        $this->assertSame([$dispatch->id, $dispatch->id], $ids); $this->assertSame('retrying', $dispatch->fresh()->status);
        $setting->update(['enabled' => false]); $job->handle($client);
        $this->assertSame('cancelled', $dispatch->fresh()->status); Http::assertNothingSent();
    }

    public function test_queue_occurrence_is_stable_across_retry_and_clears_between_jobs(): void
    {
        $job = $this->createMock(\Illuminate\Contracts\Queue\Job::class); $job->method('uuid')->willReturn('synthetic-job-uuid');
        $event = new \Illuminate\Queue\Events\JobProcessing('database', $job);
        $context = app(\App\Services\Notifications\NotificationEventOccurrence::class);
        event($event); $this->assertSame('synthetic-job-uuid', $context->id([]));
        event(new \Illuminate\Queue\Events\JobExceptionOccurred('database', $job, new \RuntimeException));
        $this->assertNotSame('synthetic-job-uuid', $context->id([]));
        event($event); $this->assertSame('synthetic-job-uuid', $context->id([]));
        event(new \Illuminate\Queue\Events\JobProcessed('database', $job));
        $this->assertNotSame('synthetic-job-uuid', $context->id([]));
    }

    public function test_new_class_and_registered_explicit_event_appear_without_database_seeding(): void
    {
        $map = config('notification_events.mailables'); unset($map[\App\Mail\BulkRoleMail::class]);
        config(['notification_events.mailables' => $map, 'notification_events.events' => ['example.created' => ['Ejemplo', 'Evento de ejemplo', 'Responsable del ejemplo']]]);
        $catalog = app(NotificationEventCatalog::class); $key = $catalog->discoveredKey(\App\Mail\BulkRoleMail::class);
        $this->assertTrue($catalog->find($key)['discovered']);
        $this->assertSame($key, $catalog->resolveMail(['__laravel_mailable' => \App\Mail\BulkRoleMail::class]));
        $this->assertSame('example.created', $catalog->resolveMail(['sga_event_key' => 'example.created']));
        $this->assertContains('whatsapp', $catalog->find('example.created')['channels']);
        $this->assertFalse(app(NotificationEventPolicy::class)->enabled('example.created', 'whatsapp'));
        $this->actingAs($this->testUser(role: 3))->get('/admin/eventos-notificaciones?q='.$key)->assertOk()->assertSee($key);
        $this->assertSame(0, NotificationEventSetting::count());
    }

    public function test_external_or_token_link_falls_back_to_application_and_failure_does_not_block_mail(): void
    {
        $user = $this->phoneUser(); $this->setting('system.generic_mail', 'whatsapp');
        $mail = $this->eventMail($user); $mail->actionUrl = 'https://other.example.test/secret?token=synthetic';
        $this->assertNotNull(Mail::send($mail));
        $text = \App\Models\WhatsAppDispatch::sole()->payload['text'];
        $this->assertStringContainsString('https://sga.example.test/', $text); $this->assertStringNotContainsString('synthetic', $text);
        config(['app.url' => 'http://insecure.example.test']);
        $this->assertNotNull(Mail::send($this->eventMail($user, 'different-occurrence')));
        $this->assertSame(1, \App\Models\WhatsAppDispatch::count()); Http::assertNothingSent();
    }
}
