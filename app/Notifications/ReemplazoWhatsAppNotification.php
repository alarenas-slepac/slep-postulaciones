<?php

namespace App\Notifications;

use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Messages\WhatsAppText;
use Illuminate\Notifications\Notification;

class ReemplazoWhatsAppNotification extends Notification
{
    public function __construct(private readonly WhatsAppText $message) {}

    public function via(object $notifiable): array
    {
        return [WhatsAppChannel::class];
    }

    public function toWhatsApp(object $notifiable): WhatsAppText
    {
        return $this->message;
    }
}
