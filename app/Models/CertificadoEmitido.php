<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificadoEmitido extends Model
{
    protected $table = 'certificados_emitidos';

    protected $fillable = [
        'tipo',
        'numero',
        'codigo_validacion',
        'rut_normalizado',
        'nombre_snapshot',
        'fecha_antiguedad',
        'calidad_juridica_snapshot',
        'regimen_juridico_snapshot',
        'establecimientos_snapshot',
        'contratos_snapshot',
        'es_funcionario_ac_snapshot',
        'importacion_id',
        'usuario_beneficiario_id',
        'emitido_por_user_id',
        'rol_emisor',
        'estado',
        'archivo_pdf_path',
        'documento_hash',
        'emitido_at',
        'anulado_at',
        'anulado_por_user_id',
        'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_antiguedad' => 'date',
            'establecimientos_snapshot' => 'array',
            'contratos_snapshot' => 'array',
            'es_funcionario_ac_snapshot' => 'boolean',
            'emitido_at' => 'datetime',
            'anulado_at' => 'datetime',
        ];
    }

    public function importacion(): BelongsTo
    {
        return $this->belongsTo(CertificadoImportacion::class, 'importacion_id');
    }

    public function beneficiario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_beneficiario_id');
    }

    public function emitidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emitido_por_user_id');
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por_user_id');
    }
}
