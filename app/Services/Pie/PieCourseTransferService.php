<?php

namespace App\Services\Pie;

use App\Models\EstablecimientoCurso;
use App\Models\EstablecimientoCursoPie;
use App\Support\PieHorasCalculator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PieCourseTransferService
{
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

                if (EstablecimientoCursoPie::query()
                    ->where('anio', $anioDestino)
                    ->whereIn('establecimiento_curso_id', $destinos->pluck('id'))
                    ->exists()) {
                    $this->omit($resultado, $nivel, 'Ya existen registros PIE en '.$anioDestino.'; el nivel no fue sobrescrito.');
                    continue;
                }

                [$asignaciones, $estrategia] = $this->asignaciones($registrosOrigen, $destinos, $neetTotal, $neepTotal);

                if ($asignaciones->contains(fn (array $asignacion) => ($asignacion['neet'] + $asignacion['neep']) > (int) $asignacion['curso']->matricula)) {
                    $this->omit($resultado, $nivel, 'La asignación supera la matrícula de una sección destino; no se crearon registros.');
                    continue;
                }

                foreach ($asignaciones as $asignacion) {
                    /** @var EstablecimientoCurso $cursoDestino */
                    $cursoDestino = $asignacion['curso'];
                    $neet = $asignacion['neet'];
                    $neep = $asignacion['neep'];
                    $calculo = PieHorasCalculator::calculate($cursoDestino, $neet, $neep);

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
                        'observacion' => "Traspaso automático {$anioOrigen}→{$anioDestino} por nivel ({$estrategia}).",
                        'estado' => 'borrador',
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ], $calculo));
                }

                $resultado['niveles_procesados']++;
                $resultado['registros_creados'] += $asignaciones->count();
                $resultado['detalle'][] = [
                    'nivel' => $nivel,
                    'estado' => 'traspasado',
                    'detalle' => $estrategia,
                    'neet' => $neetTotal,
                    'neep' => $neepTotal,
                    'secciones_origen' => $registrosOrigen->count(),
                    'secciones_destino' => $destinos->count(),
                ];
            }

            return $resultado;
        });
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
