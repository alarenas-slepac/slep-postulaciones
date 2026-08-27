<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenciaMedicaImportacionError extends Model
{
    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_CORREGIDO = 'corregido';

    public const ESTADO_RESUELTO = 'resuelto';

    protected $table = 'licencias_medicas_importacion_errores';

    protected $fillable = [
        'importacion_id',
        'hoja',
        'fila',
        'codigo_error',
        'motivo',
        'folio_recibido',
        'rut_recibido',
        'valores_originales',
        'valores_corregidos',
        'estado',
        'intentos_reproceso',
        'ultimo_error',
        'resultado_reproceso',
        'licencia_medica_id',
        'corregido_por',
        'corregido_at',
        'reprocesado_por',
        'reprocesado_at',
    ];

    protected $casts = [
        'fila' => 'integer',
        'valores_originales' => 'array',
        'valores_corregidos' => 'array',
        'intentos_reproceso' => 'integer',
        'corregido_at' => 'datetime',
        'reprocesado_at' => 'datetime',
    ];

    public function importacion(): BelongsTo
    {
        return $this->belongsTo(LicenciaMedicaImportacion::class, 'importacion_id');
    }

    public function licenciaMedica(): BelongsTo
    {
        return $this->belongsTo(LicenciaMedica::class, 'licencia_medica_id');
    }

    public function corregidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corregido_por');
    }

    public function reprocesadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reprocesado_por');
    }

    public function valoresEfectivos(): array
    {
        return array_replace(
            (array) $this->valores_originales,
            (array) $this->valores_corregidos
        );
    }
}
