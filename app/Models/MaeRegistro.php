<?php

namespace App\Models;

use App\Support\RutChile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaeRegistro extends Model
{
    use HasFactory;

    protected $table = 'mae_registros';

    protected $fillable = [
        'mae_carga_id',
        'anio',
        'mes',
        'dominio',
        'comuna_origen',
        'rut',
        'nombre_completo',
        'dias_trab',
        'datos_trabajador_json',
        'total_haberes',
        'monto_imponible',
        'monto_tributable',
        'imposiciones',
        'salud',
        'impuesto',
        'total_descuentos_homologados',
        'total_aportes_patronales',
        'total_otros_descuentos',
        'observaciones_importacion',
        'raw_row_json',
    ];

    protected $casts = [
        'datos_trabajador_json' => 'array',
        'raw_row_json' => 'array',
        'total_haberes' => 'decimal:2',
        'monto_imponible' => 'decimal:2',
        'monto_tributable' => 'decimal:2',
        'imposiciones' => 'decimal:2',
        'salud' => 'decimal:2',
        'impuesto' => 'decimal:2',
        'total_descuentos_homologados' => 'decimal:2',
        'total_aportes_patronales' => 'decimal:2',
        'total_otros_descuentos' => 'decimal:2',
    ];

    public function getRutDvAttribute(): ?string
    {
        $normalized = RutChile::normalize($this->rut);

        return $normalized['rut'] ?? ($this->rut ?: null);
    }

    public function carga(): BelongsTo
    {
        return $this->belongsTo(MaeCarga::class, 'mae_carga_id');
    }

    public function descuentos(): HasMany
    {
        return $this->hasMany(MaeRegistroDescuento::class, 'mae_registro_id');
    }

    public function otrosDescuentos(): HasMany
    {
        return $this->hasMany(MaeRegistroOtroDescuento::class, 'mae_registro_id');
    }
}
