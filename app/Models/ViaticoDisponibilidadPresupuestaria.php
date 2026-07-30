<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ViaticoDisponibilidadPresupuestaria extends Model
{
    use HasFactory;

    protected $table = 'viaticos_disponibilidad_presupuestaria';

    protected $fillable = [
        'anio',
        'origen_tipo',
        'monto_inicial',
        'monto_comprometido',
        'monto_ejecutado',
        'saldo_disponible',
        'vigente_desde',
        'vigente_hasta',
        'activo',
        'observaciones',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'anio' => 'integer',
        'monto_inicial' => 'integer',
        'monto_comprometido' => 'integer',
        'monto_ejecutado' => 'integer',
        'saldo_disponible' => 'integer',
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
        'activo' => 'boolean',
    ];

    public const ORIGEN_ADMINISTRACION_CENTRAL = 'administracion_central';
    public const ORIGEN_ESTABLECIMIENTOS = 'establecimientos';
    public const ORIGEN_AMBOS = 'ambos';

    public static function origenes(): array
    {
        return [
            self::ORIGEN_ADMINISTRACION_CENTRAL => 'Administración Central',
            self::ORIGEN_ESTABLECIMIENTOS => 'Establecimientos',
            self::ORIGEN_AMBOS => 'Ambos orígenes',
        ];
    }

    public function origenLabel(): string
    {
        return self::origenes()[$this->origen_tipo] ?? ucfirst(str_replace('_', ' ', (string) $this->origen_tipo));
    }

    public function recalcularSaldo(): void
    {
        $this->saldo_disponible = max(0, (int) $this->monto_inicial - (int) $this->monto_comprometido - (int) $this->monto_ejecutado);
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(ViaticoDisponibilidadMovimiento::class, 'viatico_disponibilidad_presupuestaria_id');
    }

    public function porcentajeDisponible(): float
    {
        $inicial = max(1, (int) $this->monto_inicial);

        return round(((int) $this->saldo_disponible / $inicial) * 100, 1);
    }
}
