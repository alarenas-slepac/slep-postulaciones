<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanEstudioAsignatura extends Model
{
    protected $table = 'planes_estudio_asignaturas';

    protected $fillable = [
        'plan_estudio_id',
        'asignatura',
        'horas_semanales',
        'horas_anuales',
        'tipo_bloque',
        'orden',
    ];

    protected $casts = [
        'horas_semanales' => 'decimal:2',
        'horas_anuales' => 'decimal:2',
        'orden' => 'integer',
    ];

    public function planEstudio(): BelongsTo
    {
        return $this->belongsTo(PlanEstudio::class, 'plan_estudio_id');
    }
}
