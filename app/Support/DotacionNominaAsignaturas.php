<?php

namespace App\Support;

use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use Illuminate\Support\Collection;

class DotacionNominaAsignaturas
{
    public const BLOQUES = DotacionNominaDocentes::BLOQUES;

    public function datos(Establecimiento $establecimiento, int $anio): array
    {
        return DotacionLecturaCache::ejecutar(function () use ($establecimiento, $anio): array {
            $data = DotacionEstablecimientoCalculator::build($establecimiento, $anio, true, false);
            $docentes = collect($data['docentes']);
            $periodos = $docentes->map(fn (array $docente) => !empty($docente['anio']) && !empty($docente['mes'])
                ? sprintf('%02d/%04d', $docente['mes'], $docente['anio']) : 'Período no informado')->unique()->values();

            return [
                'asignaturas' => $this->asignaturas(data_get($data, 'asignacion.necesidades.plan_estudio', []), $docentes),
                'periodos_padron' => $periodos->implode(', ') ?: 'Sin docentes en la nómina anual',
            ];
        });
    }

    /** Conserva la agrupación curricular del consolidado y suma horas aula, sin convertirlas a contrato. */
    public function asignaturas(iterable $necesidades, Collection $docentes): Collection
    {
        $personas = $docentes->keyBy(fn (array $docente) => DotacionEstablecimientoCalculator::normalizeRut(
            (string) ($docente['rut_normalizado'] ?? $docente['rut'] ?? '')
        ));
        $vistos = [];
        $necesidades = collect($necesidades)->map(function (array $necesidad) use ($personas, &$vistos): array {
            $necesidad['asignaciones'] = collect($necesidad['asignaciones'] ?? [])->filter(function ($asignacion) use ($personas, &$vistos): bool {
                $rut = DotacionEstablecimientoCalculator::normalizeRut((string) (data_get($asignacion, 'docente_rut_normalizado') ?: data_get($asignacion, 'docente_rut', '')));
                $persona = $personas->get($rut);
                if (!DotacionAsignacionCalculator::esAsignacionDocenteReal($asignacion)
                    || (string) data_get($asignacion, 'tipo_asignacion', 'plan_estudio') !== 'plan_estudio'
                    || data_get($asignacion, 'cupo_contrata_id') || isset($persona['cupo_contrata_id'])
                    || ($persona['estamento_cobertura'] ?? 'docente') === 'asistente'
                    || data_get($asignacion, 'es_plan_estudio_huerfano', false) || data_get($asignacion, 'es_plan_huerfano', false)
                    || $rut === '' || (float) data_get($asignacion, 'horas_plan_pedagogicas', 0) <= 0) {
                    return false;
                }
                $id = data_get($asignacion, 'id');
                if ($id !== null && isset($vistos[(string) $id])) { return false; }
                if ($id !== null) { $vistos[(string) $id] = true; }

                return true;
            })->values();

            return $necesidad;
        });
        $priorizados = DotacionProceso2027Calculator::docentesPriorizados($docentes);
        $orden = $priorizados->mapWithKeys(fn (array $docente, int $index) => [
            DotacionEstablecimientoCalculator::normalizeRut((string) ($docente['rut_normalizado'] ?? $docente['rut'] ?? '')) => $index,
        ]);
        $personas = $priorizados->keyBy(fn (array $docente) => DotacionEstablecimientoCalculator::normalizeRut((string) ($docente['rut_normalizado'] ?? $docente['rut'] ?? '')));
        $necesidadesPorBloque = array_fill_keys(array_keys(self::BLOQUES), []);
        foreach ($necesidades as $necesidad) {
            $curso = $necesidad['curso'] ?? null;
            $bloqueBase = $curso instanceof EstablecimientoCurso && DotacionProfesionDocenteResolver::esCursoNt($curso)
                ? 'parvularia' : 'plan_estudio';
            $grupos = collect($necesidad['asignaciones'])->groupBy(function ($asignacion) use ($bloqueBase, $personas): string {
                $rut = DotacionEstablecimientoCalculator::normalizeRut((string) (data_get($asignacion, 'docente_rut_normalizado') ?: data_get($asignacion, 'docente_rut', '')));
                if ($bloqueBase === 'parvularia' && DotacionProceso2027Calculator::bloqueLibreDisposicionNtOtroDocente(
                    $asignacion, 'bloque_2', $personas->get($rut)
                ) === 'bloque_1') {
                    return 'plan_estudio';
                }

                return $bloqueBase;
            });
            if ($grupos->isEmpty()) { $grupos = collect([$bloqueBase => collect()]); }
            foreach ($grupos as $bloque => $asignaciones) {
                $necesidadesPorBloque[$bloque][] = array_replace($necesidad, ['asignaciones' => $asignaciones]);
            }
        }
        $items = collect();
        foreach (self::BLOQUES as $bloque => $label) {
            $consolidado = DotacionAsignaturaResumenCalculator::build($necesidadesPorBloque[$bloque], $docentes);
            foreach ($consolidado['items'] as $asignatura) {
                $items->push($asignatura + ['bloque' => $bloque, 'bloque_label' => $label]);
            }
        }

        return $items->map(function (array $asignatura) use ($personas, $orden): array {
            $filas = collect($asignatura['detalle'])->flatMap(fn (array $detalle) => $detalle['asignaciones'])
                ->groupBy(fn (array $asignacion) => DotacionEstablecimientoCalculator::normalizeRut($asignacion['rut']))
                ->map(function (Collection $asignaciones, string $rut) use ($personas, $orden): array {
                    $persona = $personas->get($rut);

                    return [
                        'rut' => (string) ($persona['rut'] ?? $asignaciones->first()['rut']),
                        'nombre' => (string) ($persona['nombre'] ?? $asignaciones->first()['docente']),
                        'titulo' => (string) ($persona['titulo'] ?? 'Sin título declarado'),
                        'prelacion' => $persona['prioridad_2027_label'] ?? 'Sin antecedentes en nómina anual',
                        'tramo' => $persona['tramo'] ?? null, 'fecha_antiguedad' => $persona['fecha_antiguedad'] ?? null,
                        'horas_asignadas' => round((float) $asignaciones->sum('horas_aula'), 2),
                        'orden' => $orden->get($rut, PHP_INT_MAX),
                    ];
                })->sort(fn (array $a, array $b) => ($a['orden'] <=> $b['orden']) ?: strnatcasecmp($a['nombre'], $b['nombre']))->values();

            return ['bloque' => $asignatura['bloque'], 'bloque_label' => $asignatura['bloque_label'],
                'nombre' => $asignatura['asignatura'], 'docentes' => $filas, 'horas_asignadas' => round((float) $filas->sum('horas_asignadas'), 2)];
        })->values();
    }
}
