<?php

namespace App\Services\Mail;

use App\Jobs\DeliverSystemNotification;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Queue;

class QueuedMailChannel extends MailChannel
{
    public function send($notifiable, Notification $notification)
    {
        if (app(MailDelivery::class)->isDelivering()) { return parent::send($notifiable, $notification); }
        // También recupera trabajos Laravel anteriores que aún estaban en la cola general.
        $notification->via($notifiable);
        Queue::connection(MailDelivery::CONNECTION)->push(new DeliverSystemNotification($notifiable, $notification), '', MailDelivery::QUEUE);
        return new QueuedMail;
    }
}
