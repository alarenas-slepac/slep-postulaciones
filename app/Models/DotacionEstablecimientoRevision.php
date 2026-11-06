<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DotacionEstablecimientoRevision extends Model
{
    protected $table = 'dotacion_establecimiento_revisiones';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['finalizada_at' => 'datetime', 'reabierta_at' => 'datetime'];
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalizada_by');
    }
}
