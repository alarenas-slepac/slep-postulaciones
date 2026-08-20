<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DescuentoCgrDocumentoMensual extends Model
{
    protected $table = 'descuentos_cgr_documentos_mensuales';

    protected $fillable = [
        'descuento_cgr_id',
        'numero_cuota',
        'periodo',
        'codigo_verificacion',
        'documento_hash',
        'documento_emitido_en',
    ];

    protected function casts(): array
    {
        return [
            'numero_cuota' => 'integer',
            'periodo' => 'date',
            'documento_emitido_en' => 'datetime',
        ];
    }

    public function descuentoCgr(): BelongsTo
    {
        return $this->belongsTo(DescuentoCgr::class, 'descuento_cgr_id');
    }
}
