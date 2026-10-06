<?php

namespace App\Support;

use App\Models\EstablecimientoCurso;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/** Regla especial desde 2027; consultas sin escrituras en asignaciones históricas. */
class DotacionContratoParvulariaCalculator
{
    public const MOTIVO_REDONDEO = 'Redondeo hacia arriba del contrato acumulado por docente; las fracciones no quedan como saldo disponible.';

    public static function consolidar(Collection $asignaciones): Collection
    {
        $candidatas = $asignaciones->filter(fn ($row) => self::aplica($row));
        if ($candidatas->isEmpty()) {
            return $asignaciones;
        }
        // Sólo las antiguas filas CPEIP sin base necesitan consultar el curso.
        // Las nuevas guardan la base y el denominador en su fuente de cálculo.
        $ids = $candidatas->filter(fn ($row) => self::referenciaGuardada($row) === null
            && (int) data_get($row, 'dotacion_curso_combinado_id', 0) === 0)
            ->pluck('establecimiento_curso_id')->filter()->unique();
        $cursos = collect();
        if ($ids->isNotEmpty() && Schema::hasTable('establecimiento_cursos')
            && Schema::hasTable('cursos') && Schema::hasTable('planes_estudio')) {
            $cursos = EstablecimientoCurso::with(['curso', 'planEstudio'])->whereIn('id', $ids)->get()->keyBy('id');
        }
        $acumuladas = [];
        $porDocente = [];
        $conversiones = [];
        foreach ($candidatas->sortBy(fn ($row) => (int) data_get($row, 'id', 0)) as $indice => $row) {
            $referencia = self::referenciaGuardada($row);
            if ($referencia === null) {
                $curso = $cursos->get((int) data_get($row, 'establecimiento_curso_id'));
                if (! $curso || (int) $curso->establecimiento_id !== (int) data_get($row, 'establecimiento_id')
                    || (int) $curso->anio !== (int) data_get($row, 'anio')
                    || ! DotacionProfesionDocenteResolver::esCursoNt($curso)) {
                    continue;
                }
                $jec = DotacionParvulariaCalculator::conJec($curso);
                $referencia = [
                    'total' => (float) ($curso->planEstudio?->horas_semanales_total
                        ?? DotacionEstablecimientoCalculator::horasCurso($curso)['horas'] ?? 0),
                    'base' => DotacionParvulariaCalculator::base($curso, $jec), 'jec' => $jec,
                ];
            }
            if ($referencia['total'] <= 0 || $referencia['base'] <= 0) {
                continue;
            }
            $rut = DotacionEstablecimientoCalculator::normalizeRut((string) (
                data_get($row, 'docente_rut_normalizado') ?: data_get($row, 'docente_rut', '')
            ));
            if ($rut === '') {
                continue;
            }
            $grupo = implode('|', [data_get($row, 'establecimiento_id'), data_get($row, 'anio'), $rut,
                (int) data_get($row, 'dotacion_curso_combinado_id', 0) > 0
                    ? 'grupo:'.data_get($row, 'dotacion_curso_combinado_id')
                    : 'curso:'.data_get($row, 'establecimiento_curso_id'),
                $referencia['total'], $referencia['base'],
            ]);
            $previas = $acumuladas[$grupo] ?? ['aula' => 0.0, 'exactas' => 0.0];
            $aula = round($previas['aula'] + (float) data_get($row, 'horas_plan_pedagogicas'), 2);
            $calculo = DotacionParvulariaCalculator::convertir($aula, $referencia['total'], $referencia['base'], $referencia['jec']);
            // Cada curso conserva su base; sólo se redondea el total del docente.
            // No redondear por asignatura ni acumular centésimas como saldo usable.
            $exactas = $aula / $referencia['total'] * $referencia['base'];
            $docenteKey = implode('|', [data_get($row, 'establecimiento_id'), data_get($row, 'anio'), $rut]);
            $docente = $porDocente[$docenteKey] ?? ['exactas' => 0.0, 'contrato' => 0.0];
            $totalExactas = $docente['exactas'] - $previas['exactas'] + $exactas;
            // Evita que residuos binarios conviertan, por ejemplo, 55 en 56.
            $contrato = (float) ceil(round($totalExactas, 8));
            $conversiones[$indice] = [
                'horas_contrato' => round($contrato - $docente['contrato'], 2),
                'proporcion_aplicada' => $calculo['proporcion_label'],
            ];
            if (data_get($row, 'proporcion_aplicada') === 'NT JEC · CPEIP 65/35') {
                $fila = DotacionParvulariaCalculator::convertir((float) data_get($row, 'horas_plan_pedagogicas'),
                    $referencia['total'], $referencia['base'], $referencia['jec']);
                $conversiones[$indice]['fuente_calculo'] = 'Regla especial Parvularia · '.$fila['motivo'].' '.self::MOTIVO_REDONDEO;
            }
            $acumuladas[$grupo] = ['aula' => $aula, 'exactas' => $exactas];
            $porDocente[$docenteKey] = ['exactas' => $totalExactas, 'contrato' => $contrato];
        }

        return $asignaciones->map(function ($row, $indice) use ($conversiones) {
            if (! isset($conversiones[$indice])) {
                return $row;
            }
            if (is_array($row)) {
                return array_replace($row, $conversiones[$indice]);
            }
            $copia = clone $row;
            foreach ($conversiones[$indice] as $campo => $valor) {
                $copia->{$campo} = $valor;
            }
            return $copia;
        });
    }

    private static function aplica(object|array $row): bool
    {
        $label = (string) data_get($row, 'proporcion_aplicada', '');
        return (int) data_get($row, 'anio', 0) >= 2027
            && DotacionAsignacionCalculator::esAsignacionDocenteReal($row)
            && (data_get($row, 'estado') ?? 'activa') === 'activa'
            && in_array(data_get($row, 'tipo_asignacion'), ['plan_estudio', 'acompanamiento_parvularia'], true)
            && (float) data_get($row, 'horas_plan_pedagogicas', 0) > 0
            && ($label === 'NT JEC · CPEIP 65/35'
                || str_starts_with($label, 'NT Con JEC · base contractual')
                || str_starts_with($label, 'NT Sin JEC · base contractual'));
    }

    private static function referenciaGuardada(object|array $row): ?array
    {
        if (! preg_match('/Reparto proporcional:\s*[\d.]+\s*\/\s*([\d.]+)\s*h de plan.*?([\d.]+)\s*h de contrato/u',
            (string) data_get($row, 'fuente_calculo', ''), $matches)) {
            return null;
        }
        return ['total' => (float) $matches[1], 'base' => (float) $matches[2],
            'jec' => str_starts_with((string) data_get($row, 'proporcion_aplicada'), 'NT Con JEC')];
    }
}
