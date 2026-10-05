<?php

namespace App\Support;

use App\Models\EstablecimientoCurso;
use App\Models\PlanEstudio;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DotacionPlanEstudioResolver
{
    /**
     * Obtiene el plan asociado o el plan referencial activo para el mismo
     * curso, año y régimen. El respaldo no modifica datos históricos.
     */
    public static function resolve(EstablecimientoCurso $curso): ?PlanEstudio
    {
        $planId = $curso->relationLoaded('planEstudio')
            ? $curso->getRelation('planEstudio')?->id
            : $curso->plan_estudio_id;
        // Un identificador histórico sin plan puede usar un respaldo diferente
        // según el curso, año y régimen; esos datos también forman parte de la clave.
        $clave = 'plan:'.($planId ?? 0).':'.$curso->curso_id.':'.$curso->anio.':'.self::normalizeRegimen($curso->regimen_jec);

        return DotacionLecturaCache::recordar($clave, fn () => self::consultar($curso));
    }

    private static function consultar(EstablecimientoCurso $curso): ?PlanEstudio
    {
        if (! DotacionLecturaCache::recordar('tabla:planes_estudio', fn () => Schema::hasTable('planes_estudio'))) {
            return null;
        }

        $planAsociado = null;
        if ($curso->relationLoaded('planEstudio')) {
            $planAsociado = $curso->getRelation('planEstudio');
        } elseif ($curso->plan_estudio_id) {
            $planAsociado = $curso->planEstudio()->first();
        }

        if ($planAsociado) {
            return $planAsociado->loadMissing(['asignaturas', 'bloques']);
        }

        if (! $curso->curso_id || ! $curso->anio) {
            return null;
        }

        return PlanEstudio::query()
            ->with(['asignaturas', 'bloques'])
            ->where('curso_id', $curso->curso_id)
            ->where('anio', $curso->anio)
            ->where('regimen_jec', self::normalizeRegimen($curso->regimen_jec))
            ->where('activo', true)
            ->first();
    }

    public static function isReferential(EstablecimientoCurso $curso, ?PlanEstudio $plan): bool
    {
        return $plan !== null && (int) ($curso->plan_estudio_id ?? 0) !== (int) $plan->id;
    }

    private static function normalizeRegimen(?string $regimen): string
    {
        $normalizado = Str::of((string) $regimen)
            ->ascii()
            ->upper()
            ->squish()
            ->toString();

        if ($normalizado === 'NO APLICA' || str_contains($normalizado, 'SIN JEC')) {
            return 'Sin JEC';
        }

        return 'Con JEC';
    }
}
