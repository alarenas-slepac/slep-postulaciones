<?php

namespace App\Services\Padron;

/** Diagnóstico local sin base de datos, sin persistencia ni datos personales en la salida. */
class PadronArchivoDiagnostico
{
    public function analizar(array $incoming): array
    {
        $salt = random_bytes(32);
        $anonymous = [];
        $periods = $rbds = $persons = $types = $issues = [];
        $seniorityPresent = $seniorityFilled = $invalid = 0;
        $dates = [];
        foreach ($incoming as $item) {
            foreach (['fecha_nacimiento', 'fecha_ingreso', 'fecha_termino', 'fecha_antiguedad'] as $field) {
                if (! empty($item['datos'][$field])) {
                    $dates[$item['datos'][$field]] = true;
                }
            }
        }
        $dates = array_keys($dates);
        sort($dates, SORT_STRING);
        $dateRanks = array_flip($dates);
        $allExcesses = collect($incoming)->groupBy('datos.rut')->filter(fn ($group) => $group->sum('datos.jornada') > 44);
        $rawExcesses = $allExcesses->filter(fn ($group) => $group->contains(fn ($item) => PadronConciliador::docente($item['datos'])));
        foreach ($incoming as $item) {
            $data = $item['datos'];
            $persons[$data['rut']] = true;
            $rbds[$data['rbd'] ?? 0] = true;
            if (($data['anio'] ?? 0) >= 2000 && ($data['anio'] ?? 0) <= 2100
                && ($data['mes'] ?? 0) >= 1 && ($data['mes'] ?? 0) <= 12) {
                $period = sprintf('%04d-%02d', $data['anio'], $data['mes']);
                $periods[$period] = ($periods[$period] ?? 0) + 1;
            }
            $type = PadronConciliador::tipo($data);
            $types[$type] = ($types[$type] ?? 0) + 1;
            $seniorityPresent += (int) array_key_exists('fecha_antiguedad', $data);
            $seniorityFilled += (int) ! empty($data['fecha_antiguedad']);
            $invalid += (int) ! empty($item['observaciones']);
            // Conservar igualdades y diferencias para duplicados, sin pasar identidades al conciliador.
            foreach (['rut', 'nombre', 'fecha_nacimiento', 'fecha_ingreso', 'fecha_termino', 'fecha_antiguedad'] as $field) {
                if (isset($data[$field]) && $data[$field] !== '') {
                    $data[$field] = str_starts_with($field, 'fecha_')
                        ? sprintf('FECHA%010d', $dateRanks[$data[$field]]) // Conservar orden cronológico, no fechas reales.
                        : hash_hmac('sha256', $field.'|'.$data[$field], $salt);
                }
            }
            $anonymous[] = array_replace($item, ['datos' => $data]);
        }
        // Catálogo provisional: SOLO valida el archivo, no existencia ni pertenencia del RBD en la BD.
        $report = (new PadronConciliador)->reconcile($anonymous, [], $rbds);
        $duplicates = 0;
        foreach ($report['filas'] as $row) {
            foreach (array_unique($row['observaciones']) as $message) {
                if (str_starts_with($message, 'Reemplazo/suplencia:')) {
                    continue;
                }
                if (str_starts_with($message, 'REEMPLAZO anterior omitido')) {
                    $message = str_contains($message, 'por transición contractual')
                        ? 'REEMPLAZO terminado antes del ingreso al contrato regular posterior; antecedente conservado, sin sumar jornada simultánea.'
                        : 'REEMPLAZO anterior omitido por vigencia y límite de 44 h; el detalle de filas seleccionadas está en la revisión.';
                }
                $issues[$message] ??= ['cantidad' => 0, 'primeras_filas' => []];
                $issues[$message]['cantidad']++;
                if (count($issues[$message]['primeras_filas']) < 20) {
                    $issues[$message]['primeras_filas'][] = $row['fila_excel'];
                }
                $duplicates += (int) str_starts_with($message, 'Fila duplicada');
            }
        }
        arsort($types);
        ksort($periods);
        uasort($issues, fn ($a, $b) => $b['cantidad'] <=> $a['cantidad']);
        return [
            'alcance' => 'Archivo solamente. Sin consultas ni escrituras a BD. No determina altas, bajas, traslados ni cobertura real.',
            'filas' => count($incoming),
            'identificadores_rut_distintos' => count($persons),
            'valores_rbd_distintos' => count($rbds),
            'periodos_validos' => $periods,
            'tipos' => $types,
            'filas_con_columna_antiguedad' => $seniorityPresent,
            'filas_con_antiguedad_valida' => $seniorityFilled,
            'filas_con_errores_lector' => $invalid,
            'filas_duplicadas' => $duplicates,
            'filas_invalidas_conciliacion' => $report['resumen']['error'] ?? 0,
            'rut_docentes_sobre_44_antes_seleccion' => $rawExcesses->count(),
            'rut_todos_estamentos_sobre_44_antes_seleccion' => $allExcesses->count(),
            'filas_de_rut_docentes_sobre_44_antes_seleccion' => $rawExcesses->sum(fn ($group) => $group->count()),
            'reemplazos_anteriores_omitidos' => $report['resumen'][PadronReemplazosVigentes::OMITIDO] ?? 0,
            'rut_docentes_sobre_44_horas' => count($report['excesos']),
            'excesos_primeros_20' => array_slice(array_values($report['excesos']), 0, 20),
            'observaciones' => $issues,
            'errores_globales' => $report['errores'],
            'advertencia_excesos' => 'Suma las líneas no omitidas por RUT, incluidas duplicadas o inválidas; corregirlas antes de autorizar excepciones. Con períodos mezclados no se seleccionan reemplazos automáticamente.',
        ];
    }
}
