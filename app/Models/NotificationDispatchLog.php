<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NotificationDispatchLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel',
        'status',
        'event_key',
        'description',
        'recipient_email',
        'recipient_name',
        'subject',
        'mailable_class',
        'notification_class',
        'notifiable_type',
        'notifiable_id',
        'related_type',
        'related_id',
        'triggered_by_user_id',
        'context',
        'error_message',
        'sent_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'sent' => 'success',
            'queued' => 'info',
            'failed' => 'danger',
            default => 'secondary',
        };
    }
}
