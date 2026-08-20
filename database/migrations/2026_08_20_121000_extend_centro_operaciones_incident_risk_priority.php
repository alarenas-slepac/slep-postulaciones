<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('centro_operaciones_incidente_configuraciones', function (Blueprint $table) {
            $table->string('familia', 48)->default('otra')->after('severidad');
            $table->string('riesgo_dimension_codigo', 80)->nullable()->after('familia');
            $table->unsignedTinyInteger('impacto_base')->default(3)->after('riesgo_dimension_codigo');
            $table->unsignedTinyInteger('urgencia_base')->default(3)->after('impacto_base');
            $table->string('prioridad_minima', 4)->default('P3')->after('urgencia_base');
            $table->unsignedSmallInteger('sla_horas')->nullable()->after('plazo_dias');
            $table->boolean('forzar_p1')->default(false)->after('sla_horas');
        });

        Schema::table('centro_operaciones_incidencias', function (Blueprint $table) {
            $table->string('familia', 48)->nullable()->after('severidad');
            $table->unsignedTinyInteger('impacto')->nullable()->after('familia');
            $table->unsignedTinyInteger('urgencia')->nullable()->after('impacto');
            $table->decimal('prioridad_puntaje', 5, 2)->nullable()->after('urgencia');
            $table->string('prioridad_nivel', 4)->nullable()->after('prioridad_puntaje');
            $table->text('prioridad_motivo')->nullable()->after('prioridad_nivel');
            $table->unsignedTinyInteger('irte_snapshot')->nullable()->after('prioridad_motivo');
            $table->string('riesgo_categoria_snapshot', 32)->nullable()->after('irte_snapshot');
            $table->unsignedInteger('matricula_snapshot')->nullable()->after('riesgo_categoria_snapshot');
            $table->timestamp('prioridad_calculada_en')->nullable()->after('matricula_snapshot');
            $table->index(['estado', 'prioridad_nivel'], 'co_incidencias_estado_prioridad_idx');
        });

        foreach ($this->clasificaciones() as $tipo => $clasificacion) {
            DB::table('centro_operaciones_incidente_configuraciones')
                ->where('tipo', $tipo)
                ->update($clasificacion);
        }

        $this->poblarIncidenciasExistentes();
    }

    public function down(): void
    {
        Schema::table('centro_operaciones_incidencias', function (Blueprint $table) {
            $table->dropIndex('co_incidencias_estado_prioridad_idx');
            $table->dropColumn([
                'familia',
                'impacto',
                'urgencia',
                'prioridad_puntaje',
                'prioridad_nivel',
                'prioridad_motivo',
                'irte_snapshot',
                'riesgo_categoria_snapshot',
                'matricula_snapshot',
                'prioridad_calculada_en',
            ]);
        });

        Schema::table('centro_operaciones_incidente_configuraciones', function (Blueprint $table) {
            $table->dropColumn([
                'familia',
                'riesgo_dimension_codigo',
                'impacto_base',
                'urgencia_base',
                'prioridad_minima',
                'sla_horas',
                'forzar_p1',
            ]);
        });
    }

    /** @return array<string, array<string, mixed>> */
    private function clasificaciones(): array
    {
        return [
            'corte_agua' => $this->datos('continuidad_operacional', 'demanda_apoyo_slep', 5, 5, 'P1', 4, true),
            'corte_energia' => $this->datos('continuidad_operacional', 'demanda_apoyo_slep', 5, 5, 'P1', 4, true),
            'corte_internet' => $this->datos('continuidad_operacional', 'demanda_apoyo_slep', 3, 3, 'P3', 24),
            'robo' => $this->datos('seguridad', 'convivencia_seguridad', 4, 4, 'P2', 12),
            'vandalismo' => $this->datos('seguridad', 'convivencia_seguridad', 4, 3, 'P2', 24),
            'filtraciones' => $this->datos('infraestructura', 'impacto_sistemico_crisis', 3, 3, 'P3', 24),
            'dano_estructural' => $this->datos('infraestructura', 'impacto_sistemico_crisis', 5, 5, 'P1', 2, true),
            'emergencia_sanitaria' => $this->datos('sanitaria', 'convivencia_seguridad', 5, 5, 'P1', 2, true),
            'violencia_escolar' => $this->datos('convivencia_seguridad', 'convivencia_seguridad', 5, 5, 'P1', 2, true),
            'accidente_escolar' => $this->datos('seguridad', 'convivencia_seguridad', 5, 5, 'P1', 2, true),
            'problemas_calefaccion' => $this->datos('continuidad_operacional', 'demanda_apoyo_slep', 3, 3, 'P3', 24),
            'toma_establecimiento' => $this->datos('gobernanza_conflictividad', 'conflictividad_activa', 5, 5, 'P1', 2, true),
            'amago_incendio' => $this->datos('seguridad', 'impacto_sistemico_crisis', 5, 5, 'P1', 1, true),
            'sismo' => $this->datos('seguridad', 'impacto_sistemico_crisis', 4, 4, 'P2', 4),
            'evacuacion' => $this->datos('seguridad', 'impacto_sistemico_crisis', 5, 5, 'P1', 1, true),
            'control_plagas_vencido' => $this->datos('sanitaria', 'convivencia_seguridad', 4, 3, 'P2', 24),
            'extintor_no_operativo' => $this->datos('seguridad', 'impacto_sistemico_crisis', 5, 4, 'P1', 4, true),
            'otro' => $this->datos('otra', '', 3, 3, 'P3', 96),
        ];
    }

    /** @return array<string, mixed> */
    private function datos(
        string $familia,
        string $dimension,
        int $impacto,
        int $urgencia,
        string $prioridad,
        int $slaHoras,
        bool $forzarP1 = false
    ): array {
        return [
            'familia' => $familia,
            'riesgo_dimension_codigo' => $dimension,
            'impacto_base' => $impacto,
            'urgencia_base' => $urgencia,
            'prioridad_minima' => $prioridad,
            'sla_horas' => $slaHoras,
            'forzar_p1' => $forzarP1,
        ];
    }

    private function poblarIncidenciasExistentes(): void
    {
        $configuraciones = DB::table('centro_operaciones_incidente_configuraciones')
            ->get()
            ->keyBy('tipo');
        $matriculas = Schema::hasColumn('establecimientos', 'matricula_total')
            ? DB::table('establecimientos')->where('matricula_total', '>', 0)->pluck('matricula_total')->map(fn ($valor) => (int) $valor)->sort()->values()
            : collect();

        DB::table('centro_operaciones_incidencias')->orderBy('id')->chunkById(200, function ($incidencias) use ($configuraciones, $matriculas) {
            foreach ($incidencias as $incidencia) {
                $configuracion = $configuraciones->get($incidencia->tipo);
                $base = $this->clasificaciones()[$incidencia->tipo] ?? $this->datos(
                    'otra',
                    '',
                    $incidencia->severidad === 'critico' ? 5 : 3,
                    $incidencia->severidad === 'critico' ? 5 : 3,
                    $incidencia->severidad === 'critico' ? 'P2' : 'P3',
                    96
                );
                $impacto = (int) ($configuracion->impacto_base ?? $base['impacto_base']);
                $urgencia = (int) ($configuracion->urgencia_base ?? $base['urgencia_base']);
                $matricula = Schema::hasColumn('establecimientos', 'matricula_total')
                    ? (int) DB::table('establecimientos')->where('id', $incidencia->establecimiento_id)->value('matricula_total')
                    : 0;
                $exposicion = $matricula > 0 && $matriculas->isNotEmpty()
                    ? $matriculas->filter(fn (int $valor) => $valor <= $matricula)->count() / $matriculas->count() * 100
                    : 0;
                $puntaje = round(($impacto / 5 * 45) + 12.5 + ($urgencia / 5 * 15) + ($exposicion / 100 * 10), 2);
                $nivel = match (true) {
                    $puntaje >= 80 => 'P1',
                    $puntaje >= 60 => 'P2',
                    $puntaje >= 40 => 'P3',
                    default => 'P4',
                };
                $minima = (string) ($configuracion->prioridad_minima ?? $base['prioridad_minima']);
                $orden = ['P1' => 1, 'P2' => 2, 'P3' => 3, 'P4' => 4];
                if (($orden[$minima] ?? 4) < ($orden[$nivel] ?? 4)) {
                    $nivel = $minima;
                }
                $forzar = (bool) ($configuracion->forzar_p1 ?? $base['forzar_p1']);
                if ($forzar && $incidencia->severidad === 'critico') {
                    $nivel = 'P1';
                    $puntaje = max(80, $puntaje);
                }

                DB::table('centro_operaciones_incidencias')->where('id', $incidencia->id)->update([
                    'familia' => $configuracion->familia ?? $base['familia'],
                    'impacto' => $impacto,
                    'urgencia' => $urgencia,
                    'prioridad_puntaje' => $puntaje,
                    'prioridad_nivel' => $nivel,
                    'prioridad_motivo' => "{$nivel}: cálculo inicial sin evaluación IRTE publicada; impacto {$impacto}/5, urgencia {$urgencia}/5 y matrícula {$matricula}.",
                    'matricula_snapshot' => $matricula,
                    'prioridad_calculada_en' => now(),
                ]);
            }
        });
    }
};
