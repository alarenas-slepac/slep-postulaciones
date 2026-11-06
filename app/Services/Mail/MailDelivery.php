<?php

namespace App\Services\Mail;

use App\Jobs\DeliverSystemMail;
use App\Models\NotificationDispatchLog;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MailDelivery
{
    public const CONNECTION = 'sga_mail';
    public const QUEUE = 'sga-mail';
    private bool $delivering = false;
    private bool $sent = false;

    public function isDelivering(): bool { return $this->delivering; }

    public function enqueue(Mailable $mail, string $mailer, mixed $delay = null): QueuedMail
    {
        $mail->mailer($mailer);
        $mail->with('sga_occurrence_id', $mail->viewData['sga_occurrence_id'] ?? (string) Str::uuid());
        $job = new DeliverSystemMail($mail);
        $delay ??= $mail->delay ?? null;
        if ($delay !== null) { $job->delay($delay); }
        Queue::connection(self::CONNECTION)->push($job, '', self::QUEUE);
        return new QueuedMail;
    }

    public function duringDelivery(?int $auditId, callable $send): mixed
    {
        $previous = [$this->delivering, $this->sent];
        $this->delivering = true;
        $this->sent = false;
        try {
            $result = $send();
            $this->audit($auditId, $this->sent ? 'sent' : 'suppressed');
            return $result;
        } finally {
            [$this->delivering, $this->sent] = $previous;
        }
    }

    public function markSent(): void { if ($this->delivering) { $this->sent = true; } }

    public function audit(?int $id, string $status, ?string $code = null): void
    {
        if (!$id || !Schema::hasTable('notification_dispatch_logs')) { return; }
        NotificationDispatchLog::whereKey($id)->update([
            'status' => $status, 'sent_at' => $status === 'sent' ? now() : null,
            'failed_at' => null, 'error_message' => $code,
        ]);
    }

    public function paused(): bool
    {
        return Storage::disk('local')->exists('mail-delivery-paused');
    }

    public function pause(bool $paused): void
    {
        $disk = Storage::disk('local');
        $ok = $paused ? $disk->put('mail-delivery-paused', 'paused') : $disk->delete('mail-delivery-paused');
        if (!$ok) { throw new \RuntimeException('No se pudo guardar el estado de entrega de correo.'); }
    }
}
