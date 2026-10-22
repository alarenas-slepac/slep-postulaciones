<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RutaVotacionConvocado extends Model
{
    protected $table = 'ruta_votacion_convocados';

    protected $fillable = ['rbd', 'nombre_establecimiento'];

    protected function casts(): array
    {
        return ['rbd' => 'integer'];
    }

    public function ruta(): BelongsTo
    {
        return $this->belongsTo(RutaVotacion::class, 'ruta_votacion_id');
    }
}
