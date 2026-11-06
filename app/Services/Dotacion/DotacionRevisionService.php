<?php

namespace App\Services\Dotacion;

use App\Models\DotacionEstablecimientoRevision;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DotacionRevisionService
{
    public static function disponible(): bool
    {
        return Schema::hasTable('dotacion_establecimiento_revisiones');
    }

    public static function vigentes(int $anio)
    {
        return DotacionEstablecimientoRevision::query()->where('anio', $anio)->whereNull('reabierta_at')->whereNotNull('clave_activa');
    }

    public static function finalizado(int $establecimientoId, int $anio): bool
    {
        return self::disponible() && self::vigentes($anio)->where('establecimiento_id', $establecimientoId)->exists();
    }

    public function historial(iterable $ids, int $anio): Collection
    {
        return self::disponible() ? DotacionEstablecimientoRevision::query()->with('responsable')
            ->whereIn('establecimiento_id', $ids)->where('anio', $anio)->orderByDesc('id')->get()->groupBy('establecimiento_id') : collect();
    }

    public function finalizar(int $establecimientoId, int $anio, int $userId): void
    {
        DB::transaction(function () use ($establecimientoId, $anio, $userId): void {
            DocentesDisposicionService::establecimientos()->whereKey($establecimientoId)->lockForUpdate()->firstOrFail();
            if (! self::finalizado($establecimientoId, $anio)) {
                DotacionEstablecimientoRevision::create(['establecimiento_id' => $establecimientoId, 'anio' => $anio,
                    'clave_activa' => $establecimientoId.':'.$anio, 'finalizada_at' => now(), 'finalizada_by' => $userId]);
            }
        }, 3);
    }

    public function reabrir(int $establecimientoId, int $anio, int $revisionId, string $motivo, int $userId): void
    {
        DB::transaction(function () use ($establecimientoId, $anio, $revisionId, $motivo, $userId): void {
            DocentesDisposicionService::establecimientos()->whereKey($establecimientoId)->lockForUpdate()->firstOrFail();
            $registro = DotacionEstablecimientoRevision::query()->where('establecimiento_id', $establecimientoId)->where('anio', $anio)
                ->lockForUpdate()->findOrFail($revisionId);
            if (! $registro->reabierta_at) {
                $registro->forceFill(['reabierta_at' => now(), 'reabierta_by' => $userId, 'motivo_reapertura' => $motivo, 'clave_activa' => null])->save();
            }
        }, 3);
    }
}
