<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CentroOperacionesRiesgoEvaluacion extends Model
{
    protected $table = 'centro_operaciones_riesgo_evaluaciones';

    protected $fillable = [
        'establecimiento_id',
        'modelo_id',
        'evaluado_por_id',
        'fecha_evaluacion',
        'vigente_hasta',
        'estado',
        'irte',
        'categoria',
        'alerta',
        'accion_sugerida',
        'motivos_principales',
        'observaciones',
        'snapshot',
        'publicado_en',
    ];

    protected function casts(): array
    {
        return [
            'fecha_evaluacion' => 'date',
            'vigente_hasta' => 'date',
            'irte' => 'integer',
            'motivos_principales' => 'array',
            'snapshot' => 'array',
            'publicado_en' => 'datetime',
        ];
    }

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class, 'establecimiento_id');
    }

    public function modelo(): BelongsTo
    {
        return $this->belongsTo(CentroOperacionesRiesgoModelo::class, 'modelo_id');
    }

    public function evaluadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluado_por_id')->withTrashed();
    }

    public function respuestas(): HasMany
    {
        return $this->hasMany(CentroOperacionesRiesgoRespuesta::class, 'evaluacion_id');
    }

    public function getCategoriaLabelAttribute(): string
    {
        return match ($this->categoria) {
            'critico' => 'Crítico',
            'atencion_prioritaria' => 'Atención prioritaria',
            'monitoreo' => 'Monitoreo',
            'estable' => 'Estable',
            default => 'Sin evaluación',
        };
    }

    public function getEstaVencidaAttribute(): bool
    {
        return $this->vigente_hasta?->isBefore(
            now(config('centro_operaciones.timezone'))->startOfDay()
        ) === true;
    }
}
