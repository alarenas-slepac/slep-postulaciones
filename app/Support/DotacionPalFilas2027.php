<?php

namespace App\Support;

/** Consolida entradas únicamente si las fórmulas nativas conservan todos los totales. */
class DotacionPalFilas2027
{
    public function consolidar(array $filas, array $tablas): array
    {
        $grupos = [];
        foreach ($filas as $i => $fila) {
            $fila['_pal_orden'] = $i;
            $rut = DotacionEstablecimientoCalculator::normalizeRut($fila['A'] ?? null);
            $key = $rut !== '' && isset($fila['C']) && (string) $fila['C'] !== ''
                ? json_encode([$rut, (string) $fila['C'], $fila['G'] ?? '', $fila['H'] ?? '']) : 'sin_identidad_'.$i;
            $grupos[$key][] = $fila;
        }
        $resultado = [];
        foreach ($grupos as $grupo) {
            if (count($grupo) === 1) { $resultado[] = $grupo[0]; continue; }
            $unica = $this->combinar($grupo, $tablas);
            if ($unica !== null) { $resultado[] = $unica; continue; }
            // Intenta consolidar subconjuntos cuando no cabe el grupo completo.
            $lotes = [];
            foreach ($grupo as $fila) {
                foreach ($lotes as &$lote) {
                    if ($this->combinar([...$lote, $fila], $tablas) !== null) {
                        $lote[] = $fila; unset($lote); continue 2;
                    }
                }
                unset($lote);
                $lotes[] = [$fila];
            }
            foreach ($lotes as $lote) {
                $fila = count($lote) === 1 ? $lote[0] : $this->combinar($lote, $tablas);
                $fila['S'] = ($fila['S'] ?? '').(($fila['M'] ?? null) === '60/40'
                    ? ' Consolidación PAL: se conservan filas separadas; la proporción 60/40 queda excluida de la consolidación.'
                    : ' Consolidación PAL: se conservan filas separadas por proporción, nivel, incompatibilidad o límites de la tabla original.');
                $resultado[] = $fila;
            }
        }
        usort($resultado, fn ($a, $b) => $a['_pal_orden'] <=> $b['_pal_orden']);
        foreach ($resultado as &$fila) { unset($fila['_pal_orden']); }
        unset($fila);
        return $resultado;
    }

    private function combinar(array $filas, array $tablas): ?array
    {
        $fila = $filas[0]; $niveles = []; $proporciones = []; $lectivas = 0.0; $noLectivas = 0.0;
        foreach ($filas as $origen) {
            if (($origen['M'] ?? null) === '60/40') { return null; }
            if (str_contains($origen['S'] ?? '', 'INCOMPATIBLE:')) { return null; }
            foreach (['B', 'E', 'F', 'I'] as $campo) {
                if (($origen[$campo] ?? null) !== ($fila[$campo] ?? null)) { return null; }
            }
            $niveles[(string) ($origen['D'] ?? '')] = true;
            if (! empty($origen['M'])) { $proporciones[$origen['M']] = true; }
            $calculadas = $this->calcular($origen, $tablas);
            if ($calculadas === null) { return null; }
            $lectivas += $calculadas[0]; $noLectivas += $calculadas[1];
        }
        if (count($proporciones) > 1) { return null; }
        if (count($niveles) > 1) {
            if (array_diff(array_keys($niveles), ['Educación Básica', 'Educación Media', 'Ed. Básica/Media']) !== []) { return null; }
            $fila['D'] = 'Ed. Básica/Media';
        }
        $fila['M'] = array_key_first($proporciones);
        foreach (['K', 'L', 'P', 'Q', 'R'] as $campo) {
            $valores = array_filter(array_column($filas, $campo), fn ($v) => $v !== null);
            $fila[$campo] = $valores === [] ? null : round(array_sum($valores), 4);
        }
        // N y O buscan K+L una sola vez, no una búsqueda por cada nivel.
        $calculadas = $this->calcular($fila, $tablas);
        if ($calculadas === null || abs($calculadas[0] - $lectivas) > 0.00001 || abs($calculadas[1] - $noLectivas) > 0.00001) { return null; }
        $notas = array_values(array_unique(array_filter(array_column($filas, 'S'))));
        $fila['S'] = 'Consolidado PAL de '.count($filas).' filas de origen. '.implode("\n", $notas);
        return $fila;
    }

    private function calcular(array $fila, array $tablas): ?array
    {
        $aula = (float) ($fila['K'] ?? 0) + (float) ($fila['L'] ?? 0);
        if ($aula <= 0) { return [0.0, 0.0]; }
        $regla = $tablas[$fila['M'] ?? ''][number_format($aula, 4, '.', '')] ?? null;
        return isset($regla['lectivas'], $regla['no_lectivas'])
            ? [(float) $regla['lectivas'], (float) $regla['no_lectivas']] : null;
    }
}
