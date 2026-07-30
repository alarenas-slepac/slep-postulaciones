<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenciaMedicaHistorial extends Model
{
    protected $table = 'licencias_medicas_historial';

    public $timestamps = false;

    protected $fillable = [
        'licencia_medica_id',
        'accion',
        'descripcion',
        'datos_anteriores',
        'datos_nuevos',
        'user_id',
        'created_at',
    ];

    protected $casts = [
        'datos_anteriores' => 'array',
        'datos_nuevos' => 'array',
        'created_at' => 'datetime',
    ];

    public function licenciaMedica(): BelongsTo
    {
        return $this->belongsTo(LicenciaMedica::class, 'licencia_medica_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
