<?php

namespace App\Services\Padron;

/** Sugerencia de solo lectura: nunca selecciona IDs ni confirma bajas. */
class PadronRedistribucionService
{
    public function proponer(array $entrante, array $candidatos, array $entrantesRut, array $actualesRut): ?array
    {
        if (count($candidatos) !== 2 || count(array_unique(array_column($candidatos, 'id'))) !== 2
            || ! PadronConciliador::docente($entrante) || $this->contrato($entrante) === null) {
            return null;
        }
        foreach (array_merge($entrantesRut, $actualesRut) as $row) {
            if (! $this->horasValidas($row) || ! ($row['vigente'] ?? true) || ! empty($row['_historico'])) {
                return null;
            }
        }
        $totalAntes = array_sum(array_column($actualesRut, 'jornada'));
        $totalDespues = array_sum(array_column($entrantesRut, 'jornada'));
        if (abs($totalAntes - $totalDespues) > 0.01) {
            return null;
        }
        $financiamiento = PadronConciliador::text($entrante['financiamiento'] ?? '');
        $receptores = array_values(array_filter($candidatos, fn ($r) => PadronConciliador::text($r['financiamiento'] ?? '') === $financiamiento));
        if ($financiamiento === '' || count($receptores) !== 1) {
            return null;
        }
        $receptor = $receptores[0];
        $absorbido = array_values(array_filter($candidatos, fn ($r) => $r['id'] !== $receptor['id']))[0];
        foreach ($candidatos as $row) {
            if ((int) ($row['id'] ?? 0) <= 0 || ! $this->horasValidas($row)
                || ! ($row['vigente'] ?? true) || ! empty($row['_historico'])
                || ! PadronConciliador::docente($row) || $this->contrato($row) !== $this->contrato($entrante)
                || PadronConciliador::rut($row['rut'] ?? '') !== PadronConciliador::rut($entrante['rut'] ?? '')) {
                return null;
            }
            foreach (['rbd', 'estatuto', 'anio'] as $key) {
                if (empty($entrante[$key]) || PadronConciliador::text((string) ($row[$key] ?? '')) !== PadronConciliador::text((string) $entrante[$key])) {
                    return null;
                }
            }
        }
        // Conservar el contrato receptor exige una fecha de ingreso compatible.
        // El escalafón se actualiza desde el archivo, no identifica el receptor.
        if (empty($entrante['fecha_ingreso']) || ($receptor['fecha_ingreso'] ?? null) !== $entrante['fecha_ingreso']
            || PadronConciliador::text($absorbido['financiamiento'] ?? '') === ''
            || (float) $absorbido['jornada'] <= 0 || (float) $receptor['jornada'] <= 0
            || (float) $entrante['jornada'] <= (float) $receptor['jornada']) {
            return null;
        }
        foreach (['jornada', 'jornada_basica', 'jornada_media'] as $key) {
            if (abs((float) $entrante[$key] - (float) $receptor[$key] - (float) $absorbido[$key]) > 0.01) {
                return null;
            }
        }
        return [
            'receptor_id' => (int) $receptor['id'], 'absorbido_id' => (int) $absorbido['id'],
            'financiamiento_origen' => $absorbido['financiamiento'], 'financiamiento_destino' => $entrante['financiamiento'],
            'horas_antes' => (float) $receptor['jornada'], 'horas_absorbidas' => (float) $absorbido['jornada'],
            'horas_despues' => (float) $entrante['jornada'], 'total_antes' => (float) $totalAntes, 'total_despues' => (float) $totalDespues,
        ];
    }

    private function horasValidas(array $row): bool
    {
        foreach (['jornada', 'jornada_basica', 'jornada_media'] as $key) {
            if (! is_numeric($row[$key] ?? null) || ! is_finite((float) $row[$key]) || (float) $row[$key] < 0) {
                return false;
            }
        }
        return true;
    }

    private function contrato(array $row): ?string
    {
        $tipo = PadronConciliador::text($row['tipocontrato'] ?? '');
        $fin = PadronConciliador::text($row['financiamiento'] ?? '');
        if (in_array($fin, ['SEP', 'PIE'], true) && $tipo === 'PLANTA '.$fin) {
            return 'PLANTA';
        }
        return PadronConciliador::tipo($row) === 'regular' ? $tipo : null;
    }
}
