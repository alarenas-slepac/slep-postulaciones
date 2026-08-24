<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DotacionDocenteExclusion extends Model
{
    protected $table = 'dotacion_docente_exclusiones';

    public const MOTIVOS = [
        'sumario_administrativo' => 'Sumario administrativo',
        'trabaja_slep' => 'Trabaja en SLEP',
        'fuero_maternal' => 'Fuero maternal',
        'desvinculado' => 'Desvinculado',
        'traslado' => 'Traslado',
        'renuncia_voluntaria' => 'Renuncia voluntaria',
        'proceso_bir' => 'Proceso BIR',
        'horas_lactancia' => 'Horas de lactancia',
        'horas_gremiales' => 'Horas gremiales',
    ];

    protected $fillable = [
        'establecimiento_id',
        'anio',
        'docente_rut',
        'docente_rut_normalizado',
        'docente_nombre',
        'motivo',
        'horas',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'establecimiento_id' => 'integer',
        'anio' => 'integer',
        'horas' => 'decimal:2',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class, 'establecimiento_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getMotivoLabelAttribute(): string
    {
        return self::MOTIVOS[$this->motivo] ?? ucfirst(str_replace('_', ' ', (string) $this->motivo));
    }
}
