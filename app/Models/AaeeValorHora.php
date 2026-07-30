<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AaeeValorHora extends Model
{
    protected $table = 'aaee_valores_hora';

    protected $fillable = [
        'area_desempeno_id',
        'categoria',
        'valor_hora',
        'activo',
    ];

    protected $casts = [
        'valor_hora' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public function areaDesempeno(): BelongsTo
    {
        return $this->belongsTo(AreaDesempeno::class, 'area_desempeno_id');
    }

    public static function categorias(): array
    {
        return ['profesional', 'tecnico', 'administrativo', 'auxiliar'];
    }
}
