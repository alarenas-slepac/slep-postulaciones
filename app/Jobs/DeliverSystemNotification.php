<?php

namespace App\Jobs;

use App\Services\Mail\MailDeliveryMiddleware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification as Notifications;

class DeliverSystemNotification implements ShouldQueue, ShouldBeEncrypted
{
    use Queueable, InteractsWithQueue, SerializesModels;
    public int $tries = 0;
    public int $timeout = 60;
    public array $backoff = [60, 300, 900, 1800, 3600];
    public ?int $mailAuditLogId;

    public function __construct(public object $notifiable, public Notification $notification)
    {
        $this->afterCommit();
        $this->mailAuditLogId = $notification->mailAuditLogId ?? null;
    }

    public function middleware(): array { return [new MailDeliveryMiddleware]; }
    public function handle(): void { Notifications::sendNow($this->notifiable, $this->notification, ['mail']); }
}
