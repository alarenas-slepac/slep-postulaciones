<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SolicitudReemplazo extends Model
{
    protected $table = 'solicitudes_reemplazo';

    protected $fillable = [
        'establecimiento_id',
        'reemplazo_personal_id',
        'area_desempeno_id',
        'postulant_profile_id',
        'anio',
        'correlativo',
        'numero_solicitud',
        'contacto_nombre',
        'contacto_fono',
        'contacto_email',
        'tipo_reemplazo',
        'tipo_reemplazo_otro',
        'fecha_inicio',
        'fecha_termino',
        'propone_reemplazo',
        'continuidad',
        'es_continuidad',
        'solicitud_anterior_id',
        'continuidad_validada_at',
        'regla_minima_aplicada',
        'regla_minima_excepcion',
        'rut_titular_normalizado',
        'rut_reemplazo_normalizado',
        'oficio_pdf_path',
        'respaldo_pdf_path',
        'horario_titular_pdf_path',
        'observaciones',
        'horas_aula_cronologicas_titular',
        'horas_aula_pedagogicas_titular',
        'horas_aula_cronologicas_reemplazo',
        'horas_aula_pedagogicas_reemplazo',
        'declaracion_responsabilidad_aceptada',
        'estado',
        'motivo_rechazo',
        'justificacion_tecnica_uatp',
        'plani_motivo_rechazo',
        'anulada_motivo',
        'anulada_by',
        'anulada_at',
        'uatp_decision_user_id',
        'uatp_decision_at',
        'plani_decision_user_id',
        'plani_decision_at',
        'uatp_rechazo_reabierto_motivo',
        'uatp_reapertura_motivo',
        'uatp_reapertura_user_id',
        'uatp_reapertura_at',
        'plani_rechazo_reabierto_motivo',
        'plani_reapertura_motivo',
        'plani_reapertura_user_id',
        'plani_reapertura_at',
        'derivada_a_user_id',
        'derivada_por_user_id',
        'derivada_at',
        'observacion_slep',
        'observacion_slep_user_id',
        'observacion_slep_at',

        // Orden de trabajo (creada por funcionario_slep)
        'fecha_inicio_trabajo',
        'orden_trabajo_creada_por_user_id',
        'orden_trabajo_creada_at',

        // Contrato de trabajo (AAEE)
        'contrato_trabajo_docx_path',
        'contrato_trabajo_postulant_profile_id',
        'contrato_trabajo_fecha_inicio_trabajo',
        'contrato_trabajo_is_final',
        'contrato_trabajo_subido_por_user_id',
        'contrato_trabajo_subido_at',
        'contrato_trabajo_firmado_pdf_path',
        'contrato_trabajo_firmado_subido_por_user_id',
        'contrato_trabajo_firmado_subido_at',
        'contrato_trabajo_firmado_enviado_por_user_id',
        'contrato_trabajo_firmado_enviado_at',
        'cerrado_por_user_id',
        'cerrado_at',
        'finiquito_pagado',
        'finiquito_pagado_por_user_id',
        'finiquito_pagado_at',
        'finiquito_observacion',
        'finiquito_estado',
        'finiquito_monto',
        'finiquito_fecha_emision',
        'finiquito_pdf_path',
        'finiquito_generado_por_user_id',
        'finiquito_generado_at',
        'finiquito_firmante_nombre',
        'finiquito_firmante_rut',
        'finiquito_firmante_cargo',
        'finiquito_firmante_es_subrogante',
        'finiquito_firmado_pdf_path',
        'finiquito_firmado_nombre_original',
        'finiquito_firmado_mime',
        'finiquito_firmado_size',
        'finiquito_firmado_observacion',
        'finiquito_firmado_cargado_por_user_id',
        'finiquito_firmado_cargado_at',
        'aaee_categoria',

        // Reasignación de postulante (auditoría)
        'reasignacion_postulante_from',
        'reasignacion_postulante_motivo',
        'reasignacion_postulante_by',
        'reasignacion_postulante_at',
        'reemplazo_ajuste_observacion',
        'reemplazo_ajuste_user_id',
        'reemplazo_ajuste_role',
        'reemplazo_ajuste_at',
        'devuelta_desde',
        'retornar_a_etapa',
        'ultima_observacion_rechazo',
        'fecha_ultima_devolucion',
        'usuario_ultima_devolucion_id',
        'corregida_establecimiento_at',
        'corregida_establecimiento_user_id',
        'correccion_establecimiento_observacion',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_termino' => 'date',
        'fecha_inicio_trabajo' => 'date',
        'propone_reemplazo' => 'boolean',
        'declaracion_responsabilidad_aceptada' => 'boolean',
        'horas_aula_cronologicas_titular' => 'float',
        'horas_aula_pedagogicas_titular' => 'float',
        'horas_aula_cronologicas_reemplazo' => 'float',
        'horas_aula_pedagogicas_reemplazo' => 'float',
        'continuidad' => 'boolean',
        'es_continuidad' => 'boolean',
        'continuidad_validada_at' => 'datetime',
        'uatp_decision_at' => 'datetime',
        'plani_decision_at' => 'datetime',
        'uatp_reapertura_at' => 'datetime',
        'plani_reapertura_at' => 'datetime',
        'derivada_at' => 'datetime',
        'observacion_slep_at' => 'datetime',
        'orden_trabajo_creada_at' => 'datetime',
        'contrato_trabajo_subido_at' => 'datetime',
        'contrato_trabajo_firmado_subido_at' => 'datetime',
        'contrato_trabajo_firmado_enviado_at' => 'datetime',
        'cerrado_at' => 'datetime',
        'finiquito_pagado' => 'boolean',
        'finiquito_pagado_at' => 'datetime',
        'finiquito_monto' => 'integer',
        'finiquito_fecha_emision' => 'date',
        'finiquito_generado_at' => 'datetime',
        'finiquito_firmante_es_subrogante' => 'boolean',
        'finiquito_firmado_size' => 'integer',
        'finiquito_firmado_cargado_at' => 'datetime',
        'anulada_at' => 'datetime',
        'reasignacion_postulante_at' => 'datetime',
        'reemplazo_ajuste_at' => 'datetime',
        'fecha_ultima_devolucion' => 'datetime',
        'corregida_establecimiento_at' => 'datetime',
    ];

    public function jornadas(): HasMany
    {
        return $this->hasMany(SolicitudReemplazoJornada::class, 'solicitud_reemplazo_id');
    }

    public function observacionesFlujo(): HasMany
    {
        return $this->hasMany(SolicitudReemplazoObservacion::class, 'solicitud_reemplazo_id')->latest();
    }

    public function establecimiento()
    {
        return $this->belongsTo(Establecimiento::class);
    }

    public function funcionarioTitular()
    {
        return $this->belongsTo(ReemplazoPersonal::class, 'reemplazo_personal_id');
    }

    public function areaDesempeno()
    {
        return $this->belongsTo(AreaDesempeno::class, 'area_desempeno_id');
    }

    public function postulante()
    {
        return $this->belongsTo(PostulantProfile::class, 'postulant_profile_id');
    }
public function contratoPostulante()
{
    return $this->belongsTo(PostulantProfile::class, 'contrato_trabajo_postulant_profile_id');
}
    public function uatpDecisionUser()
    {
        return $this->belongsTo(User::class, 'uatp_decision_user_id');
    }

    public function derivadaA()
    {
        return $this->belongsTo(User::class, 'derivada_a_user_id');
    }

    public function planiDecisionUser()
    {
        return $this->belongsTo(User::class, 'plani_decision_user_id');
    }

    public function uatpReaperturaUser()
    {
        return $this->belongsTo(User::class, 'uatp_reapertura_user_id');
    }

    public function planiReaperturaUser()
    {
        return $this->belongsTo(User::class, 'plani_reapertura_user_id');
    }

    public function derivadaPor()
    {
        return $this->belongsTo(User::class, 'derivada_por_user_id');
    }

    public function observacionSlepUser()
    {
        return $this->belongsTo(User::class, 'observacion_slep_user_id');
    }

    public function ordenTrabajoCreadaPor()
    {
        return $this->belongsTo(User::class, 'orden_trabajo_creada_por_user_id');
    }

    public function contratoTrabajoSubidoPor()
    {
        return $this->belongsTo(User::class, 'contrato_trabajo_subido_por_user_id');
    }

    public function contratoTrabajoFirmadoSubidoPor()
    {
        return $this->belongsTo(User::class, 'contrato_trabajo_firmado_subido_por_user_id');
    }

    public function contratoTrabajoFirmadoEnviadoPor()
    {
        return $this->belongsTo(User::class, 'contrato_trabajo_firmado_enviado_por_user_id');
    }

    public function cerradoPor()
    {
        return $this->belongsTo(User::class, 'cerrado_por_user_id');
    }


    public function finiquitoPagadoPor()
    {
        return $this->belongsTo(User::class, 'finiquito_pagado_por_user_id');
    }

    public function finiquitoFirmadoCargadoPor()
    {
        return $this->belongsTo(User::class, 'finiquito_firmado_cargado_por_user_id');
    }

    public function finiquitoGeneradoPor()
    {
        return $this->belongsTo(User::class, 'finiquito_generado_por_user_id');
    }

    public function reemplazoAjusteUser()
    {
        return $this->belongsTo(User::class, 'reemplazo_ajuste_user_id');
    }

    public function solicitudAnterior()
    {
        return $this->belongsTo(self::class, 'solicitud_anterior_id');
    }
}

