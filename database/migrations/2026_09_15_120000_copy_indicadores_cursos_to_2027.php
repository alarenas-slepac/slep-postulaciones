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
        DB::transaction(function (): void {
            $this->copiarPorcentajesPrioritarios();
            $this->copiarRegistrosPie();
        });
    }

    private function copiarPorcentajesPrioritarios(): void
    {
        if (! Schema::hasTable('alumnos_prioritarios_porcentajes') || ! Schema::hasTable('establecimiento_cursos')) {
            return;
        }

        DB::table('alumnos_prioritarios_porcentajes')
            ->where('anio', self::ANIO_ORIGEN)
            ->orderBy('id')
            ->chunkById(200, function ($porcentajes): void {
                foreach ($porcentajes as $porcentajeOrigen) {
                    $tieneCursosDestino = DB::table('establecimiento_cursos')
                        ->where('establecimiento_id', $porcentajeOrigen->establecimiento_id)
                        ->where('anio', self::ANIO_DESTINO)
                        ->where('activo', true)
                        ->exists();
                    if (! $tieneCursosDestino) {
                        continue;
                    }

                    $yaExiste = DB::table('alumnos_prioritarios_porcentajes')
                        ->where('establecimiento_id', $porcentajeOrigen->establecimiento_id)
                        ->where('anio', self::ANIO_DESTINO)
                        ->exists();
                    if ($yaExiste) {
                        continue;
                    }

                    DB::table('alumnos_prioritarios_porcentajes')->insert([
                        'establecimiento_id' => $porcentajeOrigen->establecimiento_id,
                        'anio' => self::ANIO_DESTINO,
                        'porcentaje' => $porcentajeOrigen->porcentaje,
                        'observacion' => $porcentajeOrigen->observacion,
                        'created_by' => $porcentajeOrigen->created_by,
                        'updated_by' => $porcentajeOrigen->updated_by,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    private function copiarRegistrosPie(): void
    {
        if (! Schema::hasTable('establecimiento_curso_pie') || ! Schema::hasTable('establecimiento_cursos')) {
            return;
        }

        DB::table('establecimiento_curso_pie as pie')
            ->join('establecimiento_cursos as curso_origen', 'curso_origen.id', '=', 'pie.establecimiento_curso_id')
            ->where('pie.anio', self::ANIO_ORIGEN)
            ->where('curso_origen.anio', self::ANIO_ORIGEN)
            ->where('curso_origen.activo', true)
            ->orderBy('pie.id')
            ->select(['pie.*', 'curso_origen.curso_id as curso_origen_id', 'curso_origen.letra as letra_origen'])
            ->chunkById(200, function ($registrosPie): void {
                foreach ($registrosPie as $pieOrigen) {
                    $cursoDestino = DB::table('establecimiento_cursos')
                        ->where('establecimiento_id', $pieOrigen->establecimiento_id)
                        ->where('curso_id', $pieOrigen->curso_origen_id)
                        ->where('anio', self::ANIO_DESTINO)
                        ->where('activo', true)
                        ->where(function ($query) use ($pieOrigen): void {
                            if ($pieOrigen->letra_origen === null) {
                                $query->whereNull('letra');
                            } else {
                                $query->where('letra', $pieOrigen->letra_origen);
                            }
                        })
                        ->first();
                    if (! $cursoDestino) {
                        continue;
                    }

                    $yaExiste = DB::table('establecimiento_curso_pie')
                        ->where('establecimiento_curso_id', $cursoDestino->id)
                        ->where('anio', self::ANIO_DESTINO)
                        ->exists();
                    if ($yaExiste) {
                        continue;
                    }

                    DB::table('establecimiento_curso_pie')->insert([
                        'establecimiento_id' => $cursoDestino->establecimiento_id,
                        'establecimiento_curso_id' => $cursoDestino->id,
                        'curso_id' => $cursoDestino->curso_id,
                        'plan_estudio_id' => $cursoDestino->plan_estudio_id,
                        'anio' => self::ANIO_DESTINO,
                        'rbd' => $cursoDestino->rbd,
                        'necesidades_transitorias' => $pieOrigen->necesidades_transitorias,
                        'necesidades_permanentes' => $pieOrigen->necesidades_permanentes,
                        'total_pie' => $pieOrigen->total_pie,
                        'observacion' => $pieOrigen->observacion,
                        'estado' => $pieOrigen->estado,
                        'regimen_calculo' => $pieOrigen->regimen_calculo,
                        'neet_calculo' => $pieOrigen->neet_calculo,
                        'neep_calculo' => $pieOrigen->neep_calculo,
                        'total_crono_minutos' => $pieOrigen->total_crono_minutos,
                        'prof_educ_dif_minutos' => $pieOrigen->prof_educ_dif_minutos,
                        'pae_minutos' => $pieOrigen->pae_minutos,
                        'calculo_observacion' => $pieOrigen->calculo_observacion,
                        'calculado_at' => $pieOrigen->calculado_at,
                        'created_by' => $pieOrigen->created_by,
                        'updated_by' => $pieOrigen->updated_by,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }, 'pie.id', 'id');
    }

    public function down(): void
    {
        // No se eliminan los registros 2027: pueden haber sido ajustados
        // después de su preparación inicial.
    }
};
