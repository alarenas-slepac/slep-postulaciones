<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LicenciaMedica extends Model
{
    protected $table = 'licencias_medicas';

    protected $fillable = [
        'tipo_ingreso_licencia',
        'cuerpo_licencia',
        'dv_licencia',
        'folio_licencia',
        'rut_funcionario',
        'dv_funcionario',
        'rut_normalizado',
        'rut_formateado',
        'nombre_funcionario',
        'apellido_paterno',
        'apellido_materno',
        'nombres',
        'sexo',
        'edad',
        'tipo_dependencia',
        'establecimiento_id',
        'establecimiento_nombre',
        'comuna',
        'subdireccion',
        'unidad_departamento',
        'cargo',
        'grado',
        'escalafon',
        'calidad_juridica',
        'estamento',
        'sistema_salud',
        'institucion_salud',
        'vigencia',
        'fecha_emision',
        'fecha_recepcion',
        'fecha_inicio',
        'fecha_termino',
        'dias_solicitados',
        'dias_corridos',
        'dias_laborales',
        'tipo_licencia',
        'tipo_licencia_glosa',
        'valor_licencia',
        'se_puede_recuperar',
        'primer_estado',
        'segundo_estado',
        'fecha_revision',
        'gestion_cobro',
        'numero_ord',
        'fecha_cobro',
        'numero_ord_nuevo_cobro',
        'fecha_nuevo_cobro',
        'ingresar_siaper',
        'rex_siaper',
        'realizo_apelacion',
        'tipo_reposo',
        'lugar_reposo',
        'direccion_reposo',
        'telefono',
        'correo_trabajador',
        'correo_funcionario',
        'rut_empleador',
        'nombre_empleador',
        'estado_actual',
        'estado_compin',
        'dias_autorizados',
        'derecho_subsidio',
        'monto_subsidio',
        'monto_recuperable',
        'monto_cotizacion',
        'estado_notificacion',
        'estado_alerta',
        'origen_ingreso',
        'tipo_documento_ingreso',
        'archivo_licencia_path',
        'archivo_licencia_nombre',
        'archivo_licencia_mime',
        'archivo_licencia_size',
        'extraccion_pdf_estado',
        'extraccion_pdf_json',
        'extraccion_pdf_confianza',
        'fuente_asociacion_funcionario',
        'periodo_reemplazos_usado',
        'importacion_id',
        'origen_planilla_anio',
        'observaciones',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'fecha_recepcion' => 'date',
        'fecha_inicio' => 'date',
        'fecha_termino' => 'date',
        'fecha_revision' => 'date',
        'fecha_cobro' => 'date',
        'fecha_nuevo_cobro' => 'date',
        'dias_solicitados' => 'integer',
        'dias_corridos' => 'integer',
        'dias_laborales' => 'integer',
        'dias_autorizados' => 'integer',
        'edad' => 'integer',
        'valor_licencia' => 'decimal:2',
        'monto_subsidio' => 'decimal:2',
        'monto_recuperable' => 'decimal:2',
        'monto_cotizacion' => 'decimal:2',
        'extraccion_pdf_json' => 'array',
    ];

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class, 'establecimiento_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function actualizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(LicenciaMedicaHistorial::class, 'licencia_medica_id')->latest();
    }

    public function getTipoLicenciaDescripcionAttribute(): string
    {
        return match ((string) $this->tipo_licencia) {
            '1' => 'Enfermedad o Accidente Comun',
            '2' => 'Prorroga Medicina Preventiva',
            '3' => 'Licencia Maternal Pre y Post Natal',
            '4' => 'Enfermedad Grave Hijo Menor de 1 Ano',
            '5' => 'Accidente del Trabajo o del Trayecto',
            '6' => 'Enfermedad Profesional',
            '7' => 'Patologia del Embarazo',
            default => $this->tipo_licencia_glosa ?: 'No informado',
        };
    }
}
