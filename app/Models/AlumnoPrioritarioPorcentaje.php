<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlumnoPrioritarioPorcentaje extends Model
{
    protected $table = 'alumnos_prioritarios_porcentajes';

    protected $fillable = [
        'establecimiento_id',
        'anio',
        'porcentaje',
        'observacion',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'anio' => 'integer',
        'porcentaje' => 'decimal:2',
    ];

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class, 'establecimiento_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
