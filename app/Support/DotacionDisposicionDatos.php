<?php

namespace App\Support;

use App\Models\DotacionNoRenovacion;
use App\Models\DotacionDocenteExclusion;
use App\Models\DotacionSobredotacionJustificacion;
use App\Models\Establecimiento;
use App\Services\Dotacion\ContratacionHabilitacionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Lectura anual: comparte las reglas de la nómina y nunca modifica el padrón. */
class DotacionDisposicionDatos
{
    public const BLOQUES = ['plan_estudio' => 'Planes de Estudio', 'parvularia' => 'Educación Parvularia', 'pie' => 'PIE'];

    public function datos(Establecimiento $establecimiento, int $anio): array
    {
        return DotacionLecturaCache::ejecutar(function () use ($establecimiento, $anio): array {
            $data = DotacionEstablecimientoCalculator::build($establecimiento, $anio, true, false);
            $proceso = DotacionProceso2027Calculator::resumen($establecimiento, $anio, $data);
            $informe = app(DotacionSobredotacionInforme::class)->preparar($data, $proceso,
                DotacionSobredotacionJustificacion::registrosPara((int) $establecimiento->id, $anio));

            return ['filas' => $this->filas($establecimiento, $anio, $informe)];
        });
    }

    public function referenciasDestino(Establecimiento $establecimiento, int $anio): array
    {
        return DotacionLecturaCache::ejecutar(function () use ($establecimiento, $anio): array {
            $docentes = DotacionEstablecimientoCalculator::docentes($establecimiento, $anio)
                ->concat(app(ContratacionHabilitacionService::class)->docentesVirtuales($establecimiento, $anio));
            return self::cuposDestino($docentes, (int) $establecimiento->id, $anio,
                DotacionDocenteExclusion::situacionesPorRut((int) $establecimiento->id, $anio));
        });
    }

    public static function cuposDestino(iterable $docentes, int $establecimientoId, int $anio, array $situaciones = []): array
    {
        $resultado = array_fill_keys(array_keys(self::BLOQUES), ['cupos' => 0.0, 'contrata' => 0.0, 'horas' => 0.0, 'personas' => 0]);
        foreach (collect($docentes)->unique(fn ($d) => DotacionEstablecimientoCalculator::normalizeRut($d['rut'] ?? '')) as $docente) {
            $virtual = isset($docente['cupo_contrata_id']);
            $contrato = $docente['horas_contrato'] ?? null;
            $situacion = $situaciones[DotacionEstablecimientoCalculator::normalizeRut($docente['rut'] ?? '')] ?? ($docente['exclusion_docente'] ?? []);
            if ($situacion instanceof \Illuminate\Database\Eloquent\Model) { $situacion = $situacion->toArray(); }
            if (($docente['estamento_cobertura'] ?? 'docente') !== 'docente'
                || ! empty($docente['es_plaza_por_asumir']) || self::funcionExcluida($docente['funcion'] ?? '')
                || DotacionDocenteExclusion::tieneFueroMaternal($situacion)
                || in_array($situacion['motivo'] ?? '', ['horas_gremiales', 'fuero_gremial'], true)
                || (! $virtual && ($contrato === null || $contrato <= 0 || (float) ($docente['horas_planta'] ?? 0) > 0
                    || abs($contrato - (float) ($docente['horas_contrata'] ?? 0)) >= 0.005))) {
                continue;
            }
            $asignaciones = collect($docente['asignaciones'] ?? [])->filter(fn ($a) =>
                (int) data_get($a, 'establecimiento_id') === $establecimientoId && (int) data_get($a, 'anio') === $anio
                && data_get($a, 'estado', 'activa') === 'activa'
                && DotacionAsignacionCalculator::coverageEstamento($a) === 'docente'
                && ! in_array(data_get($a, 'tipo_asignacion'), ['funcion_directiva', 'reserva_no_normativa'], true)
                && ! DotacionAsignacionCalculator::esAsignacionCoordinacionPie($a)
                && ! self::funcionExcluida(implode(' ', [data_get($a, 'subtipo_asignacion', ''), data_get($a, 'asignatura_nombre', '')])));
            $porBloque = DotacionContratoPlanCalculator::consolidar($asignaciones)->groupBy(function ($a) use ($docente): string {
                $bloque = DotacionProceso2027Calculator::bloqueFuncionPorDocente($a, $docente)
                    ?? DotacionProceso2027Calculator::bloqueLibreDisposicionNtOtroDocente($a, null, $docente)
                    ?? DotacionProceso2027Calculator::bloqueParaAsignacion($a);
                return ['bloque_1' => 'plan_estudio', 'bloque_2' => 'parvularia', 'bloque_3' => 'pie'][$bloque] ?? '';
            });
            foreach ($porBloque as $bloque => $asignadas) {
                if (! isset($resultado[$bloque])) { continue; }
                $horas = round((float) $asignadas->sum('horas_contrato'), 2);
                if ($horas <= 0) { continue; }
                $resultado[$bloque][$virtual ? 'cupos' : 'contrata'] += $horas;
                $resultado[$bloque]['personas']++;
                $resultado[$bloque]['horas'] = round($resultado[$bloque]['cupos'] + $resultado[$bloque]['contrata'], 2);
            }
        }
        return $resultado;
    }

