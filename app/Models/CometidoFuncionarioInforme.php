<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CometidoFuncionarioInforme extends Model
{
    protected $table = 'cometidos_funcionarios_informes';

    protected $fillable = [
        'cometido_funcionario_id',
        'estado_informe',
        'fecha_desde_real',
        'fecha_hasta_real',
        'hora_salida_real',
        'hora_regreso_real',
        'justificacion_cambio_fechas',
        'organismos_autoridades_relatores',
        'descripcion_actividades_realizadas',
        'resultados_obtenidos',
        'opiniones_propuestas',
        'requiere_nuevo_cometido_diferencia',
        'fecha_envio',
        'fecha_revision_jefatura',
        'jefatura_revisora_id',
        'decision_jefatura',
        'observacion_jefatura',
        'user_id_envia',
        'observacion_sistema',
    ];

    protected $casts = [
        'fecha_desde_real' => 'date',
        'fecha_hasta_real' => 'date',
        'fecha_envio' => 'datetime',
        'fecha_revision_jefatura' => 'datetime',
        'requiere_nuevo_cometido_diferencia' => 'boolean',
    ];

    public function cometido(): BelongsTo
    {
        return $this->belongsTo(CometidoFuncionario::class, 'cometido_funcionario_id');
    }

    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id_envia');
    }

    public function jefaturaRevisora(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jefatura_revisora_id');
    }

    public function estaAprobadoPorJefatura(): bool
    {
        return in_array((string) $this->estado_informe, ['aprobado_jefatura', 'informe_aprobado', 'aprobado'], true);
    }

    public function documentoGenerado()
    {
        return $this->cometido?->documentosGenerados()
            ->where('tipo_documento', 'informe_cometido')
            ->latest('id')
            ->first();
    }

}
