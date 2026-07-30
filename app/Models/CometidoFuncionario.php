<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CometidoFuncionario extends Model
{
    protected $table = 'cometidos_funcionarios';

    protected $fillable = [
        'user_id',
        'establecimiento_id',
        'rbd',
        'estado',
        'estado_viatico',
        'estado_reembolso',
        'fecha_solicitud',
        'reemplazo_personal_id',
        'funcionario_rut',
        'funcionario_nombre',
        'calidad_juridica',
        'estamento',
        'cargo_funcion',
        'region_destino',
        'comuna_destino_id',
        'comuna_destino_nombre',
        'institucion_destino',
        'destino',
        'fecha_desde',
        'fecha_hasta',
        'hora_salida',
        'hora_regreso',
        'medios_transporte',
        'medio_transporte_otro',
        'motivo',
        'motivo_otro',
        'descripcion_actividades',
        'existe_citacion_invitacion',
        'archivo_citacion_invitacion_path',
        'archivo_citacion_invitacion_nombre',
        'solicita_viatico',
        'solicita_reembolso',
        'contempla_alojamiento',
        'servicio_contempla_colacion',
        'solicita_anticipo_viatico',
        'porcentaje_anticipo_viatico',
        'monto_anticipo_viatico',
        'monto_saldo_viatico',
        'banco_pago',
        'tipo_cuenta_pago',
        'numero_cuenta_pago',
        'declaracion_aceptada',
        'declaracion_aceptada_at',
        'declaracion_texto',
        'uatp_revisado_por',
        'uatp_revisado_at',
        'uatp_decision',
        'uatp_observacion',
        'cdp_revisado_por',
        'cdp_revisado_at',
        'cdp_aprobado',
        'cdp_observacion',
        'cdp_referencia',
        'cdp_catalogo_valor_id',
        'cdp_estamento',
        'cdp_cargo_funcion',
        'cdp_viatico_total',
        'cdp_reembolso_total_maximo',
        'cdp_monto_total',
        'cdp_monto_asignado_at',
        'cdp_monto_asignado_by',
        'gdp_revisado_por',
        'gdp_revisado_at',
        'numero_resolucion_cometido',
        'fecha_resolucion_cometido',
        'archivo_resolucion_cometido_path',
        'finanzas_revisado_por',
        'finanzas_revisado_at',
        'fecha_pago_viatico',
        'monto_pagado_viatico',
        'folio_tesoreria_viatico',
        'documento_pago_viatico_path',
        'observacion_pago_viatico',
        'usuario_pago_viatico_id',
        'fecha_registro_pago_viatico',
        'folio_compromiso_viatico',
        'fecha_compromiso_viatico',
        'folio_devengo_viatico',
        'fecha_devengo_viatico',
        'documento_contable_viatico_path',
        'observacion_contable_viatico',
        'daf_contable_viatico_user_id',
        'daf_contable_viatico_at',
        'viatico_finalizado_at',
        'reembolso_finalizado_at',
        'finanzas_observacion',
        'origen_cometido',
        'funcionario_ac_autorizado_id',
        'numero_cometido_interno',
        'region_origen',
        'comuna_origen_id',
        'comuna_origen_nombre',
        'es_destino_extranjero',
        'pais_destino',
        'ciudad_destino_extranjero',
        'subdireccion_dependencia_ac',
        'unidad_departamento_ac',
        'es_jefatura_ac',
        'estado_autorizacion_jefatura_ac',
        'jefatura_autorizadora_ac_id',
        'jefatura_autorizadora_user_id',
        'autorizado_por_subrogante',
        'fecha_autorizacion_jefatura_ac',
        'observacion_jefatura_ac',
        'requiere_pasaje_aereo',
        'ssgg_notificado_vehiculo_at',
        'ssgg_notificado_vehiculo_email',
        'ssgg_notificado_vehiculo_por',
        'tipo_pasaje_aereo',
        'dias_habiles_anticipacion',
        'justificacion_menor_7_dias',
        'requiere_autorizacion_director_sin_disponibilidad',
        'estado_autorizacion_director',
        'monto_viatico_solicitado_director',
        'monto_disponible_director',
        'diferencia_presupuestaria_director',
        'fundamento_planificacion_director',
        'decision_director',
        'observacion_director',
        'fecha_solicitud_director',
        'fecha_decision_director',
        'director_user_id',
        'viatico_reconvertido_a_reembolso',
        'motivo_reconversion_reembolso',
        'tenia_derecho_viatico_original',
        'monto_viatico_original',
    ];

    protected $casts = [
        'fecha_solicitud' => 'date',
        'fecha_desde' => 'date',
        'fecha_hasta' => 'date',
        'fecha_resolucion_cometido' => 'date',
        'fecha_pago_viatico' => 'date',
        'monto_pagado_viatico' => 'integer',
        'fecha_registro_pago_viatico' => 'datetime',
        'fecha_compromiso_viatico' => 'date',
        'fecha_devengo_viatico' => 'date',
        'daf_contable_viatico_at' => 'datetime',
        'medios_transporte' => 'array',
        'existe_citacion_invitacion' => 'boolean',
        'solicita_viatico' => 'boolean',
        'solicita_reembolso' => 'boolean',
        'contempla_alojamiento' => 'boolean',
        'servicio_contempla_colacion' => 'string',
        'solicita_anticipo_viatico' => 'boolean',
        'porcentaje_anticipo_viatico' => 'integer',
        'monto_anticipo_viatico' => 'integer',
        'monto_saldo_viatico' => 'integer',
        'declaracion_aceptada' => 'boolean',
        'declaracion_aceptada_at' => 'datetime',
        'cdp_aprobado' => 'boolean',
        'uatp_revisado_at' => 'datetime',
        'cdp_revisado_at' => 'datetime',
        'cdp_monto_asignado_at' => 'datetime',
        'cdp_viatico_total' => 'integer',
        'cdp_reembolso_total_maximo' => 'integer',
        'cdp_monto_total' => 'integer',
        'gdp_revisado_at' => 'datetime',
        'finanzas_revisado_at' => 'datetime',
        'es_destino_extranjero' => 'boolean',
        'es_jefatura_ac' => 'boolean',
        'autorizado_por_subrogante' => 'boolean',
        'fecha_autorizacion_jefatura_ac' => 'datetime',
        'requiere_pasaje_aereo' => 'boolean',
        'ssgg_notificado_vehiculo_at' => 'datetime',
        'viatico_finalizado_at' => 'datetime',
        'reembolso_finalizado_at' => 'datetime',
        'requiere_autorizacion_director_sin_disponibilidad' => 'boolean',
        'monto_viatico_solicitado_director' => 'integer',
        'monto_disponible_director' => 'integer',
        'diferencia_presupuestaria_director' => 'integer',
        'fecha_solicitud_director' => 'datetime',
        'fecha_decision_director' => 'datetime',
        'viatico_reconvertido_a_reembolso' => 'boolean',
        'tenia_derecho_viatico_original' => 'boolean',
        'monto_viatico_original' => 'integer',
    ];

    public const ESTADOS = [
        'borrador' => 'Borrador',
        'en_revision_uatp' => 'En revisión UATP',
        'en_revision_jefatura_ac' => 'En revisión Jefatura AC',
        'observado_jefatura_ac' => 'Observado por Jefatura AC',
        'rechazado_jefatura_ac' => 'Rechazado por Jefatura AC',
        'aprobado_jefatura_ac' => 'Aprobado por Jefatura AC',
        'observado_uatp' => 'Observado por UATP',
        'rechazado_uatp' => 'Rechazado por UATP',
        'aprobado_uatp' => 'Aprobado por UATP',
        'en_revision_cdp' => 'En revisión CDP',
        'pendiente_autorizacion_director_sin_disponibilidad' => 'Pendiente Director Ejecutivo por falta de disponibilidad',
        'reconvertido_a_reembolso_por_sin_disponibilidad' => 'Reconversión a reembolso aprobada',
        'rechazado_director_sin_disponibilidad' => 'Rechazado por Director Ejecutivo por falta de disponibilidad',
        'en_gestion_paralela' => 'En gestión paralela de viático y reembolso',
        'cdp_aprobado' => 'CDP aprobado',
        'cdp_rechazado' => 'CDP rechazado',
        'autorizado_sin_gasto' => 'Autorizado sin gasto',
        'en_gdp_resolucion' => 'En GDP para resolución',
        'en_gdp_rex_cgr' => 'En GDP para REX cometido CGR',
        'resolucion_cometido_emitida' => 'Resolución de cometido emitida',
        'informe_pendiente_funcionario' => 'Informe de cometido pendiente',
        'informe_pendiente_jefatura' => 'Informe pendiente de firma jefatura',
        'informe_observado' => 'Informe observado',
        'informe_aprobado' => 'Informe aprobado',
        'informe_rechazado' => 'Informe rechazado por jefatura',
        'en_daf_viatico' => 'En DAF - viático',
        'en_daf_contable_viatico' => 'En DAF contable - viático',
        'en_pago_viatico' => 'En pago de viático',
        'viatico_pagado' => 'Viático pagado',
        'en_daf_reembolso' => 'En DAF - reembolso',
        'en_rendicion_reembolso' => 'En rendición de reembolso',
        'pendiente_rendicion' => 'Pendiente de rendición',
        'pendiente_rendicion_informe' => 'Pendiente de rendición e informe de cometido',
        'rendicion_enviada_pendiente_informe' => 'Rendición enviada, informe pendiente',
        'rendicion_rectificada_pendiente_daf' => 'Rendición rectificada, pendiente DAF',
        'rendicion_enviada' => 'Rendición enviada',
        'en_revision_daf_rendicion' => 'En revisión DAF de rendición',
        'rendicion_observada_daf' => 'Rendición observada por DAF',
        'rendicion_rechazada_daf' => 'Rendición rechazada por DAF',
        'rendicion_autorizada_daf' => 'Rendición autorizada por DAF',
        'en_revision_cdp_rendicion' => 'En revisión CDP de rendición',
        'cdp_observado_rendicion' => 'CDP de rendición observado',
        'cdp_rechazado_rendicion' => 'CDP de rendición rechazado',
        'cdp_reembolso_aprobado' => 'CDP de reembolso aprobado',
        'en_juridica_resolucion_reembolso' => 'En Jurídica para resolución de reembolso',
        'observada_juridica_reembolso' => 'Observado por Jurídica',
        'resolucion_reembolso_emitida' => 'Resolución de reembolso emitida',
        'en_daf_contable_reembolso' => 'En DAF contable - reembolso',
        'en_pago_reembolso' => 'En pago de reembolso',
        'reembolso_pagado' => 'Reembolso pagado',
        'cerrado_sin_pago_reembolso' => 'Cerrado sin pago de reembolso',
        'cerrado' => 'Cerrado',
    ];



    public function esFlujoViaticoReembolso(): bool
    {
        return (bool) $this->solicita_viatico && (bool) $this->solicita_reembolso;
    }

    public function estadoViaticoActual(): ?string
    {
        if ($this->estado_viatico) {
            return $this->estado_viatico;
        }

        if (! $this->solicita_viatico) {
            return null;
        }

        return match ($this->estado) {
            'en_revision_cdp' => 'en_revision_cdp',
            'en_gdp_resolucion' => 'en_gdp_resolucion',
            'informe_pendiente_funcionario' => 'informe_pendiente_funcionario',
            'informe_pendiente_jefatura' => 'informe_pendiente_jefatura',
            'informe_observado' => 'informe_observado',
            'informe_aprobado' => 'informe_aprobado',
            'en_daf_viatico' => 'en_daf_viatico',
            'en_pago_viatico' => 'en_pago_viatico',
            'viatico_pagado', 'cerrado' => 'viatico_pagado',
            default => null,
        };
    }

    public function estadoReembolsoActual(): ?string
    {
        if ($this->estado_reembolso) {
            return $this->estado_reembolso;
        }

        if (! $this->solicita_reembolso) {
            return null;
        }

        return match ($this->estado) {
            'en_gdp_resolucion' => 'en_gdp_resolucion',
            'pendiente_rendicion', 'pendiente_rendicion_informe', 'en_rendicion_reembolso' => 'pendiente_rendicion',
            'rendicion_enviada_pendiente_informe' => 'rendicion_enviada_pendiente_informe',
            'rendicion_enviada', 'rendicion_rectificada_pendiente_daf', 'en_revision_daf_rendicion', 'rendicion_observada_daf', 'rendicion_rechazada_daf', 'rendicion_autorizada_daf',
            'en_revision_cdp_rendicion', 'cdp_observado_rendicion', 'cdp_rechazado_rendicion', 'cdp_reembolso_aprobado',
            'en_juridica_resolucion_reembolso', 'observada_juridica_reembolso', 'resolucion_reembolso_emitida', 'en_daf_contable_reembolso', 'en_pago_reembolso',
            'reembolso_pagado', 'cerrado_sin_pago_reembolso' => $this->estado,
            'cerrado' => 'reembolso_pagado',
            default => null,
        };
    }

    public function viaticoPagado(): bool
    {
        return $this->estadoViaticoActual() === 'viatico_pagado'
            || $this->estado === 'viatico_pagado'
            || $this->fecha_pago_viatico !== null
            || $this->monto_pagado_viatico !== null;
    }

    public function reembolsoPagado(): bool
    {
        return in_array($this->estadoReembolsoActual(), ['reembolso_pagado', 'cerrado_sin_pago_reembolso'], true)
            || in_array($this->estado, ['reembolso_pagado', 'cerrado_sin_pago_reembolso'], true);
    }

    public function listoParaCierre(): bool
    {
        if ($this->esFlujoViaticoReembolso()) {
            return $this->viaticoPagado() && $this->reembolsoPagado();
        }

        if ($this->solicita_viatico) {
            return $this->viaticoPagado();
        }

        if ($this->solicita_reembolso) {
            return $this->reembolsoPagado();
        }

        return in_array($this->estado, ['resolucion_cometido_emitida', 'cerrado_sin_pago_reembolso'], true);
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class);
    }

    public function funcionarioPadron(): BelongsTo
    {
        return $this->belongsTo(ReemplazoPersonal::class, 'reemplazo_personal_id');
    }

    public function comunaDestino(): BelongsTo
    {
        return $this->belongsTo(Commune::class, 'comuna_destino_id');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(CometidoFuncionarioHistorial::class, 'cometido_funcionario_id')->latest();
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(CometidoFuncionarioDocumento::class, 'cometido_funcionario_id');
    }

    public function cdpMontos(): HasMany
    {
        return $this->hasMany(CometidoFuncionarioCdpMonto::class, 'cometido_funcionario_id')->orderBy('tipo')->orderBy('dia_numero');
    }

    public function funcionarioAcAutorizado(): BelongsTo
    {
        return $this->belongsTo(FuncionarioAcAutorizado::class, 'funcionario_ac_autorizado_id');
    }

    public function pasajeAereo(): HasMany
    {
        return $this->hasMany(CometidoFuncionarioPasajeAereo::class, 'cometido_funcionario_id');
    }

    public function documentosGenerados(): HasMany
    {
        return $this->hasMany(CometidoFuncionarioDocumentoGenerado::class, 'cometido_funcionario_id');
    }

    public function firmasDigitales(): HasMany
    {
        return $this->hasMany(CometidoFuncionarioFirma::class, 'cometido_funcionario_id');
    }

    public function catalogoValorCdp(): BelongsTo
    {
        return $this->belongsTo(ViaticoReembolsoValor::class, 'cdp_catalogo_valor_id');
    }

    public function etiquetaEstado(): string
    {
        return self::ESTADOS[$this->estado] ?? (string) $this->estado;
    }

    public function esAdministracionCentral(): bool
    {
        return (string) ($this->origen_cometido ?? 'establecimiento') === 'administracion_central';
    }

    public function esEditablePorFuncionarioAc(): bool
    {
        if (! $this->esAdministracionCentral()) {
            return false;
        }

        if (in_array($this->estado, ['borrador', 'observado_jefatura_ac'], true)) {
            return true;
        }

        if ($this->estado === 'en_revision_jefatura_ac') {
            return $this->fecha_autorizacion_jefatura_ac === null
                && empty($this->estado_autorizacion_jefatura_ac);
        }

        return false;
    }

    public function esEliminablePorFuncionarioAc(): bool
    {
        if (! $this->esAdministracionCentral()) {
            return false;
        }

        if (! in_array($this->estado, ['borrador', 'en_revision_jefatura_ac'], true)) {
            return false;
        }

        return $this->fecha_autorizacion_jefatura_ac === null
            && empty($this->estado_autorizacion_jefatura_ac);
    }

    public function esEditablePorEstablecimiento(): bool
    {
        if (in_array($this->estado, ['borrador', 'observado_uatp'], true)) {
            return true;
        }

        if ($this->estado === 'en_revision_uatp') {
            return $this->uatp_decision === null && $this->uatp_revisado_at === null;
        }

        return false;
    }

    public function esEliminablePorEstablecimiento(): bool
    {
        if (! in_array($this->estado, ['borrador', 'en_revision_uatp'], true)) {
            return false;
        }

        return $this->uatp_decision === null && $this->uatp_revisado_at === null;
    }

    public function requiereGestionPresupuestaria(): bool
    {
        return (bool) $this->solicita_viatico || (bool) $this->solicita_reembolso;
    }
    public function informesCometido(): HasMany
    {
        return $this->hasMany(CometidoFuncionarioInforme::class, 'cometido_funcionario_id');
    }

    public function informeCometidoActual()
    {
        return $this->hasOne(CometidoFuncionarioInforme::class, 'cometido_funcionario_id')->latestOfMany();
    }

}
