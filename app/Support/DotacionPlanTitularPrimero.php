<?php

namespace App\Support;

use App\Models\DotacionDocenteAsignacion;
use App\Models\EstablecimientoCurso;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DotacionPlanTitularPrimero
{
    private const MINIMO_SALDO_ASIGNABLE = 1.0;

    /** Se evalúa con la necesidad vigente, nunca con el subtipo enviado por el formulario. */
    public static function permiteSeleccionLibre(array $necesidad): bool
    {
        $curso = $necesidad['curso'] ?? null;

        return ($necesidad['tipo_asignacion'] ?? '') === 'plan_estudio'
            && (($necesidad['subtipo_asignacion'] ?? '') === 'libre_disposicion'
                || (($necesidad['curso_combinado'] ?? false)
                    && ($necesidad['curso_combinado_libre_disposicion'] ?? false)))
            && $curso instanceof EstablecimientoCurso
            && DotacionProfesionDocenteResolver::esCursoNt($curso)
            && DotacionParvulariaCalculator::conJec($curso, $necesidad['proporcion_key'] ?? null);
    }

    /** @param Collection<int, array<string, mixed>> $docentes
     *  @param array<int, string> $rutsPermitidos
     *  @return Collection<int, array<string, mixed>>
     */
    public static function elegibles(Collection $docentes, array $rutsPermitidos, array $necesidad, ?DotacionDocenteAsignacion $current = null): Collection
    {
        $curso = $necesidad['curso'] ?? null;
        $soloParvularia = $curso instanceof EstablecimientoCurso
            && DotacionProfesionDocenteResolver::esCursoNt($curso)
            && ! DotacionParvulariaCalculator::conJec($curso, $necesidad['proporcion_key'] ?? null);
        $permitidos = array_fill_keys($rutsPermitidos, true);
        $rutActual = $current && ($current->estamento_cobertura ?? 'docente') === 'docente'
            ? DotacionEstablecimientoCalculator::normalizeRut((string) ($current->docente_rut_normalizado ?: $current->docente_rut))
            : null;

        return $docentes->filter(function (array $docente) use ($permitidos, $soloParvularia): bool {
            $rut = DotacionEstablecimientoCalculator::normalizeRut((string) ($docente['rut_normalizado'] ?? $docente['rut'] ?? ''));

            return isset($permitidos[$rut])
                && (! $soloParvularia || DotacionProfesionDocenteResolver::perfilTitulo($docente)['es_educacion_parvulos']);
        })->map(function (array $docente) use ($current, $rutActual): array {
            $rut = DotacionEstablecimientoCalculator::normalizeRut((string) ($docente['rut_normalizado'] ?? $docente['rut'] ?? ''));
            if ($rutActual !== null && $rut === $rutActual) {
                $liberadas = DotacionContratoPlanCalculator::horasLiberadas(collect($docente['asignaciones'] ?? []), $current);
                $asignadas = max(0.0, (float) ($docente['horas_asignadas_total'] ?? 0) - $liberadas);
                $planta = max(0.0, (float) ($docente['horas_planta'] ?? 0));
                $contrata = max(0.0, (float) ($docente['horas_contrata'] ?? 0));
                $docente['horas_disponibles'] = max(0.0, round((float) ($docente['horas_contrato'] ?? 0) - $asignadas, 2));
                $docente['horas_titulares_disponibles'] = max(0.0, round($planta - min($planta, $asignadas), 2));
                $docente['horas_contrata_disponibles'] = max(0.0, round($contrata - max(0.0, $asignadas - $planta), 2));
            }

            return $docente;
        })->values();
    }

    /** @param Collection<int, array<string, mixed>> $docentes */
    public static function fase(Collection $docentes): string
    {
        if ($docentes->contains(fn (array $docente) =>
            TramoDocente::esNoRegidoLey20903($docente['tramo'] ?? null)
            && (float) ($docente['horas_disponibles'] ?? 0) >= self::MINIMO_SALDO_ASIGNABLE
        )) {
            return 'preferente';
        }

        return $docentes->contains(fn (array $docente) => self::disponibles($docente, 'titular') >= self::MINIMO_SALDO_ASIGNABLE)
            ? 'titular'
            : 'contrata';
    }

    /** @param array<string, mixed> $docente */
    public static function disponibles(array $docente, string $fase): float
    {
        if ($fase === 'preferente') {
            // El nuevo grupo usa todo su saldo contractual. Fuero/gremiales
            // conserva la precedencia; los demás docentes sólo usan titulares.
            return in_array((int) ($docente['prioridad_2027'] ?? 0), [1, 2], true)
                ? max(0.0, (float) ($docente['horas_disponibles'] ?? 0))
                : self::disponibles($docente, 'titular');
        }

        $campo = $fase === 'titular' ? 'horas_titulares_disponibles' : 'horas_contrata_disponibles';

        return max(0.0, min(
            (float) ($docente['horas_disponibles'] ?? 0),
            (float) ($docente[$campo] ?? 0)
        ));
    }

    /** Saldo para una asignación, manteniendo aparte la elegibilidad de la fase. */
    public static function saldoAsignable(array $docente, string $fase): float
    {
        $saldoFase = self::disponibles($docente, $fase);
        if (in_array($fase, ['titular', 'preferente'], true)
            && self::disponibles($docente, 'titular') >= self::MINIMO_SALDO_ASIGNABLE) {
            // El consumo contractual ya descuenta primero planta. Una misma
            // asignación puede agotar ese saldo y completar con contrata propia.
            return max($saldoFase, min(
                (float) ($docente['horas_disponibles'] ?? 0),
                self::disponibles($docente, 'titular') + self::disponibles($docente, 'contrata')
            ));
        }

        return $saldoFase;
    }

    /** @param Collection<int, array<string, mixed>> $docentes
     *  @return Collection<int, array<string, mixed>>
     */
    public static function opciones(Collection $docentes): Collection
    {
        $fase = self::fase($docentes);

        return $docentes->filter(fn (array $docente) => self::disponibles($docente, $fase) >= self::MINIMO_SALDO_ASIGNABLE)->values();
    }

    /** Compara sólo las opciones del selector, con su capacidad real de asignación. */
    public static function candidatosParaPrelacion(Collection $docentes): Collection
    {
        $fase = self::fase($docentes);

        return self::opciones($docentes)->map(function (array $docente) use ($fase): array {
            $saldo = self::saldoAsignable($docente, $fase);
            // Proyección para la comparación de prioridad; no altera el saldo original.
            $docente['horas_disponibles'] = $saldo;
            $docente['horas_titulares_disponibles'] = $saldo;
            return $docente;
        });
    }

    /** @param Collection<int, array<string, mixed>> $docentes */
    public static function validar(Collection $docentes, ?array $seleccionado, float $horasContrato, bool $esAsistente): void
    {
        $fase = self::fase($docentes);
        if ($esAsistente) {
            if ($fase !== 'contrata') {
                throw ValidationException::withMessages([
                    'docente_rut' => $fase === 'preferente'
                        ? 'Primero asigne el saldo de los docentes con prelación, incluido No Regido Ley 20903. La cobertura por asistente se habilita después.'
                        : 'Primero asigne las horas titulares disponibles de los docentes asociados a esta asignatura. La cobertura por asistente se habilita después.',
                ]);
            }

            return;
        }

        $disponibles = $seleccionado ? self::disponibles($seleccionado, $fase) : 0.0;
        if ($disponibles < self::MINIMO_SALDO_ASIGNABLE) {
            throw ValidationException::withMessages([
                'docente_rut' => match ($fase) {
                    'preferente' => 'Seleccione un docente asociado con saldo titular o con prioridad No Regido Ley 20903, fuero maternal u horas gremiales y al menos 1 h disponible.',
                    'titular' => 'Esta asignatura aún tiene docentes con al menos 1 h titular disponible. Seleccione uno de ellos.',
                    default => 'Seleccione un docente con al menos 1 h a contrata disponible para esta asignatura.',
                },
            ]);
        }
        $saldoAsignable = self::saldoAsignable($seleccionado, $fase);
        if ($horasContrato > $saldoAsignable + 0.001) {
            $saldo = DotacionEstablecimientoCalculator::formatHoras($saldoAsignable);
            throw ValidationException::withMessages([
                'horas_plan_pedagogicas' => "La asignación requiere {$horasContrato} h de contrato, pero el docente dispone de {$saldo} h de contrato asignable. Revise sus saldos titular y a contrata y reduzca las horas aula o seleccione otro docente habilitado.",
            ]);
        }
    }
}
