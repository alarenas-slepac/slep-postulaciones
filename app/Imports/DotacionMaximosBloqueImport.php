<?php

namespace App\Imports;

use App\Exports\DotacionMaximosBloqueExport;
use App\Models\DotacionProceso2027Configuracion;
use App\Models\Establecimiento;
use App\Support\DotacionHorasCargaMasiva;
use Illuminate\Support\Facades\DB;

class DotacionMaximosBloqueImport
{
    public function import(string $path, int $anio, ?int $userId): array
    {
        $datos = DotacionHorasCargaMasiva::leer($path, $anio, 'Máximos', DotacionMaximosBloqueExport::HEADERS, DotacionMaximosBloqueExport::COLUMNAS, 9999);
        DB::transaction(function () use ($anio, $userId, $datos): void {
            Establecimiento::query()->whereIn('id', array_keys($datos['cambios']))->orderBy('id')->lockForUpdate()->get();
            foreach ($datos['cambios'] as $id => $maximos) {
                $config = DotacionProceso2027Configuracion::firstOrNew(['establecimiento_id' => $id, 'anio' => $anio]);
                $config->fill($maximos);
                $config->maximos_configurados_by = $userId;
                $config->maximos_configurados_at = now();
                $config->save();
            }
        });

        return ['cargadas' => count($datos['cambios']), 'omitidas' => $datos['omitidas']];
    }
}
