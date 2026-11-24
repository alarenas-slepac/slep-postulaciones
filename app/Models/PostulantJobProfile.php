<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostulantJobProfile extends Model
{
    public const EDUCATION_LEVELS = [
        'Enseñanza Media', 'Enseñanza Media Laboral', 'Enseñanza Media TP', 'Técnico Nivel Superior', 'Universitaria',
    ];

    protected $fillable = [
        'area_desempeno_id', 'estamento', 'titulo', 'nivel_estudios', 'mencion',
        'especialidad_tp', 'cargos_funcion', 'anios_experiencia', 'activo',
    ];

    protected $casts = ['activo' => 'boolean'];

    public function postulantProfile(): BelongsTo
    {
        return $this->belongsTo(PostulantProfile::class);
    }

    public function areaDesempeno(): BelongsTo
    {
        return $this->belongsTo(AreaDesempeno::class, 'area_desempeno_id');
    }

    public function getAreaDesempenoNombreAttribute(): ?string
    {
        return $this->areaDesempeno?->nombre;
    }
}
