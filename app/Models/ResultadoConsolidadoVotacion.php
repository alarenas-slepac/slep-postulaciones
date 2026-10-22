<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ResultadoConsolidadoVotacion extends Model
{
    protected $table = 'votacion_resultados_consolidados';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'dias' => 'array', 'resumen' => 'array', 'publicado_at' => 'datetime', 'retirado_at' => 'datetime'];
    }

    public function scopePublicados(Builder $query): Builder
    {
        return $query->whereNotNull('publicado_at')->whereNull('retirado_at');
    }

    protected static function booted(): void
    {
        static::updating(function (self $consolidado): void {
            if ($consolidado->isDirty(['jornada_votacion_id', 'version', 'huella', 'dias', 'resumen'])) {
                throw new \LogicException('El contenido de una consolidación es inmutable. Genera una nueva versión.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Las consolidaciones se conservan como historial.'));
    }
}
