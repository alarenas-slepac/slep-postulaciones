<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DescuentoCgr extends Model
{
    protected $table = 'descuentos_cgr';

    protected $fillable = [
        'rut',
        'nombre',
        'numero_resolucion',
        'fecha_resolucion',
        'deuda_definitiva_pesos',
        'deuda_equivalente_utm',
        'cuota_utm',
        'numero_cuotas',
        'tasa_interes_anual',
        'tasa_interes_mensual',
        'fecha_primer_descuento',
        'resolucion_pdf_path',
        'resolucion_pdf_nombre',
        'resolucion_pdf_tamano',
        'observaciones',
        'codigo_verificacion',
        'documento_hash',
        'documento_emitido_en',
        'creado_por_id',
        'actualizado_por_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_resolucion' => 'date',
            'fecha_primer_descuento' => 'date',
            'deuda_definitiva_pesos' => 'integer',
            'deuda_equivalente_utm' => 'decimal:4',
            'cuota_utm' => 'decimal:4',
            'numero_cuotas' => 'integer',
            'tasa_interes_anual' => 'decimal:4',
            'tasa_interes_mensual' => 'decimal:4',
            'resolucion_pdf_tamano' => 'integer',
            'documento_emitido_en' => 'datetime',
        ];
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_id');
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por_id');
    }

    public function documentosMensuales(): HasMany
    {
        return $this->hasMany(DescuentoCgrDocumentoMensual::class, 'descuento_cgr_id');
    }
}
