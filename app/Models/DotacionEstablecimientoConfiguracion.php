<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DotacionEstablecimientoConfiguracion extends Model
{
    protected $table = 'dotacion_establecimiento_configuraciones';

    protected $fillable = [
        'establecimiento_id',
        'anio',
        'director_adp',
        'observacion',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'establecimiento_id' => 'integer',
        'anio' => 'integer',
        'director_adp' => 'boolean',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class, 'establecimiento_id');
    }
}
