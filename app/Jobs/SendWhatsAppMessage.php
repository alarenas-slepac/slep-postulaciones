<?php

namespace App\Jobs;

use App\Models\WhatsAppDispatch;
use App\Models\SolicitudReemplazo;
use App\Services\WhatsApp\ReemplazoWhatsAppRecipients;
use App\Services\WhatsApp\WhatsAppClient;
use App\Services\WhatsApp\WhatsAppException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 35;

    public function __construct(public readonly string $dispatchId) {}

    public function backoff(): array
    {
        return [30, 90, 300, 900];
    }

    public function handle(WhatsAppClient $client): void
    {
        $dispatch = WhatsAppDispatch::find($this->dispatchId);
        if (!$dispatch || in_array($dispatch->status, ['sent', 'unknown', 'failed', 'cancelled'], true)) {
            return;
        }
        if (!config('whatsapp.enabled')) {
            $dispatch->finish('cancelled', 'disabled');
            return;
        }

        try {
            $payload = $dispatch->payload;
            if (!$payload) {
                $dispatch->finish('failed', 'invalid_message');
                return;
            }
            if (isset($payload['policy'])) {
                $policy = $payload['policy'];
                if (($policy['scope'] ?? '') !== 'reemplazos') {
                    $dispatch->finish('cancelled', 'invalid_policy');
                    return;
                }
                if (!config('whatsapp.reemplazos.enabled')) {
                    $dispatch->finish('cancelled', 'event_disabled');
                    return;
                }
                $solicitud = SolicitudReemplazo::find($policy['solicitud_id'] ?? 0);
                if (!$solicitud || $solicitud->estado !== ($policy['state'] ?? '')
                    || (!empty($policy['assigned']) && (int) $solicitud->derivada_a_user_id !== (int) ($policy['user_id'] ?? 0))) {
                    $dispatch->finish('cancelled', 'stage_changed');
                    return;
                }
                if (!app(ReemplazoWhatsAppRecipients::class)->stillEligible($policy, $payload['to'])) {
                    $dispatch->finish('cancelled', 'recipient_ineligible');
                    return;
                }
            }
            $dispatch->update(['status' => 'sending', 'error_code' => null]);
            $result = $client->request('POST', 'messages', ['id' => $dispatch->id, 'to' => $payload['to'], 'text' => $payload['text']]);
            if (($result['status'] ?? '') !== 'sent') {
                throw new WhatsAppException('delivery_unknown');
            }
            $dispatch->finish('sent');
        } catch (WhatsAppException $exception) {
            if ($exception->errorCode === 'delivery_unknown') {
                $dispatch->finish('unknown', 'delivery_unknown');
                return;
            }
            if (!$exception->retryable) {
                $dispatch->finish('failed', $exception->errorCode);
                return;
            }
            $dispatch->update(['status' => 'retrying', 'error_code' => $exception->errorCode]);
            throw $exception;
        } catch (Throwable) {
            throw new WhatsAppException('worker_error', true);
        }
    }

    public function failed(?Throwable $exception): void
    {
        // Si el último intento perdió la respuesta, el servicio podría haber enviado.
        WhatsAppDispatch::whereKey($this->dispatchId)->whereNotIn('status', ['sent', 'unknown', 'failed', 'cancelled'])
            ->update(['status' => 'unknown', 'error_code' => 'retry_exhausted', 'payload' => null]);
    }
}
