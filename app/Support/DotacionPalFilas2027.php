<?php

namespace App\Support;

/** Consolida la docencia e invierte la tabla cuando necesita aula equivalente. */
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
        $finales = [];
        foreach ($resultado as $fila) {
            unset($fila['_pal_orden']);
            array_push($finales, ...$this->finalizar($fila, $tablas));
        }
        return $finales;
    }

    private function combinar(array $filas, array $tablas): ?array
    {
        $fila = $filas[0]; $niveles = []; $proporciones = []; $lectivas = 0.0; $noLectivas = 0.0;
        $referenciaSga = false; $contratoAula = 0.0;
        foreach ($filas as $origen) {
            if (($origen['M'] ?? null) === '60/40') { return null; }
            if (str_contains($origen['S'] ?? '', 'INCOMPATIBLE:')) { return null; }
            foreach (['B', 'E', 'F', 'I'] as $campo) {
                if (($origen[$campo] ?? null) !== ($fila[$campo] ?? null)) { return null; }
            }
            $niveles[(string) ($origen['D'] ?? '')] = true;
            if (! empty($origen['M'])) { $proporciones[$origen['M']] = true; }
            $calculadas = $this->calcularOrigen($origen, $tablas);
            if ($calculadas === null) { return null; }
            $lectivas += $calculadas[0]; $noLectivas += $calculadas[1];
            if (array_key_exists('_pal_contrato_aula', $origen)) {
                $referenciaSga = true; $contratoAula += (float) $origen['_pal_contrato_aula'];
            } else { $contratoAula += array_sum($calculadas); }
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
        if ($referenciaSga && abs(array_sum($calculadas) - $contratoAula) > 0.00001) { return null; }
        $notas = array_values(array_unique(array_filter(array_column($filas, 'S'))));
        $fila['S'] = 'Consolidado PAL de '.count($filas).' filas de origen. '.implode("\n", $notas);
        if ($referenciaSga) {
            $fila['_pal_contrato_aula'] = round($contratoAula, 4);
            $aula = (float) ($fila['K'] ?? 0) + (float) ($fila['L'] ?? 0);
            $fila['S'] .= ' Docencia validada en conjunto: '.$aula.' h pedagógicas / '.$fila['_pal_contrato_aula'].' h de contrato SGA ('.$fila['M'].').';
        }
        return $fila;
    }

    private function calcularOrigen(array $fila, array $tablas): ?array
    {
        $nativas = $this->calcular($fila, $tablas);
        if (! array_key_exists('_pal_contrato_aula', $fila)) { return $nativas; }
        $contrato = (float) $fila['_pal_contrato_aula'];
        if ($nativas !== null && abs(array_sum($nativas) - $contrato) <= 0.00001) { return $nativas; }
        // El SGA acumula aula y reparte incrementos contractuales. Un tramo
        // puede no equivaler por sí solo a BUSCARV; conserva sus horas reales
        // (45 minutos por hora pedagógica) y su contrato hasta validar el grupo.
        $lectivas = ((float) ($fila['K'] ?? 0) + (float) ($fila['L'] ?? 0)) * 0.75;
        return [$lectivas, $contrato - $lectivas];
    }

    private function finalizar(array $fila, array $tablas): array
    {
        if (! array_key_exists('_pal_contrato_aula', $fila)) { return [$fila]; }
        $contrato = (float) $fila['_pal_contrato_aula'];
        unset($fila['_pal_contrato_aula']);
        $nativas = $this->calcular($fila, $tablas);
        if ($nativas === null || abs(array_sum($nativas) - $contrato) > 0.00001) {
            $aula = (float) ($fila['K'] ?? 0) + (float) ($fila['L'] ?? 0);
            // Se invierte el BUSCARV efectivo, igual que para Parvularia, pero
            // conservando la proporción 65/35 o 60/40 de este tramo.
            $bloques = $aula > 0 && in_array($fila['M'] ?? null, ['65/35', '60/40'], true)
                ? DotacionPalParvularia65::convertir($contrato, $tablas[$fila['M']] ?? []) : null;
            if ($bloques !== null) {
                $resultado = [];
                foreach ($bloques as $i => $bloque) {
                    $parte = $fila;
                    $basica = (float) ($fila['K'] ?? 0);
                    $media = (float) ($fila['L'] ?? 0);
                    $parte['K'] = $basica > 0 ? round($bloque['aula'] * $basica / $aula, 4) : null;
                    $parte['L'] = $media > 0 ? round($bloque['aula'] - (float) $parte['K'], 4) : null;
                    if ($i > 0) { $parte['P'] = $parte['Q'] = $parte['R'] = null; unset($parte['_pal_exclusiones']); }
                    $parte['S'] .= ' Conversión PAL de aula a '.$fila['M'].': '.$bloque['aula'].' h aula equivalentes = '.$bloque['contrato'].' h contrato.'
                        .' Referencia SGA del tramo: '.$aula.' h pedagógicas reales (K: '.$basica.'; L: '.$media.') y '.$contrato.' h contrato.'
                        .' Parte '.($i + 1).' de '.count($bloques).'. Se conservan las fórmulas originales N/O/J; las horas aula equivalentes no son el aula real del SGA.';
                    $resultado[] = $parte;
                }
                return $resultado;
            }
            $fila['S'] .= ' INCOMPATIBLE: la fórmula original no representa '.$aula.' h pedagógicas / '.$contrato.' h de contrato SGA ('.$fila['M'].'). Estas horas no se cargan en las entradas numéricas; J/N/O no representan este tramo.';
            $fila['_pal_exclusiones'][] = ['motivo' => 'Aula '.$fila['M'].' sin equivalencia exacta', 'horas' => $contrato,
                'detalle' => $fila['S']];
            $fila['K'] = $fila['L'] = $fila['M'] = null;
        }
        return [$fila];
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
