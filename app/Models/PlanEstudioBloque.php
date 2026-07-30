<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanEstudioBloque extends Model
{
    protected $table = 'planes_estudio_bloques';

    protected $fillable = [
        'plan_estudio_id',
        'nombre',
        'tipo_bloque',
        'horas_semanales',
        'horas_anuales',
        'permite_asignaturas_establecimiento',
        'permite_asignaturas_personalizadas',
        'orden',
        'activo',
    ];

    protected $casts = [
        'horas_semanales' => 'decimal:2',
        'horas_anuales' => 'decimal:2',
        'permite_asignaturas_establecimiento' => 'boolean',
        'permite_asignaturas_personalizadas' => 'boolean',
        'orden' => 'integer',
        'activo' => 'boolean',
    ];

    public function planEstudio(): BelongsTo
    {
        return $this->belongsTo(PlanEstudio::class, 'plan_estudio_id');
    }
}
