<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class DotacionSobredotacionJustificacion extends Model
{
    protected $table = 'dotacion_sobredotacion_justificaciones';

    protected $fillable = [
        'establecimiento_id', 'anio', 'docente_rut_normalizado', 'bloque',
        'tipo_horas', 'horas_detectadas', 'justificacion', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'horas_detectadas' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static function clave(string $bloque, string $rut, string $tipo): string
    {
        return $bloque.'|'.\App\Support\DotacionEstablecimientoCalculator::normalizeRut($rut).'|'.$tipo;
    }

    public function vigentePara(float $horas): bool
    {
        return abs((float) $this->horas_detectadas - $horas) < 0.01;
    }

    /** Una justificación registrada retira al docente de otras funciones de ese año y establecimiento. */
    public static function rutsJustificados(int $establecimientoId, int $anio): array
    {
        if (! Schema::hasTable('dotacion_sobredotacion_justificaciones')) {
            return [];
        }

        return self::query()->where('establecimiento_id', $establecimientoId)->where('anio', $anio)
            ->whereIn('bloque', ['plan_estudio', 'parvularia', 'pie'])
            ->whereIn('tipo_horas', ['titular', 'contrata'])->where('horas_detectadas', '>', 0)
            ->get(['docente_rut_normalizado', 'justificacion'])
            ->filter(fn (self $registro) => trim((string) $registro->justificacion) !== '')
            ->map(fn (self $registro) => \App\Support\DotacionEstablecimientoCalculator::normalizeRut($registro->docente_rut_normalizado))
            ->filter()->unique()->values()->all();
    }

    public static function horasVacantes(array $sobredotacion, string $bloque, string $rut, string $tipo): float
    {
        if (! in_array($bloque, ['plan_estudio', 'parvularia', 'pie'], true)
            || ! in_array($tipo, ['titular', 'contrata'], true)) {
            return 0.0;
        }

        $rutNormalizado = \App\Support\DotacionEstablecimientoCalculator::normalizeRut($rut);
        if ($rutNormalizado === '') {
            return 0.0;
        }

        $docente = collect(data_get($sobredotacion, 'vacantes_por_bloque.'.$bloque.'.items', []))
            ->first(fn (array $item) => \App\Support\DotacionEstablecimientoCalculator::normalizeRut($item['rut']) === $rutNormalizado);
        $campo = $tipo === 'titular' ? 'horas_sobredotacion_planta' : 'horas_sobredotacion_contrata';

        return round((float) ($docente[$campo] ?? 0), 2);
    }
}
