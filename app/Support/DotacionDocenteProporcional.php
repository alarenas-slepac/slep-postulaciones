<?php

namespace App\Support;

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
        $totalPorDocente = [];
        $horasPorEstablecimiento = [];
        $filas = [];
        $acumular = function (int $id, string $grupo, string $rut, float $horas, bool $esDocente) use (&$filas, &$horasPorEstablecimiento, &$totalPorDocente): void {
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
        foreach ($establecimientos as $establecimiento) {
            $id = (int) $establecimiento->id;
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
                $cargo = in_array($asignacion->tipo_asignacion, ['funcion_directiva', 'otra_funcion'], true)
                    ? $this->cargoDirectivo($reglas[$asignacion->dotacion_funcion_regla_id] ?? null, $asignacion->asignatura_nombre)
                    : null;
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
                $grupo = match (true) {
                    $cargo === 'subdirector' => 'directiva',
                    $asignacion->tipo_asignacion === 'funcion_directiva' =>
                        $cargo === 'director_adp' || ($cargo === 'director' && ! $hayDirectorAdp) ? 'directiva' : 'tecnico_pedagogica',
                    $asignacion->subtipo_asignacion === 'libre_disposicion',
                    in_array($asignacion->tipo_asignacion, ['plan_estudio', 'acompanamiento_parvularia',
                        'pie_colaborativo', 'pie_educadora_diferencial'], true) => 'aula',
                    default => 'tecnico_pedagogica',
                };
                $acumular($id, $grupo, $rut, $horas, $esDocente);
            }

            $pendientes = $informeFunciones->saldosSinAsignar($establecimiento, $anio);
            if ($pendientes !== []) {
                $oferta = app(DotacionInformePlanes::class)->ofertaEducativa($establecimiento, $anio);
                $bolsa = DotacionEstablecimientoCalculator::horasContratoPieEstablecimiento($establecimiento, $anio, soloCursosActivos: true);
                $saldoAula = max(0.0, round($oferta['contrato_mas_pie'] + $bolsa['educadoras_diferenciales_horas']
                    - ($filas[$id]['aula']['horas'] ?? 0), 2));
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

        return $filas;
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
