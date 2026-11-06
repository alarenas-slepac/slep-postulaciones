<?php

namespace Tests\Feature;

use App\Mail\BaseMailable;
use App\Models\NotificationDispatchLog;
use App\Services\Mail\MailDelivery;
use App\Services\Mail\QueuedMail;
use App\Services\Mail\QueuedMailer;
use App\Support\NotificationAudit;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\Support\IsolatedSecurityTestCase;

class ControlledSystemMailTransport extends AbstractTransport
{
    public bool $fail = false;
    public array $messages = [];
    protected function doSend(SentMessage $message): void
    {
        if ($this->fail) { throw new TransportException('SMTP de prueba no disponible.'); }
        $this->messages[] = $message->getOriginalMessage();
    }
    public function __toString(): string { return 'controlled'; }
}

class SystemMailQueueTest extends IsolatedSecurityTestCase
{
    private ControlledSystemMailTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/0001_01_01_000002_create_jobs_table.php'))->up();
        (require database_path('migrations/2026_11_06_120000_create_sga_mail_jobs_table.php'))->up();
        (require database_path('migrations/2026_03_20_110000_create_notification_dispatch_logs_table.php'))->up();
        Schema::create('password_reset_tokens', function (Blueprint $t) {
            $t->string('email')->primary(); $t->string('token'); $t->timestamp('created_at')->nullable();
        });
        config(['queue.default' => 'sync', 'mail.default' => 'controlled',
            'mail.mailers.controlled' => ['transport' => 'controlled'],
            'mail.from' => ['address' => 'sga@example.test', 'name' => 'SGA sintético'], 'whatsapp.enabled' => false]);
        $this->transport = new ControlledSystemMailTransport;
        Mail::extend('controlled', fn () => $this->transport);
        $this->assertInstanceOf(QueuedMailer::class, Mail::mailer());
    }

    private function work(): void
    {
        app('queue.worker')->runNextJob(MailDelivery::CONNECTION, MailDelivery::QUEUE,
            new WorkerOptions(sleep: 0, maxTries: 0, timeout: 60));
        if (DB::table('failed_jobs')->exists()) {
            $this->fail((string) DB::table('failed_jobs')->value('exception'));
        }
    }

    public function test_smtp_caido_no_revierte_la_transaccion_externa_y_se_conserva_hasta_reintento(): void
    {
        $user = $this->testUser();
        $this->transport->fail = true;
        DB::transaction(function () use ($user) {
            $user->update(['nombres' => 'Operación confirmada']);
            DB::transaction(fn () => NotificationAudit::sendMail('destino@example.test', new BaseMailable('Aviso sintético', lines: ['Contenido de prueba'])));
            $this->assertSame(0, DB::table('mail_jobs')->count());
            $this->assertCount(0, $this->transport->messages);
        });
        $this->assertSame('Operación confirmada', $user->fresh()->nombres);
        $this->assertSame(1, DB::table('mail_jobs')->count());
        $this->assertSame('queued', NotificationDispatchLog::first()->status);
        $this->assertNull(NotificationDispatchLog::first()->sent_at);
        $this->assertStringNotContainsString('destino@example.test', DB::table('mail_jobs')->value('payload'));
        $this->work();
        $this->assertSame(1, DB::table('mail_jobs')->count());
        $this->assertSame('queued', NotificationDispatchLog::first()->status);
        $this->assertSame('smtp_temporarily_unavailable', NotificationDispatchLog::first()->error_message);
        $this->assertSame('Operación confirmada', $user->fresh()->nombres);
        $this->travel(61)->seconds();
        $this->transport->fail = false;
        $this->work();
        $this->assertSame(0, DB::table('mail_jobs')->count());
        $this->assertCount(1, $this->transport->messages);
        $this->assertSame('sent', NotificationDispatchLog::first()->status);
        $this->assertNotNull(NotificationDispatchLog::first()->sent_at);
    }

    public function test_rollback_descarta_el_correo_y_su_auditoria(): void
    {
        try {
            DB::transaction(function () {
                NotificationAudit::sendMail('destino@example.test', new BaseMailable('Aviso revertido'));
                throw new \RuntimeException('Rollback solicitado en prueba.');
            });
        } catch (\RuntimeException) {}
        $this->assertSame(0, DB::table('mail_jobs')->count());
        $this->assertSame(0, NotificationDispatchLog::count());
    }

    public function test_envios_directos_y_adjuntos_conservan_to_cc_bcc_asunto_y_cuerpo(): void
    {
        $result = Mail::send(['raw' => 'Cuerpo sintético'], [], function ($message) {
            $message->to('destino@example.test')->cc('copia@example.test')->bcc('oculta@example.test')
                ->subject('Vista directa')->attachData('adjunto sintético', 'prueba.txt');
        });
        $this->assertInstanceOf(QueuedMail::class, $result);
        $this->assertCount(0, $this->transport->messages);
        $this->work();
        $message = $this->transport->messages[0];
        $this->assertSame('Vista directa', $message->getSubject());
        $this->assertSame('Cuerpo sintético', $message->getTextBody());
        $this->assertSame('destino@example.test', $message->getTo()[0]->getAddress());
        $this->assertSame('copia@example.test', $message->getCc()[0]->getAddress());
        $this->assertSame('oculta@example.test', $message->getBcc()[0]->getAddress());
        $this->assertSame('adjunto sintético', $message->getAttachments()[0]->getBody());
        $this->assertTrue(app(\App\Services\Notifications\NotificationEventCatalog::class)->registeredClass(\App\Mail\QueuedViewMail::class));
    }

    public function test_pausa_conserva_los_pendientes_y_reanudar_no_envia_por_si_solo(): void
    {
        $this->artisan('sga-mail:control', ['accion' => 'pausar'])->assertSuccessful();
        Mail::to('destino@example.test')->send(new BaseMailable('En pausa'));
        $this->work();
        $this->assertCount(0, $this->transport->messages);
        $this->assertSame(1, DB::table('mail_jobs')->count());
        $this->artisan('sga-mail:control', ['accion' => 'reanudar'])->assertSuccessful();
        $this->assertCount(0, $this->transport->messages);
        $this->travel(61)->seconds(); $this->work();
        $this->assertCount(1, $this->transport->messages);
    }

    public function test_verificacion_se_encola_con_sync_y_el_enlace_es_nuevo_al_entregar(): void
    {
        $user = $this->testUser(); $user->forceFill(['email_verified_at' => null])->save();
        DB::transaction(function () use ($user) {
            $user->sendEmailVerificationNotification();
            $this->assertSame(0, DB::table('mail_jobs')->count());
        });
        $this->assertSame(1, DB::table('mail_jobs')->count());
        app(MailDelivery::class)->pause(true);
        $this->work();
        $this->assertCount(0, $this->transport->messages);
        $this->travel(120)->minutes(); app(MailDelivery::class)->pause(false);
        $this->work();
        $this->assertCount(1, $this->transport->messages);
        $html = $this->transport->messages[0]->getHtmlBody();
        $dom = new \DOMDocument; @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $link = (new \DOMXPath($dom))->query('//a[contains(@href,"signature=")]')->item(0);
        $url = $link?->getAttribute('href') ?? '';
        $this->assertNotEmpty($url);
        $this->assertTrue(URL::hasValidSignature(Request::create($url)));
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertGreaterThan(now()->addMinutes(59)->timestamp, (int) $query['expires']);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->assertSame('sent', NotificationDispatchLog::first()->status);
    }

    public function test_verificacion_obsoleta_por_cambio_de_correo_o_ya_verificada_no_se_envia(): void
    {
        $user = $this->testUser(); $user->forceFill(['email_verified_at' => null])->save();
        $user->sendEmailVerificationNotification();
        $user->update(['email' => 'nuevo@example.test']);
        $this->work();
        $this->assertCount(0, $this->transport->messages);
        $user->sendEmailVerificationNotification();
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->work();
        $this->assertCount(0, $this->transport->messages);
        $this->assertSame(['suppressed'], NotificationDispatchLog::pluck('status')->unique()->values()->all());
    }

    public function test_recuperacion_cifra_el_token_y_omite_enlaces_caducados(): void
    {
        $user = $this->testUser();
        $token = Password::broker()->createToken($user);
        $user->sendPasswordResetNotification($token);
        $this->assertStringNotContainsString($token, DB::table('mail_jobs')->value('payload'));
        $this->travel(61)->minutes(); $this->work();
        $this->assertCount(0, $this->transport->messages);
        $this->assertSame('suppressed', NotificationDispatchLog::first()->status);
        $token = Password::broker()->createToken($user);
        $user->sendPasswordResetNotification($token); $this->work();
        $this->assertCount(1, $this->transport->messages);
        $this->assertStringContainsString($token, $this->transport->messages[0]->getHtmlBody());
    }

    public function test_mail_queue_y_later_usan_una_sola_cola_persistente(): void
    {
        Mail::to('destino@example.test')->queue(new BaseMailable('Cola explícita'));
        Mail::to('destino@example.test')->later(now()->addMinutes(5), new BaseMailable('Entrega posterior'));
        $this->assertSame(2, DB::table('mail_jobs')->where('queue', 'sga-mail')->count());
        $this->work();
        $this->assertCount(1, $this->transport->messages);
        $this->travel(6)->minutes(); $this->work();
        $this->assertCount(2, $this->transport->messages);
    }

    public function test_pausa_prolongada_supera_255_intentos_sin_perder_mensaje_y_migracion_es_idempotente(): void
    {
        app(MailDelivery::class)->pause(true);
        Mail::to('destino@example.test')->send(new BaseMailable('Pausa prolongada'));
        DB::table('mail_jobs')->update(['attempts' => 255]);
        $this->work();
        $this->assertSame(256, DB::table('mail_jobs')->value('attempts'));
        $migration = require database_path('migrations/2026_11_06_120000_create_sga_mail_jobs_table.php');
        $migration->up(); $migration->down(); $migration->up();
        $this->assertSame(1, DB::table('mail_jobs')->count());
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_cambio_de_preferencia_despues_de_encolar_se_respeta_al_entregar(): void
    {
        NotificationAudit::sendMail('destino@example.test', new BaseMailable('Aviso desactivado'));
        $this->mock(\App\Services\Notifications\NotificationEventPolicy::class)
            ->shouldReceive('enabled')->with('system.generic_mail', 'mail')->andReturn(false);
        $this->work();
        $this->assertCount(0, $this->transport->messages);
        $this->assertSame('suppressed', NotificationDispatchLog::first()->status);
        $this->assertSame(0, DB::table('mail_jobs')->count());
    }

    public function test_verificacion_reintenta_smtp_y_omite_cuentas_eliminadas(): void
    {
        $user = $this->testUser(); $user->forceFill(['email_verified_at' => null])->save();
        $user->sendEmailVerificationNotification(); $this->transport->fail = true; $this->work();
        $this->assertSame(1, DB::table('mail_jobs')->count());
        $this->assertSame('queued', NotificationDispatchLog::first()->status);
        $this->transport->fail = false; $this->travel(61)->seconds(); $this->work();
        $this->assertCount(1, $this->transport->messages);
        $user->sendEmailVerificationNotification();
        $user->delete(); $this->work();
        $this->assertCount(1, $this->transport->messages);
    }

    public function test_reenvio_http_de_verificacion_con_smtp_caido_confirma_que_quedo_en_cola(): void
    {
        $this->transport->fail = true;
        $user = $this->testUser(); $user->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($user)->from('/verify-email')->post(route('verification.send'))
            ->assertRedirect('/verify-email')->assertSessionHas('status', 'verification-link-sent');
        $this->assertSame(1, DB::table('mail_jobs')->count());
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->assertCount(0, $this->transport->messages);
    }

    public function test_notify_now_y_trabajos_de_notificacion_anteriores_tambien_se_aplazan(): void
    {
        $user = $this->testUser(); $user->forceFill(['email_verified_at' => null])->save();
        $user->notifyNow(new \App\Notifications\Auth\CustomVerifyEmail);
        $this->assertSame(1, DB::table('mail_jobs')->count());
        $this->assertCount(0, $this->transport->messages);
        $this->travel(2)->hours(); $this->work();
        $this->assertCount(1, $this->transport->messages);
        $this->assertSame(0, DB::table('mail_jobs')->count());
    }

    public function test_vistas_generadas_con_closures_no_se_serializan_y_conservan_contenido(): void
    {
        Mail::send([
            'html' => fn ($data) => new \Illuminate\Support\HtmlString('<p>'.e($data['texto']).'</p>'),
            'text' => fn ($data) => new \Illuminate\Support\HtmlString($data['texto']),
        ], ['texto' => 'Aviso sintético con formato'], fn ($message) => $message->to('destino@example.test')->subject('Vista generada'));
        $this->work();
        $this->assertSame('<p>Aviso sintético con formato</p>', $this->transport->messages[0]->getHtmlBody());
        $this->assertSame('Aviso sintético con formato', $this->transport->messages[0]->getTextBody());
    }

    public function test_recuperacion_no_envia_el_token_sustituido_por_otra_solicitud(): void
    {
        $user = $this->testUser();
        $old = Password::broker()->createToken($user); $user->sendPasswordResetNotification($old);
        $new = Password::broker()->createToken($user); $user->sendPasswordResetNotification($new);
        $this->work(); $this->assertCount(0, $this->transport->messages);
        $this->work(); $this->assertCount(1, $this->transport->messages);
        $this->assertStringNotContainsString($old, $this->transport->messages[0]->getHtmlBody());
    }

    public function test_worker_rapido_no_es_sobrescrito_como_en_cola_por_la_peticion(): void
    {
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Queue\Events\JobQueued::class, function ($event) {
            if ($event->connectionName === MailDelivery::CONNECTION) { $this->work(); }
        });
        NotificationAudit::sendMail('destino@example.test', new BaseMailable('Entrega inmediata del worker'));
        $this->assertCount(1, $this->transport->messages);
        $this->assertSame(0, DB::table('mail_jobs')->count());
        $this->assertSame('sent', NotificationDispatchLog::first()->status);
        $this->assertNotNull(NotificationDispatchLog::first()->sent_at);
    }
}
