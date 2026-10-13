<?php

namespace App\Models;

use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionLecturaCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class DotacionNoRenovacion extends Model
{
    protected $table = 'dotacion_no_renovaciones';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['asignaciones_anteriores' => 'array', 'horas_contrata' => 'float', 'anulada_at' => 'datetime'];
    }

    public static function disponible(): bool
    {
        return DotacionLecturaCache::recordar('schema:dotacion-no-renovaciones', fn () => Schema::hasTable('dotacion_no_renovaciones'))
            && Schema::hasColumn('dotacion_contrata_habilitaciones', 'no_renovacion_id')
            && Schema::hasColumn('dotacion_contrata_habilitaciones', 'titulo')
            && Schema::hasColumn('dotacion_contrata_habilitaciones', 'funcion');
    }

    public static function rutsRetirados(int $establecimientoId, int $anio): array
    {
        return DotacionLecturaCache::recordar('schema:dotacion-no-renovaciones', fn () => Schema::hasTable('dotacion_no_renovaciones'))
            ? self::query()->where('establecimiento_id', $establecimientoId)->where('anio', $anio)
                ->whereNull('anulada_at')->pluck('docente_rut_normalizado')->all()
            : [];
    }

    public static function estaRetirado(int $establecimientoId, int $anio, string $rut): bool
    {
        return in_array(DotacionEstablecimientoCalculator::normalizeRut($rut), self::rutsRetirados($establecimientoId, $anio), true);
    }
}
