<?php

namespace App\Imports;

use App\Exports\DotacionConvivenciaHorasExport;
use App\Models\Establecimiento;
use App\Support\DotacionConvivenciaAnual;
use App\Support\DotacionHorasCargaMasiva;
use Illuminate\Support\Facades\DB;

class DotacionConvivenciaHorasImport
{
    public function import(string $path, int $anio, ?int $userId): array
    {
        $datos = DotacionHorasCargaMasiva::leer($path, $anio, 'Convivencia', DotacionConvivenciaHorasExport::HEADERS, ['D' => 'horas'], 44);
        DB::transaction(function () use ($anio, $userId, $datos): void {
            Establecimiento::query()->whereIn('id', array_keys($datos['cambios']))->orderBy('id')->lockForUpdate()->get();
            foreach ($datos['cambios'] as $id => $horas) {
                $existe = DB::table(DotacionConvivenciaAnual::TABLE)->where('establecimiento_id', $id)->where('anio', $anio)->exists();
                $values = $horas + ['updated_by' => $userId, 'updated_at' => now()];
                if (! $existe) {
                    $values += ['created_by' => $userId, 'created_at' => now()];
                }
                DB::table(DotacionConvivenciaAnual::TABLE)->updateOrInsert(['establecimiento_id' => $id, 'anio' => $anio], $values);
            }
        });

        return ['cargadas' => count($datos['cambios']), 'omitidas' => $datos['omitidas']];
    }
}
