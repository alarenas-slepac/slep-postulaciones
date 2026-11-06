<?php

namespace App\Jobs;

use App\Services\Mail\MailDeliveryMiddleware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Mail\Factory;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class DeliverSystemMail implements ShouldQueue, ShouldBeEncrypted
{
    use Queueable, InteractsWithQueue;

    public int $tries = 0;
    public int $timeout = 60;
    public array $backoff = [60, 300, 900, 1800, 3600];
    public ?int $mailAuditLogId;

    public function __construct(public Mailable $mailable)
    {
        $this->afterCommit();
        $this->mailAuditLogId = $mailable->viewData['sga_dispatch_log_id'] ?? null;
    }

    public function middleware(): array { return [new MailDeliveryMiddleware]; }

    public function handle(Factory $mailer): void { $this->mailable->send($mailer); }
}
