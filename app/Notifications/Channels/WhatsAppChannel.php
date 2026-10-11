<?php

namespace App\Notifications\Channels;

use App\Notifications\Messages\WhatsAppText;
use App\Services\WhatsApp\WhatsAppDispatcher;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppChannel
{
    public function __construct(private readonly WhatsAppDispatcher $dispatcher) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (!config('whatsapp.enabled') || !method_exists($notification, 'toWhatsApp')) {
            return;
        }
        try {
            // Sólo rutas explícitas: ningún teléfono histórico se presume autorizado.
            $to = $notifiable->routeNotificationFor('whatsapp', $notification);
            $message = $notification->toWhatsApp($notifiable);
            if (!$to || !$message instanceof WhatsAppText) {
                return;
            }
            $this->dispatcher->enqueue((string) $to, $message->text, $message->idempotencyKey, $message->eventKey,
                deliveryPolicy: $message->deliveryPolicy);
        } catch (Throwable) {
            Log::warning('No fue posible preparar la notificación WhatsApp.', ['code' => 'channel_unavailable']);
        }
    }
}
