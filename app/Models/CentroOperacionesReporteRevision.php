<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CentroOperacionesReporteRevision extends Model
{
    protected $table = 'centro_operaciones_reporte_revisiones';

    protected $fillable = ['reporte_id', 'version', 'editado_por_id', 'datos'];

    protected $casts = [
        'version' => 'integer',
        'datos' => 'array',
    ];

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(CentroOperacionesReporte::class, 'reporte_id');
    }

    public function editadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editado_por_id');
    }
}
