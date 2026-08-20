<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CentroOperacionesRiesgoRespuesta extends Model
{
    protected $table = 'centro_operaciones_riesgo_respuestas';

    protected $fillable = [
        'evaluacion_id',
        'dimension_id',
        'opcion_id',
        'dimension_nombre',
        'respuesta_nombre',
        'score',
        'peso',
        'observacion',
    ];

    protected function casts(): array
    {
        return ['score' => 'integer', 'peso' => 'integer'];
    }

    public function evaluacion(): BelongsTo
    {
        return $this->belongsTo(CentroOperacionesRiesgoEvaluacion::class, 'evaluacion_id');
    }

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(CentroOperacionesRiesgoDimension::class, 'dimension_id');
    }

    public function opcion(): BelongsTo
    {
        return $this->belongsTo(CentroOperacionesRiesgoOpcion::class, 'opcion_id');
    }
}
