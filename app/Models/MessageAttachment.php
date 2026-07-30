<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageAttachment extends Model
{
    protected $fillable = [
        'message_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'is_image',
    ];

    protected $casts = [
        'size' => 'integer',
        'is_image' => 'boolean',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
