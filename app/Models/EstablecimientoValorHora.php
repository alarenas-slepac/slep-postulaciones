<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstablecimientoValorHora extends Model
{
    protected $table = 'establecimiento_valores_hora';

    protected $fillable = [
        'establecimiento_id',
        'rol',
        'valor_hora',
        'activo',
    ];

    protected $casts = [
        'valor_hora' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class, 'establecimiento_id');
    }

    public static function roles(): array
    {
        return [
            'educadora_parvulos' => 'Educadora de Párvulos',
            'directora_jardin' => 'Directora de Jardín',
            'directora_sala_cuna' => 'Directora de Sala Cuna',
        ];
    }
}
