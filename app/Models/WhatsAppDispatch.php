<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppDispatch extends Model
{
    protected $table = 'whatsapp_dispatches';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['payload', 'fingerprint', 'idempotency_key'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'sent_at' => 'datetime'];
    }

    public function finish(string $status, ?string $errorCode = null): void
    {
        $this->update([
            'status' => $status, 'error_code' => $errorCode, 'payload' => null,
            'sent_at' => $status === 'sent' ? now() : null,
        ]);
    }
}
