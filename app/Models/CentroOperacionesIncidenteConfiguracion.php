<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CentroOperacionesIncidenteConfiguracion extends Model
{
    protected $table = 'centro_operaciones_incidente_configuraciones';

    protected $fillable = [
        'tipo',
        'nombre',
        'severidad',
        'familia',
        'riesgo_dimension_codigo',
        'impacto_base',
        'urgencia_base',
        'prioridad_minima',
        'unidad_departamento',
        'subdireccion_dependencia',
        'responsable_funcionario_ac_id',
        'segunda_subdireccion_responsable',
        'segundo_responsable_funcionario_ac_id',
        'plazo_dias',
        'sla_horas',
        'forzar_p1',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'plazo_dias' => 'integer',
            'sla_horas' => 'integer',
            'impacto_base' => 'integer',
            'urgencia_base' => 'integer',
            'forzar_p1' => 'boolean',
        ];
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(FuncionarioAcAutorizado::class, 'responsable_funcionario_ac_id');
    }

    public function segundoResponsable(): BelongsTo
    {
        return $this->belongsTo(FuncionarioAcAutorizado::class, 'segundo_responsable_funcionario_ac_id');
    }
}
