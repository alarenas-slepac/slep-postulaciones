<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaeRegistroDescuento extends Model
{
    use HasFactory;

    protected $table = 'mae_registro_descuentos';

    protected $fillable = [
        'mae_registro_id',
        'orden_columna',
        'columna_origen',
        'columna_normalizada',
        'campo_canonico',
        'grupo',
        'subgrupo',
        'tipo_movimiento',
        'es_aporte_patronal',
        'valor',
        'cuota_actual',
        'total_cuotas',
        'cuota_observacion',
        'cuota_importacion_id',
        'cuota_updated_by',
        'cuota_updated_at',
    ];

    protected $casts = [
        'es_aporte_patronal' => 'boolean',
        'valor' => 'decimal:2',
        'cuota_actual' => 'integer',
        'total_cuotas' => 'integer',
        'cuota_updated_at' => 'datetime',
    ];

    public function registro(): BelongsTo
    {
        return $this->belongsTo(MaeRegistro::class, 'mae_registro_id');
    }


    public function tieneInformacionCuota(): bool
    {
        return $this->cuota_actual !== null || $this->total_cuotas !== null;
    }

    public function cuotaEsIndefinida(): bool
    {
        if (!$this->tieneInformacionCuota()) {
            return false;
        }

        return ($this->cuota_actual !== null && (int) $this->cuota_actual === 0)
            || ($this->total_cuotas !== null && (int) $this->total_cuotas === 0);
    }

    public function cuotaEtiqueta(): ?string
    {
        if (!$this->tieneInformacionCuota()) {
            return null;
        }

        $cuotaActual = $this->cuota_actual !== null ? (int) $this->cuota_actual : null;
        $totalCuotas = $this->total_cuotas !== null ? (int) $this->total_cuotas : null;

        if ($cuotaActual === 0 && $totalCuotas === 0) {
            return 'Descuento indefinido (sin inicio ni término)';
        }

        if ($cuotaActual === 0) {
            return $totalCuotas !== null && $totalCuotas > 0
                ? 'Descuento indefinido (sin inicio informado; total ' . $totalCuotas . ' cuotas)'
                : 'Descuento indefinido (sin inicio informado)';
        }

        if ($cuotaActual !== null && $cuotaActual > 0 && $totalCuotas === 0) {
            return 'Cuota ' . $cuotaActual . ' — indefinida (sin término)';
        }

        if ($cuotaActual !== null && $cuotaActual > 0 && $totalCuotas !== null && $totalCuotas > 0) {
            return 'Cuota ' . $cuotaActual . ' de ' . $totalCuotas;
        }

        if ($cuotaActual !== null && $cuotaActual > 0) {
            return 'Cuota ' . $cuotaActual;
        }

        if ($totalCuotas !== null && $totalCuotas > 0) {
            return 'Total ' . $totalCuotas . ' cuotas (inicio no informado)';
        }

        return 'Descuento indefinido';
    }

    public function mesInicioCuota(int $anio, int $mes): ?CarbonImmutable
    {
        $cuotaActual = $this->cuota_actual !== null ? (int) $this->cuota_actual : 0;
        if ($cuotaActual <= 0 || $anio <= 0 || $mes < 1 || $mes > 12) {
            return null;
        }

        return CarbonImmutable::create($anio, $mes, 1, 0, 0, 0)
            ->startOfMonth()
            ->subMonthsNoOverflow($cuotaActual - 1);
    }

    public function mesInicioCuotaEtiqueta(int $anio, int $mes): ?string
    {
        return $this->mesInicioCuota($anio, $mes)?->format('m/Y');
    }

    public function cuotaImportacion(): BelongsTo
    {
        return $this->belongsTo(MaeCuotasImportacion::class, 'cuota_importacion_id');
    }

    public function cuotaActualizadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cuota_updated_by');
    }
}
