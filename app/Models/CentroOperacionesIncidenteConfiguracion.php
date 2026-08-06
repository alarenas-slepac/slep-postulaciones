<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CentroOperacionesIncidenteConfiguracion extends Model
{
    protected $table = 'centro_operaciones_incidente_configuraciones';

    protected $fillable = ['tipo', 'unidad_departamento', 'subdireccion_dependencia', 'responsable_funcionario_ac_id', 'plazo_dias', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'plazo_dias' => 'integer'];
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(FuncionarioAcAutorizado::class, 'responsable_funcionario_ac_id');
    }
}
