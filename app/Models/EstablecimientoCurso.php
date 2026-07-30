<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstablecimientoCurso extends Model
{
    protected $table = 'establecimiento_cursos';

    protected $fillable = [
        'establecimiento_id',
        'rbd',
        'curso_id',
        'plan_estudio_id',
        'anio',
        'letra',
        'nombre_seccion',
        'matricula',
        'regimen_jec',
        'fuente',
        'activo',
    ];

    protected $casts = [
        'establecimiento_id' => 'integer',
        'curso_id' => 'integer',
        'plan_estudio_id' => 'integer',
        'anio' => 'integer',
        'matricula' => 'integer',
        'activo' => 'boolean',
    ];

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class, 'establecimiento_id');
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_id');
    }

    public function planEstudio(): BelongsTo
    {
        return $this->belongsTo(PlanEstudio::class, 'plan_estudio_id');
    }
}
