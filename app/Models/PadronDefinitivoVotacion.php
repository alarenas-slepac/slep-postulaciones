<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PadronDefinitivoVotacion extends Model
{
    protected $table = 'votacion_padrones_definitivos';

    protected $guarded = ['id'];

    protected $hidden = ['datos', 'certificado_path', 'base_huella'];

    protected function casts(): array
    {
        return ['personas' => 'integer', 'datos' => 'array', 'resumen' => 'array', 'publicado_at' => 'datetime'];
    }

    public function jornada(): BelongsTo
    {
        return $this->belongsTo(JornadaVotacion::class, 'jornada_votacion_id');
    }

    public function resolveRouteBinding($value, $field = null)
    {
        // Las rutas de revisión/certificado necesitan metadatos, nunca cargar el padrón personal.
        return $this->newQuery()->select(['id', 'jornada_votacion_id', 'personas', 'certificado_path', 'publicado_at'])
            ->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            if ($version->getOriginal('publicado_at') !== null) {
                throw new \LogicException('El padrón definitivo publicado se conserva como historial. Prepare una nueva versión.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Las versiones del padrón definitivo se conservan como historial.'));
    }
}
