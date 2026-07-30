<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaeHomologacionColumna extends Model
{
    use HasFactory;

    protected $table = 'mae_homologacion_columnas';

    protected $fillable = [
        'columna_origen',
        'columna_normalizada',
        'campo_canonico',
        'grupo',
        'subgrupo',
        'normativa_bucket',
        'normativa_label',
        'normativa_regla',
        'normativa_prioridad',
        'normativa_activa',
        'seccion_archivo',
        'tipo_movimiento',
        'es_aporte_patronal',
        'es_guardable',
        'guardar_en_resumen',
        'guardar_en_detalle',
        'prioridad',
        'activo',
        'observaciones',
    ];

    protected $casts = [
        'es_aporte_patronal' => 'boolean',
        'es_guardable' => 'boolean',
        'guardar_en_resumen' => 'boolean',
        'guardar_en_detalle' => 'boolean',
        'activo' => 'boolean',
        'normativa_activa' => 'boolean',
    ];
}
