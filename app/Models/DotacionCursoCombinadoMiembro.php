<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DotacionCursoCombinadoMiembro extends Model
{
    protected $table = 'dotacion_curso_combinado_miembros';

    protected $fillable = [
        'dotacion_curso_combinado_id',
        'establecimiento_curso_id',
    ];

    protected $casts = [
        'dotacion_curso_combinado_id' => 'integer',
        'establecimiento_curso_id' => 'integer',
    ];

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(DotacionCursoCombinado::class, 'dotacion_curso_combinado_id');
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(EstablecimientoCurso::class, 'establecimiento_curso_id');
    }
}
