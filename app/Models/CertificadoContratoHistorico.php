<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificadoContratoHistorico extends Model
{
    protected $table = 'certificado_contratos_historicos';

    protected $fillable = [
        'importacion_id',
        'fila_origen',
        'rut_normalizado',
        'nombre',
        'establecimiento',
        'comuna',
        'fecha_ingreso',
        'fecha_finiquito',
        'termino_indefinido',
        'calidad_juridica',
        'regimen_juridico',
        'row_hash',
    ];

    protected function casts(): array
    {
        return [
            'fecha_ingreso' => 'date',
            'fecha_finiquito' => 'date',
            'termino_indefinido' => 'boolean',
        ];
    }

    public function importacion(): BelongsTo
    {
        return $this->belongsTo(CertificadoImportacion::class, 'importacion_id');
    }
}
