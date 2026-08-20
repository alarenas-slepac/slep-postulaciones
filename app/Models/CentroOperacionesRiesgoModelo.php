<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CentroOperacionesRiesgoModelo extends Model
{
    protected $table = 'centro_operaciones_riesgo_modelos';

    protected $fillable = [
        'version',
        'nombre',
        'estado',
        'umbral_monitoreo',
        'umbral_atencion',
        'umbral_critico',
        'score_alerta_roja',
        'vigencia_dias',
        'accion_estable',
        'accion_monitoreo',
        'accion_atencion',
        'accion_critica',
        'accion_factor_critico',
        'creado_por_id',
        'publicado_en',
    ];

    protected function casts(): array
    {
        return [
            'umbral_monitoreo' => 'integer',
            'umbral_atencion' => 'integer',
            'umbral_critico' => 'integer',
            'score_alerta_roja' => 'integer',
            'vigencia_dias' => 'integer',
            'publicado_en' => 'datetime',
        ];
    }

    public function dimensiones(): HasMany
    {
        return $this->hasMany(CentroOperacionesRiesgoDimension::class, 'modelo_id')->orderBy('orden');
    }

    public function evaluaciones(): HasMany
    {
        return $this->hasMany(CentroOperacionesRiesgoEvaluacion::class, 'modelo_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_id')->withTrashed();
    }
}
