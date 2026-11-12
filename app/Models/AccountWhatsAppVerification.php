<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountWhatsAppVerification extends Model
{
    protected $table = 'account_whatsapp_verifications';

    protected $guarded = ['id'];

    protected $hidden = ['verified_phone', 'pending_phone', 'challenge_id', 'code_hash', 'email_hash', 'dispatch_id'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'expires_at' => 'datetime', 'resend_at' => 'datetime', 'attempts' => 'integer'];
    }
}
