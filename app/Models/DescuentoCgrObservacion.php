<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DescuentoCgrObservacion extends Model
{
    protected $table = 'descuentos_cgr_observaciones';

    protected $fillable = [
        'etapa_destino', 'observacion', 'elementos', 'respaldos_previos',
        'observada_por_id', 'reenviada_por_id', 'reenviada_en',
    ];

    protected function casts(): array
    {
        return ['elementos' => 'array', 'respaldos_previos' => 'array', 'reenviada_en' => 'datetime'];
    }

    public function observadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'observada_por_id');
    }

    public function reenviadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reenviada_por_id');
    }
}
