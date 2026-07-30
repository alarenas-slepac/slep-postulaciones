<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DotacionProporcionExcepcion extends Model
{
    protected $table = 'dotacion_proporcion_excepciones';

    protected $fillable = [
        'establecimiento_id',
        'anio',
        'proporcion',
        'alcance',
        'justificacion',
        'activa',
        'ultima_recalculacion_total',
        'ultima_recalculacion_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'establecimiento_id' => 'integer',
        'anio' => 'integer',
        'activa' => 'boolean',
        'ultima_recalculacion_total' => 'integer',
        'ultima_recalculacion_at' => 'datetime',
        'created_by' => 'integer',
        'updated_by' => 'integer',
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
