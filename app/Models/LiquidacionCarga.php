<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiquidacionCarga extends Model
{
    use HasFactory;

    protected $table = 'liquidacion_cargas';

    protected $fillable = [
        'mes',
        'anio',
        'dominio',
        'archivo_original_path',
        'archivo_original_nombre',
        'estado',
        'total_paginas',
        'total_con_rut',
        'total_reemplazos',
        'total_publicadas',
        'total_errores',
        'errores',
        'subida_por_id',
        'procesada_at',
    ];

    protected $casts = [
        'mes' => 'integer',
        'anio' => 'integer',
        'total_paginas' => 'integer',
        'total_con_rut' => 'integer',
        'total_reemplazos' => 'integer',
        'total_publicadas' => 'integer',
        'total_errores' => 'integer',
        'errores' => 'array',
        'procesada_at' => 'datetime',
    ];

    public function subidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subida_por_id');
    }

    public function liquidaciones(): HasMany
    {
        return $this->hasMany(LiquidacionFuncionario::class, 'liquidacion_carga_id');
    }

    public function mesNombre(): string
    {
        return [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ][$this->mes] ?? (string) $this->mes;
    }
}
