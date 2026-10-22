<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResultadoDiarioVotacion extends Model
{
    protected $table = 'votacion_resultados_diarios';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'conteos' => 'array', 'version' => 'integer', 'publicado_at' => 'datetime'];
    }

    public function jornada(): BelongsTo
    {
        return $this->belongsTo(JornadaVotacion::class, 'jornada_votacion_id');
    }
}
