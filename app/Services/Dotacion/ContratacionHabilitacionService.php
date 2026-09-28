<?php

namespace App\Services\Dotacion;

use App\Models\Establecimiento;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ContratacionHabilitacionService
{
    public const BLOQUES = ['parvularia', 'pie'];

    public static function rutVirtual(int $id): string
    {
        return 'VACANTE-'.$id;
    }

    public static function idVirtual(?string $rut): ?int
    {
        return preg_match('/^VACANTE[-_](\d+)$/i', trim((string) $rut), $matches)
            ? (int) $matches[1]
            : null;
    }

    public static function esRutVirtual(?string $rut): bool
    {
        return self::idVirtual($rut) !== null;
    }

    /** @return Collection<int, array<string, mixed>> */
    public function docentesVirtuales(Establecimiento $establecimiento, int $anio): Collection
    {
        if (! Schema::hasTable('dotacion_contrata_habilitaciones')) {
            return collect();
        }

        $asignaciones = DotacionAsignacionCalculator::assignmentsByRut($establecimiento, $anio);

        return DB::table('dotacion_contrata_habilitaciones')
            ->where('establecimiento_id', $establecimiento->getKey())
            ->where('anio', $anio)
            ->orderBy('id')
            ->get()
            ->map(function ($cupo) use ($asignaciones): array {
                $rut = self::rutVirtual((int) $cupo->id);
                $rutNormalizado = DotacionEstablecimientoCalculator::normalizeRut($rut);
                $detalle = $asignaciones[$rutNormalizado] ?? [];
                $horas = (float) $cupo->horas;
                $asignadas = round((float) collect($detalle['items'] ?? [])->sum('horas_contrato'), 2);
                $parvularia = $cupo->bloque === 'parvularia';

                return [
                    'rut' => $rut,
                    'rut_normalizado' => $rutNormalizado,
                    'nombre' => 'Docente por contratar #'.$cupo->id.' · '.($parvularia ? 'Parvularia' : 'PIE'),
                    'titulo' => $parvularia ? 'Pedagogía en Educación de Párvulos' : 'Pedagogía en Educación Diferencial',
                    'funcion' => $parvularia ? 'Educadora de Párvulos por contratar' : 'Educadora Diferencial por contratar',
                    'estamento' => 'Docente',
                    'estamento_cobertura' => 'docente',
                    'horas_contrato' => $horas,
                    'horas_contrato_base' => $horas,
                    'horas_planta' => 0.0,
                    'horas_contrata' => $horas,
                    'horas_asignadas_total' => $asignadas,
                    'diferencia' => round($horas - $asignadas, 2),
                    'asignaciones' => $detalle['items'] ?? collect(),
                    'tipo_contrato' => 'Por contratar',
                    'fecha_antiguedad' => null,
                    'tramo' => null,
                    'declaracion' => null,
                    'fuente_contrato' => 'dotacion_contrata_habilitaciones',
                    'cupo_contrata_id' => (int) $cupo->id,
                    'cupo_bloque' => (string) $cupo->bloque,
                ];
            });
    }

    public function docenteVirtual(Establecimiento $establecimiento, int $anio, string $rut): ?array
    {
        $id = self::idVirtual($rut);
        if ($id === null) {
            return null;
        }

        return $this->docentesVirtuales($establecimiento, $anio)
            ->first(fn (array $docente) => $docente['cupo_contrata_id'] === $id);
    }

    public static function brecha(array $resumen, string $bloque): float
    {
        return match ($bloque) {
            'parvularia' => round((float) ($resumen['contrato_educacion_parvularia_mas_trabajo_colaborativo_pie'] ?? 0)
                - (float) ($resumen['horas_contrato_docentes_parvularia'] ?? 0), 2),
            'pie' => round((float) ($resumen['horas_contrato_pie_necesarias'] ?? 0)
                - (float) ($resumen['horas_contrato_docente_pie'] ?? 0), 2),
            default => throw ValidationException::withMessages(['bloque' => 'Bloque de dotación no válido.']),
        };
    }

    public function habilitar(Establecimiento $establecimiento, int $anio, string $bloque, int $cantidad, float $horas, ?int $userId): void
    {
        if (! in_array($bloque, self::BLOQUES, true)) {
            throw ValidationException::withMessages(['bloque' => 'Bloque de dotación no válido.']);
        }

        $horasCent = (int) round($horas * 100);
        if ($cantidad < 1 || $cantidad > 100 || $horasCent < 1 || $horasCent > 4400) {
            throw ValidationException::withMessages(['horas' => 'Indique entre 0,01 y 44 horas por funcionario y una cantidad entre 1 y 100.']);
        }

        DB::transaction(function () use ($establecimiento, $anio, $bloque, $cantidad, $horasCent, $userId): void {
            // Serializa las habilitaciones de ambos bloques del establecimiento.
            Establecimiento::query()->whereKey($establecimiento->getKey())->lockForUpdate()->firstOrFail();
            $resumen = $this->resumen($establecimiento, $anio);
            $brechaCent = (int) round(max(0, self::brecha($resumen, $bloque)) * 100);
            $habilitadasCent = (int) round((float) DB::table('dotacion_contrata_habilitaciones')
                ->where('establecimiento_id', $establecimiento->getKey())
                ->where('anio', $anio)
                ->where('bloque', $bloque)
                ->sum('horas') * 100);

            if ($brechaCent <= 0 || $cantidad * $horasCent > $brechaCent - $habilitadasCent) {
                throw ValidationException::withMessages([
                    'horas' => 'La habilitación supera las horas por contratar disponibles en este bloque.',
                ]);
            }

            $now = now();
            $rows = [];
            for ($index = 0; $index < $cantidad; $index++) {
                $rows[] = [
                    'establecimiento_id' => $establecimiento->getKey(),
                    'anio' => $anio,
                    'bloque' => $bloque,
                    'horas' => $horasCent / 100,
                    'created_by' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('dotacion_contrata_habilitaciones')->insert($rows);
        });
    }

    public function revocar(Establecimiento $establecimiento, int $anio, int $id): bool
    {
        return DB::transaction(function () use ($establecimiento, $anio, $id): bool {
            $cupo = DB::table('dotacion_contrata_habilitaciones')
                ->where('id', $id)
                ->where('establecimiento_id', $establecimiento->getKey())
                ->where('anio', $anio)
                ->lockForUpdate()
                ->first();
            if (! $cupo) {
                return false;
            }

            $asignado = Schema::hasTable('dotacion_docente_asignaciones') && DB::table('dotacion_docente_asignaciones')
                ->where('establecimiento_id', $establecimiento->getKey())
                ->where('anio', $anio)
                ->where('docente_rut_normalizado', DotacionEstablecimientoCalculator::normalizeRut(self::rutVirtual($id)))
                ->where('estado', 'activa')
                ->exists();
            if ($asignado) {
                throw ValidationException::withMessages([
                    'habilitacion' => 'Retire primero las horas asignadas al docente por contratar.',
                ]);
            }

            return DB::table('dotacion_contrata_habilitaciones')->where('id', $id)->delete() === 1;
        });
    }

    protected function resumen(Establecimiento $establecimiento, int $anio): array
    {
        return DotacionEstablecimientoCalculator::build($establecimiento, $anio)['resumen'];
    }
}
