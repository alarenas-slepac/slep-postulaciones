<?php

namespace App\Services\Padron;

use App\Models\ReemplazoPersonal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/** Elegibilidad actual; nunca sustituye la lectura de documentos históricos. */
class PadronVigenciaService
{
    public function consultaActual(): Builder
    {
        // Máximo por establecimiento entre TODAS las filas, después vigencia,
        // y piso global de cargas completas aplicadas. No filtrar por RUT aquí.
        return ReemplazoPersonal::query()->padronVigente();
    }

    public function porRut(string $rut): array
    {
        $normalizado = strtoupper(preg_replace('/[^0-9Kk]/', '', $rut));
        $base = ReemplazoPersonal::query()->whereRaw(
            "REPLACE(REPLACE(REPLACE(UPPER(TRIM(rut)), '.', ''), '-', ''), ' ', '') = ?",
            [$normalizado]
        );
        $maximo = app(PadronPeriodoService::class)->periodoMaximo();
        $vigentes = (clone $base)->where(function ($query) use ($maximo): void {
            $query->whereIn('id', $this->consultaActual()->select('reemplazos_personal.id'))
                // Una fila actual huérfana requiere regularización; no debe
                // convertir silenciosamente al funcionario en postulante.
                ->orWhere(function ($orphan) use ($maximo): void {
                    $orphan->whereNull('establecimiento_id')
                        ->whereRaw('anio * 100 + mes = ?', [$maximo]);
                    if (Schema::hasColumn('reemplazos_personal', 'vigente')) {
                        $orphan->where('vigente', true);
                    }
                });
        })->with('establecimiento:id,rbd,nombre_establecimiento,comuna')
            ->orderByDesc('anio')->orderByDesc('mes')->orderByDesc('id')->get();

        return [
            'vigentes' => $vigentes,
            'tiene_antecedentes' => $vigentes->isNotEmpty() || $base->exists(),
        ];
    }
}
