<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CentroOperacionesIncidencia extends Model
{
    protected $table = 'centro_operaciones_incidencias';

    protected $fillable = [
        'reporte_id',
        'establecimiento_id',
        'fecha_incidencia',
        'tipo',
        'severidad',
        'descripcion',
        'estado',
        'resuelta_en',
        'resuelta_por_id',
        'resuelta_en_reporte_id',
    ];

    protected $casts = [
        'fecha_incidencia' => 'date',
        'resuelta_en' => 'datetime',
    ];

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(CentroOperacionesReporte::class, 'reporte_id');
    }

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class, 'establecimiento_id');
    }

    public function resueltaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelta_por_id');
    }

    public function reporteResolucion(): BelongsTo
    {
        return $this->belongsTo(CentroOperacionesReporte::class, 'resuelta_en_reporte_id');
    }
}
