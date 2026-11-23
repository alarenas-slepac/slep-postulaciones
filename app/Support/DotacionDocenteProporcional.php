<?php

namespace App\Support;

use App\Exports\DotacionPal2027Export;
use App\Models\DotacionSobredotacionJustificacion;
use App\Models\DotacionFuncionRegla;
use App\Models\Establecimiento;
use App\Services\Dotacion\ContratacionHabilitacionService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DotacionDocenteProporcional
{
    public const GRUPOS = ['directiva', 'tecnico_pedagogica', 'aula'];

    public function resumir(int $anio): array
    {
        return $this->calcular($anio)['dotacion'];
    }

    public function paraInforme(int $anio): array
    {
        return $this->calcular($anio, incluirGlosa: $anio === DotacionProceso2027Calculator::ANIO);
    }

    private function calcular(int $anio, bool $incluirGlosa = false): array
    {
        $totalPorDocente = [];
        $horasPorEstablecimiento = [];
        $filas = [];
        $acumular = function (int $id, string $grupo, string $rut, float $horas, bool $esDocente) use (&$filas, &$horasPorEstablecimiento, &$totalPorDocente): void {
            if ($horas <= 0) {
                return;
            }
            $filas[$id][$grupo] ??= ['docentes' => 0.0, 'horas' => 0.0];
            $filas[$id][$grupo]['horas'] += $horas;
            if ($esDocente) {
                $horasPorEstablecimiento[$id][$grupo][$rut] = ($horasPorEstablecimiento[$id][$grupo][$rut] ?? 0.0) + $horas;
                $totalPorDocente[$rut] = ($totalPorDocente[$rut] ?? 0.0) + $horas;
            }
        };
        $reglas = Schema::hasTable('dotacion_funciones_reglas')
            ? DotacionFuncionRegla::query()->pluck('codigo', 'id')->all()
            : [];
        $establecimientos = Establecimiento::query()
            ->where(fn ($q) => $q->whereNull('sala_cuna')->orWhere('sala_cuna', false))->lazyById();
        $contrataciones = app(ContratacionHabilitacionService::class);
        $informeFunciones = app(DotacionInformeFunciones::class);
        $tablasPal = $anio === 2027 ? app(DotacionPal2027Export::class)->tablas() : [];
        $glosa = $incluirGlosa ? new DotacionDocenteGlosa : null;
        $criterios = [];
        foreach ($establecimientos as $establecimiento) {
            // Conservar sólo los acumulados territoriales al cambiar de establecimiento.
            DotacionLecturaCache::ejecutar(function () use (
                $establecimiento, $anio, $acumular, $reglas, $contrataciones, $informeFunciones, $tablasPal, $glosa, &$filas, &$criterios
            ): void {
                $id = (int) $establecimiento->id;
                if ($anio === 2027) {
                    $datos = app(DotacionPal2027::class)->datos($establecimiento);
                    foreach ($this->horasPal2027($establecimiento, $datos, $tablasPal, $reglas) as $rut => $grupos) {
                        foreach ($grupos as $grupo => $horas) {
                            $acumular($id, $grupo, (string) $rut, $horas, true);
                        }
                    }
                    if ($glosa !== null) {
                        $horasDotacion = array_sum(array_column($filas[$id] ?? [], 'horas'));
                        $criterios[$id] = $glosa->criterio($establecimiento, $datos, $horasDotacion);
                    }
                    return;
                }
                $justificados = array_flip(DotacionSobredotacionJustificacion::rutsJustificados($id, $anio));
                $cupos = $contrataciones->docentesVirtuales($establecimiento, $anio, false)->pluck('rut_normalizado')->flip()->all();
                $asignaciones = [];
                $hayDirectorAdp = false;
                foreach (DotacionAsignacionCalculator::assignmentsFor($establecimiento, $anio) as $asignacion) {
                    $rut = DotacionEstablecimientoCalculator::normalizeRut($asignacion->docente_rut_normalizado ?: $asignacion->docente_rut);
                    $horas = (float) $asignacion->horas_contrato;
                    if ($rut === ''
                        || (ContratacionHabilitacionService::esRutVirtual($rut) && ! isset($cupos[$rut]))
                        || $horas <= 0
                        || $asignacion->tipo_asignacion === 'reserva_no_normativa') {
                        continue;
                    }
                    $esDocente = DotacionAsignacionCalculator::coverageEstamento($asignacion) === 'docente';
                    $cargo = $this->cargoAsignacion($asignacion, $reglas);
                    $hayDirectorAdp = $hayDirectorAdp || ($esDocente && $asignacion->tipo_asignacion === 'funcion_directiva' && $cargo === 'director_adp');
                    if (isset($justificados[$rut])) {
                        continue;
                    }
                    $asignaciones[] = [$asignacion, $rut, $horas, $cargo, $esDocente];
                }

                if (! $hayDirectorAdp) {
                    foreach (DotacionAsignacionCalculator::plazasPorAsumir($establecimiento, $anio) as $plaza) {
                        $acumular($id, 'directiva', 'DIRECTOR_ADP_POR_ASUMIR_'.$id, (float) $plaza['horas_contrato'], true);
                        $hayDirectorAdp = true;
                    }
                }

                foreach ($asignaciones as [$asignacion, $rut, $horas, $cargo, $esDocente]) {
                    $grupo = $this->grupoAsignacion($asignacion, $cargo, $hayDirectorAdp);
                    $acumular($id, $grupo, $rut, $horas, $esDocente);
                }

                $pendientes = $informeFunciones->saldosSinAsignar($establecimiento, $anio);
                if ($pendientes !== []) {
                    $saldoAula = $this->saldoAula($establecimiento, $anio, $filas[$id]['aula']['horas'] ?? 0);
                    foreach ($pendientes as $rut => $horas) {
                        $aula = min($horas, $saldoAula);
                        $saldoAula = round($saldoAula - $aula, 2);
                        if ($aula > 0) {
                            $acumular($id, 'aula', $rut, $aula, true);
                        }
                        if ($horas > $aula) {
                            $acumular($id, 'tecnico_pedagogica', $rut, round($horas - $aula, 2), true);
                        }
                    }
                }
            });
        }

        foreach ($horasPorEstablecimiento as $id => $grupos) {
            foreach ($grupos as $grupo => $horasPorRut) {
                $fracciones = 0.0;
                foreach ($horasPorRut as $rut => $horas) {
                    $fracciones += $horas / $totalPorDocente[$rut];
                }
                $filas[$id][$grupo]['docentes'] = $fracciones;
            }
        }

        return ['dotacion' => $filas, 'glosa' => $glosa?->distribuir($criterios)];
    }

    private function horasPal2027(Establecimiento $establecimiento, array $datos, array $tablas, array $reglas): array
    {
        $pal = app(DotacionPal2027::class);
        $hayDirectorAdp = collect(data_get($datos, 'asignacion.plazas_por_asumir'))->isNotEmpty()
            || collect($datos['docentes'] ?? [])->contains(fn ($docente) =>
                empty($docente['no_renovacion_activa'])
                && data_get($docente, 'exclusion_docente.motivo') !== 'proceso_bir'
                && collect($docente['asignaciones'] ?? [])->contains(fn ($a) =>
                    data_get($a, 'tipo_asignacion') === 'funcion_directiva'
                    && DotacionAsignacionCalculator::esAsignacionDocenteReal($a)
                    && (float) data_get($a, 'horas_contrato') > 0
                    && $this->cargoAsignacion($a, $reglas) === 'director_adp'));
        $reporte = $pal->construir($establecimiento, $datos, $tablas, fn ($a) =>
            $this->grupoAsignacion($a, $this->cargoAsignacion($a, $reglas), $hayDirectorAdp));

        $porRut = [];
        $filasPal = new DotacionPalFilas2027;
        foreach ($reporte['docentes'] as $fila) {
            $rut = DotacionPal2027::identidad($fila['A']);
            foreach ($filasPal->horasPorComponente($fila, $tablas) as $componente => $horas) {
                if ($horas <= 0) {
                    continue;
                }
                $pesos = array_filter($fila['_pal_dotacion'][$componente] ?? [], fn ($peso) => $peso > 0);
                $totalPesos = array_sum($pesos);
                if ($totalPesos <= 0) {
                    throw new \LogicException('El RBD '.$establecimiento->rbd.' tiene horas PAL sin clasificación de dotación.');
                }
                $saldo = $horas;
                foreach ($pesos as $grupo => $peso) {
                    // El último grupo absorbe la diferencia de redondeo de la fila PAL.
                    $parte = $grupo === array_key_last($pesos) ? $saldo : min($saldo, round($horas * $peso / $totalPesos, 2));
                    $porRut[$rut][$grupo] = ($porRut[$rut][$grupo] ?? 0.0) + $parte;
                    $saldo = round($saldo - $parte, 2);
                }
            }
        }

        if (array_column($porRut, 'pendiente') === []) {
            return $porRut;
        }
        $saldoAula = $this->saldoAula($establecimiento, 2027, array_sum(array_column($porRut, 'aula')));
        ksort($porRut);
        foreach ($porRut as &$grupos) {
            $pendiente = $grupos['pendiente'] ?? 0.0;
            unset($grupos['pendiente']);
            if ($pendiente > 0) {
                $aula = min($pendiente, $saldoAula);
                $saldoAula = round($saldoAula - $aula, 2);
                $grupos['aula'] = ($grupos['aula'] ?? 0.0) + $aula;
                $grupos['tecnico_pedagogica'] = ($grupos['tecnico_pedagogica'] ?? 0.0) + round($pendiente - $aula, 2);
            }
        }
        unset($grupos);

        return $porRut;
    }

    private function saldoAula(Establecimiento $establecimiento, int $anio, float $asignadas): float
    {
        $oferta = app(DotacionInformePlanes::class)->ofertaEducativa($establecimiento, $anio);
        $bolsa = DotacionEstablecimientoCalculator::horasContratoPieEstablecimiento($establecimiento, $anio, soloCursosActivos: true);

        return max(0.0, round($oferta['contrato_mas_pie'] + $bolsa['educadoras_diferenciales_horas'] - $asignadas, 2));
    }

    private function cargoAsignacion(object|array $asignacion, array $reglas): ?string
    {
        return in_array(data_get($asignacion, 'tipo_asignacion'), ['funcion_directiva', 'otra_funcion'], true)
            ? $this->cargoDirectivo($reglas[data_get($asignacion, 'dotacion_funcion_regla_id')] ?? null, data_get($asignacion, 'asignatura_nombre'))
            : null;
    }

    private function grupoAsignacion(object|array $asignacion, ?string $cargo, bool $hayDirectorAdp): string
    {
        $tipo = data_get($asignacion, 'tipo_asignacion');

        return match (true) {
            $cargo === 'subdirector' => 'directiva',
            $tipo === 'funcion_directiva' =>
                $cargo === 'director_adp' || ($cargo === 'director' && ! $hayDirectorAdp) ? 'directiva' : 'tecnico_pedagogica',
            data_get($asignacion, 'subtipo_asignacion') === 'libre_disposicion',
            in_array($tipo, ['plan_estudio', 'acompanamiento_parvularia', 'pie_colaborativo', 'pie_educadora_diferencial'], true) => 'aula',
            default => 'tecnico_pedagogica',
        };
    }

    private function cargoDirectivo(?string $codigo, ?string $nombre): ?string
    {
        $nombre = Str::of($nombre ?? '')->ascii()->upper()->replaceMatches('/[^A-Z0-9]+/', ' ')->squish()->toString();
        if (preg_match('/^(?:SUBDIRECTOR(?:A| A)?|SUBDIRECCION)(?: (?:ESCOLAR|DEL ESTABLECIMIENTO))?$/', $nombre) === 1) {
            return 'subdirector';
        }

        if ($codigo !== null) {
            return $codigo;
        }

        return match (true) {
            preg_match('/^(?:DIRECTOR(?:A| A)?|DIRECCION) ADP$/', $nombre) === 1 => 'director_adp',
            preg_match('/^(?:DIRECTOR(?:A| A)?|DIRECCION)(?: (?:ESCOLAR|DEL ESTABLECIMIENTO))?$/', $nombre) === 1 => 'director',
            default => null,
        };
    }
}
