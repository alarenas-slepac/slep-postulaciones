<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BitacoraVotacion extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'bitacora_votacion';

    protected $fillable = ['jornada_votacion_id', 'grupo_votacion_id', 'ruta_votacion_id', 'user_id', 'evento', 'descripcion', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('La bitácora de votaciones es inmutable.'));
        static::deleting(fn () => throw new \LogicException('La bitácora de votaciones es inmutable.'));
    }
}
