<?php

namespace App\Services\Mail;

trait QueuesMailNotification
{
    public int $tries = 0;
    public int $timeout = 60;
    public array $backoff = [60, 300, 900, 1800, 3600];
    public ?int $mailAuditLogId = null;
    public ?string $mailQueuedEmail = null;

    public function viaConnections(): array { return ['mail' => MailDelivery::CONNECTION]; }
    public function viaQueues(): array { return ['mail' => MailDelivery::QUEUE]; }
    public function middleware($notifiable, $channel): array { return [new MailDeliveryMiddleware]; }

    protected function mailChannels($notifiable): array
    {
        $this->mailQueuedEmail = strtolower(trim((string) $notifiable->routeNotificationFor('mail', $this)));
        return ['mail'];
    }

    protected function sameMailRecipient($notifiable): bool
    {
        if (method_exists($notifiable, 'trashed') && $notifiable->trashed()) { return false; }
        return !$this->mailQueuedEmail || $this->mailQueuedEmail === strtolower(trim((string) $notifiable->routeNotificationFor('mail', $this)));
    }
}
