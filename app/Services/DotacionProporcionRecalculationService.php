<?php

namespace App\Services;

use App\Models\DotacionDocenteAsignacion;
use App\Models\Establecimiento;
use App\Support\DocenteHorasNoLectivasCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionProfesionDocenteResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DotacionProporcionRecalculationService
{
    public function recalculate(Establecimiento $establecimiento, int $anio, ?int $userId = null): array
    {
        if (! Schema::hasTable('dotacion_docente_asignaciones')) {
            return ['total' => 0, 'actualizadas' => 0, 'omitidas' => 0];
        }

        DocenteHorasNoLectivasCalculator::clearExceptionCache((int) $establecimiento->id, $anio);
        $porcentajePrioritarios = DotacionEstablecimientoCalculator::porcentajePrioritariosPara($establecimiento, $anio);

        return DB::transaction(function () use ($establecimiento, $anio, $userId, $porcentajePrioritarios): array {
            $total = 0;
            $actualizadas = 0;
            $omitidas = 0;

            DotacionDocenteAsignacion::query()
                ->with(['establecimientoCurso.curso', 'establecimientoCurso.planEstudio', 'declaracionSostenedor'])
                ->where('establecimiento_id', $establecimiento->id)
                ->where('anio', $anio)
                ->where('estado', 'activa')
                ->where('tipo_asignacion', 'plan_estudio')
                ->when(
                    Schema::hasColumn('dotacion_docente_asignaciones', 'estamento_cobertura'),
                    fn ($query) => $query->where(function ($query) {
                        $query->whereNull('estamento_cobertura')
                            ->orWhere('estamento_cobertura', '!=', 'asistente');
                    })
                )
                ->orderBy('id')
                ->chunkById(100, function ($asignaciones) use (&$total, &$actualizadas, &$omitidas, $porcentajePrioritarios, $userId): void {
                    foreach ($asignaciones as $asignacion) {
                        $total++;

                        if (($asignacion->estamento_cobertura ?? 'docente') === 'asistente') {
                            $omitidas++;
                            continue;
                        }

                        $curso = $asignacion->establecimientoCurso;
                        $horasAula = max(0.0, (float) ($asignacion->horas_plan_pedagogicas ?? 0));

                        if (! $curso || $horasAula <= 0) {
                            $omitidas++;
                            continue;
                        }

                        $persona = [
                            'titulo' => $asignacion->declaracionSostenedor?->nombre_titulo ?: 'Sin título declarado',
                            'declaracion' => $asignacion->declaracionSostenedor,
                        ];
                        $calculoNt = DotacionProfesionDocenteResolver::conversionNt(
                            $curso,
                            $horasAula,
                            $persona,
                            (string) ($asignacion->proporcion_aplicada ?? '')
                        );
                        $calculo = $calculoNt ?? DotacionEstablecimientoCalculator::contratoEquivalenteAsignacion(
                            $curso,
                            $horasAula,
                            $porcentajePrioritarios,
                            $asignacion->subtipo_asignacion
                        );

                        $payload = [
                            'horas_contrato' => (float) ($calculo['horas_contrato_equivalente_redondeado'] ?? 0),
                            'horas_cronologicas_aula' => (float) ($calculo['horas_aula_cronologicas'] ?? 0),
                            'proporcion_aplicada' => $calculo['proporcion_label'] ?? null,
                            'fuente_calculo' => $calculoNt !== null
                                ? 'Conversión NT1/NT2 según profesión declarada · '.($calculo['origen_proporcion_label'] ?? 'Regla profesional').' · '.($calculo['motivo'] ?? '')
                                : 'Conversión automática desde horas aula · '.($calculo['origen_proporcion_label'] ?? 'Regla general'),
                            'updated_by' => $userId,
                        ];

                        $changed = $this->payloadDiffers($asignacion, $payload);

                        if ($changed) {
                            $asignacion->update($payload);
                            $actualizadas++;
                        }
                    }
                });

            return compact('total', 'actualizadas', 'omitidas');
        });
    }

    private function payloadDiffers(DotacionDocenteAsignacion $asignacion, array $payload): bool
    {
        foreach ($payload as $field => $value) {
            if ($field === 'updated_by') {
                continue;
            }

            if (in_array($field, ['horas_contrato', 'horas_cronologicas_aula'], true)) {
                if (abs((float) $asignacion->{$field} - (float) $value) > 0.001) {
                    return true;
                }
                continue;
            }

            if ((string) ($asignacion->{$field} ?? '') !== (string) ($value ?? '')) {
                return true;
            }
        }

        return false;
    }
}
