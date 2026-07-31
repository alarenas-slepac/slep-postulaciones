<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CertificadoImportacion extends Model
{
    protected $table = 'certificado_importaciones';

    protected $fillable = [
        'nombre_archivo',
        'ruta_archivo',
        'hash_archivo',
        'estado',
        'es_vigente',
        'total_filas',
        'filas_validas',
        'filas_omitidas',
        'filas_duplicadas',
        'errores',
        'subido_por',
        'procesado_at',
        'activado_at',
    ];

    protected function casts(): array
    {
        return [
            'es_vigente' => 'boolean',
            'errores' => 'array',
            'procesado_at' => 'datetime',
            'activado_at' => 'datetime',
        ];
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(CertificadoContratoHistorico::class, 'importacion_id');
    }

    public function certificados(): HasMany
    {
        return $this->hasMany(CertificadoEmitido::class, 'importacion_id');
    }

    public function subidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }
}
