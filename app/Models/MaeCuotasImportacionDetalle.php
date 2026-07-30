<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaeCuotasImportacionDetalle extends Model
{
    use HasFactory;

    protected $table = 'mae_cuotas_importacion_detalles';

    protected $fillable = [
        'mae_cuotas_importacion_id',
        'mae_registro_descuento_id',
        'numero_fila',
        'rut',
        'cuota_actual',
        'total_cuotas',
        'observacion',
        'estado',
        'mensaje',
    ];


    protected $casts = [
        'numero_fila' => 'integer',
        'cuota_actual' => 'integer',
        'total_cuotas' => 'integer',
    ];

    public function importacion(): BelongsTo
    {
        return $this->belongsTo(MaeCuotasImportacion::class, 'mae_cuotas_importacion_id');
    }

    public function descuento(): BelongsTo
    {
        return $this->belongsTo(MaeRegistroDescuento::class, 'mae_registro_descuento_id');
    }
}
