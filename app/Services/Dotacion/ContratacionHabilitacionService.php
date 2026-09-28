<?php

namespace App\Services\Dotacion;

use App\Models\Establecimiento;
use App\Support\DotacionEstablecimientoCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContratacionHabilitacionService
{
    public const BLOQUES = ['parvularia', 'pie'];

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
        return DB::table('dotacion_contrata_habilitaciones')
            ->where('id', $id)
            ->where('establecimiento_id', $establecimiento->getKey())
            ->where('anio', $anio)
            ->delete() === 1;
    }

    protected function resumen(Establecimiento $establecimiento, int $anio): array
    {
        return DotacionEstablecimientoCalculator::build($establecimiento, $anio)['resumen'];
    }
}
