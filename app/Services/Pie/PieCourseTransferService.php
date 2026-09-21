<?php

namespace App\Services\Pie;

use App\Models\EstablecimientoCurso;
use App\Models\EstablecimientoCursoPie;
use App\Support\PieHorasCalculator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PieCourseTransferService
{
    public function transferForEstablishments(iterable $establecimientoIds, int $anioOrigen, int $anioDestino, ?int $userId): array
    {
        $resultado = [
            'anio_origen' => $anioOrigen,
            'anio_destino' => $anioDestino,
            'establecimientos_procesados' => 0,
            'establecimientos_con_error' => 0,
            'niveles_procesados' => 0,
            'registros_creados' => 0,
            'registros_actualizados' => 0,
            'registros_omitidos_por_igualdad' => 0,
            'niveles_omitidos' => 0,
            'detalle' => [],
        ];

        $ids = collect($establecimientoIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->sort()
            ->values();

        foreach ($ids as $establecimientoId) {
            try {
                $traspaso = $this->transfer($establecimientoId, $anioOrigen, $anioDestino, $userId);

                $resultado['establecimientos_procesados']++;
                $resultado['niveles_procesados'] += $traspaso['niveles_procesados'];
                $resultado['registros_creados'] += $traspaso['registros_creados'];
                $resultado['registros_actualizados'] += $traspaso['registros_actualizados'];
                $resultado['registros_omitidos_por_igualdad'] += $traspaso['registros_omitidos_por_igualdad'];
                $resultado['niveles_omitidos'] += $traspaso['niveles_omitidos'];

                foreach ($traspaso['detalle'] as $detalle) {
                    $resultado['detalle'][] = array_merge(['establecimiento_id' => $establecimientoId], $detalle);
                }
            } catch (\Throwable $exception) {
                report($exception);

                $resultado['establecimientos_con_error']++;
                $resultado['detalle'][] = [
                    'establecimiento_id' => $establecimientoId,
                    'nivel' => '—',
                    'estado' => 'error',
                    'detalle' => 'No fue posible procesar este establecimiento.',
                ];
            }
        }

        return $resultado;
    }

    public function transfer(int $establecimientoId, int $anioOrigen, int $anioDestino, ?int $userId): array
    {
        return DB::transaction(function () use ($establecimientoId, $anioOrigen, $anioDestino, $userId): array {
            $origenes = EstablecimientoCursoPie::query()
                ->with(['establecimientoCurso.curso', 'establecimientoCurso.planEstudio'])
                ->where('establecimiento_id', $establecimientoId)
                ->where('anio', $anioOrigen)
                ->whereHas('establecimientoCurso', fn ($query) => $query
                    ->where('anio', $anioOrigen)
                    ->where('activo', true)
                    ->whereNotNull('curso_id'))
                ->get();

            $destinosPorNivel = EstablecimientoCurso::query()
                ->with(['curso', 'planEstudio'])
                ->where('establecimiento_id', $establecimientoId)
                ->where('anio', $anioDestino)
                ->where('activo', true)
                ->whereNotNull('curso_id')
                ->orderBy('letra')
                ->orderBy('id')
                ->get()
                ->groupBy('curso_id');

            $origenesPorNivel = $origenes
                ->filter(fn (EstablecimientoCursoPie $pie) => $pie->establecimientoCurso !== null)
                ->groupBy(fn (EstablecimientoCursoPie $pie) => $pie->establecimientoCurso->curso_id);

            $resultado = [
                'anio_origen' => $anioOrigen,
                'anio_destino' => $anioDestino,
                'niveles_procesados' => 0,
                'registros_creados' => 0,
                'registros_actualizados' => 0,
                'registros_omitidos_por_igualdad' => 0,
                'niveles_omitidos' => 0,
                'detalle' => [],
            ];

            foreach ($origenesPorNivel as $cursoId => $registrosOrigen) {
                $nivel = $registrosOrigen->first()->establecimientoCurso->curso?->nombre ?? "Curso {$cursoId}";
                $destinos = $destinosPorNivel->get($cursoId, collect());
                $neetTotal = (int) $registrosOrigen->sum('necesidades_transitorias');
                $neepTotal = (int) $registrosOrigen->sum('necesidades_permanentes');

                if ($destinos->isEmpty()) {
                    $this->omit($resultado, $nivel, 'No existen secciones activas para este nivel en '.$anioDestino.'.');
                    continue;
                }

                if ($neetTotal + $neepTotal === 0) {
                    $this->omit($resultado, $nivel, 'No hay estudiantes NEET ni NEEP para traspasar.');
                    continue;
                }

                [$asignaciones, $estrategia] = $this->asignaciones($registrosOrigen, $destinos, $neetTotal, $neepTotal);

                if ($asignaciones->contains(fn (array $asignacion) => ($asignacion['neet'] + $asignacion['neep']) > (int) $asignacion['curso']->matricula)) {
                    $this->omit($resultado, $nivel, 'La asignación supera la matrícula de una sección destino; no se crearon registros.');
                    continue;
                }

                $existentesPorCurso = EstablecimientoCursoPie::query()
                    ->where('anio', $anioDestino)
                    ->whereIn('establecimiento_curso_id', $destinos->pluck('id'))
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('establecimiento_curso_id');
                $creados = 0;
                $actualizados = 0;
                $omitidosPorIgualdad = 0;

                foreach ($asignaciones as $asignacion) {
                    /** @var EstablecimientoCurso $cursoDestino */
                    $cursoDestino = $asignacion['curso'];
                    $neet = $asignacion['neet'];
                    $neep = $asignacion['neep'];
                    $calculo = PieHorasCalculator::calculate($cursoDestino, $neet, $neep);
                    $mensajeTraspaso = "Traspaso automático {$anioOrigen}→{$anioDestino} por nivel ({$estrategia}).";
                    /** @var EstablecimientoCursoPie|null $existente */
                    $existente = $existentesPorCurso->get($cursoDestino->id);

                    if ($existente) {
                        if ((int) $existente->necesidades_transitorias === $neet
                            && (int) $existente->necesidades_permanentes === $neep) {
                            $omitidosPorIgualdad++;
                            continue;
                        }

                        $existente->update(array_merge([
                            'necesidades_transitorias' => $neet,
                            'necesidades_permanentes' => $neep,
                            'total_pie' => $neet + $neep,
                            'observacion' => $this->observacionTraspaso($existente->observacion, $mensajeTraspaso),
                            'updated_by' => $userId,
                        ], $calculo));
                        $actualizados++;
                        continue;
                    }

                    EstablecimientoCursoPie::create(array_merge([
                        'establecimiento_id' => $cursoDestino->establecimiento_id,
                        'establecimiento_curso_id' => $cursoDestino->id,
                        'curso_id' => $cursoDestino->curso_id,
                        'plan_estudio_id' => $cursoDestino->plan_estudio_id,
                        'anio' => $anioDestino,
                        'rbd' => $cursoDestino->rbd,
                        'necesidades_transitorias' => $neet,
                        'necesidades_permanentes' => $neep,
                        'total_pie' => $neet + $neep,
                        'observacion' => $mensajeTraspaso,
                        'estado' => 'borrador',
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ], $calculo));
                    $creados++;
                }

                $resultado['registros_omitidos_por_igualdad'] += $omitidosPorIgualdad;
                if ($creados + $actualizados === 0) {
                    $this->omit($resultado, $nivel, 'Los valores NEET y NEEP de destino ya coinciden; no se realizaron cambios.');
                    continue;
                }

                $resultado['niveles_procesados']++;
                $resultado['registros_creados'] += $creados;
                $resultado['registros_actualizados'] += $actualizados;
                $resultado['detalle'][] = [
                    'nivel' => $nivel,
                    'estado' => 'traspasado',
                    'detalle' => $estrategia,
                    'neet' => $neetTotal,
                    'neep' => $neepTotal,
                    'secciones_origen' => $registrosOrigen->count(),
                    'secciones_destino' => $destinos->count(),
                    'registros_creados' => $creados,
                    'registros_actualizados' => $actualizados,
                    'registros_omitidos_por_igualdad' => $omitidosPorIgualdad,
                ];
            }

            return $resultado;
        });
    }

    private function observacionTraspaso(?string $observacionActual, string $mensajeTraspaso): string
    {
        $observacionActual = trim((string) $observacionActual);

        return $observacionActual === ''
            ? $mensajeTraspaso
            : $observacionActual."\n".$mensajeTraspaso;
    }

    private function asignaciones(Collection $origenes, Collection $destinos, int $neetTotal, int $neepTotal): array
    {
        $origenesPorSeccion = $origenes->keyBy(fn (EstablecimientoCursoPie $pie) => $this->claveSeccion($pie->establecimientoCurso));
        $mismaEstructura = $origenes->count() === $destinos->count()
            && $origenesPorSeccion->count() === $origenes->count()
            && $destinos->every(fn (EstablecimientoCurso $curso) => $origenesPorSeccion->has($this->claveSeccion($curso)));

        if ($mismaEstructura) {
            return [
                $destinos->map(function (EstablecimientoCurso $curso) use ($origenesPorSeccion): array {
                    $origen = $origenesPorSeccion->get($this->claveSeccion($curso));

                    return [
                        'curso' => $curso,
                        'neet' => (int) $origen->necesidades_transitorias,
                        'neep' => (int) $origen->necesidades_permanentes,
                    ];
                }),
                'Se conserva la distribución por sección.',
            ];
        }

        $destinosOrdenados = $destinos->sortBy(fn (EstablecimientoCurso $curso) => $this->claveSeccion($curso).'-'.str_pad((string) $curso->id, 10, '0', STR_PAD_LEFT))->values();
        $neetDistribuido = $this->distribuir($neetTotal, $destinosOrdenados->count());
        $neepDistribuido = $this->distribuir($neepTotal, $destinosOrdenados->count());

        return [
            $destinosOrdenados->map(fn (EstablecimientoCurso $curso, int $indice): array => [
                'curso' => $curso,
                'neet' => $neetDistribuido[$indice],
                'neep' => $neepDistribuido[$indice],
            ]),
            $origenes->count() > $destinos->count()
                ? 'Totales consolidados en las secciones destino.'
                : 'Totales distribuidos en partes enteras entre las secciones destino.',
        ];
    }

    private function distribuir(int $total, int $cantidad): array
    {
        $base = intdiv($total, $cantidad);
        $resto = $total % $cantidad;

        return array_map(fn (int $indice): int => $base + ($indice < $resto ? 1 : 0), range(0, $cantidad - 1));
    }

    private function claveSeccion(EstablecimientoCurso $curso): string
    {
        return mb_strtoupper(trim((string) $curso->letra), 'UTF-8');
    }

    private function omit(array &$resultado, string $nivel, string $motivo): void
    {
        $resultado['niveles_omitidos']++;
        $resultado['detalle'][] = [
            'nivel' => $nivel,
            'estado' => 'omitido',
            'detalle' => $motivo,
        ];
    }
}
