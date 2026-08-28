<?php

namespace App\Support;

use Illuminate\Support\Collection;

class DotacionContratoEnsenanzaCalculator
{
    /**
     * Separa el contrato plan ajustado y el trabajo colaborativo PIE entre
     * Educacion Parvularia y el resto de los niveles. Los grupos combinados
     * reemplazan el contrato individual de sus cursos antes de distribuir el
     * total, de modo que ambos segmentos siempre cuadren con el total real.
     *
     * @return array{
     *     contrato_plan_parvularia: float,
     *     contrato_plan_general: float,
     *     trabajo_colaborativo_pie_parvularia: float,
     *     trabajo_colaborativo_pie_general: float,
     *     contrato_parvularia_mas_pie: float,
     *     contrato_general_mas_pie: float
     * }
     */
    public static function split(
        array $cursos,
        Collection|array $gruposCombinados,
        float $contratoPlanAjustado
    ): array {
        $contratoPlanAjustado = max(0.0, round($contratoPlanAjustado, 2));
        $grupoParvularia = data_get($cursos, 'grupos.parvularia', []);
        $nivelKeys = collect(data_get($grupoParvularia, 'niveles', []));
        $detallesParvularia = $nivelKeys
            ->flatMap(fn ($nivelKey) => collect(data_get($cursos, 'rows.'.$nivelKey.'.detalles', [])))
            ->values();
        $cursoIdsParvularia = $detallesParvularia
            ->pluck('establecimiento_curso_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $gruposActivos = collect($gruposCombinados)
            ->where('activo', true)
            ->values();
        $cursoIdsCombinados = $gruposActivos
            ->flatMap(fn ($grupo) => collect(data_get($grupo, 'miembros', []))->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $contratoParvulariaBruto = (float) data_get(
            $grupoParvularia,
            'totales.horas_contrato_equivalente',
            0
        );
        $contratoParvulariaReemplazado = (float) $detallesParvularia
            ->filter(fn ($detalle) => $cursoIdsCombinados->contains(
                (int) data_get($detalle, 'establecimiento_curso_id', 0)
            ))
            ->sum(fn ($detalle) => (float) data_get(
                $detalle,
                'horas_contrato_equivalente_redondeado',
                0
            ));
        $contratoRefuerzoParvulariaCombinado = (float) $detallesParvularia
            ->filter(fn ($detalle) => $cursoIdsCombinados->contains(
                (int) data_get($detalle, 'establecimiento_curso_id', 0)
            ))
            ->sum(fn ($detalle) => (float) data_get(
                $detalle,
                'horas_contrato_refuerzo_ld_otro_docente',
                0
            ));
        $contratoGruposParvularia = (float) $gruposActivos
            ->filter(fn ($grupo) => self::grupoCorrespondeParvularia($grupo, $cursoIdsParvularia))
            ->sum(fn ($grupo) => (float) data_get($grupo, 'totales.horas_contrato', 0));

        $contratoPlanParvularia = round(max(
            0.0,
            $contratoParvulariaBruto
                - $contratoParvulariaReemplazado
                + $contratoGruposParvularia
                + $contratoRefuerzoParvulariaCombinado
        ), 2);
        $contratoPlanParvularia = min($contratoPlanAjustado, $contratoPlanParvularia);
        $contratoPlanGeneral = max(0.0, round($contratoPlanAjustado - $contratoPlanParvularia, 2));

        $trabajoColaborativoPieTotal = max(0.0, round((float) data_get(
            $cursos,
            'totales.trabajo_colaborativo_pie',
            0
        ), 2));
        $trabajoColaborativoPieParvularia = max(0.0, round((float) data_get(
            $grupoParvularia,
            'totales.trabajo_colaborativo_pie',
            0
        ), 2));
        $trabajoColaborativoPieParvularia = min(
            $trabajoColaborativoPieTotal,
            $trabajoColaborativoPieParvularia
        );
        $trabajoColaborativoPieGeneral = max(
            0.0,
            round($trabajoColaborativoPieTotal - $trabajoColaborativoPieParvularia, 2)
        );

        return [
            'contrato_plan_parvularia' => $contratoPlanParvularia,
            'contrato_plan_general' => $contratoPlanGeneral,
            'trabajo_colaborativo_pie_parvularia' => $trabajoColaborativoPieParvularia,
            'trabajo_colaborativo_pie_general' => $trabajoColaborativoPieGeneral,
            'contrato_parvularia_mas_pie' => round(
                $contratoPlanParvularia + $trabajoColaborativoPieParvularia,
                2
            ),
            'contrato_general_mas_pie' => round(
                $contratoPlanGeneral + $trabajoColaborativoPieGeneral,
                2
            ),
        ];
    }

    private static function grupoCorrespondeParvularia(
        mixed $grupo,
        Collection $cursoIdsParvularia
    ): bool {
        $miembroIds = collect(data_get($grupo, 'miembros', []))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($miembroIds->isEmpty()) {
            return false;
        }

        $miembrosParvularia = $miembroIds
            ->filter(fn ($id) => $cursoIdsParvularia->contains((int) $id))
            ->count();
        if ($miembrosParvularia === $miembroIds->count()) {
            return true;
        }
        if ($miembrosParvularia === 0) {
            return false;
        }

        $proporcionConfigurada = (string) data_get($grupo, 'proporcion', '');
        if (in_array($proporcionConfigurada, ['nt_jec', 'nt_sin_jec'], true)) {
            return true;
        }

        return collect(data_get($grupo, 'asignaturas', []))->contains(
            fn ($asignatura) => str_starts_with(
                (string) data_get($asignatura, 'proporcion_key', ''),
                'parvularia_'
            )
        );
    }
}
