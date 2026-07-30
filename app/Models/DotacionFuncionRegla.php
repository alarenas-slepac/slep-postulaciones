<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DotacionFuncionRegla extends Model
{
    protected $table = 'dotacion_funciones_reglas';

    protected $fillable = [
        'codigo',
        'categoria',
        'nombre',
        'tipo_regla',
        'horas_fijas',
        'horas_minimas',
        'horas_maximas',
        'umbral_matricula',
        'horas_bajo_umbral',
        'horas_sobre_umbral',
        'permite_multiples',
        'declarable',
        'obligatoria',
        'requiere_validacion',
        'fundamento',
        'vigente',
    ];

    protected $casts = [
        'horas_fijas' => 'integer',
        'horas_minimas' => 'integer',
        'horas_maximas' => 'integer',
        'umbral_matricula' => 'integer',
        'horas_bajo_umbral' => 'integer',
        'horas_sobre_umbral' => 'integer',
        'permite_multiples' => 'boolean',
        'declarable' => 'boolean',
        'obligatoria' => 'boolean',
        'requiere_validacion' => 'boolean',
        'vigente' => 'boolean',
    ];

    public function registros(): HasMany
    {
        return $this->hasMany(DotacionFuncionEstablecimiento::class, 'regla_id');
    }
}
