<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CentroOperacionesReporteAfectacion extends Model
{
    protected $table = 'centro_operaciones_reporte_afectaciones';

    protected $fillable = ['reporte_id', 'tipo', 'detalle'];

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(CentroOperacionesReporte::class, 'reporte_id');
    }
}
