<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CentroOperacionesTicket extends Model
{
    protected $table = 'centro_operaciones_tickets';

    protected $fillable = ['numero', 'incidencia_id', 'configuracion_id', 'unidad_departamento', 'subdireccion_dependencia', 'responsable_funcionario_ac_id', 'creado_por_id', 'vence_en', 'estado', 'notificado_responsable_en', 'escalado_en', 'resuelto_en', 'resuelto_por_id', 'resolucion'];

    protected function casts(): array
    {
        return ['vence_en' => 'datetime', 'notificado_responsable_en' => 'datetime', 'escalado_en' => 'datetime', 'resuelto_en' => 'datetime'];
    }

    public function incidencia(): BelongsTo { return $this->belongsTo(CentroOperacionesIncidencia::class, 'incidencia_id'); }
    public function configuracion(): BelongsTo { return $this->belongsTo(CentroOperacionesIncidenteConfiguracion::class, 'configuracion_id'); }
    public function responsable(): BelongsTo { return $this->belongsTo(FuncionarioAcAutorizado::class, 'responsable_funcionario_ac_id'); }
    public function creadoPor(): BelongsTo { return $this->belongsTo(User::class, 'creado_por_id'); }
    public function resueltoPor(): BelongsTo { return $this->belongsTo(User::class, 'resuelto_por_id'); }
}
