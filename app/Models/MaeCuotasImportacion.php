<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaeCuotasImportacion extends Model
{
    use HasFactory;

    protected $table = 'mae_cuotas_importaciones';

    protected $fillable = [
        'mae_carga_id',
        'columna_origen',
        'columna_normalizada',
        'nombre_archivo',
        'ruta_archivo',
        'estado',
        'total_filas',
        'total_asociadas',
        'total_errores',
        'resumen_json',
        'created_by',
        'procesado_at',
    ];

    protected $casts = [
        'total_filas' => 'integer',
        'total_asociadas' => 'integer',
        'total_errores' => 'integer',
        'resumen_json' => 'array',
        'procesado_at' => 'datetime',
    ];

    public function carga(): BelongsTo
    {
        return $this->belongsTo(MaeCarga::class, 'mae_carga_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(MaeCuotasImportacionDetalle::class, 'mae_cuotas_importacion_id');
    }
}
