<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SnedCuota extends Model
{
    protected $table = 'sned_cuotas';

    protected $guarded = [];

    protected $casts = [
        'resultados' => 'array',
        'cerrado_at' => 'datetime',
    ];

    public const MESES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    public const METODOS = [
        'jornada_corte' => 'Jornada del mes de corte',
        'jornada_dias' => 'Jornada y días del trimestre',
    ];

    public function meses(): array
    {
        return range($this->numero * 3 - 2, $this->numero * 3);
    }

    public function cargas(): HasMany
    {
        return $this->hasMany(SnedCarga::class, 'cuota_id');
    }

    public function aportes(): HasMany
    {
        return $this->hasMany(SnedAporte::class, 'cuota_id');
    }
}
