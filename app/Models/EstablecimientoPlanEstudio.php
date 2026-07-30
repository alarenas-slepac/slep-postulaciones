<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EstablecimientoPlanEstudio extends Model
{
    protected $table = 'establecimiento_planes_estudio';

    protected $fillable = [
        'establecimiento_id',
        'establecimiento_curso_id',
        'plan_estudio_id',
        'curso_id',
        'anio',
        'estado',
        'created_by',
        'submitted_at',
        'reviewed_at',
        'observacion',
    ];

    protected $casts = [
        'establecimiento_id' => 'integer',
        'establecimiento_curso_id' => 'integer',
        'plan_estudio_id' => 'integer',
        'curso_id' => 'integer',
        'anio' => 'integer',
        'created_by' => 'integer',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public const ESTADOS = [
        'borrador' => 'Borrador',
        'enviado' => 'Enviado',
        'observado' => 'Observado',
        'aprobado' => 'Aprobado',
        'cerrado' => 'Cerrado',
    ];

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class, 'establecimiento_id');
    }

    public function establecimientoCurso(): BelongsTo
    {
        return $this->belongsTo(EstablecimientoCurso::class, 'establecimiento_curso_id');
    }

    public function planEstudio(): BelongsTo
    {
        return $this->belongsTo(PlanEstudio::class, 'plan_estudio_id');
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(EstablecimientoPlanEstudioAsignatura::class, 'establecimiento_plan_estudio_id')
            ->orderBy('orden')
            ->orderBy('id');
    }

    public function getEstadoLabelAttribute(): string
    {
        return self::ESTADOS[$this->estado] ?? ucfirst((string) $this->estado);
    }
}
