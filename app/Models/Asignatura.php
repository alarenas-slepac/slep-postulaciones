<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asignatura extends Model
{
    use HasFactory;

    protected $table = 'asignaturas';

    protected $fillable = [
        'nombre',
        'codigo',
        'nivel_educativo',
        'area',
        'tipo_asignatura',
        'es_oficial',
        'activo',
        'observacion',
    ];

    protected $casts = [
        'es_oficial' => 'boolean',
        'activo' => 'boolean',
    ];

    public const TIPOS = [
        'obligatoria' => 'Obligatoria',
        'plan_comun_electivo' => 'Plan común electivo',
        'plan_diferenciado_hc' => 'Plan diferenciado HC',
        'plan_diferenciado_tp' => 'Plan diferenciado TP',
        'plan_diferenciado_artistico' => 'Plan diferenciado artístico',
        'libre_disposicion' => 'Libre disposición',
        'personalizada' => 'Personalizada',
    ];

    public static function tiposOptions(): array
    {
        return self::TIPOS;
    }

    public function getTipoAsignaturaLabelAttribute(): string
    {
        return self::TIPOS[$this->tipo_asignatura] ?? ucfirst(str_replace('_', ' ', (string) $this->tipo_asignatura));
    }
}
