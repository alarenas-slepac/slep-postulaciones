<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LicenciaMedicaImportacion extends Model
{
    protected $table = 'licencias_medicas_importaciones';

    protected $fillable = [
        'tipo',
        'dimension_estado',
        'nombre_archivo',
        'archivo_path',
        'periodo',
        'total_filas',
        'total_importadas',
        'total_actualizadas',
        'total_omitidas',
        'total_duplicadas',
        'total_inconsistencias',
        'resumen_json',
        'huella_prevalidacion',
        'estado',
        'prevalidado_at',
        'confirmado_at',
        'revertido_at',
        'revertido_por',
        'subido_por',
    ];

    protected $casts = [
        'resumen_json' => 'array',
        'total_filas' => 'integer',
        'total_importadas' => 'integer',
        'total_actualizadas' => 'integer',
        'total_omitidas' => 'integer',
        'total_duplicadas' => 'integer',
        'total_inconsistencias' => 'integer',
        'prevalidado_at' => 'datetime',
        'confirmado_at' => 'datetime',
        'revertido_at' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    public function licencias(): HasMany
    {
        return $this->hasMany(LicenciaMedica::class, 'importacion_id');
    }

    public function historiales(): HasMany
    {
        return $this->hasMany(LicenciaMedicaHistorial::class, 'importacion_id');
    }

    public function revertidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revertido_por');
    }
}
