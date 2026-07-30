<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaeCarga extends Model
{
    use HasFactory;

    protected $table = 'mae_cargas';

    protected $fillable = [
        'anio',
        'mes',
        'dominio',
        'comuna_origen',
        'version',
        'es_vigente',
        'reemplaza_carga_id',
        'motivo_reemplazo',
        'nombre_archivo',
        'ruta_archivo',
        'hash_archivo',
        'estado',
        'total_filas',
        'filas_validas',
        'filas_omitidas',
        'filas_observadas',
        'observaciones',
        'subido_por',
        'procesado_at',
    ];

    protected $casts = [
        'es_vigente' => 'boolean',
        'procesado_at' => 'datetime',
    ];

    public function registros(): HasMany
    {
        return $this->hasMany(MaeRegistro::class, 'mae_carga_id');
    }

    public function cuotasImportaciones(): HasMany
    {
        return $this->hasMany(MaeCuotasImportacion::class, 'mae_carga_id');
    }

    public function subidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    public function reemplazaCarga(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reemplaza_carga_id');
    }

    public function versionesRelacionadas(): HasMany
    {
        return $this->hasMany(self::class, 'reemplaza_carga_id');
    }
}
