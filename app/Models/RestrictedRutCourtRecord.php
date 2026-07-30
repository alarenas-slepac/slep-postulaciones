<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestrictedRutCourtRecord extends Model
{
    protected $fillable = [
        'restricted_rut_id',
        'nombre',
        'run_original',
        'juzgado_origen',
        'rit',
        'fecha_fallo',
        'inhabilidad_texto',
        'activa',
        'archivo_origen',
    ];

    protected $casts = [
        'fecha_fallo' => 'date',
        'activa' => 'boolean',
    ];

    public function restrictedRut(): BelongsTo
    {
        return $this->belongsTo(RestrictedRut::class);
    }
}
