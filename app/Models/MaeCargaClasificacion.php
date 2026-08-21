<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaeCargaClasificacion extends Model
{
    use HasFactory;

    protected $table = 'mae_carga_clasificaciones';

    protected $fillable = [
        'mae_carga_id',
        'orden_columna',
        'columna_origen',
        'columna_normalizada',
        'campo_canonico',
        'categoria_detectada',
        'categoria_seleccionada',
        'fuente_deteccion',
        'grupo',
        'subgrupo',
        'tipo_movimiento',
        'es_aporte_patronal',
        'homologacion_id',
        'confirmado_por',
        'confirmado_at',
    ];

    protected $casts = [
        'es_aporte_patronal' => 'boolean',
        'confirmado_at' => 'datetime',
    ];

    public function carga(): BelongsTo
    {
        return $this->belongsTo(MaeCarga::class, 'mae_carga_id');
    }

    public function homologacion(): BelongsTo
    {
        return $this->belongsTo(MaeHomologacionColumna::class, 'homologacion_id');
    }

    public function confirmadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmado_por');
    }
}
