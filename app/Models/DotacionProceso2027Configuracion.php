<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DotacionProceso2027Configuracion extends Model
{
    protected $table = 'dotacion_proceso_2027_configuraciones';

    public const COMBINACIONES = [
        'sin_combinacion' => 'No aplica combinación de cursos',
        'combinaciones_configuradas' => 'Combinaciones configuradas',
    ];

    protected $fillable = [
        'establecimiento_id',
        'anio',
        'decision_combinacion',
        'observacion_combinacion',
        'max_horas_bloque_1',
        'max_horas_bloque_2',
        'max_horas_bloque_3',
        'funciones_normativas',
        'funciones_normativas_configuradas_by',
        'funciones_normativas_configuradas_at',
        'combinacion_confirmada_by',
        'combinacion_confirmada_at',
        'maximos_configurados_by',
        'maximos_configurados_at',
    ];

    protected $casts = [
        'establecimiento_id' => 'integer',
        'anio' => 'integer',
        'max_horas_bloque_1' => 'decimal:2',
        'max_horas_bloque_2' => 'decimal:2',
        'max_horas_bloque_3' => 'decimal:2',
        'funciones_normativas' => 'array',
        'funciones_normativas_configuradas_by' => 'integer',
        'funciones_normativas_configuradas_at' => 'datetime',
        'combinacion_confirmada_by' => 'integer',
        'combinacion_confirmada_at' => 'datetime',
        'maximos_configurados_by' => 'integer',
        'maximos_configurados_at' => 'datetime',
    ];

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class);
    }
}
