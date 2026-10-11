<?php

namespace App\Services\Notifications;

use App\Models\WhatsAppDispatch;
use App\Services\WhatsApp\WhatsAppDispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;
use Throwable;

class MailWhatsAppNotifier
{
    public function notify(string $key, array $event, MessageSending $sending): void
    {
        if (!($event['whatsapp_from_mail'] ?? false) || !config('whatsapp.enabled')) return;
        try {
            $policy = app(NotificationEventPolicy::class);
            if (!$policy->enabled($key, 'whatsapp')) return;
            $addresses = array_merge($sending->message->getTo(), $sending->message->getCc(), $sending->message->getBcc());
            $recipients = app(NotificationWhatsAppRecipients::class)->forEmails(array_map(fn ($a) => $a->getAddress(), $addresses));
            if ($recipients->isEmpty()) return;
            $occurrence = app(NotificationEventOccurrence::class)->id($sending->data);
            $entities = [];
            foreach ($sending->data as $name => $value) if ($value instanceof Model) $entities[$name] = [$value->getMorphClass(), $value->getKey()];
            ksort($entities);
            // El resumen no replica adjuntos privados ni cuerpos de alertas técnicas. El detalle permanece en el SGA.
            $link = $this->link($sending->data);
            $text = "SGA SLEP Andalién Costa\n".$this->line($event['name'], 100)."\n";
            $text .= "Tienes una actualización. Consulta el detalle y los documentos disponibles en el SGA:\n".$link
                ."\n\nEsta es una notificación automática del SGA.\nPor favor, no respondas a este mensaje.";
            foreach ($recipients as $recipient) {
                if (!$policy->recipientAllowed($key, $recipient['user_id'])) continue;
                $id = 'event-mail:'.hash_hmac('sha256', json_encode([$occurrence, $key, $recipient['user_id'], $entities], JSON_THROW_ON_ERROR), (string) config('app.key'));
                // No recalcular un envío anterior al reintentar: conserva número, plantilla y resultado incierto.
                if (WhatsAppDispatch::where('idempotency_key', $id)->exists()) continue;
                app(WhatsAppDispatcher::class)->enqueue($recipient['phone'], $text, $id, $key, deliveryPolicy: [
                    'scope' => 'notification_event', 'user_id' => $recipient['user_id'], 'recipient_email' => $recipient['email'],
                ]);
            }
        } catch (Throwable) {
            Log::warning('No fue posible preparar el aviso WhatsApp del evento.', ['code' => 'event_whatsapp_unavailable', 'event' => $key]);
        }
    }

    private function link(array $data): string
    {
        $base = rtrim((string) config('app.url'), '/');
        $url = parse_url($base);
        if (!$url || ($url['scheme'] ?? '') !== 'https' || empty($url['host']) || isset($url['user']) || isset($url['pass'])
            || isset($url['query']) || isset($url['fragment']) || preg_match('/[\s\x00-\x1f\x7f]/', $base) || strlen($base) > 250) {
            throw new \RuntimeException('invalid_application_url');
        }
        // Sólo enlaces explícitos del emisor, del mismo origen; nunca enlaces de acceso con tokens.
        foreach (['actionUrl', 'detailUrl', 'url'] as $field) {
            $candidate = $data[$field] ?? null;
            if (!is_string($candidate) || strlen($candidate) > 250 || preg_match('/[\s\x00-\x1f\x7f]/', $candidate)) continue;
            $parsed = parse_url($candidate);
            if ($parsed && ($parsed['scheme'] ?? '') === 'https' && ($parsed['host'] ?? '') === $url['host']
                && ($parsed['port'] ?? 443) === ($url['port'] ?? 443) && !isset($parsed['user']) && !isset($parsed['pass'])
                && !isset($parsed['query']) && !preg_match('~/(?:password|reset|verify|verification)(?:/|$)~i', $parsed['path'] ?? '')) return $candidate;
        }
        return $base.'/';
    }

    private function line(string $text, int $limit): string { return mb_substr(trim(preg_replace('/[\x00-\x1f\x7f]+/u', ' ', $text) ?? ''), 0, $limit); }
}
