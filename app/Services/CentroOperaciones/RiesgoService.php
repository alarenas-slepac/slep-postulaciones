<?php

namespace App\Services\CentroOperaciones;

use App\Models\CentroOperacionesRiesgoEvaluacion;
use App\Models\CentroOperacionesRiesgoModelo;
use App\Models\Establecimiento;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RiesgoService
{
    public function __construct(private readonly PrioridadIncidenciaService $prioridades)
    {
    }

    public function modeloPublicado(): ?CentroOperacionesRiesgoModelo
    {
        return CentroOperacionesRiesgoModelo::query()
            ->where('estado', 'publicado')
            ->with('dimensiones.opciones')
            ->latest('publicado_en')
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<int|string, int|string|null>  $selecciones
     * @return array<string, mixed>
     */
    public function calcular(CentroOperacionesRiesgoModelo $modelo, array $selecciones): array
    {
        $modelo->loadMissing('dimensiones.opciones');
        $dimensiones = $modelo->dimensiones->where('activo', true)->sortBy('orden')->values();

        if ($dimensiones->sum('peso') !== 100) {
            throw ValidationException::withMessages([
                'modelo' => 'El modelo de riesgo no puede utilizarse porque sus pesos no suman 100%.',
            ]);
        }

        $respuestas = $dimensiones->map(function ($dimension) use ($selecciones) {
            $opcionId = (int) Arr::get($selecciones, (string) $dimension->id);
            $opcion = $dimension->opciones
                ->where('activo', true)
                ->firstWhere('id', $opcionId);

            if (! $opcion) {
                throw ValidationException::withMessages([
                    "respuestas.{$dimension->id}" => "Selecciona una alternativa válida para {$dimension->nombre}.",
                ]);
            }

            return [
                'dimension_id' => $dimension->id,
                'dimension_codigo' => $dimension->codigo,
                'dimension_nombre' => $dimension->nombre,
                'opcion_id' => $opcion->id,
                'respuesta_nombre' => $opcion->nombre,
                'score' => (int) $opcion->score,
                'peso' => (int) $dimension->peso,
            ];
        });

        $irte = (int) round($respuestas->sum(fn (array $respuesta) =>
            $respuesta['score'] * $respuesta['peso']
        ) / 5);
        $scoreMaximo = (int) $respuestas->max('score');
        $factorCritico = $scoreMaximo >= $modelo->score_alerta_roja;
        $categoria = match (true) {
            $irte >= $modelo->umbral_critico => 'critico',
            $irte >= $modelo->umbral_atencion => 'atencion_prioritaria',
            $irte >= $modelo->umbral_monitoreo => 'monitoreo',
            default => 'estable',
        };
        $alerta = match (true) {
            $irte >= $modelo->umbral_critico || $factorCritico => 'roja',
            $irte >= $modelo->umbral_atencion => 'naranja',
            $irte >= $modelo->umbral_monitoreo => 'amarilla',
            default => 'sin_alerta',
        };
        $accion = match (true) {
            $factorCritico && $irte < $modelo->umbral_critico => $modelo->accion_factor_critico,
            $irte >= $modelo->umbral_critico => $modelo->accion_critica,
            $irte >= $modelo->umbral_atencion => $modelo->accion_atencion,
            $irte >= $modelo->umbral_monitoreo => $modelo->accion_monitoreo,
            default => $modelo->accion_estable,
        };
        $motivos = $respuestas
            ->where('score', $scoreMaximo)
            ->pluck('dimension_nombre')
            ->values()
            ->all();

        return compact('irte', 'categoria', 'alerta', 'accion', 'motivos', 'respuestas');
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function guardar(
        Establecimiento $establecimiento,
        User $usuario,
        CentroOperacionesRiesgoModelo $modelo,
        array $datos
    ): CentroOperacionesRiesgoEvaluacion {
        if ($modelo->estado !== 'publicado') {
            throw ValidationException::withMessages([
                'modelo_id' => 'Solo se puede evaluar con el modelo de riesgo publicado.',
            ]);
        }

        $estado = Arr::get($datos, 'estado') === 'publicado' ? 'publicado' : 'borrador';
        $fecha = CarbonImmutable::parse(
            (string) Arr::get($datos, 'fecha_evaluacion'),
            config('centro_operaciones.timezone')
        )->startOfDay();
        $selecciones = (array) Arr::get($datos, 'respuestas', []);
        $resultado = $estado === 'publicado' ? $this->calcular($modelo, $selecciones) : null;

        $evaluacion = DB::transaction(function () use (
            $establecimiento,
            $usuario,
            $modelo,
            $datos,
            $estado,
            $fecha,
            $selecciones,
            $resultado
        ) {
            $evaluacion = CentroOperacionesRiesgoEvaluacion::query()->create([
                'establecimiento_id' => $establecimiento->id,
                'modelo_id' => $modelo->id,
                'evaluado_por_id' => $usuario->id,
                'fecha_evaluacion' => $fecha->toDateString(),
                'vigente_hasta' => $estado === 'publicado'
                    ? $fecha->addDays($modelo->vigencia_dias)->toDateString()
                    : null,
                'estado' => $estado,
                'irte' => $resultado['irte'] ?? null,
                'categoria' => $resultado['categoria'] ?? null,
                'alerta' => $resultado['alerta'] ?? null,
                'accion_sugerida' => $resultado['accion'] ?? null,
                'motivos_principales' => $resultado['motivos'] ?? null,
                'observaciones' => Arr::get($datos, 'observaciones'),
                'snapshot' => $resultado ? $this->snapshot($modelo, $resultado) : null,
                'publicado_en' => $estado === 'publicado' ? now() : null,
            ]);

            $respuestas = $resultado['respuestas'] ?? $this->respuestasBorrador($modelo, $selecciones);
            foreach ($respuestas as $respuesta) {
                $evaluacion->respuestas()->create([
                    'dimension_id' => $respuesta['dimension_id'],
                    'opcion_id' => $respuesta['opcion_id'],
                    'dimension_nombre' => $respuesta['dimension_nombre'],
                    'respuesta_nombre' => $respuesta['respuesta_nombre'],
                    'score' => $respuesta['score'],
                    'peso' => $respuesta['peso'],
                    'observacion' => Arr::get(
                        (array) Arr::get($datos, 'observaciones_dimension', []),
                        (string) $respuesta['dimension_id']
                    ),
                ]);
            }

            return $evaluacion->fresh(['modelo', 'respuestas', 'evaluadoPor']);
        });

        if ($estado === 'publicado') {
            $this->prioridades->recalcularEstablecimiento((int) $establecimiento->id);
        }

        return $evaluacion;
    }

    /** @return array<int, array<string, mixed>> */
    private function respuestasBorrador(CentroOperacionesRiesgoModelo $modelo, array $selecciones): array
    {
        $modelo->loadMissing('dimensiones.opciones');

        return $modelo->dimensiones->map(function ($dimension) use ($selecciones) {
            $opcionId = (int) Arr::get($selecciones, (string) $dimension->id);
            $opcion = $dimension->opciones->firstWhere('id', $opcionId);

            return $opcion ? [
                'dimension_id' => $dimension->id,
                'dimension_nombre' => $dimension->nombre,
                'opcion_id' => $opcion->id,
                'respuesta_nombre' => $opcion->nombre,
                'score' => (int) $opcion->score,
                'peso' => (int) $dimension->peso,
            ] : null;
        })->filter()->values()->all();
    }

    /** @param array<string, mixed> $resultado */
    private function snapshot(CentroOperacionesRiesgoModelo $modelo, array $resultado): array
    {
        return [
            'modelo' => [
                'id' => $modelo->id,
                'version' => $modelo->version,
                'umbrales' => [
                    'monitoreo' => $modelo->umbral_monitoreo,
                    'atencion' => $modelo->umbral_atencion,
                    'critico' => $modelo->umbral_critico,
                    'score_alerta_roja' => $modelo->score_alerta_roja,
                ],
            ],
            'formula' => 'round(sum(score*peso)/5)',
            'resultado' => Arr::only($resultado, ['irte', 'categoria', 'alerta', 'accion', 'motivos']),
            'respuestas' => collect($resultado['respuestas'])->values()->all(),
        ];
    }
}
