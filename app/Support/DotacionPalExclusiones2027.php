<?php

namespace App\Support;

use App\Models\DotacionDocenteExclusion;
use App\Models\DotacionNoRenovacion;
use App\Models\DotacionSobredotacionJustificacion;
use App\Models\Establecimiento;
use App\Services\Padron\PadronPeriodoService;
use Illuminate\Support\Facades\Schema;

/** Catálogo de lectura: no reincorpora personas retiradas a la nómina del SGA. */
class DotacionPalExclusiones2027
{
    public function proyeccion(Establecimiento $ee): array
    {
        if (! Schema::hasTable('reemplazos_personal')) { return []; }
        $anioBase = app(PadronPeriodoService::class)->anioDisponibleParaDotacion(2027) ?? 2027;
        $retirados = DotacionNoRenovacion::rutsRetirados((int) $ee->id, 2027);
        $continuidad = $anioBase < 2027 && Schema::hasTable('dotacion_docente_exclusiones')
            ? DotacionDocenteExclusion::continuidadPorRut((int) $ee->id, $anioBase) : [];
        if ($retirados === [] && ! in_array(false, $continuidad, true)) { return []; }
        $situaciones = $anioBase < 2027 && Schema::hasTable('dotacion_docente_exclusiones')
            ? DotacionDocenteExclusion::situacionesPorRut((int) $ee->id, $anioBase) : [];
        $noRenovaciones = $retirados === [] ? [] : DotacionNoRenovacion::query()
            ->where('establecimiento_id', $ee->id)->where('anio', 2027)->whereNull('anulada_at')
            ->get()->keyBy('docente_rut_normalizado')->all();
        $filas = [];
        foreach (DotacionEstablecimientoCalculator::docentes($ee, 2027, incluirAsignaciones: false, incluirExcluidosProyeccion: true) as $docente) {
            $rut = DotacionEstablecimientoCalculator::normalizeRut($docente['rut'] ?? null);
            $horas = (float) ($docente['horas_contrato_base'] ?? $docente['horas_contrato'] ?? 0);
            if (in_array($rut, $retirados, true)) {
                $registro = $noRenovaciones[$rut] ?? null;
                $detalle = 'No renovación activa registrada para 2027. Contrato del último padrón vigente: '.$horas.' h. '
                    .$this->detalleRegistro($registro?->getAttributes() ?? []);
                $filas[] = $this->fila($ee, $docente, 'No renovación 2027', $horas, trim($detalle), anioBase: $anioBase);
            } elseif (($continuidad[$rut] ?? true) === false) {
                $situacion = $situaciones[$rut] ?? null;
                $motivo = $situacion?->motivo_label ?? 'Sin motivo adicional';
                $detalle = 'Situación '.$anioBase.': '.$motivo.'. Considerar en dotación siguiente: No. '
                    .'Contrato del último padrón vigente: '.$horas.' h. '.$this->detalleRegistro($situacion?->getAttributes() ?? []);
                $filas[] = $this->fila($ee, $docente, 'No continúa en 2027, según situación registrada en '.$anioBase,
                    $horas, trim($detalle), anioBase: $anioBase);
            }
        }
        return $filas;
    }

    /** Misma selección de justificaciones que aplica el descuento del PAL. */
    public function justificaciones(Establecimiento $ee): array
    {
        if (! Schema::hasTable('dotacion_sobredotacion_justificaciones')) { return []; }
        $detalle = [];
        foreach (DotacionSobredotacionJustificacion::query()->where('establecimiento_id', $ee->id)->where('anio', 2027)
            ->where('tipo_horas', 'titular')->whereIn('bloque', ['plan_estudio', 'parvularia', 'pie'])
            ->where('horas_detectadas', '>', 0)->get() as $registro) {
            if (trim((string) $registro->justificacion) === '') { continue; }
            $rut = DotacionEstablecimientoCalculator::normalizeRut($registro->docente_rut_normalizado);
            $detalle[$rut][] = $registro->bloque.': '.$registro->horas_detectadas.' h. '.trim((string) $registro->justificacion);
        }
        return array_map(fn ($items) => implode(' | ', $items), $detalle);
    }

    public function fila(Establecimiento $ee, array $docente, string $motivo, float $horas, string $detalle,
        ?string $calidad = null, string $base = 'Contrato docente', ?int $anioBase = null): array
    {
        $titular = (float) ($docente['horas_planta'] ?? 0);
        $contrata = (float) ($docente['horas_contrata'] ?? 0);
        $calidad ??= $titular > 0 && $contrata > 0 ? 'Mixto' : ($titular > 0 ? 'Titular' : ($contrata > 0 ? 'Plazo Fijo' : 'Otros'));
        return ['rbd' => (string) $ee->rbd, 'establecimiento' => (string) $ee->nombre_establecimiento,
            'rut' => (string) ($docente['rut'] ?? ''), 'nombre' => (string) ($docente['nombre'] ?? ''),
            'calidad' => $calidad, 'motivo' => $motivo, 'horas' => round(max(0, $horas), 4),
            'detalle' => $detalle, 'base' => $base, 'anio_base' => $anioBase ?? (int) ($docente['anio'] ?? 2027)];
    }

    private function detalleRegistro(array $atributos): string
    {
        $partes = [];
        foreach (['motivo_no_renovacion', 'justificacion', 'observacion', 'observaciones', 'detalle'] as $campo) {
            if (is_string($atributos[$campo] ?? null) && trim($atributos[$campo]) !== '') { $partes[] = trim($atributos[$campo]); }
        }
        return implode(' | ', array_unique($partes));
    }
}
