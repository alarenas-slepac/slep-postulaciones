<?php

namespace App\Services\Mail;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class MailDeliveryMiddleware
{
    public function handle($job, $next): mixed
    {
        $delivery = app(MailDelivery::class);
        $auditId = $job->mailAuditLogId ?? $job->notification->mailAuditLogId ?? null;
        if ($delivery->paused()) { $job->release(60); return null; }
        try {
            return $delivery->duringDelivery($auditId, fn () => $next($job));
        } catch (TransportExceptionInterface $exception) {
            // El contenido y los destinatarios permanecen en el payload cifrado.
            $delivery->audit($auditId, 'queued', 'smtp_temporarily_unavailable');
            $delays = [60, 300, 900, 1800, 3600];
            $delay = $delays[min(max(0, $job->attempts() - 1), count($delays) - 1)];
            $job->release($delay);
            $detail = strtolower($exception->getMessage());
            $reason = match (true) {
                str_contains($detail, 'connection refused') => 'connection_refused',
                str_contains($detail, 'getaddrinfo') => 'dns_resolution',
                str_contains($detail, 'certificate verify failed') => 'tls_certificate',
                in_array((int) $exception->getCode(), [530, 534, 535], true) => 'authentication',
                default => 'transport_error',
            };
            Log::warning('Correo conservado en cola por indisponibilidad del transporte.', ['code' => 'smtp_temporarily_unavailable', 'reason' => $reason]);
            return null;
        }
    }
}
