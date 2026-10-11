<?php

namespace App\Services\Notifications;

use Illuminate\Support\Str;

class NotificationEventOccurrence
{
    private ?string $jobId = null;

    public function processing($event): void { $this->jobId = $event->job->uuid() ?: $event->connectionName.':'.$event->job->getJobId(); }
    public function clear($event): void { $this->jobId = null; }
    public function id(array $data): string { return $this->jobId ?? $data['sga_occurrence_id'] ?? (string) Str::uuid(); }
}
