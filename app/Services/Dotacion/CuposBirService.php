<?php

namespace App\Services\Dotacion;

use App\Models\DotacionDocenteExclusion;
use App\Models\Establecimiento;
use App\Support\DotacionContratoVigentePorBloque;
use App\Support\DotacionEstablecimientoCalculator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CuposBirService
{
    public static function disponible(): bool
    {
        return Schema::hasTable('dotacion_docente_exclusiones')
            && Schema::hasColumns('dotacion_docente_exclusiones', ['horas_traspaso_bir', 'considerar_dotacion_siguiente', 'conservar_horas_necesarias'])
            && Schema::hasTable('dotacion_contrata_habilitaciones')
            && Schema::hasColumns('dotacion_contrata_habilitaciones', ['bir_situacion_id', 'bir_docente_rut_normalizado', 'titulo', 'funcion']);
    }

    public function resumen(Establecimiento $establecimiento, int $anio): Collection
    {
        if (! self::disponible() || $anio < 2027 || $anio > 2100) return collect();
        $situaciones = DotacionDocenteExclusion::query()->where('establecimiento_id', $establecimiento->id)
            ->where('anio', $anio - 1)->where('motivo', 'proceso_bir')->orderBy('id')->get();
        if ($situaciones->isEmpty()) return collect();

        $origen = DotacionEstablecimientoCalculator::docentes($establecimiento, $anio - 1)->keyBy('rut_normalizado');
        $destino = DotacionEstablecimientoCalculator::docentes($establecimiento, $anio, false)->keyBy('rut_normalizado');
        $existentes = DB::table('dotacion_contrata_habilitaciones')->where('establecimiento_id', $establecimiento->id)
            ->where('anio', $anio)->whereIn('bir_docente_rut_normalizado', $situaciones->map(
                fn ($situacion) => DotacionEstablecimientoCalculator::normalizeRut($situacion->docente_rut_normalizado ?: $situacion->docente_rut)
            )->filter()->unique())->get()
            ->groupBy('bir_docente_rut_normalizado');
        $items = collect();
        foreach ($situaciones as $situacion) {
            $rut = DotacionEstablecimientoCalculator::normalizeRut($situacion->docente_rut_normalizado ?: $situacion->docente_rut);
            $docente = $origen->get($rut);
            $base = max(0.0, (float) ($docente['horas_contrato'] ?? 0));
            $definidas = $situacion->horas_traspaso_bir === null ? $base : max(0.0, (float) $situacion->horas_traspaso_bir);
            $conservadas = round(min($base, $definidas), 2);
            $motivo = match (true) {
                ! $docente || (int) ($docente['anio'] ?? 0) !== $anio - 1 => 'No hay contrato docente vigente del año de origen para obtener título y bloque.',
                ($situacion->considerar_dotacion_siguiente ?? true) => 'El docente está marcado para continuar. Revise su situación antes de crear una vacante.',
                ! ($situacion->conservar_horas_necesarias ?? true) => 'La situación no conserva horas para el año siguiente.',
                $destino->has($rut) => 'El docente figura en la nómina del año destino. No se duplica su contrato con un cupo.',
                $conservadas <= 0 => 'No hay horas conservadas para generar cupos.',
                $conservadas > 44 => 'Revise el contrato de origen: supera las 44 horas por docente.',
                default => null,
            };
            $distribucion = $docente ? app(DotacionContratoVigentePorBloque::class)->desdeDocentes(
                collect([array_replace($docente, ['horas_contrato' => $conservadas])]),
                collect($docente['asignaciones'] ?? []), (bool) $establecimiento->especial
            ) : [];
            $horasPorBloque = [];
            foreach (['plan_estudio' => 1, 'parvularia' => 2, 'pie' => 3] as $bloque => $numero) {
                $horas = max(0.0, round((float) ($distribucion['contrato_vigente_bloque_'.$numero] ?? 0), 2));
                if ($horas > 0) $horasPorBloque[$bloque] = $horas;
            }
            if ((int) round(array_sum($horasPorBloque) * 100) !== (int) round($conservadas * 100)) {
                $motivo ??= 'Revise la distribución contractual por bloque antes de generar cupos.';
            }
            if (! $horasPorBloque) $horasPorBloque[NoRenovacionService::bloqueDocente($docente ?? [])] = 0.0;
            // La persona y el año identifican el origen aunque se elimine y recree
            // la situación. Un cambio de título/bloque no permite duplicar el cupo.
            $cuposPersona = $existentes->get($rut, collect());
            if ($cuposPersona->contains(fn ($cupo) => ! isset($horasPorBloque[$cupo->bloque])
                || (float) $cupo->horas <= 0 || (float) $cupo->horas > $horasPorBloque[$cupo->bloque] + 0.001
                || $cupo->titulo !== ($docente['titulo'] ?? 'Sin título declarado'))) {
                $motivo = 'Existen cupos BIR con otra distribución, horas o título. Revise los cupos generados antes de volver a crear.';
            }
            foreach ($horasPorBloque as $bloque => $horas) {
                $clave = $situacion->id.':'.$bloque;
                $item = [
                    'clave' => $clave, 'situacion_id' => $situacion->id, 'rut_normalizado' => $rut, 'bloque' => $bloque,
                    'nombre' => $docente['nombre'] ?? $situacion->docente_nombre, 'rut' => $situacion->docente_rut,
                    'titulo' => $docente['titulo'] ?? 'Sin título declarado', 'funcion' => $docente['funcion'] ?? 'Docente por contratar',
                    'contrato_origen' => $base, 'horas_definidas' => $definidas, 'horas_conservadas' => $conservadas,
                    'horas' => $horas, 'motivo' => $motivo, 'historico' => $situacion->horas_traspaso_bir === null,
                ];
                $item['huella'] = hash('sha256', json_encode([$establecimiento->id, $anio, $item, $situacion->updated_at?->toISOString()], JSON_THROW_ON_ERROR));
                $item['cupo'] = $cuposPersona->firstWhere('bloque', $bloque);
                $items->push($item);
            }
        }

        return $items;
    }

    public function generar(Establecimiento $establecimiento, int $anio, array $seleccion, array $huellas, ?int $usuario, ?array $horasSeleccionadas = null): int
    {
        if (! self::disponible()) throw ValidationException::withMessages(['cupos' => 'Aplique la migración de origen BIR antes de crear estos cupos.']);
        if ($anio < 2027 || $anio > 2100 || ! $seleccion || count($seleccion) > 1000 || count(array_unique($seleccion)) !== count($seleccion)) {
            throw ValidationException::withMessages(['cupos' => 'Seleccione cupos válidos del año 2027 o posterior, sin repetirlos.']);
        }

        return DB::transaction(function () use ($establecimiento, $anio, $seleccion, $huellas, $usuario, $horasSeleccionadas): int {
            // La asignación, retiro y creación de cupos comparten este bloqueo.
            Establecimiento::query()->whereKey($establecimiento->id)->lockForUpdate()->firstOrFail();
            DotacionDocenteExclusion::query()->where('establecimiento_id', $establecimiento->id)
                ->where('anio', $anio - 1)->where('motivo', 'proceso_bir')->lockForUpdate()->get(['id']);
            $candidatos = $this->resumen($establecimiento, $anio)->keyBy('clave');
            $nuevos = [];
            foreach ($seleccion as $clave) {
                $item = $candidatos->get($clave);
                if (! $item || $item['motivo'] || ! hash_equals($item['huella'], (string) ($huellas[$clave] ?? ''))) {
                    throw ValidationException::withMessages(['cupos' => 'La situación, el contrato o el título cambiaron o el cupo no está habilitado. Recargue la revisión BIR antes de generar.']);
                }
                // Los consumidores anteriores sin selección explícita conservan
                // el máximo inicial o la cantidad del cupo ya generado.
                $cantidad = $horasSeleccionadas === null
                    ? ($item['cupo']?->horas ?? $item['horas'])
                    : ($horasSeleccionadas[$clave] ?? null);
                Validator::make(['horas' => [$clave => $cantidad]], [
                    'horas.'.$clave => ['bail', 'required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:'.$item['horas']],
                ], [
                    'horas.*.required' => 'Indique las horas del cupo seleccionado.',
                    'horas.*.max' => 'Las horas del cupo no pueden superar las '.DotacionEstablecimientoCalculator::formatHoras($item['horas']).' h disponibles en este bloque.',
                ], ['horas.'.$clave => 'horas del cupo'])->validate();
                $cantidad = round((float) $cantidad, 2);
                if ($item['cupo']) {
                    if (abs((float) $item['cupo']->horas - $cantidad) > 0.001) {
                        throw ValidationException::withMessages(['horas.'.$clave => 'El cupo BIR ya fue generado con otra cantidad de horas. Para cambiarla, retire primero el cupo sin asignaciones desde Docentes por contratar.']);
                    }
                    continue; // Reenviar una solicitud idéntica no duplica contrato.
                }
                $nuevos[] = [
                    'establecimiento_id' => $establecimiento->id, 'anio' => $anio, 'bloque' => $item['bloque'],
                    'horas' => $cantidad, 'titulo' => $item['titulo'], 'funcion' => $item['funcion'],
                    'bir_situacion_id' => $item['situacion_id'], 'bir_docente_rut_normalizado' => $item['rut_normalizado'],
                    'created_by' => $usuario, 'created_at' => now(), 'updated_at' => now(),
                ];
            }
            foreach (array_chunk($nuevos, 100) as $lote) DB::table('dotacion_contrata_habilitaciones')->insert($lote);

            return count($nuevos);
        }, 3);
    }
}
