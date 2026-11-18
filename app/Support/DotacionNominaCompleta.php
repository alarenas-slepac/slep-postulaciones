<?php

namespace App\Support;

use App\Models\DotacionDocenteExclusion;
use App\Models\DotacionNoRenovacion;
use App\Models\DotacionSobredotacionJustificacion;
use App\Models\Establecimiento;
use App\Services\Dotacion\ContratacionHabilitacionService;
use App\Services\Padron\PadronPeriodoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Nómina del padrón base, incluso cuando la proyección no considera al docente. */
class DotacionNominaCompleta
{
    public function datos(Collection $establecimientos, int $anio, array $filtros = []): Collection
    {
        $filas = $establecimientos->flatMap(function (Establecimiento $ee) use ($anio) {
            // Libera la caché de lectura de cada establecimiento antes de pasar al siguiente.
            return DotacionLecturaCache::ejecutar(function () use ($ee, $anio) {
                $anioPadron = app(PadronPeriodoService::class)->anioDisponibleParaDotacion($anio) ?? $anio;

                return $this->filas($ee, DotacionEstablecimientoCalculator::docentes($ee, $anio, true, true), [
                    'retirados' => DotacionNoRenovacion::rutsRetirados((int) $ee->id, $anio),
                    'continuidad' => $anioPadron < $anio ? DotacionDocenteExclusion::continuidadPorRut((int) $ee->id, $anioPadron) : [],
                    'justificados' => DotacionSobredotacionJustificacion::rutsJustificados((int) $ee->id, $anio),
                ]);
            });
        });

        return $this->filtrar($filas, $filtros)->sortBy(fn ($fila) =>
            $this->normalizar($fila['comuna'].' '.$fila['establecimiento'].' '.$fila['nombre']).' '.$fila['rut_normalizado']
        )->values();
    }

    public function filas(Establecimiento $ee, Collection $docentes, array $contexto = []): Collection
    {
        $nomina = new DotacionNominaDocentes;

        return $docentes->reject(fn (array $d) => isset($d['cupo_contrata_id'])
            || ($d['estamento_cobertura'] ?? 'docente') !== 'docente'
            || ContratacionHabilitacionService::esRutVirtual((string) ($d['rut'] ?? '')))
            ->unique(fn ($d) => DotacionEstablecimientoCalculator::normalizeRut((string) ($d['rut'] ?? '')))
            ->map(function (array $d) use ($ee, $nomina, $contexto): array {
                $rut = DotacionEstablecimientoCalculator::normalizeRut((string) ($d['rut'] ?? ''));
                $contrato = max(0.0, (float) ($d['horas_contrato_base'] ?? $d['horas_contrato'] ?? 0));
                $base = array_replace($d, ['horas_contrato' => $contrato]);
                // La distribución usa el contrato original, aunque la situación lo reduzca a cero.
                $base['horas_contrato_pie'] = DotacionAsignacionCalculator::contratoPiePorDocente($base);
                $distribucion = $nomina->distribucionPorBloque($base, (bool) $ee->especial);
                $retirado = in_array($rut, $contexto['retirados'] ?? [], true);
                $noContinua = ($contexto['continuidad'][$rut] ?? true) === false;
                $considerado = $noContinua && ! $retirado ? 0.0 : max(0.0, (float) ($d['horas_contrato'] ?? 0));
                $estado = match (true) {
                    $retirado => 'Con no renovación registrada; contrato considerado en dotación',
                    $noContinua => 'Fuera de proyección: no continúa',
                    $considerado < $contrato - 0.01 => 'Con ajuste de horas en dotación',
                    default => 'Sin reducción de contrato en dotación',
                };

                return [
                    'establecimiento_id' => (int) $ee->id, 'rbd' => (string) $ee->rbd,
                    'establecimiento' => (string) $ee->nombre_establecimiento, 'comuna' => (string) $ee->comuna,
                    'rut' => (string) ($d['rut'] ?? ''), 'rut_normalizado' => $rut,
                    'nombre' => (string) ($d['nombre'] ?? ''), 'titulo' => trim((string) ($d['titulo'] ?? '')) ?: 'Sin título declarado',
                    'tramo' => (string) ($d['tramo'] ?? ''), 'tipo_contrato' => (string) ($d['tipo_contrato'] ?? ''),
                    'bloques' => $distribucion['bloques'],
                    'bloques_label' => implode(' / ', array_map(fn ($b) => DotacionNominaDocentes::BLOQUES[$b], $distribucion['bloques'])),
                    'contrato_original' => round($contrato, 2),
                    'titulares' => round(max(0.0, (float) ($d['horas_planta'] ?? 0)), 2),
                    'contrata' => round(max(0.0, (float) ($d['horas_contrata'] ?? 0)), 2),
                    'otras_calidades' => round(max(0.0, $contrato - (float) ($d['horas_planta'] ?? 0) - (float) ($d['horas_contrata'] ?? 0)), 2),
                    'plan_estudio' => $distribucion['horas']['plan_estudio'],
                    'parvularia' => $distribucion['horas']['parvularia'], 'pie' => $distribucion['horas']['pie'],
                    'contrato_considerado' => round($considerado, 2), 'estado_dotacion' => $estado,
                    'justificada' => in_array($rut, $contexto['justificados'] ?? [], true),
                    'periodo_padron' => !empty($d['anio']) && !empty($d['mes']) ? sprintf('%02d/%04d', $d['mes'], $d['anio']) : 'No informado',
                    'registros_contrato' => (int) ($d['registros_contrato'] ?? 1),
                ];
            })->values();
    }

    public function filtrar(Collection $filas, array $filtros): Collection
    {
        $bloque = (string) ($filtros['bloque'] ?? '');
        $busqueda = $this->normalizar((string) ($filtros['q'] ?? ''));

        return $filas->filter(fn ($fila) => ($bloque === '' || in_array($bloque, $fila['bloques'], true))
            && ($busqueda === '' || str_contains($this->normalizar(implode(' ', [
                $fila['nombre'], $fila['rut'], $fila['titulo'], $fila['rbd'], $fila['establecimiento'],
            ])), $busqueda)))->values();
    }

    public function resumen(Collection $filas): array
    {
        return [
            'personas' => $filas->unique('rut_normalizado')->count(), 'registros' => $filas->count(),
            'establecimientos' => $filas->unique('establecimiento_id')->count(),
            'contrato' => round((float) $filas->sum('contrato_original'), 2),
        ];
    }

    private function normalizar(string $texto): string
    {
        return Str::of($texto)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '')->toString();
    }
}
