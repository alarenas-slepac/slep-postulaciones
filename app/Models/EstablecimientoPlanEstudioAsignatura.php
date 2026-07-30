<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstablecimientoPlanEstudioAsignatura extends Model
{
    protected $table = 'establecimiento_planes_estudio_asignaturas';

    protected $fillable = [
        'establecimiento_plan_estudio_id',
        'plan_estudio_bloque_id',
        'asignatura_id',
        'asignatura_plan_comun_id',
        'nombre_asignatura_personalizada',
        'horas_semanales',
        'horas_anuales',
        'origen',
        'observacion',
        'orden',
    ];

    protected $casts = [
        'establecimiento_plan_estudio_id' => 'integer',
        'plan_estudio_bloque_id' => 'integer',
        'asignatura_id' => 'integer',
        'asignatura_plan_comun_id' => 'integer',
        'horas_semanales' => 'decimal:2',
        'horas_anuales' => 'decimal:2',
        'orden' => 'integer',
    ];

    public function establecimientoPlanEstudio(): BelongsTo
    {
        return $this->belongsTo(EstablecimientoPlanEstudio::class, 'establecimiento_plan_estudio_id');
    }

    public function bloque(): BelongsTo
    {
        return $this->belongsTo(PlanEstudioBloque::class, 'plan_estudio_bloque_id');
    }

    public function asignatura(): BelongsTo
    {
        return $this->belongsTo(Asignatura::class, 'asignatura_id');
    }

    public function asignaturaPlanComun(): BelongsTo
    {
        return $this->belongsTo(Asignatura::class, 'asignatura_plan_comun_id');
    }
}
