<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UtmValor extends Model
{
    protected $table = 'utm_valores';

    protected $fillable = [
        'anio',
        'mes',
        'valor',
        'creado_por_id',
        'actualizado_por_id',
    ];

    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'mes' => 'integer',
            'valor' => 'decimal:2',
        ];
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_id');
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por_id');
    }
}
