<?php

namespace App\Services\Dotacion;

use App\Models\DotacionDocenteAsignacion;
use App\Models\DotacionDocenteExclusion;
use App\Models\DotacionNoRenovacion;
use App\Models\Establecimiento;
use App\Support\DotacionContratoPlanCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionProceso2027Calculator;
use App\Support\DotacionProfesionDocenteResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class NoRenovacionService
{
    public const BLOQUES = ['plan_estudio' => 'Plan general', 'parvularia' => 'Educación Parvularia', 'pie' => 'PIE especializado'];

    public static function bloqueDocente(array $docente): string
    {
        $perfil = DotacionProfesionDocenteResolver::perfilTitulo($docente);

        return $perfil['es_educacion_parvulos'] ? 'parvularia'
            : ($perfil['es_educacion_diferencial'] ? 'pie' : 'plan_estudio');
    }

    public static function elegible(array $docente): bool
    {
        return empty($docente['cupo_contrata_id'])
            && (float) ($docente['horas_planta'] ?? 0) <= 0
            && (float) ($docente['horas_contrata'] ?? 0) > 0
            && abs((float) ($docente['horas_contrato'] ?? 0) - (float) $docente['horas_contrata']) < 0.005
            && ! DotacionDocenteExclusion::tieneFueroMaternal($docente['exclusion_docente'] ?? null)
            && data_get($docente, 'exclusion_docente.motivo') !== 'horas_gremiales';
    }

    public function candidatos(Establecimiento $establecimiento, int $anio): Collection
    {
        return DotacionEstablecimientoCalculator::docentes($establecimiento, $anio)
            ->filter(fn (array $docente) => self::elegible($docente))
            ->groupBy(fn (array $docente) => self::bloqueDocente($docente));
    }

    public function reemplazar(Establecimiento $establecimiento, int $anio, string $bloque, string $rut, string $justificacion, ?int $userId): DotacionNoRenovacion
    {
        if (! DotacionNoRenovacion::disponible()) {
            throw ValidationException::withMessages(['habilitacion' => 'Aplique la migración de no renovaciones antes de reemplazar docentes.']);
        }
        $justificacion = trim($justificacion);
        if ($anio < 2027 || $anio > 2100 || ! isset(self::BLOQUES[$bloque])) {
            throw ValidationException::withMessages(['bloque' => 'Seleccione un bloque válido del año 2027 o posterior.']);
        }
        if (mb_strlen($justificacion) < 10 || mb_strlen($justificacion) > 3000) {
            throw ValidationException::withMessages(['justificacion' => 'Fundamente la no renovación con entre 10 y 3000 caracteres.']);
        }

        return DB::transaction(function () use ($establecimiento, $anio, $bloque, $rut, $justificacion, $userId): DotacionNoRenovacion {
            // Todas las escrituras de asignación y reemplazo toman primero este
            // bloqueo: una selección anterior al retiro no puede crear horas nuevas.
            Establecimiento::query()->whereKey($establecimiento->id)->lockForUpdate()->firstOrFail();
            $normalizado = DotacionEstablecimientoCalculator::normalizeRut($rut);
            $docente = DotacionEstablecimientoCalculator::docentes($establecimiento, $anio)
                ->first(fn (array $item) => $item['rut_normalizado'] === $normalizado);
            if (! $docente || ! self::elegible($docente) || self::bloqueDocente($docente) !== $bloque) {
                throw ValidationException::withMessages(['docente_rut' => 'Seleccione un docente íntegramente a contrata, sin contrato protegido, del bloque indicado.']);
            }

            $totalCent = (int) round((float) $docente['horas_contrata'] * 100);
            if ($totalCent <= 0 || $totalCent > 4400) {
                throw ValidationException::withMessages(['docente_rut' => 'Revise el contrato del docente: debe tener entre 0,01 y 44 horas.']);
            }
            $asignaciones = DotacionDocenteAsignacion::query()->with('establecimientoCurso.curso')
                ->where('establecimiento_id', $establecimiento->id)->where('anio', $anio)
                ->where('docente_rut_normalizado', $normalizado)->where('estado', 'activa')
                ->lockForUpdate()->get();
            $consolidadas = DotacionContratoPlanCalculator::consolidar($asignaciones);
            $porBloque = $consolidadas->groupBy(fn ($asignacion) => self::bloqueAsignacion($asignacion, $docente))
                ->map(fn (Collection $items) => (int) round((float) $items->sum('horas_contrato') * 100))->all();
            $ocupadas = array_sum($porBloque);
            if ($ocupadas > $totalCent) {
                throw ValidationException::withMessages(['docente_rut' => 'El docente tiene horas sobreasignadas. Corrija sus asignaciones antes de reemplazarlo.']);
            }
            $porBloque[$bloque] = ($porBloque[$bloque] ?? 0) + $totalCent - $ocupadas;
            $registro = DotacionNoRenovacion::create([
                'establecimiento_id' => $establecimiento->id, 'anio' => $anio,
                'docente_rut_normalizado' => $normalizado, 'docente_nombre' => $docente['nombre'],
                'titulo' => $docente['titulo'], 'bloque' => $bloque, 'horas_contrata' => $totalCent / 100,
                'justificacion' => $justificacion, 'asignaciones_anteriores' => $asignaciones->map->getAttributes()->all(),
                'clave_activa' => hash('sha256', $establecimiento->id.'|'.$anio.'|'.$normalizado), 'created_by' => $userId,
            ]);
            foreach ($porBloque as $destino => $centavos) {
                if ($centavos <= 0) {
                    continue;
                }
                DB::table('dotacion_contrata_habilitaciones')->insert([
                    'establecimiento_id' => $establecimiento->id, 'anio' => $anio, 'bloque' => $destino,
                    'horas' => $centavos / 100, 'no_renovacion_id' => $registro->id,
                    'titulo' => $docente['titulo'], 'funcion' => $docente['funcion'],
                    'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            foreach ($asignaciones as $asignacion) {
                // Conserva los registros y el detalle previo para consulta; el
                // cupo queda vacío y debe asociarse y asignarse expresamente.
                $asignacion->forceFill(['estado' => 'no_renovada', 'updated_by' => $userId])->save();
            }

            return $registro;
        });
    }

    public function anular(Establecimiento $establecimiento, int $anio, int $id, ?int $userId): bool
    {
        if (! DotacionNoRenovacion::disponible()) {
            throw ValidationException::withMessages(['habilitacion' => 'Aplique la migración de no renovaciones antes de anular un retiro.']);
        }

        return DB::transaction(function () use ($establecimiento, $anio, $id, $userId): bool {
            Establecimiento::query()->whereKey($establecimiento->id)->lockForUpdate()->firstOrFail();
            $registro = DotacionNoRenovacion::query()->whereKey($id)
                ->where('establecimiento_id', $establecimiento->id)->where('anio', $anio)
                ->whereNull('anulada_at')->lockForUpdate()->first();
            if (! $registro) {
                return false;
            }
            $cupos = DB::table('dotacion_contrata_habilitaciones')->where('no_renovacion_id', $registro->id)
                ->where('establecimiento_id', $establecimiento->id)->where('anio', $anio)->lockForUpdate()->get();
            $ruts = $cupos->map(fn ($row) => DotacionEstablecimientoCalculator::normalizeRut(
                ContratacionHabilitacionService::rutVirtual((int) $row->id)
            ));
            if (Schema::hasTable('dotacion_docente_asignaciones') && DB::table('dotacion_docente_asignaciones')
                ->where('establecimiento_id', $establecimiento->id)->where('anio', $anio)
                ->whereIn('docente_rut_normalizado', $ruts)->where('estado', 'activa')->exists()) {
                throw ValidationException::withMessages(['habilitacion' => 'Retire las asignaciones de todos los cupos de este reemplazo antes de anular la no renovación.']);
            }
            if (Schema::hasTable('dotacion_docente_subsectores')) {
                DB::table('dotacion_docente_subsectores')->where('establecimiento_id', $establecimiento->id)
                    ->where('anio', $anio)->whereIn('docente_rut_normalizado', $ruts)->delete();
            }
            DB::table('dotacion_contrata_habilitaciones')->whereIn('id', $cupos->pluck('id'))->delete();
            // Conserva la justificación y las asignaciones archivadas. Liberar
            // la clave activa reincorpora al docente sin reactivar esas horas.
            $registro->forceFill(['anulada_at' => now(), 'anulada_by' => $userId, 'clave_activa' => null])->save();

            return true;
        });
    }

    public static function bloqueAsignacion(object|array $asignacion, array $docente): string
    {
        $bloque = DotacionProceso2027Calculator::bloqueFuncionPorDocente($asignacion, $docente)
            ?: DotacionProceso2027Calculator::bloqueLibreDisposicionNtOtroDocente($asignacion, null, $docente)
            ?: DotacionProceso2027Calculator::bloqueParaAsignacion($asignacion);

        return match ($bloque) {
            'bloque_1' => 'plan_estudio', 'bloque_2' => 'parvularia', 'bloque_3' => 'pie',
            default => throw ValidationException::withMessages(['docente_rut' => 'Existe una asignación cuyo bloque no pudo identificarse. Revísela antes de reemplazar al docente.']),
        };
    }
}
