<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CentroOperacionesRiesgoOpcion extends Model
{
    protected $table = 'centro_operaciones_riesgo_opciones';

    protected $fillable = ['dimension_id', 'nombre', 'score', 'orden', 'activo'];

    protected function casts(): array
    {
        return ['score' => 'integer', 'orden' => 'integer', 'activo' => 'boolean'];
    }

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(CentroOperacionesRiesgoDimension::class, 'dimension_id');
    }
}
