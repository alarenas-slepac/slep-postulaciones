<?php

namespace App\Models;

use App\Support\DotacionEstablecimientoCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

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
        'considerar_dotacion_siguiente',
        'conservar_horas_necesarias',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'establecimiento_id' => 'integer',
        'anio' => 'integer',
        'horas' => 'decimal:2',
        'considerar_dotacion_siguiente' => 'boolean',
        'conservar_horas_necesarias' => 'boolean',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class, 'establecimiento_id');
    }

    public static function continuidadDisponible(): bool
    {
        return Schema::hasColumn('dotacion_docente_exclusiones', 'considerar_dotacion_siguiente');
    }

    /** La decisión pertenece al establecimiento y año base, no al padrón global. */
    public static function continuidadPorRut(int $establecimientoId, int $anio): array
    {
        if (! self::continuidadDisponible()) {
            return [];
        }

        return self::query()->where('establecimiento_id', $establecimientoId)->where('anio', $anio)
            ->get(['docente_rut', 'docente_rut_normalizado', 'considerar_dotacion_siguiente'])
            ->mapWithKeys(fn (self $situacion) => [
                DotacionEstablecimientoCalculator::normalizeRut($situacion->docente_rut_normalizado ?: $situacion->docente_rut)
                    => $situacion->considerar_dotacion_siguiente ?? true,
            ])->all();
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function conservacionHorasDisponible(): bool
    {
        return Schema::hasColumn('dotacion_docente_exclusiones', 'conservar_horas_necesarias');
    }

    public static function conservacionHorasPorRut(int $establecimientoId, int $anio): array
    {
        if (! self::conservacionHorasDisponible()) {
            return [];
        }

        return self::query()->where('establecimiento_id', $establecimientoId)->where('anio', $anio)
            ->get(['docente_rut', 'docente_rut_normalizado', 'conservar_horas_necesarias'])
            ->mapWithKeys(fn (self $situacion) => [
                DotacionEstablecimientoCalculator::normalizeRut($situacion->docente_rut_normalizado ?: $situacion->docente_rut)
                    => $situacion->conservar_horas_necesarias ?? true,
            ])->all();
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
