<?php

namespace App\Services\CentroOperaciones;

use App\Models\CentroOperacionesIncidencia;
use App\Models\CentroOperacionesIncidenteConfiguracion;
use App\Models\CentroOperacionesRiesgoEvaluacion;
use App\Models\Establecimiento;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;

class PrioridadIncidenciaService
{
    /** @return array<string, mixed> */
    public function recalcular(CentroOperacionesIncidencia $incidencia): array
    {
        $incidencia->loadMissing('establecimiento');
        $configuracion = Schema::hasTable('centro_operaciones_incidente_configuraciones')
            ? CentroOperacionesIncidenteConfiguracion::query()->where('tipo', $incidencia->tipo)->first()
            : null;
        $impacto = (int) ($configuracion?->impacto_base ?: ($incidencia->severidad === 'critico' ? 5 : 3));
        $urgencia = (int) ($configuracion?->urgencia_base ?: ($incidencia->severidad === 'critico' ? 5 : 3));
        $evaluacion = $this->evaluacionVigente((int) $incidencia->establecimiento_id);
        $irteCalculo = (int) ($evaluacion?->irte ?? 50);
        $matricula = (int) ($incidencia->establecimiento?->matricula_total ?? 0);
        $exposicion = $this->percentilMatricula($matricula);
        $slaHoras = (int) ($configuracion?->sla_horas ?: (($configuracion?->plazo_dias ?: 4) * 24));
        $antiguedad = min(100, max(0, $this->horasTranscurridas($incidencia) / max(1, $slaHoras) * 100));

        $puntaje = round(
            ($impacto / 5 * 45)
            + ($irteCalculo / 100 * 25)
            + ($urgencia / 5 * 15)
            + ($exposicion / 100 * 10)
            + ($antiguedad / 100 * 5),
            2
        );
        $nivel = $this->nivel($puntaje);
        $nivel = $this->masUrgente($nivel, (string) ($configuracion?->prioridad_minima ?: 'P4'));
        $forzada = (bool) $configuracion?->forzar_p1 && $incidencia->severidad === 'critico';
        if ($forzada) {
            $nivel = 'P1';
            $puntaje = max(80, $puntaje);
        }

        $motivo = sprintf(
            '%s: impacto %d/5, urgencia %d/5, %s, matrícula %s y SLA de %d horas.',
            $nivel,
            $impacto,
            $urgencia,
            $evaluacion ? 'IRTE '.$evaluacion->irte.' ('.$evaluacion->categoria_label.')' : 'sin IRTE vigente (valor neutral 50)',
            number_format($matricula, 0, ',', '.'),
            $slaHoras
        );
        if ($forzada) {
            $motivo .= ' Prioridad inmediata por regla crítica del tipo de incidencia.';
        }

        $resultado = [
            'familia' => $configuracion?->familia ?: 'otra',
            'impacto' => $impacto,
            'urgencia' => $urgencia,
            'prioridad_puntaje' => $puntaje,
            'prioridad_nivel' => $nivel,
            'prioridad_motivo' => $motivo,
            'irte_snapshot' => $evaluacion?->irte,
            'riesgo_categoria_snapshot' => $evaluacion?->categoria,
            'matricula_snapshot' => $matricula,
            'prioridad_calculada_en' => now(),
        ];

        if (Schema::hasColumn('centro_operaciones_incidencias', 'prioridad_nivel')) {
            $incidencia->forceFill($resultado)->save();
        }

        return $resultado;
    }

    public function recalcularEstablecimiento(int $establecimientoId): int
    {
        if (! Schema::hasColumn('centro_operaciones_incidencias', 'prioridad_nivel')) {
            return 0;
        }

        $incidencias = CentroOperacionesIncidencia::query()
            ->where('establecimiento_id', $establecimientoId)
            ->where('estado', 'activa')
            ->get();
        $incidencias->each(fn (CentroOperacionesIncidencia $incidencia) => $this->recalcular($incidencia));

        return $incidencias->count();
    }

    private function evaluacionVigente(int $establecimientoId): ?CentroOperacionesRiesgoEvaluacion
    {
        if (! Schema::hasTable('centro_operaciones_riesgo_evaluaciones')) {
            return null;
        }

        return CentroOperacionesRiesgoEvaluacion::query()
            ->where('establecimiento_id', $establecimientoId)
            ->where('estado', 'publicado')
            ->where(function ($query) {
                $query->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', now()->toDateString());
            })
            ->latest('fecha_evaluacion')
            ->latest('id')
            ->first();
    }

    private function percentilMatricula(int $matricula): float
    {
        if ($matricula <= 0 || ! Schema::hasColumn('establecimientos', 'matricula_total')) {
            return 0;
        }

        $total = Establecimiento::query()->whereNotNull('matricula_total')->where('matricula_total', '>', 0)->count();
        if ($total === 0) {
            return 0;
        }

        $hasta = Establecimiento::query()
            ->whereNotNull('matricula_total')
            ->where('matricula_total', '>', 0)
            ->where('matricula_total', '<=', $matricula)
            ->count();

        return round($hasta / $total * 100, 2);
    }

    private function horasTranscurridas(CentroOperacionesIncidencia $incidencia): float
    {
        $creada = $incidencia->created_at
            ? CarbonImmutable::parse($incidencia->created_at)
            : CarbonImmutable::parse($incidencia->fecha_incidencia);

        return max(0, $creada->floatDiffInHours(now()));
    }

    private function nivel(float $puntaje): string
    {
        return match (true) {
            $puntaje >= 80 => 'P1',
            $puntaje >= 60 => 'P2',
            $puntaje >= 40 => 'P3',
            default => 'P4',
        };
    }

    private function masUrgente(string $calculada, string $minima): string
    {
        $orden = ['P1' => 1, 'P2' => 2, 'P3' => 3, 'P4' => 4];

        return ($orden[$minima] ?? 4) < ($orden[$calculada] ?? 4) ? $minima : $calculada;
    }
}
