<?php

namespace App\Services\WhatsApp;

use App\Jobs\SendWhatsAppMessage;
use App\Models\WhatsAppDispatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class WhatsAppDispatcher
{
    public function enqueue(string $to, string $text, string $key, string $event, ?int $userId = null): ?WhatsAppDispatch
    {
        if (!config('whatsapp.enabled')) {
            return null;
        }
        $to = trim($to);
        if (!preg_match('/^\+[1-9][0-9]{7,14}$/D', $to) || trim($text) === '' || mb_strlen($text) > 1000
            || strlen($key) > 128 || $key === '' || strlen($event) > 100) {
            throw new WhatsAppException('invalid_message');
        }
        $connection = (string) config('whatsapp.queue_connection');
        if (!in_array(config("queue.connections.$connection.driver"), ['database', 'redis', 'sqs', 'beanstalkd'], true)) {
            throw new WhatsAppException('queue_configuration');
        }
        $fingerprint = hash_hmac('sha256', json_encode([$to, $text], JSON_THROW_ON_ERROR), (string) config('app.key'));
        $dispatch = null;
        try {
            return DB::transaction(function () use ($to, $text, $key, $event, $userId, $fingerprint, $connection, &$dispatch) {
                $dispatch = WhatsAppDispatch::firstOrCreate(['idempotency_key' => $key], [
                    'id' => (string) Str::uuid(), 'event_key' => $event, 'fingerprint' => $fingerprint,
                    'payload' => ['to' => $to, 'text' => $text], 'triggered_by_user_id' => $userId,
                    'status' => 'queued',
                ]);
                if (!hash_equals($dispatch->fingerprint, $fingerprint)) {
                    throw new WhatsAppException('idempotency_conflict');
                }
                if ($dispatch->wasRecentlyCreated) {
                    $id = $dispatch->id;
                    $queue = (string) config('whatsapp.queue');
                    // Capturar también fallos al confirmar una transacción EXTERNA del trámite.
                    DB::afterCommit(fn () => $this->push($id, $connection, $queue));
                }
                return $dispatch;
            });
        } catch (WhatsAppException $exception) {
            throw $exception;
        } catch (Throwable) {
            // Los correos/trámites originales continúan aunque falle la cola de WhatsApp.
            Log::warning('No fue posible encolar WhatsApp.', ['code' => 'queue_unavailable']);
            try {
                if ($dispatch?->exists) {
                    $dispatch->finish('failed', 'queue_unavailable');
                }
            } catch (Throwable) {
                // Sin datos personales ni excepción SQL en el log.
            }
            return null;
        }
    }

    private function push(string $id, string $connection, string $queue): void
    {
        try {
            SendWhatsAppMessage::dispatch($id)->onConnection($connection)->onQueue($queue)->beforeCommit();
        } catch (Throwable) {
            Log::warning('No fue posible encolar WhatsApp.', ['code' => 'queue_unavailable']);
            try { WhatsAppDispatch::find($id)?->finish('failed', 'queue_unavailable'); }
            catch (Throwable) { /* El trámite original ya confirmó; no propagar este fallo. */ }
        }
    }
}
