<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DotacionCursoCombinado extends Model
{
    protected $table = 'dotacion_cursos_combinados';

    protected $fillable = [
        'establecimiento_id',
        'anio',
        'nombre',
        'proporcion',
        'observacion',
        'activo',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'establecimiento_id' => 'integer',
        'anio' => 'integer',
        'activo' => 'boolean',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class, 'establecimiento_id');
    }

    public function miembros(): HasMany
    {
        return $this->hasMany(DotacionCursoCombinadoMiembro::class, 'dotacion_curso_combinado_id');
    }

    public function asignaturas(): HasMany
    {
        return $this->hasMany(DotacionCursoCombinadoAsignatura::class, 'dotacion_curso_combinado_id');
    }
}
