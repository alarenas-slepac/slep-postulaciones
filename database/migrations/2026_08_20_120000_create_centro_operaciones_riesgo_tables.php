<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centro_operaciones_riesgo_modelos', function (Blueprint $table) {
            $table->id();
            $table->string('version', 20)->unique();
            $table->string('nombre', 160);
            $table->string('estado', 20)->default('borrador')->index();
            $table->unsignedTinyInteger('umbral_monitoreo')->default(40);
            $table->unsignedTinyInteger('umbral_atencion')->default(60);
            $table->unsignedTinyInteger('umbral_critico')->default(80);
            $table->unsignedTinyInteger('score_alerta_roja')->default(5);
            $table->unsignedSmallInteger('vigencia_dias')->default(90);
            $table->string('accion_estable', 255);
            $table->string('accion_monitoreo', 255);
            $table->string('accion_atencion', 255);
            $table->string('accion_critica', 255);
            $table->string('accion_factor_critico', 255);
            $table->foreignId('creado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('publicado_en')->nullable();
            $table->timestamps();
        });

        Schema::create('centro_operaciones_riesgo_dimensiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modelo_id')->constrained('centro_operaciones_riesgo_modelos')->cascadeOnDelete();
            $table->string('codigo', 80);
            $table->string('nombre', 160);
            $table->text('pregunta');
            $table->unsignedTinyInteger('peso');
            $table->unsignedTinyInteger('orden');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['modelo_id', 'codigo'], 'co_riesgo_dimension_modelo_codigo_uq');
            $table->unique(['modelo_id', 'orden'], 'co_riesgo_dimension_modelo_orden_uq');
        });

        Schema::create('centro_operaciones_riesgo_opciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dimension_id')->constrained('centro_operaciones_riesgo_dimensiones')->cascadeOnDelete();
            $table->string('nombre', 190);
            $table->unsignedTinyInteger('score');
            $table->unsignedTinyInteger('orden');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['dimension_id', 'orden'], 'co_riesgo_opcion_dimension_orden_uq');
        });

        Schema::create('centro_operaciones_riesgo_evaluaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_id')
                ->constrained('establecimientos', 'id', 'co_riesgo_eval_establecimiento_fk')
                ->restrictOnDelete();
            $table->foreignId('modelo_id')->constrained('centro_operaciones_riesgo_modelos')->restrictOnDelete();
            $table->foreignId('evaluado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha_evaluacion');
            $table->date('vigente_hasta')->nullable();
            $table->string('estado', 20)->default('borrador');
            $table->unsignedTinyInteger('irte')->nullable();
            $table->string('categoria', 32)->nullable();
            $table->string('alerta', 24)->nullable();
            $table->string('accion_sugerida', 255)->nullable();
            $table->json('motivos_principales')->nullable();
            $table->text('observaciones')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamp('publicado_en')->nullable();
            $table->timestamps();

            $table->index(
                ['establecimiento_id', 'estado', 'fecha_evaluacion'],
                'co_riesgo_eval_est_estado_fecha_idx'
            );
        });

        Schema::create('centro_operaciones_riesgo_respuestas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluacion_id')->constrained('centro_operaciones_riesgo_evaluaciones')->cascadeOnDelete();
            $table->foreignId('dimension_id')->constrained('centro_operaciones_riesgo_dimensiones')->restrictOnDelete();
            $table->foreignId('opcion_id')->nullable()->constrained('centro_operaciones_riesgo_opciones')->nullOnDelete();
            $table->string('dimension_nombre', 160);
            $table->string('respuesta_nombre', 190);
            $table->unsignedTinyInteger('score');
            $table->unsignedTinyInteger('peso');
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->unique(['evaluacion_id', 'dimension_id'], 'co_riesgo_respuesta_eval_dimension_uq');
        });

        $ahora = now();
        $modeloId = DB::table('centro_operaciones_riesgo_modelos')->insertGetId([
            'version' => '1.0',
            'nombre' => 'IRTE SLEP Andalién Costa 2026',
            'estado' => 'publicado',
            'umbral_monitoreo' => 40,
            'umbral_atencion' => 60,
            'umbral_critico' => 80,
            'score_alerta_roja' => 5,
            'vigencia_dias' => 90,
            'accion_estable' => 'Seguimiento regular',
            'accion_monitoreo' => 'Monitoreo mensual preventivo',
            'accion_atencion' => 'Plan de apoyo y seguimiento quincenal',
            'accion_critica' => 'Intervención prioritaria en 30 días',
            'accion_factor_critico' => 'Revisión inmediata del factor crítico y definición de un plan de intervención',
            'publicado_en' => $ahora,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);

        foreach ($this->dimensiones() as $indice => $dimension) {
            $dimensionId = DB::table('centro_operaciones_riesgo_dimensiones')->insertGetId([
                'modelo_id' => $modeloId,
                'codigo' => $dimension['codigo'],
                'nombre' => $dimension['nombre'],
                'pregunta' => $dimension['pregunta'],
                'peso' => $dimension['peso'],
                'orden' => $indice + 1,
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);

            DB::table('centro_operaciones_riesgo_opciones')->insert(
                collect($dimension['opciones'])->map(fn (string $nombre, int $indiceOpcion) => [
                    'dimension_id' => $dimensionId,
                    'nombre' => $nombre,
                    'score' => $indiceOpcion + 1,
                    'orden' => $indiceOpcion + 1,
                    'activo' => true,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ])->all()
            );
        }

        $this->poblarEvaluacionInicial($modeloId, $ahora);
    }

    public function down(): void
    {
        Schema::dropIfExists('centro_operaciones_riesgo_respuestas');
        Schema::dropIfExists('centro_operaciones_riesgo_evaluaciones');
        Schema::dropIfExists('centro_operaciones_riesgo_opciones');
        Schema::dropIfExists('centro_operaciones_riesgo_dimensiones');
        Schema::dropIfExists('centro_operaciones_riesgo_modelos');
    }

    /** @return array<int, array<string, mixed>> */
    private function dimensiones(): array
    {
        return [
            [
                'codigo' => 'conflictividad_activa',
                'nombre' => 'Conflictividad activa',
                'pregunta' => '¿Hay conflictos vigentes que obligan al SLEP a poner atención?',
                'peso' => 13,
                'opciones' => ['Sin conflictos relevantes', 'Conflictos aislados', 'Conflictos recurrentes', 'Conflicto significativo', 'Crisis activa'],
            ],
            [
                'codigo' => 'reclamos_apoderados',
                'nombre' => 'Reclamos de apoderados',
                'pregunta' => '¿Con qué frecuencia el establecimiento recibe reclamos de apoderados?',
                'peso' => 10,
                'opciones' => ['Muy escasos', 'Ocasionales', 'Frecuentes', 'Muy frecuentes', 'Permanentes'],
            ],
            [
                'codigo' => 'inestabilidad_directiva',
                'nombre' => 'Inestabilidad directiva',
                'pregunta' => '¿Qué tan estable está la conducción del establecimiento?',
                'peso' => 11,
                'opciones' => ['Dirección consolidada', 'Ajustes menores', 'Cambios recientes', 'Alta rotación', 'Situación crítica'],
            ],
            [
                'codigo' => 'actores_movilizados',
                'nombre' => 'Actores movilizados',
                'pregunta' => '¿Hay actores asociados al establecimiento capaces de presionar o movilizarse?',
                'peso' => 8,
                'opciones' => ['No existen', 'Poco activos', 'Activos', 'Muy activos', 'Altamente movilizados'],
            ],
            [
                'codigo' => 'exposicion_reputacional',
                'nombre' => 'Exposición reputacional',
                'pregunta' => '¿El establecimiento está expuesto públicamente o puede dañar la reputación institucional?',
                'peso' => 8,
                'opciones' => ['Sin exposición', 'Exposición baja', 'Exposición moderada', 'Exposición alta', 'Exposición crítica'],
            ],
            [
                'codigo' => 'convivencia_seguridad',
                'nombre' => 'Convivencia y seguridad',
                'pregunta' => '¿La convivencia o seguridad afecta el funcionamiento cotidiano?',
                'peso' => 10,
                'opciones' => ['Sin dificultades relevantes', 'Situaciones aisladas', 'Situaciones recurrentes', 'Situación compleja', 'Situación crítica'],
            ],
            [
                'codigo' => 'cambios_sensibles_proximos',
                'nombre' => 'Cambios sensibles próximos',
                'pregunta' => '¿Se vienen cambios que pueden tensionar al establecimiento?',
                'peso' => 8,
                'opciones' => ['No existen cambios relevantes', 'Un cambio menor', 'Algunos cambios relevantes', 'Varios cambios sensibles', 'Cambios altamente sensibles'],
            ],
            [
                'codigo' => 'relevancia_estrategica_territorial',
                'nombre' => 'Relevancia estratégica territorial',
                'pregunta' => '¿Qué tan importante es este establecimiento para el territorio?',
                'peso' => 9,
                'opciones' => ['Baja', 'Media-baja', 'Media', 'Alta', 'Muy alta'],
            ],
            [
                'codigo' => 'demanda_apoyo_slep',
                'nombre' => 'Demanda de apoyo al SLEP',
                'pregunta' => '¿Cuánto apoyo requiere del SLEP para funcionar adecuadamente?',
                'peso' => 12,
                'opciones' => ['Autónoma', 'Requiere apoyo ocasional', 'Requiere apoyo frecuente', 'Requiere acompañamiento permanente', 'Alta dependencia del SLEP'],
            ],
            [
                'codigo' => 'impacto_sistemico_crisis',
                'nombre' => 'Impacto sistémico de una crisis',
                'pregunta' => 'Si hay una crisis aquí, ¿qué tanto impacta al sistema local?',
                'peso' => 11,
                'opciones' => ['Impacto menor', 'Impacto acotado', 'Impacto relevante', 'Impacto alto', 'Impacto crítico'],
            ],
        ];
    }

    private function poblarEvaluacionInicial(int $modeloId, $ahora): void
    {
        $establecimientoId = DB::table('establecimientos')
            ->whereRaw('UPPER(nombre_establecimiento) = ?', ['ESCUELA DIFERENCIAL PIERRE MENDES FRANCE'])
            ->value('id');
        if (! $establecimientoId) {
            return;
        }

        $scores = [
            'conflictividad_activa' => 1,
            'reclamos_apoderados' => 2,
            'inestabilidad_directiva' => 1,
            'actores_movilizados' => 2,
            'exposicion_reputacional' => 4,
            'convivencia_seguridad' => 2,
            'cambios_sensibles_proximos' => 1,
            'relevancia_estrategica_territorial' => 2,
            'demanda_apoyo_slep' => 2,
            'impacto_sistemico_crisis' => 1,
        ];
        $dimensiones = DB::table('centro_operaciones_riesgo_dimensiones')
            ->where('modelo_id', $modeloId)
            ->get()
            ->keyBy('codigo');
        $snapshotRespuestas = [];
        foreach ($scores as $codigo => $score) {
            $dimension = $dimensiones->get($codigo);
            $opcion = DB::table('centro_operaciones_riesgo_opciones')
                ->where('dimension_id', $dimension->id)
                ->where('score', $score)
                ->first();
            $snapshotRespuestas[] = [
                'dimension_id' => $dimension->id,
                'dimension_codigo' => $codigo,
                'dimension_nombre' => $dimension->nombre,
                'opcion_id' => $opcion->id,
                'respuesta_nombre' => $opcion->nombre,
                'score' => $score,
                'peso' => $dimension->peso,
            ];
        }

        $evaluacionId = DB::table('centro_operaciones_riesgo_evaluaciones')->insertGetId([
            'establecimiento_id' => $establecimientoId,
            'modelo_id' => $modeloId,
            'fecha_evaluacion' => '2026-08-20',
            'vigente_hasta' => '2026-11-18',
            'estado' => 'publicado',
            'irte' => 35,
            'categoria' => 'estable',
            'alerta' => 'sin_alerta',
            'accion_sugerida' => 'Seguimiento regular',
            'motivos_principales' => json_encode(['Exposición reputacional'], JSON_UNESCAPED_UNICODE),
            'observaciones' => 'Evaluación inicial extraída del análisis institucional 2026.',
            'snapshot' => json_encode([
                'modelo' => ['id' => $modeloId, 'version' => '1.0'],
                'formula' => 'round(sum(score*peso)/5)',
                'resultado' => ['irte' => 35, 'categoria' => 'estable', 'alerta' => 'sin_alerta'],
                'respuestas' => $snapshotRespuestas,
            ], JSON_UNESCAPED_UNICODE),
            'publicado_en' => $ahora,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);

        DB::table('centro_operaciones_riesgo_respuestas')->insert(
            collect($snapshotRespuestas)->map(fn (array $respuesta) => [
                'evaluacion_id' => $evaluacionId,
                'dimension_id' => $respuesta['dimension_id'],
                'opcion_id' => $respuesta['opcion_id'],
                'dimension_nombre' => $respuesta['dimension_nombre'],
                'respuesta_nombre' => $respuesta['respuesta_nombre'],
                'score' => $respuesta['score'],
                'peso' => $respuesta['peso'],
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ])->all()
        );
    }
};