    private static function funcionExcluida(string $funcion): bool
    {
        $texto = Str::of($funcion)->ascii()->upper()->replaceMatches('/[^A-Z0-9]+/', ' ')->toString();
        return (bool) preg_match('/\b(DIRECTOR[A]?|INSPECTOR[A]?|UTP)\b|\bU T P\b|UNIDAD TECNIC[AO] PEDAGOGIC|CONVIVENCIA|COORD.*\bPIE\b|\bPIE\b.*COORD/', $texto);
    }

    public function filas(Establecimiento $ee, int $anio, array $informe): Collection
    {
        $retirados = Schema::hasTable('dotacion_no_renovaciones')
            ? DotacionNoRenovacion::query()->where('establecimiento_id', $ee->id)->where('anio', $anio)->whereNull('anulada_at')->get()
            : collect();
        $rutsRetirados = $retirados->keyBy(fn ($r) => DotacionEstablecimientoCalculator::normalizeRut($r->docente_rut_normalizado));
        $antecedentesRetirados = $retirados->isNotEmpty() && Schema::hasTable('reemplazos_personal')
            ? DotacionEstablecimientoCalculator::docentes($ee, $anio, false, true)->keyBy('rut_normalizado') : collect();
        $registros = DotacionSobredotacionJustificacion::registrosPara((int) $ee->id, $anio)
            ->keyBy(fn ($r) => DotacionSobredotacionJustificacion::clave($r->bloque, $r->docente_rut_normalizado, $r->tipo_horas));
        $totalPorRut = collect($informe['bloques'] ?? [])->flatMap(fn ($b) => $b['filas'] ?? [])
            ->groupBy(fn ($f) => DotacionEstablecimientoCalculator::normalizeRut($f['rut']))
            ->map(fn ($filas) => round($filas->sum('horas_titular') + $filas->sum('horas_contrata'), 2));
        $filas = collect();
        foreach (self::BLOQUES as $bloque => $label) {
            foreach (data_get($informe, 'bloques.'.$bloque.'.filas', []) as $fila) {
                $rut = DotacionEstablecimientoCalculator::normalizeRut($fila['rut']);
                if ($rutsRetirados->has($rut)) { continue; }
                foreach (['titular', 'contrata'] as $tipo) {
                    $horas = round((float) ($fila['horas_'.$tipo] ?? 0), 2);
                    if ($horas <= 0) { continue; }
                    $registro = $registros->get(DotacionSobredotacionJustificacion::clave($bloque, $rut, $tipo));
                    $contrato = $fila['contrato_considerado'] ?? null;
                    $total = $contrato !== null && $contrato > 0 && $totalPorRut->get($rut, 0) >= $contrato - 0.005;
                    $filas->push($this->base($ee, $anio, $rut, $bloque, $tipo) + [
                        'nombre' => $fila['nombre'], 'titulo' => $fila['titulo'] ?? 'Sin título declarado', 'funcion' => $fila['funcion'] ?? 'Sin información',
                        'prelacion' => $fila['prelacion'] ?? '', 'tramo' => $fila['tramo'] ?? '', 'fecha_antiguedad' => $fila['fecha_antiguedad'] ?? null,
                        'prioridad' => preg_match('/^([1-5])\./', $fila['prelacion'] ?? '', $grupo) ? (int) $grupo[1] : 6,
                        'contrato' => $contrato, 'horas' => $horas,
                        'tipo' => $tipo === 'contrata' ? 'No renovación' : ($contrato === null ? 'Supresión sin clasificar' : ($total ? 'Supresión total' : 'Supresión parcial')),
                        'situacion' => 'Propuesta en revisión', 'fuente' => 'sobredotacion', 'fuente_id' => $registro?->id,
                        'justificacion' => $registro?->justificacion ?: 'Sin justificación',
                        'estado_justificacion' => $fila['estado_'.$tipo] ?? 'Sin justificación',
                    ]);
                }
            }
        }
        foreach ($retirados as $r) {
            $persona = $antecedentesRetirados->get(DotacionEstablecimientoCalculator::normalizeRut($r->docente_rut_normalizado), []);
            $prelacion = DotacionProceso2027Calculator::docentesPriorizados(collect([array_replace($persona,
                ['horas_planta' => 0, 'horas_contrata' => (float) $r->horas_contrata, 'horas_contrato' => (float) $r->horas_contrata])]))->first();
            if ($prelacion['prioridad_2027'] === 1) { continue; }
            $cupos = Schema::hasTable('dotacion_contrata_habilitaciones')
                ? DB::table('dotacion_contrata_habilitaciones')->where('no_renovacion_id', $r->id)
                    ->where('establecimiento_id', $ee->id)->where('anio', $anio)->get()->groupBy('bloque')->map->sum('horas') : collect();
            // Cupos y retiro representan las mismas horas. Nunca se suman ambos como fuentes.
            if ($cupos->isEmpty()) { $cupos = collect([$r->bloque => (float) $r->horas_contrata]); }
            $restante = (int) round((float) $r->horas_contrata * 100);
            foreach ($cupos as $bloque => $cantidad) {
                if (! isset(self::BLOQUES[$bloque])) { continue; }
                $centavos = min($restante, max(0, (int) round($cantidad * 100)));
                if ($centavos <= 0) { continue; }
                $restante -= $centavos;
                $filas->push($this->base($ee, $anio, $r->docente_rut_normalizado, $bloque, 'contrata') + [
                    'nombre' => $r->docente_nombre, 'titulo' => $r->titulo, 'funcion' => $persona['funcion'] ?? 'Sin información',
                    'prelacion' => $prelacion['prioridad_2027_label'], 'prioridad' => $prelacion['prioridad_2027'],
                    'tramo' => $persona['tramo'] ?? '', 'fecha_antiguedad' => $persona['fecha_antiguedad'] ?? null, 'contrato' => (float) $r->horas_contrata,
                    'horas' => $centavos / 100, 'tipo' => 'No renovación', 'situacion' => 'No renovación registrada',
                    'fuente' => 'no_renovacion', 'fuente_id' => $r->id, 'justificacion' => $r->justificacion, 'estado_justificacion' => 'Vigente',
                ]);
            }
        }
        return $filas;
    }

    private function base(Establecimiento $ee, int $anio, string $rut, string $bloque, string $tipo): array
    {
        return ['origen_id' => (int) $ee->id, 'anio' => $anio, 'rut' => DotacionEstablecimientoCalculator::normalizeRut($rut),
            'bloque' => $bloque, 'tipo_horas' => $tipo, 'rbd' => $ee->rbd, 'establecimiento' => $ee->nombre_establecimiento];
    }
}
