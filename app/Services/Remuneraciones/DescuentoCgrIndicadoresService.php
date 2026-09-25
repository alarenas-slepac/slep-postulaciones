<?php

namespace App\Services\Remuneraciones;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DescuentoCgrIndicadoresService
{
    /**
     * Resume todos los registros de la pestaña que cumplen los filtros, no solo la página visible.
     * Los documentos se cuentan por cuota asociada; un PDF compartido cubre cada cuota vinculada.
     */
    public function calcular(Builder $consulta, string $estado): array
    {
        $totales = (clone $consulta)->reorder()
            ->selectRaw('COUNT(*) AS registros, COALESCE(SUM(deuda_definitiva_pesos), 0) AS deuda_pesos, COALESCE(SUM(numero_cuotas), 0) AS cuotas')
            ->toBase()->first();

        $indicadores = [
            'registros' => (int) $totales->registros,
            'deuda_pesos' => (int) $totales->deuda_pesos,
            'cuotas' => (int) $totales->cuotas,
            'documentos' => 0,
            'listos' => 0,
            'firmados' => 0,
            'cierres_mes' => 0,
        ];

        $tipos = match ($estado) {
            'ingresado' => ['liquidacion'],
            'descuentos_realizados' => ['sigfe', 'tgr'],
            'en_auditoria' => ['liquidacion_validada'],
            default => [],
        };

        if ($tipos !== []) {
            $indicadores['documentos'] = DB::table('descuentos_cgr_archivos')
                ->whereIn('descuento_cgr_id', (clone $consulta)->select('descuentos_cgr.id'))
                ->whereIn('tipo', $tipos)
                ->count();
        }

        if (in_array($estado, ['ingresado', 'descuentos_realizados'], true)) {
            $listos = (clone $consulta)->where('descuentos_cgr.numero_cuotas', '>', 0);
            foreach ($tipos as $tipo) {
                $listos->whereRaw(
                    '(SELECT COUNT(*) FROM descuentos_cgr_archivos WHERE descuentos_cgr_archivos.descuento_cgr_id = descuentos_cgr.id AND descuentos_cgr_archivos.tipo = ?) = descuentos_cgr.numero_cuotas',
                    [$tipo]
                );
            }
            $indicadores['listos'] = $listos->count();
        } elseif ($estado === 'en_auditoria') {
            $indicadores['firmados'] = (clone $consulta)->whereNotNull('certificado_firmado_path')->count();
        } else {
            $indicadores['cierres_mes'] = (clone $consulta)->whereBetween('cerrado_en', [now()->startOfMonth(), now()->endOfMonth()])->count();
        }

        return $indicadores;
    }
}
