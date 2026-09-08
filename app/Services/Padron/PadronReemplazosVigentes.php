<?php

namespace App\Services\Padron;

/** Selección por RUT, nunca por RBD ni por orden físico del Excel. No modifica datos. */
class PadronReemplazosVigentes
{
    public const OMITIDO = 'reemplazo_anterior_omitido';

    public function evaluar(array $filas): array
    {
        $omitidas = $bloqueos = [];
        foreach (collect($filas)->groupBy('rut', preserveKeys: true) as $grupo) {
            $reemplazos = $grupo->filter(fn ($fila) => PadronConciliador::text($fila['datos']['tipocontrato'] ?? '') === 'REEMPLAZO');
            if ($reemplazos->isEmpty()) {
                continue;
            }
            // Ninguna regla de vigencia puede esconder errores o duplicados del archivo.
            if ($grupo->contains(fn ($fila) => $fila['accion'] === 'error')) {
                continue;
            }
            // Primero distinguir una transición contractual de contratos simultáneos.
            // La fecha de término es inclusiva: el nuevo ingreso debe ser estrictamente posterior.
            $regularesPosteriores = $grupo->filter(fn ($fila) => PadronConciliador::tipo($fila['datos']) === 'regular'
                && ! empty($fila['datos']['fecha_ingreso']) && (float) ($fila['datos']['jornada'] ?? 0) > 0);
            $transiciones = [];
            foreach ($reemplazos as $index => $fila) {
                if (empty($fila['datos']['fecha_ingreso']) || empty($fila['datos']['fecha_termino'])) {
                    continue;
                }
                $posteriores = $regularesPosteriores->filter(fn ($posterior) => $fila['datos']['fecha_termino'] < $posterior['datos']['fecha_ingreso']);
                if ($posteriores->isNotEmpty()) {
                    $transiciones[$index] = 'REEMPLAZO anterior omitido por transición contractual: terminó antes del ingreso al contrato regular de la(s) fila(s) '.implode(', ', $posteriores->pluck('fila_excel')->all()).'. No corresponde sumar contratos sucesivos como jornada simultánea. La fila y el historial se conservan.';
                }
            }
            $omitidas += $transiciones;
            $reemplazos = $reemplazos->reject(fn ($fila, $index) => isset($transiciones[$index]));
            $grupo = $grupo->reject(fn ($fila, $index) => isset($transiciones[$index]));
            if ($reemplazos->isEmpty() || $grupo->sum('datos.jornada') <= 44) {
                continue;
            }
            $restante = 44 - (float) $grupo->reject(fn ($fila) => PadronConciliador::text($fila['datos']['tipocontrato'] ?? '') === 'REEMPLAZO')->sum('datos.jornada');
            $motivo = null;
            if ($reemplazos->contains(fn ($fila) => empty($fila['datos']['fecha_ingreso']) || empty($fila['datos']['fecha_termino']))) {
                $motivo = 'REEMPLAZO: faltan Fecha_Ingreso o Fecha_Termino para determinar los registros más recientes. Corrija el archivo; no se omiten líneas.';
            }
            $ordenados = $reemplazos->sort(function ($a, $b) {
                return [$b['datos']['fecha_ingreso'] ?? '', $b['datos']['fecha_termino'] ?? '']
                    <=> [$a['datos']['fecha_ingreso'] ?? '', $a['datos']['fecha_termino'] ?? ''];
            })->groupBy(fn ($fila) => ($fila['datos']['fecha_ingreso'] ?? '').'|'.($fila['datos']['fecha_termino'] ?? ''), preserveKeys: true);
            $seleccionadas = [];
            $corte = false;
            $propuestas = [];
            foreach ($ordenados as $lote) {
                $horas = (float) $lote->sum('datos.jornada');
                if (! $corte && $horas <= $restante) {
                    $restante -= $horas;
                    $seleccionadas = array_merge($seleccionadas, $lote->pluck('fila_excel')->all());
                    continue;
                }
                if (! $seleccionadas) {
                    $motivo ??= 'REEMPLAZO: el registro o conjunto empatado más reciente suma '.$horas.' h y supera el margen disponible de '.max(0, $restante).' h, considerando los otros contratos y el máximo de 44 h. Revise fechas y jornadas; no se elige un reemplazo antiguo ni se fracciona una línea.';
                    break;
                }
                // Conservar un prefijo reciente completo. No saltar a contratos más antiguos para llenar cupo.
                $corte = true;
                foreach ($lote as $index => $fila) {
                    $propuestas[$index] = 'REEMPLAZO anterior omitido de la propuesta vigente por límite de 44 h. Se conservan las filas más recientes: '.implode(', ', $seleccionadas).'. Esta fila permanece en la revisión; no se borra historial ni se modifican asignaciones.';
                }
            }
            if ($motivo !== null) {
                foreach ($reemplazos as $index => $fila) {
                    $bloqueos[$index] = $motivo;
                }
            } else {
                $omitidas += $propuestas;
            }
        }
        return ['omitidas' => $omitidas, 'bloqueos' => $bloqueos];
    }
}
