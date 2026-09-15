<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ANIO_ORIGEN = 2026;
    private const ANIO_DESTINO = 2027;

    public function up(): void
    {
        if (! Schema::hasTable('planes_estudio')) {
            return;
        }

        DB::transaction(function (): void {
            $planesCopiados = [];

            DB::table('planes_estudio')
                ->where('anio', self::ANIO_ORIGEN)
                ->orderBy('id')
                ->chunkById(100, function ($planes) use (&$planesCopiados): void {
                    foreach ($planes as $planOrigen) {
                        $planDestino = DB::table('planes_estudio')
                            ->where('curso_id', $planOrigen->curso_id)
                            ->where('anio', self::ANIO_DESTINO)
                            ->where('regimen_jec', $planOrigen->regimen_jec)
                            ->first(['id']);

                        if ($planDestino) {
                            continue;
                        }

                        $planDestinoId = DB::table('planes_estudio')->insertGetId([
                            'curso_id' => $planOrigen->curso_id,
                            'anio' => self::ANIO_DESTINO,
                            'nombre_plan' => $planOrigen->nombre_plan,
                            'nivel_educativo' => $planOrigen->nivel_educativo,
                            'modalidad' => $planOrigen->modalidad,
                            'regimen_jec' => $planOrigen->regimen_jec,
                            'horas_semanales_subtotal' => $planOrigen->horas_semanales_subtotal,
                            'horas_semanales_libre_disposicion' => $planOrigen->horas_semanales_libre_disposicion,
                            'horas_semanales_total' => $planOrigen->horas_semanales_total,
                            'horas_anuales_total' => $planOrigen->horas_anuales_total,
                            'decreto_referencia' => $planOrigen->decreto_referencia,
                            'observacion' => $planOrigen->observacion,
                            'activo' => $planOrigen->activo,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $planesCopiados[(int) $planOrigen->id] = $planDestinoId;
                    }
                });

            $this->copiarAsignaturas($planesCopiados);
            $this->copiarBloques($planesCopiados);
            $this->asignarPlanesACursosSinPlan();
        });
    }

    /** @param array<int, int> $planesCopiados */
    private function copiarAsignaturas(array $planesCopiados): void
    {
        if ($planesCopiados === [] || ! Schema::hasTable('planes_estudio_asignaturas')) {
            return;
        }

        foreach ($planesCopiados as $planOrigenId => $planDestinoId) {
            foreach (DB::table('planes_estudio_asignaturas')->where('plan_estudio_id', $planOrigenId)->orderBy('id')->get() as $asignatura) {
                DB::table('planes_estudio_asignaturas')->insert([
                    'plan_estudio_id' => $planDestinoId,
                    'asignatura' => $asignatura->asignatura,
                    'horas_semanales' => $asignatura->horas_semanales,
                    'horas_anuales' => $asignatura->horas_anuales,
                    'tipo_bloque' => $asignatura->tipo_bloque,
                    'orden' => $asignatura->orden,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /** @param array<int, int> $planesCopiados */
    private function copiarBloques(array $planesCopiados): void
    {
        if ($planesCopiados === [] || ! Schema::hasTable('planes_estudio_bloques')) {
            return;
        }

        foreach ($planesCopiados as $planOrigenId => $planDestinoId) {
            foreach (DB::table('planes_estudio_bloques')->where('plan_estudio_id', $planOrigenId)->orderBy('id')->get() as $bloque) {
                DB::table('planes_estudio_bloques')->insert([
                    'plan_estudio_id' => $planDestinoId,
                    'nombre' => $bloque->nombre,
                    'tipo_bloque' => $bloque->tipo_bloque,
                    'horas_semanales' => $bloque->horas_semanales,
                    'horas_anuales' => $bloque->horas_anuales,
                    'permite_asignaturas_establecimiento' => $bloque->permite_asignaturas_establecimiento,
                    'permite_asignaturas_personalizadas' => $bloque->permite_asignaturas_personalizadas,
                    'orden' => $bloque->orden,
                    'activo' => $bloque->activo,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function asignarPlanesACursosSinPlan(): void
    {
        if (! Schema::hasTable('establecimiento_cursos')) {
            return;
        }

        $planesPorCursoYRegimen = DB::table('planes_estudio')
            ->where('anio', self::ANIO_DESTINO)
            ->where('activo', true)
            ->orderBy('id')
            ->get(['id', 'curso_id', 'regimen_jec'])
            ->mapWithKeys(fn ($plan) => [
                $this->clavePlan((int) $plan->curso_id, (string) $plan->regimen_jec) => (int) $plan->id,
            ]);

        DB::table('establecimiento_cursos')
            ->where('anio', self::ANIO_DESTINO)
            ->where('activo', true)
            ->whereNull('plan_estudio_id')
            ->orderBy('id')
            ->chunkById(200, function ($cursos) use ($planesPorCursoYRegimen): void {
                foreach ($cursos as $curso) {
                    $planId = $planesPorCursoYRegimen->get(
                        $this->clavePlan((int) $curso->curso_id, (string) $curso->regimen_jec)
                    );

                    if (! $planId) {
                        continue;
                    }

                    DB::table('establecimiento_cursos')
                        ->where('id', $curso->id)
                        ->whereNull('plan_estudio_id')
                        ->update(['plan_estudio_id' => $planId, 'updated_at' => now()]);
                }
            });
    }

    private function clavePlan(int $cursoId, string $regimen): string
    {
        $regimenNormalizado = mb_strtoupper(trim($regimen), 'UTF-8');
        if ($regimenNormalizado === 'NO APLICA' || str_contains($regimenNormalizado, 'SIN JEC')) {
            $regimenNormalizado = 'SIN JEC';
        } else {
            $regimenNormalizado = 'CON JEC';
        }

        return $cursoId.'|'.$regimenNormalizado;
    }

    public function down(): void
    {
        // No se eliminan planes ni asociaciones 2027: pueden haber sido ajustados
        // por usuarios después de su preparación inicial.
    }
};
