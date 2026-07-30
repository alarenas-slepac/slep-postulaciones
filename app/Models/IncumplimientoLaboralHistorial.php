<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncumplimientoLaboralHistorial extends Model
{
    protected $table = 'incumplimientos_laborales_historial';

    protected $fillable = [
        'incumplimiento_laboral_id',
        'action',
        'user_id',
        'old_values',
        'new_values',
        'changed_fields',
    ];

    protected $casts = [
        'incumplimiento_laboral_id' => 'integer',
        'user_id' => 'integer',
        'old_values' => 'array',
        'new_values' => 'array',
        'changed_fields' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function incumplimientoLaboral(): BelongsTo
    {
        return $this->belongsTo(IncumplimientoLaboral::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
