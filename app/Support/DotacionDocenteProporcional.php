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
        $reglas = Schema::hasTable('dotacion_funciones_reglas')
            ? DotacionFuncionRegla::query()->pluck('codigo', 'id')->all()
            : [];
        $establecimientos = Establecimiento::query()
            ->where(fn ($q) => $q->whereNull('sala_cuna')->orWhere('sala_cuna', false))->lazyById();
        $contrataciones = app(ContratacionHabilitacionService::class);
        foreach ($establecimientos as $establecimiento) {
            $id = (int) $establecimiento->id;
            $justificados = array_flip(DotacionSobredotacionJustificacion::rutsJustificados($id, $anio));
            $cupos = $contrataciones->docentesVirtuales($establecimiento, $anio, false)->pluck('rut_normalizado')->flip()->all();
            $asignaciones = [];
            $hayDirectorAdp = false;
            foreach (DotacionAsignacionCalculator::assignmentsFor($establecimiento, $anio) as $asignacion) {
                $rut = DotacionEstablecimientoCalculator::normalizeRut($asignacion->docente_rut_normalizado ?: $asignacion->docente_rut);
                $horas = (float) $asignacion->horas_contrato;
                if ($rut === '' || isset($justificados[$rut])
                    || (ContratacionHabilitacionService::esRutVirtual($rut) && ! isset($cupos[$rut]))
                    || $horas <= 0 || DotacionAsignacionCalculator::coverageEstamento($asignacion) !== 'docente'
                    || $asignacion->tipo_asignacion === 'reserva_no_normativa') {
                    continue;
                }
                $cargo = $asignacion->tipo_asignacion === 'funcion_directiva'
                    ? $this->cargoDirectivo($reglas[$asignacion->dotacion_funcion_regla_id] ?? null, $asignacion->asignatura_nombre)
                    : null;
                $hayDirectorAdp = $hayDirectorAdp || $cargo === 'director_adp';
                $asignaciones[] = [$asignacion, $rut, $horas, $cargo];
            }

            foreach ($asignaciones as [$asignacion, $rut, $horas, $cargo]) {
                $grupo = match (true) {
                    $cargo === 'director_adp', $cargo === 'subdirector', $cargo === 'director' && ! $hayDirectorAdp => 'directiva',
                    $asignacion->tipo_asignacion === 'funcion_directiva' => 'tecnico_pedagogica',
                    $asignacion->subtipo_asignacion === 'libre_disposicion',
                    in_array($asignacion->tipo_asignacion, ['plan_estudio', 'acompanamiento_parvularia',
                        'pie_colaborativo', 'pie_educadora_diferencial'], true) => 'aula',
                    default => 'tecnico_pedagogica',
                };
                $horasPorEstablecimiento[$id][$grupo][$rut] = ($horasPorEstablecimiento[$id][$grupo][$rut] ?? 0.0) + $horas;
                $totalPorDocente[$rut] = ($totalPorDocente[$rut] ?? 0.0) + $horas;
            }
        }

        $filas = [];
        foreach ($horasPorEstablecimiento as $id => $grupos) {
            foreach ($grupos as $grupo => $horasPorRut) {
                $fracciones = 0.0;
                foreach ($horasPorRut as $rut => $horas) {
                    $fracciones += $horas / $totalPorDocente[$rut];
                }
                $filas[$id][$grupo] = ['docentes' => $fracciones, 'horas' => array_sum($horasPorRut)];
            }
        }

        return $filas;
    }

    private function cargoDirectivo(?string $codigo, ?string $nombre): ?string
    {
        if ($codigo !== null) {
            return $codigo;
        }

        $nombre = Str::of($nombre ?? '')->ascii()->upper()->replaceMatches('/[^A-Z0-9]+/', ' ')->squish()->toString();

        return match (true) {
            preg_match('/^(?:DIRECTOR(?:A| A)?|DIRECCION) ADP$/', $nombre) === 1 => 'director_adp',
            preg_match('/^(?:DIRECTOR(?:A| A)?|DIRECCION)(?: (?:ESCOLAR|DEL ESTABLECIMIENTO))?$/', $nombre) === 1 => 'director',
            preg_match('/^(?:SUBDIRECTOR(?:A| A)?|SUBDIRECCION)(?: (?:ESCOLAR|DEL ESTABLECIMIENTO))?$/', $nombre) === 1 => 'subdirector',
            default => null,
        };
    }
}
