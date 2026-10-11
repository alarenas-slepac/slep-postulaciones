<?php

namespace Tests\Feature;

use App\Http\Controllers\Postulante\MisReemplazosController;
use App\Jobs\SendWhatsAppMessage;
use App\Models\PostulantProfile;
use App\Models\SolicitudReemplazo;
use App\Models\WhatsAppDispatch;
use App\Services\WhatsApp\ReemplazanteWhatsAppNotifier;
use App\Services\WhatsApp\WhatsAppClient;
use App\Services\WhatsApp\WhatsAppException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedSecurityTestCase;

class ReemplazanteWhatsAppNotificationTest extends IsolatedSecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests(); Queue::fake();
        config(['app.url' => 'https://sga.example.test', 'whatsapp.enabled' => true, 'whatsapp.reemplazantes.enabled' => true,
            'whatsapp.reemplazos.enabled' => false, 'whatsapp.queue_connection' => 'database',
            'whatsapp.url' => 'http://127.0.0.1:3210', 'whatsapp.token' => str_repeat('synthetic_token_', 4)]);
        Schema::create('whatsapp_dispatches', function (Blueprint $t): void {
            $t->uuid('id')->primary(); $t->string('idempotency_key')->unique(); $t->string('event_key');
            $t->string('fingerprint'); $t->text('payload')->nullable(); $t->string('status');
            $t->string('error_code')->nullable(); $t->unsignedBigInteger('triggered_by_user_id')->nullable();
            $t->timestamp('sent_at')->nullable(); $t->timestamps();
        });
        Schema::create('postulant_profiles', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('user_id'); $t->string('telefono1')->nullable(); $t->string('telefono2')->nullable(); $t->timestamps();
        });
        Schema::create('establecimientos', function (Blueprint $t): void {
            $t->id(); $t->string('nombre_establecimiento'); $t->string('rbd'); $t->decimal('latitud', 10, 7)->nullable();
            $t->decimal('longitud', 10, 7)->nullable();
        });
        Schema::create('admision_establecimientos', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('establecimiento_id'); $t->string('direccion_publica')->nullable();
        });
        DB::table('establecimientos')->insert(['id' => 1, 'nombre_establecimiento' => 'Escuela de Ejemplo', 'rbd' => '99999',
            'latitud' => -36.0, 'longitud' => -73.0]);
        DB::table('admision_establecimientos')->insert(['establecimiento_id' => 1, 'direccion_publica' => 'Calle de Ejemplo 123']);
        Schema::create('solicitudes_reemplazo', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('establecimiento_id'); $t->unsignedBigInteger('postulant_profile_id')->nullable();
            $t->string('numero_solicitud'); $t->string('estado'); $t->string('orden_trabajo_pdf_path')->nullable();
            $t->date('fecha_inicio'); $t->date('fecha_inicio_trabajo')->nullable(); $t->date('fecha_termino');
            $t->timestamp('orden_trabajo_creada_at')->nullable(); $t->timestamps();
        });
    }

    private function solicitud(?string $phone = '+99900000000'): SolicitudReemplazo
    {
        $user = $this->testUser();
        $profile = PostulantProfile::create(['user_id' => $user->id, 'telefono1' => $phone, 'telefono2' => '+99911111111']);
        $s = SolicitudReemplazo::create(['establecimiento_id' => 1, 'postulant_profile_id' => $profile->id, 'estado' => 'derivada_slep',
            'numero_solicitud' => '00001-2026', 'fecha_inicio' => '2026-10-12', 'fecha_inicio_trabajo' => '2026-10-14',
            'fecha_termino' => '2026-10-30', 'orden_trabajo_creada_at' => '2026-10-11 10:00:00']);
        $s->orden_trabajo_pdf_path = "private/solicitudes-reemplazo/{$s->id}/ORDEN_TRABAJO_00001-2026.pdf";
        Storage::disk('local')->put($s->orden_trabajo_pdf_path, "%PDF-1.7\nDocumento sintético, sin datos personales.");
        $s->save();
        return $s;
    }

    public function test_acceptance_enqueues_only_after_commit_with_private_pdf_maps_profile_phone_and_footer(): void
    {
        $s = $this->solicitud();
        DB::beginTransaction(); $s->update(['estado' => 'aceptada']); Queue::assertNothingPushed(); DB::commit();
        Queue::assertPushed(SendWhatsAppMessage::class, 1);
        $row = WhatsAppDispatch::firstOrFail(); $payload = $row->payload;
        $this->assertSame('+99900000000', $payload['to']);
        foreach (['00001-2026', 'Escuela de Ejemplo', 'RBD: 99999', 'Calle de Ejemplo 123', '14-10-2026', '30-10-2026',
            'https://sga.example.test/postulante/mis-reemplazos/'.$s->id, 'https://www.google.com/maps/search/?api=1&query='] as $part) {
            $this->assertStringContainsString($part, $payload['text']);
        }
        $this->assertStringEndsWith("Esta es una notificación automática del SGA.\nPor favor, no respondas a este mensaje.", $payload['text']);
        $this->assertStringNotContainsString('12-10-2026', $payload['text']);
        $this->assertSame('1/ORDEN_TRABAJO_00001-2026.pdf', $payload['document']['path']);
        $this->assertSame(-36.0, (float) $payload['document']['location']['latitude']);
        $this->assertSame('reemplazante_ot', $payload['policy']['scope']);
        $this->assertStringNotContainsString('Escuela', DB::table('whatsapp_dispatches')->value('payload'));
        Http::assertNothingSent();
    }

    public function test_rollback_discards_dispatch_and_job(): void
    {
        $s = $this->solicitud(); DB::beginTransaction(); $s->update(['estado' => 'aceptada']); DB::rollBack();
        $this->assertSame(0, WhatsAppDispatch::count()); Queue::assertNothingPushed();
    }

    public function test_regeneration_return_from_closure_and_duplicate_notification_do_not_resend_acceptance(): void
    {
        $s = $this->solicitud(); $s->update(['estado' => 'aceptada']);
        app(ReemplazanteWhatsAppNotifier::class)->notify($s);
        $s->update(['orden_trabajo_creada_at' => '2026-10-11 11:00:00']);
        $s->update(['estado' => 'cerrado']); $s->update(['estado' => 'aceptada']);
        $this->assertSame(1, WhatsAppDispatch::count()); Queue::assertPushed(SendWhatsAppMessage::class, 1);
    }

    #[DataProvider('phones')]
    public function test_primary_profile_phone_is_normalized_and_invalid_values_do_not_use_secondary(?string $phone, ?string $expected): void
    {
        $s = $this->solicitud($phone); $s->update(['estado' => 'aceptada']);
        $this->assertSame($expected ? 1 : 0, WhatsAppDispatch::count());
        if ($expected) $this->assertSame($expected, WhatsAppDispatch::first()->payload['to']);
        $this->assertSame($phone, $s->postulante->fresh()->telefono1); Http::assertNothingSent();
    }

    public static function phones(): array
    {
        return [['900000000', '+56900000000'], ['56 9 0000 0000', '+56900000000'], ['+56 (9) 0000-0000', '+56900000000'],
            ['0056900000000', '+56900000000'], [null, null], ['90000000', null], ['900000000 anexo 12', null]];
    }

    #[DataProvider('changes')]
    public function test_pending_delivery_revalidates_current_recipient_state_document_and_configuration(string $change, string $code): void
    {
        $s = $this->solicitud(); $s->update(['estado' => 'aceptada']); $row = WhatsAppDispatch::firstOrFail();
        match ($change) {
            'phone' => $s->postulante->update(['telefono1' => '+99911111111']),
            'profile' => DB::table('solicitudes_reemplazo')->where('id', $s->id)->update(['postulant_profile_id' => null]),
            'unverified' => $s->postulante->user->forceFill(['email_verified_at' => null])->save(),
            'role' => DB::table('model_has_roles')->delete(),
            'deleted' => $s->postulante->user->delete(),
            'state' => $s->update(['estado' => 'anulada']),
            'date' => $s->update(['fecha_termino' => '2026-10-31']),
            'document' => Storage::disk('local')->put($s->orden_trabajo_pdf_path, '%PDF-1.7 OT modificada'),
            'missing' => Storage::disk('local')->delete($s->orden_trabajo_pdf_path),
            'disabled' => config(['whatsapp.reemplazantes.enabled' => false]),
        };
        (new SendWhatsAppMessage($row->id))->handle(app(WhatsAppClient::class));
        $row->refresh(); $this->assertSame('cancelled', $row->status); $this->assertSame($code, $row->error_code);
        $this->assertNull($row->payload); Http::assertNothingSent();
    }

    public static function changes(): array
    {
        return [['phone', 'recipient_ineligible'], ['profile', 'recipient_ineligible'], ['unverified', 'recipient_ineligible'],
            ['role', 'recipient_ineligible'], ['deleted', 'recipient_ineligible'], ['state', 'stage_changed'], ['date', 'stage_changed'],
            ['document', 'document_changed'], ['missing', 'document_unavailable'], ['disabled', 'event_disabled']];
    }

    public function test_transport_sends_authenticated_metadata_without_policy_and_retries_same_uuid(): void
    {
        $s = $this->solicitud(); $s->update(['estado' => 'aceptada']); $row = WhatsAppDispatch::firstOrFail();
        Http::fakeSequence()->push(['error' => 'disconnected'], 503)->push(['status' => 'sent']);
        $job = new SendWhatsAppMessage($row->id);
        try { $job->handle(app(WhatsAppClient::class)); $this->fail('Se esperaba desconexión'); }
        catch (WhatsAppException $e) { $this->assertTrue($e->retryable); }
        $job->handle(app(WhatsAppClient::class));
        $requests = Http::recorded(); $this->assertCount(2, $requests);
        foreach ($requests as [$request]) {
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer '.config('whatsapp.token')));
            $this->assertSame('http://127.0.0.1:3210/v1/messages/document', $request->url());
            $this->assertSame($row->id, $request['id']); $this->assertArrayNotHasKey('policy', $request->data());
            $this->assertSame('1/ORDEN_TRABAJO_00001-2026.pdf', $request['document']['path']);
        }
        $this->assertSame('sent', $row->fresh()->status); $this->assertNull($row->fresh()->payload);
    }

    public function test_missing_coordinates_omit_card_and_missing_address_is_explicit(): void
    {
        DB::table('establecimientos')->update(['latitud' => 0, 'longitud' => 0]); DB::table('admision_establecimientos')->delete();
        $s = $this->solicitud(); $s->update(['estado' => 'aceptada']); $payload = WhatsAppDispatch::first()->payload;
        $this->assertArrayNotHasKey('location', $payload['document']);
        $this->assertStringContainsString('Dirección: No informada', $payload['text']);
        $this->assertStringContainsString('Ubicación: no informada.', $payload['text']);
    }

    public function test_old_bridge_does_not_fall_back_to_text(): void
    {
        $s = $this->solicitud(); $s->update(['estado' => 'aceptada']); $row = WhatsAppDispatch::firstOrFail();
        Http::fakeSequence()->push(['error' => 'not_found'], 404);
        (new SendWhatsAppMessage($row->id))->handle(app(WhatsAppClient::class));
        $this->assertSame('failed', $row->fresh()->status); Http::assertSentCount(1);
        foreach (Http::recorded() as [$request]) $this->assertStringEndsWith('/messages/document', $request->url());
    }

    public function test_uncertain_document_is_not_retried(): void
    {
        $s = $this->solicitud(); $s->update(['estado' => 'aceptada']); $dispatch = WhatsAppDispatch::firstOrFail();
        Http::fakeSequence()->push(['error' => 'delivery_unknown'], 409);
        $job = new SendWhatsAppMessage($dispatch->id); $job->handle(app(WhatsAppClient::class)); $job->handle(app(WhatsAppClient::class));
        $this->assertSame('unknown', $dispatch->fresh()->status); $this->assertNull($dispatch->fresh()->payload);
        Http::assertSentCount(1);
    }

    public function test_disabled_flag_invalid_document_and_bad_app_url_do_not_block_acceptance(): void
    {
        $this->assertFalse((require config_path('whatsapp.php'))['reemplazantes']['enabled']);
        $s = $this->solicitud(); config(['whatsapp.reemplazantes.enabled' => false]); $s->update(['estado' => 'aceptada']);
        config(['whatsapp.reemplazantes.enabled' => true, 'app.url' => 'http://example.test']);
        app(ReemplazanteWhatsAppNotifier::class)->notify($s);
        config(['app.url' => 'https://sga.example.test']); $s->orden_trabajo_pdf_path = 'private/otro.pdf';
        app(ReemplazanteWhatsAppNotifier::class)->notify($s);
        $this->assertSame('aceptada', $s->fresh()->estado); $this->assertSame(0, WhatsAppDispatch::count()); Queue::assertNothingPushed();
    }

    public function test_order_download_requires_the_definitive_profile_owner(): void
    {
        $s = $this->solicitud(); $controller = app(MisReemplazosController::class);
        $request = Request::create('/postulante/mis-reemplazos/1/orden-trabajo');
        $request->setUserResolver(fn () => $s->postulante->user);
        $this->assertSame(200, $controller->ordenTrabajo($request, $s)->getStatusCode());
        $other = $this->testUser(2); PostulantProfile::create(['user_id' => $other->id]);
        $request->setUserResolver(fn () => $other);
        try { $controller->ordenTrabajo($request, $s); $this->fail('No debe acceder a la OT ajena'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
    }
}
