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

    public const SUBVENCIONES = ['general' => 'Subv. General', 'sep' => 'SEP', 'pie' => 'PIE'];

    public function resumir(int $anio): array
    {
        return $this->calcular($anio)['dotacion'];
    }

    public function paraInforme(int $anio, array $idsHorasAula = []): array
    {
        return $this->calcular($anio, incluirSubvenciones: true, establecimientosHorasAula: array_fill_keys($idsHorasAula, true));
    }

    private function calcular(int $anio, bool $incluirSubvenciones = false, array $establecimientosHorasAula = []): array
    {
        $dotacion = new DotacionDocenteAcumulado;
        $subvenciones = $incluirSubvenciones ? new DotacionDocenteAcumulado : null;
        $reglas = Schema::hasTable('dotacion_funciones_reglas')
            ? DotacionFuncionRegla::query()->pluck('codigo', 'id')->all()
            : [];
        $establecimientos = Establecimiento::query()
            ->where(fn ($q) => $q->whereNull('sala_cuna')->orWhere('sala_cuna', false))->lazyById();
        $contrataciones = app(ContratacionHabilitacionService::class);
        $informeFunciones = app(DotacionInformeFunciones::class);
        $tablasPal = $anio === 2027 ? app(DotacionPal2027Export::class)->tablas() : [];
        $glosa = $incluirSubvenciones && $anio === DotacionProceso2027Calculator::ANIO ? new DotacionDocenteGlosa : null;
        $criterios = [];
        $criteriosSubvencion = [];
        $cursosProyectados = [];
        $horasAula = [];
        foreach ($establecimientos as $establecimiento) {
            // Conservar los acumulados y las filas del informe, sin retener los modelos de cada establecimiento.
            DotacionLecturaCache::ejecutar(function () use (
                $establecimiento, $anio, $dotacion, $subvenciones, $reglas, $contrataciones, $informeFunciones,
                $tablasPal, $glosa, $establecimientosHorasAula, &$criterios, &$criteriosSubvencion, &$cursosProyectados, &$horasAula
            ): void {
                $id = (int) $establecimiento->id;
                if ($anio === 2027) {
                    $datos = app(DotacionPal2027::class)->datos($establecimiento);
                    [$horasFuncion, $horasSubvencion, $cursosPal] = $this->horasPal2027($establecimiento, $datos, $tablasPal, $reglas);
                    foreach ($horasFuncion as $rut => $grupos) {
                        foreach ($grupos as $grupo => $horas) {
                            $dotacion->agregar($id, $grupo, (string) $rut, $horas);
                        }
                    }
                    foreach ($horasSubvencion as $rut => $fuentes) {
                        foreach ($fuentes as $fuente => $horas) {
                            $subvenciones?->agregar($id, $fuente, (string) $rut, $horas);
                        }
                    }
                    if ($glosa !== null) {
                        $criterios[$id] = $glosa->criterio($establecimiento, $datos, $dotacion->horas($id));
                        $criteriosSubvencion[$id] = $glosa->recalcularBrecha($criterios[$id], $subvenciones->horas($id));
                        $cursosProyectados[$id] = $cursosPal;
                    }
                    if (isset($establecimientosHorasAula[$id])) {
                        $horasAula[$id] = app(DotacionInformeHorasAula::class)->filas($datos);
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
                        $rut = 'DIRECTOR_ADP_POR_ASUMIR_'.$id;
                        $horas = (float) $plaza['horas_contrato'];
                        $dotacion->agregar($id, 'directiva', $rut, $horas);
                        $subvenciones?->agregar($id, 'general', $rut, $horas);
                        $hayDirectorAdp = true;
                    }
                }

                foreach ($asignaciones as [$asignacion, $rut, $horas, $cargo, $esDocente]) {
                    $grupo = $this->grupoAsignacion($asignacion, $cargo, $hayDirectorAdp);
                    $dotacion->agregar($id, $grupo, $rut, $horas, $esDocente);
                    $subvenciones?->agregar($id, $this->subvencionHistorica($asignacion), $rut, $horas, $esDocente);
                }

                $pendientes = $informeFunciones->saldosSinAsignar($establecimiento, $anio);
                if ($pendientes !== []) {
                    $saldoAula = $this->saldoAula($establecimiento, $anio, $dotacion->horas($id, 'aula'));
                    foreach ($pendientes as $rut => $horas) {
                        $aula = min($horas, $saldoAula);
                        $saldoAula = round($saldoAula - $aula, 2);
                        $otras = round($horas - $aula, 2);
                        $dotacion->agregar($id, 'aula', $rut, $aula);
                        $dotacion->agregar($id, 'tecnico_pedagogica', $rut, $otras);
                        $subvenciones?->agregar($id, 'general', $rut, $aula + $otras);
                    }
                }
            });
        }

        return [
            'dotacion' => $dotacion->resumir(),
            'glosa' => $glosa?->distribuir($criterios),
            'subvenciones' => $subvenciones?->resumir() ?? [],
            'glosa_subvencion' => $glosa?->distribuir($criteriosSubvencion),
            'cursos_proyectados' => $cursosProyectados,
            'horas_aula' => $horasAula,
        ];
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
        $porSubvencion = [];
        $fuentes = ['aula' => 'general', 'P' => 'pie', 'Q' => 'sep', 'R' => 'general'];
        $filasPal = new DotacionPalFilas2027;
        foreach ($reporte['docentes'] as $fila) {
            $rut = DotacionPal2027::identidad($fila['A']);
            $sepEquivalente = $fila['_pal_sep_equivalente'] ?? 0.0;
            if ($sepEquivalente > 0) {
                $porSubvencion[$rut]['sep'] = ($porSubvencion[$rut]['sep'] ?? 0.0) + $sepEquivalente;
            }
            foreach ($filasPal->horasPorComponente($fila, $tablas) as $componente => $horas) {
                if ($horas <= 0) {
                    continue;
                }
                $fuente = $fuentes[$componente];
                $porSubvencion[$rut][$fuente] = ($porSubvencion[$rut][$fuente] ?? 0.0) + $horas;
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
            return [$porRut, $porSubvencion, $reporte['cursos']];
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

        return [$porRut, $porSubvencion, $reporte['cursos']];
    }

    private function subvencionHistorica(object $asignacion): string
    {
        $fuente = DotacionInformeFinanciamiento::fuenteSubvencion($asignacion->subvencion);
        if ($fuente === 'pro_retencion') {
            return 'sep';
        }

        return DotacionAsignacionCalculator::esAsignacionPie($asignacion) ? 'pie' : ($fuente ?? 'general');
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
