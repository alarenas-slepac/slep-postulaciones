<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SnedCarga extends Model
{
    protected $table = 'sned_cargas';

    protected $guarded = [];

    protected $casts = [
        'lectura' => 'array',
    ];

    public function cuota(): BelongsTo
    {
        return $this->belongsTo(SnedCuota::class, 'cuota_id');
    }
}
