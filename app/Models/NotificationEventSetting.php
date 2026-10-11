<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationEventSetting extends Model
{
    protected $fillable = ['event_key', 'channel', 'enabled', 'subject_template', 'body_template', 'additional_emails', 'recipient_mode', 'recipient_ids', 'revision', 'updated_by'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'additional_emails' => 'encrypted:array', 'recipient_ids' => 'array', 'revision' => 'integer'];
    }
}
