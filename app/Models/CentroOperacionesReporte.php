<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CentroOperacionesReporte extends Model
{
    protected $table = 'centro_operaciones_reportes';

    protected $fillable = [
        'establecimiento_id',
        'unidad_codigo',
        'reportado_por_id',
        'reportado_por_nombre',
        'fecha_reporte',
        'reportado_en',
        'establecimiento_nombre',
        'establecimiento_rbd',
        'establecimiento_comuna',
        'funcionamiento',
        'fecha_control_plagas',
        'matricula_total',
        'matricula_fuente',
        'estudiantes_presentes',
        'docentes_total',
        'docentes_presentes',
        'asistentes_total',
        'asistentes_presentes',
        'padron_periodo',
        'observaciones',
        'necesita_apoyo',
        'apoyo_detalle',
        'prioridad',
        'estado_general',
        'regla_version',
        'version',
    ];

    protected $casts = [
        'fecha_reporte' => 'date',
        'reportado_en' => 'datetime',
        'fecha_control_plagas' => 'date',
        'matricula_total' => 'integer',
        'estudiantes_presentes' => 'integer',
        'docentes_total' => 'integer',
        'docentes_presentes' => 'integer',
        'asistentes_total' => 'integer',
        'asistentes_presentes' => 'integer',
        'necesita_apoyo' => 'boolean',
        'version' => 'integer',
    ];

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class, 'establecimiento_id');
    }

    public function reportadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reportado_por_id')->withTrashed();
    }

    public function getReportadoPorNombreVisibleAttribute(): string
    {
        return $this->reportado_por_nombre
            ?: ($this->reportadoPor?->display_name ?: 'Usuario registrado sin nombre disponible');
    }

    public function servicios(): HasMany
    {
        return $this->hasMany(CentroOperacionesReporteServicio::class, 'reporte_id');
    }

    public function afectaciones(): HasMany
    {
        return $this->hasMany(CentroOperacionesReporteAfectacion::class, 'reporte_id');
    }

    public function incidencias(): HasMany
    {
        return $this->hasMany(CentroOperacionesIncidencia::class, 'reporte_id');
    }

    public function incidenciasResueltas(): HasMany
    {
        return $this->hasMany(CentroOperacionesIncidencia::class, 'resuelta_en_reporte_id');
    }

    public function revisiones(): HasMany
    {
        return $this->hasMany(CentroOperacionesReporteRevision::class, 'reporte_id');
    }
}
