<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestrictedRutManualRecord extends Model
{
    protected $fillable = [
        'restricted_rut_id',
        'fecha_inicio_prohibicion',
        'fecha_termino_prohibicion',
        'comentario',
        'activa',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'fecha_inicio_prohibicion' => 'date',
        'fecha_termino_prohibicion' => 'date',
        'activa' => 'boolean',
    ];

    public function restrictedRut(): BelongsTo
    {
        return $this->belongsTo(RestrictedRut::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
