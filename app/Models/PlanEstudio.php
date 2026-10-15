<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanEstudio extends Model
{
    protected $table = 'planes_estudio';

    protected $fillable = [
        'curso_id',
        'anio',
        'nombre_plan',
        'nivel_educativo',
        'modalidad',
        'regimen_jec',
        'horas_semanales_subtotal',
        'horas_semanales_libre_disposicion',
        'horas_semanales_total',
        'horas_anuales_total',
        'decreto_referencia',
        'observacion',
        'activo',
    ];

    protected $casts = [
        'anio' => 'integer',
        'horas_semanales_subtotal' => 'decimal:2',
        'horas_semanales_libre_disposicion' => 'decimal:2',
        'horas_semanales_total' => 'decimal:2',
        'horas_anuales_total' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /** Sólo permite inferir el plan cuando no hay variantes activas que distinguir. */
    public static function unicoActivoParaCurso(int $cursoId, int $anio, string $regimen): ?self
    {
        $planes = self::query()->where('curso_id', $cursoId)->where('anio', $anio)
            ->where('regimen_jec', $regimen)->where('activo', true)->limit(2)->get();

        return $planes->count() === 1 ? $planes->first() : null;
    }

    public function asignaturas(): HasMany
    {
        return $this->hasMany(PlanEstudioAsignatura::class, 'plan_estudio_id')->orderBy('orden')->orderBy('id');
    }

    public function bloques(): HasMany
    {
        return $this->hasMany(PlanEstudioBloque::class, 'plan_estudio_id')->orderBy('orden')->orderBy('id');
    }
}
