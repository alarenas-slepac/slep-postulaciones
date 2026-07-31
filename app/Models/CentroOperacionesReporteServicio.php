<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CentroOperacionesReporteServicio extends Model
{
    protected $table = 'centro_operaciones_reporte_servicios';

    protected $fillable = ['reporte_id', 'servicio', 'estado', 'observacion'];

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(CentroOperacionesReporte::class, 'reporte_id');
    }
}
