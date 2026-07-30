<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermisoSinGoceExcepcion extends Model
{
    protected $table = 'permiso_sin_goce_excepciones';

    protected $fillable = [
        'rut_normalizado',
        'rut_original',
        'nombre_titular',
        'observacion',
        'activo',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function actualizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
