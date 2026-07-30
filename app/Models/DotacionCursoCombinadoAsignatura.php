<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DotacionCursoCombinadoAsignatura extends Model
{
    protected $table = 'dotacion_curso_combinado_asignaturas';

    public const MODALIDADES = [
        'conjunta' => 'Conjunta',
        'separada' => 'Separada',
        'personalizada' => 'Personalizada',
        'mixta' => 'Mixta',
    ];

    protected $fillable = [
        'dotacion_curso_combinado_id',
        'asignatura_key',
        'asignatura_nombre',
        'modalidad',
        'horas_conjuntas',
        'horas_personalizadas',
        'horas_exclusivas',
        'observacion',
    ];

    protected $casts = [
        'dotacion_curso_combinado_id' => 'integer',
        'horas_conjuntas' => 'decimal:2',
        'horas_personalizadas' => 'decimal:2',
        'horas_exclusivas' => 'array',
    ];

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(DotacionCursoCombinado::class, 'dotacion_curso_combinado_id');
    }
}
