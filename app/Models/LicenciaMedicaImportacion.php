<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenciaMedicaImportacion extends Model
{
    protected $table = 'licencias_medicas_importaciones';

    protected $fillable = [
        'tipo',
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
        'estado',
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
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }
}
