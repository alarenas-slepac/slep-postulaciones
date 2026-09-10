<?php

namespace App\Services\Padron;

use App\Models\PadronRevision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Diagnóstico de solo lectura. No autoriza ni modifica asignaciones. */
class PadronConflictosAsignacionService
{
    public function snapshot(?int $anio): array
    {
        $hash = hash_init('sha256');
        hash_update($hash, 'cobertura-v5-bajas-diferidas');
        $read = static function (string $table, array $columns, bool $annual = false) use ($anio, $hash): array {
            hash_update($hash, $table);
            if (! Schema::hasTable($table)) {
                hash_update($hash, 'ausente');
                return [];
            }
            $rows = [];
            $keep = array_flip($columns);
            // Lotes acotados también con PDO MySQL buffered. La huella incluye
            // TODAS las columnas, pero no retenemos observaciones ni adjuntos.
            foreach (DB::table($table)->when($annual && $anio, fn ($q) => $q->where('anio', $anio))
                ->when($table === 'dotacion_docente_asignaciones', fn ($q) => $q->where('estado', 'activa'))
                ->lazyById(100) as $row) {
                hash_update($hash, json_encode($row, JSON_THROW_ON_ERROR)."\n");
                $resumen = array_intersect_key((array) $row, $keep);
                if ($table === 'dotacion_docente_asignaciones') {
                    $resumen['_huella'] = hash('sha256', json_encode($row, JSON_THROW_ON_ERROR));
                }
                $rows[] = $resumen;
            }
            return $rows;
        };
        $data = [
            'asignaciones' => $read('dotacion_docente_asignaciones', ['id', 'anio', 'establecimiento_id',
                'reemplazos_personal_id', 'docente_rut', 'docente_rut_normalizado', 'tipo_asignacion',
                'asignatura_nombre', 'horas_contrato', 'estamento_cobertura'], true),
            'declaraciones' => $read('declaracion_sostenedores', ['id', 'rut', 'rbd', 'estamento', 'horas_contratadas']),
            'exclusiones' => $read('dotacion_docente_exclusiones', ['id', 'establecimiento_id', 'docente_rut', 'docente_rut_normalizado', 'horas'], true),
        ];
        return $data + ['hash' => hash_final($hash)];
    }

    public function analizar(PadronRevision $revision): array
    {
        $filas = $revision->filas()->orderBy('id')->get(['id', 'fila_excel', 'accion', 'personal_id', 'rut', 'datos']);
        $resolucion = app(PadronResolucionService::class);
        $decisiones = $resolucion->disponible() ? $resolucion->decisiones($revision) : collect();
        $resumen = $resolucion->resumen($filas, $decisiones);
        $selecciones = $resumen['selecciones'];
        $establecimientos = DB::table('establecimientos')->orderBy('id')->get()->keyBy('id');
        $estPorRbd = $establecimientos->keyBy('rbd');
        $snapshot = $this->snapshot($revision->anio);
        $snapshot['declaraciones_por_rut'] = collect($snapshot['declaraciones'])
            ->groupBy(fn ($d) => PadronConciliador::rut($d['rut']))->map(fn ($rows) => $rows->all())->all();
        $snapshot['exclusiones_por_rut'] = collect($snapshot['exclusiones'])
            ->keyBy(fn ($e) => $e['establecimiento_id'].'|'.PadronConciliador::rut(($e['docente_rut_normalizado'] ?? null) ?: ($e['docente_rut'] ?? '')))->all();
        $destinos = [];
        $grupos = [];
        $pendientes = [];
        // El caso pertenece al RUT completo, no a la última fila resuelta.
        // Incluye ausencias pendientes, pero no las ya vinculadas a un ID
        // seleccionado, las bajas confirmadas ni reemplazos omitidos.
        foreach ($filas as $fila) {
            if (in_array($resumen['estados'][$fila->id], ['pendiente', 'error'], true)) {
                $rut = PadronConciliador::rut($fila->rut);
                $pendientes[$rut] = ($pendientes[$rut] ?? 0) + 1;
            }
        }
        foreach ($filas->whereNotNull('fila_excel') as $fila) {
            if ($fila->accion === PadronReemplazosVigentes::OMITIDO) {
                continue;
            }
            $data = array_intersect_key($fila->datos, array_flip(['rbd', 'tipocontrato', 'financiamiento', 'estatuto', 'escalafon', 'jornada']));
            $rut = PadronConciliador::rut($fila->rut);
            $estId = (int) ($estPorRbd[$data['rbd'] ?? 0]->id ?? 0);
            $key = $rut.'|'.$estId;
            if (! array_key_exists($fila->id, $selecciones)) {
                continue;
            }
            $id = $selecciones[$fila->id];
            if ($id !== null) {
                $destinos[(int) $id] = ['datos' => $data, 'establecimiento_id' => $estId, 'rut' => $rut, 'fila' => $fila->fila_excel];
            }
            if ($fila->accion !== 'error' && PadronConciliador::tipo($data) === 'regular' && $estId) {
                $grupos[$key][] = $data;
            }
        }
        // El detalle agrupado no necesita conservar otra colección de modelos
        // ni nombres/fechas/documentos de todas las filas del archivo.
        $bajasAsignaciones = app(PadronBajaAsignacionesService::class)->evaluar($revision, $filas, $resumen['estados'], $snapshot['asignaciones']);
        unset($filas, $fila, $decisiones, $selecciones, $resumen, $data);
        $personal = DB::table('reemplazos_personal')->whereIn('id', array_filter(array_column($snapshot['asignaciones'], 'reemplazos_personal_id')))
            ->get(['id', 'rut', 'tipocontrato', 'financiamiento', 'estatuto', 'jornada'])->keyBy('id');
        $porGrupo = [];
        foreach ($snapshot['asignaciones'] as $asignacion) {
            $id = $asignacion['reemplazos_personal_id'] ?? null;
            $rut = PadronConciliador::rut(($asignacion['docente_rut_normalizado'] ?? null) ?: ($asignacion['docente_rut'] ?? null));
            if ($rut === '') {
                $rut = PadronConciliador::rut($personal[$id]->rut ?? null);
            }
            $asignacion['_rut'] = $rut;
            $key = $rut !== '' ? $rut.'|'.$asignacion['establecimiento_id'] : 'sin_identidad|'.$asignacion['id'];
            $porGrupo[$key][] = $asignacion;
        }
        $asignacionesRevisadas = count($snapshot['asignaciones']);
        unset($snapshot['asignaciones'], $asignacion);
        $actuales = $this->contratosActuales($revision->anio, $porGrupo);
        $items = [];
        $gruposResultado = [];
        $errores = [];
        if (! $revision->anio || $revision->errores) {
            $errores[] = 'Corrija el archivo y su período antes de considerar definitivo el diagnóstico de cobertura.';
        }
        foreach ($porGrupo as $key => $asignaciones) {
            $rut = $asignaciones[0]['_rut'];
            $estId = (int) $asignaciones[0]['establecimiento_id'];
            $est = $establecimientos[$estId] ?? null;
            $rows = $grupos[$key] ?? [];
            $cobertura = $this->cobertura($rows, $rut, (string) ($est->rbd ?? ''), $estId, $snapshot);
            $rowsActuales = $actuales[$key] ?? [];
            $coberturaActual = $this->cobertura($rowsActuales, $rut, (string) ($est->rbd ?? ''), $estId, $snapshot);
            if ($coberturaActual['fuente'] === 'Padrón propuesto') {
                $coberturaActual['fuente'] = 'Padrón actual';
            }
            $total = round(array_sum(array_map(fn ($a) => max(0, (float) ($a['horas_contrato'] ?? 0)), $asignaciones)), 2);
            $comparacion = $this->compararCobertura($total, $rowsActuales, $coberturaActual, $cobertura);
            $detalles = [];
            $motivosGrupo = [];
            $avisosGrupo = [];
            foreach ($asignaciones as $a) {
                $id = $a['reemplazos_personal_id'] ?? null;
                $destino = $destinos[$id] ?? null;
                $antes = isset($personal[$id]) ? (array) $personal[$id] : [];
                $motivos = [];
                $avisos = [];
                if ($rut === '' || ! $est) {
                    $motivos['identidad'] = 'No se pudo identificar el RUT o establecimiento de la asignación.';
                }
                if (isset($pendientes[$rut])) {
                    $motivos['correspondencia'] = 'Quedan '.$pendientes[$rut].' líneas del RUT pendientes, incluidas las ausencias por revisar o errores de archivo. Resuelva todas antes de dar por cerrado este conflicto.';
                }
                if ($id && ! $destino) {
                    $motivos['id_sin_destino'] = 'El ID contractual quedaría sin seleccionar o propuesto para baja. Otra línea del mismo RUT no reemplaza este vínculo.';
                } elseif ($destino) {
                    if ($destino['establecimiento_id'] !== $estId) {
                        $motivos['traslado'] = 'El ID contractual se trasladaría al RBD '.($destino['datos']['rbd'] ?? 'desconocido').'; la asignación permanece en su establecimiento original.';
                    }
                    if ($destino['rut'] !== $rut) {
                        $motivos['rut_incompatible'] = 'El RUT del ID seleccionado no coincide con el RUT de la asignación.';
                    }
                    if (PadronConciliador::tipo($destino['datos']) !== 'regular') {
                        $motivos['contrato_excluido'] = 'El contrato pasaría a reemplazo/suplencia o a un tipo sin clasificar, no utilizable en Dotación.';
                    }
                    foreach (['tipocontrato' => 'Contrato', 'financiamiento' => 'Financiamiento', 'estatuto' => 'Estatuto'] as $field => $label) {
                        if (PadronConciliador::text($antes[$field] ?? '') !== PadronConciliador::text($destino['datos'][$field] ?? '')) {
                            $avisos[] = $label.': '.($antes[$field] ?? '—').' → '.($destino['datos'][$field] ?? '—').'.';
                        }
                    }
                    if ((float) ($destino['datos']['jornada'] ?? 0) < (float) ($antes['jornada'] ?? 0)) {
                        $avisos[] = 'Reducción de jornada del ID: '.$antes['jornada'].' → '.$destino['datos']['jornada'].' h. La cobertura se contrasta por RUT y establecimiento.';
                    }
                }
                if (! $rows) {
                    $motivos['sin_contrato_regular'] = 'No hay una línea regular propuesta para este RUT en el establecimiento. Una declaración por sí sola no incorpora personal al padrón.';
                }
                if ($cobertura['ambigua']) {
                    $motivos['composicion'] = $cobertura['ambigua'];
                }
                $estamento = $a['estamento_cobertura'] ?? 'docente';
                $estamento = $estamento ?: 'docente';
                if ($rows && $cobertura['estamento'] !== $estamento) {
                    $motivos['estamento'] = 'La asignación es de '.$estamento.' y la cobertura propuesta corresponde a '.($cobertura['estamento'] ?? 'un estamento no determinado').'.';
                }
                if (! is_numeric($a['horas_contrato'] ?? null) || (float) $a['horas_contrato'] < 0) {
                    $motivos['horas_invalidas'] = 'Las horas contractuales de la asignación no son válidas; revise su registro en Dotación.';
                }
                if ($total > $cobertura['horas'] + 0.01) {
                    if ($comparacion['estado'] === 'preexistente') {
                        $avisos[] = 'Exceso preexistente: '.$comparacion['exceso_actual'].' h antes y '.$comparacion['exceso_propuesto'].' h con la propuesta. La carga no lo agrava; revise las asignaciones en Dotación. Este exceso por sí solo no bloquea.';
                    } else {
                        $motivos['cobertura_insuficiente'] = $total.' h asignadas al RUT/establecimiento superan las '.$cobertura['horas'].' h de cobertura propuesta ('.$cobertura['fuente'].'). '
                            .match ($comparacion['estado']) {
                                'nuevo' => 'La propuesta genera un exceso nuevo.',
                                'agravado' => 'La propuesta aumenta el exceso de '.$comparacion['exceso_actual'].' a '.$comparacion['exceso_propuesto'].' h.',
                                default => 'No hay una cobertura anterior comparable que permita acreditar un exceso preexistente.',
                            };
                    }
                }
                if ($bajasAsignaciones[$rut]['confirmada'] ?? false) {
                    // Solo se omiten conflictos que la baja y la liberación
                    // confirmadas resuelven juntas. Identidad/estamento/errores
                    // distintos siguen bloqueando la aplicación completa.
                    unset($motivos['id_sin_destino'], $motivos['sin_contrato_regular'], $motivos['cobertura_insuficiente']);
                    $avisos[] = 'Baja con liberación confirmada: estas asignaciones se inactivarán únicamente al aplicar el padrón. Sus horas quedarán disponibles; el historial se conserva.';
                }
                $item = [
                    'asignacion_id' => $a['id'], 'personal_id' => $id, 'rut' => $rut,
                    'establecimiento_id' => $estId, 'rbd' => $est->rbd ?? null,
                    'establecimiento' => $est->nombre_establecimiento ?? 'Establecimiento #'.$estId,
                    'tipo' => $a['tipo_asignacion'] ?? '', 'asignatura' => $a['asignatura_nombre'] ?? '',
                    'horas' => $a['horas_contrato'], 'total_asignadas' => $total,
                    'cobertura' => $cobertura, 'motivos' => $motivos, 'avisos' => $avisos,
                    'cobertura_actual' => $coberturaActual, 'comparacion' => $comparacion,
                    'fila_excel' => $destino['fila'] ?? null, 'bloqueante' => (bool) $motivos,
                ];
                $detalles[] = $item;
                if ($motivos || $avisos) {
                    // Compatibilidad: se conserva el detalle plano para consumidores existentes.
                    $items[] = $item;
                }
                $motivosGrupo = array_merge($motivosGrupo, array_values($motivos));
                $avisosGrupo = array_merge($avisosGrupo, $avisos);
            }
            if (! $motivosGrupo && ! $avisosGrupo) {
                continue;
            }
            $motivosGrupo = array_values(array_unique($motivosGrupo));
            $avisosGrupo = array_values(array_unique($avisosGrupo));
            foreach ($motivosGrupo as $motivo) {
                $errores[] = 'RUT '.($rut ?: 'sin identificar').' / RBD '.($est->rbd ?? 'sin identificar').': '.$motivo;
            }
            $gruposResultado[] = [
                'rut' => $rut, 'establecimiento_id' => $estId, 'rbd' => $est->rbd ?? null,
                'establecimiento' => $est->nombre_establecimiento ?? 'Establecimiento #'.$estId,
                'total_asignadas' => $total, 'cobertura' => $cobertura, 'cobertura_actual' => $coberturaActual,
                'comparacion' => $comparacion, 'motivos' => $motivosGrupo, 'avisos' => $avisosGrupo,
                'bloqueante' => (bool) $motivosGrupo, 'asignaciones' => $detalles,
                'correspondencias_pendientes' => $pendientes[$rut] ?? 0,
                'baja_asignaciones' => $bajasAsignaciones[$rut] ?? null,
            ];
        }
        usort($gruposResultado, fn ($a, $b) => ((int) $b['bloqueante'] <=> (int) $a['bloqueante'])
            ?: strcmp($a['rut'], $b['rut']) ?: ($a['establecimiento_id'] <=> $b['establecimiento_id']));
        return ['items' => $items, 'grupos' => $gruposResultado, 'errores' => array_values(array_unique($errores)),
            'bajas_asignaciones' => $bajasAsignaciones,
            'grupos_revisados' => count($porGrupo),
            'grupos_bloqueantes' => count(array_filter($gruposResultado, fn ($g) => $g['bloqueante'])),
            'grupos_avisos' => count(array_filter($gruposResultado, fn ($g) => ! $g['bloqueante'])),
            'grupos_preexistentes' => count(array_filter($gruposResultado, fn ($g) => $g['comparacion']['estado'] === 'preexistente')),
            'asignaciones_revisadas' => $asignacionesRevisadas,
            'bloqueantes' => count(array_filter($items, fn ($item) => $item['bloqueante'])),
            'avisos' => count(array_filter($items, fn ($item) => ! $item['bloqueante']))];
    }

    /** Misma base temporal anual de Dotación, sin limitarse a IDs enlazados. */
    private function contratosActuales(?int $anio, array $porGrupo): array
    {
        if (! $anio) {
            return [];
        }
        $ids = [];
        foreach ($porGrupo as $asignaciones) {
            $ids[(int) $asignaciones[0]['establecimiento_id']] = true;
        }
        $actuales = [];
        foreach (array_keys($ids) as $estId) {
            $query = app(PadronPeriodoService::class)->consultaAnual($estId, $anio)
                ->select(['id', 'rut', 'establecimiento_id', 'jornada', 'tipocontrato', 'financiamiento', 'estatuto', 'escalafon']);
            // No materializar nombres, documentos ni todas las filas de la base.
            foreach ($query->toBase()->lazyById(100) as $registro) {
                $row = (array) $registro;
                $key = PadronConciliador::rut($row['rut']).'|'.$estId;
                if (isset($porGrupo[$key]) && $this->esContratoActualRegular($row)) {
                    $actuales[$key][] = $row;
                }
            }
        }
        return $actuales;
    }

    /** Compatibilidad de lectura; no amplía los tipos admitidos del archivo nuevo. */
    private function esContratoActualRegular(array $row): bool
    {
        $tipo = PadronConciliador::tipo($row);
        if ($tipo !== 'por_clasificar') {
            return $tipo === 'regular';
        }
        $financiamiento = PadronConciliador::text($row['financiamiento'] ?? '');
        if (! in_array($financiamiento, ['SEP', 'PIE'], true)) {
            return false;
        }
        // Solo denominaciones históricas ya reconocidas por la conciliación.
        // El contrato completo y su financiamiento deben coincidir exactamente.
        $contrato = PadronConciliador::text($row['tipocontrato'] ?? '');
        foreach (['PLANTA', 'CONTRATA', 'INDEFINIDO', 'PLAZO FIJO'] as $base) {
            if ($contrato === $base.' '.$financiamiento) {
                return true;
            }
        }
        return false;
    }

    private function compararCobertura(float $total, array $actuales, array $actual, array $propuesta): array
    {
        $comparable = (bool) $actuales && ! $actual['ambigua'] && $actual['estamento'] !== null
            && $actual['estamento'] === $propuesta['estamento'] && ! $propuesta['ambigua'];
        foreach ($actuales as $row) {
            if (! is_numeric($row['jornada'] ?? null) || (float) $row['jornada'] < 0) {
                $comparable = false;
            }
        }
        $antes = $comparable ? round(max(0, $total - $actual['horas']), 2) : null;
        $despues = round(max(0, $total - $propuesta['horas']), 2);
        $estado = match (true) {
            ! $comparable => 'no_comparable',
            $despues <= 0.01 => $antes > 0.01 ? 'resuelto' : 'sin_exceso',
            $antes <= 0.01 => 'nuevo',
            $despues > $antes + 0.01 => 'agravado',
            default => 'preexistente',
        };
        return ['estado' => $estado, 'comparable' => $comparable,
            'exceso_actual' => $antes, 'exceso_propuesto' => $despues];
    }

    private function cobertura(array $rows, string $rut, string $rbd, int $estId, array $snapshot): array
    {
        // Misma selección que declaracionesPorRut en Dotación: última declaración
        // compatible por RBD o por RUT almacenado normalizado. Nunca sumar al Excel.
        $declaracion = null;
        foreach (array_reverse($snapshot['declaraciones_por_rut'][$rut] ?? []) as $d) {
            if (PadronConciliador::rut($d['rut']) === $rut
                && ((string) ($d['rbd'] ?? '') === $rbd || (string) $d['rut'] === $rut)) {
                $declaracion = $d;
                break;
            }
        }
        $estamentoDeclarado = $this->estamentoDeclarado($declaracion['estamento'] ?? '');
        $estamentos = array_unique(array_map(fn ($r) => $estamentoDeclarado ?? $this->estamentoPersonal($r), $rows));
        $estamento = count($estamentos) === 1 ? reset($estamentos) : null;
        $horasDeclaradas = (float) ($declaracion['horas_contratadas'] ?? 0);
        $horasArchivo = round(array_sum(array_column($rows, 'jornada')), 2);
        $horas = $rows ? ($horasDeclaradas > 0 ? $horasDeclaradas : $horasArchivo) : 0;
        $ambigua = null;
        if ($rows && ! $estamento) {
            $ambigua = 'Composición contractual mixta o estamento no identificado. Revise el padrón y la declaración antes de aplicar.';
        } elseif ($estamento === 'asistente' && count($rows) > 1 && $horasDeclaradas <= 0) {
            // El consumidor de asistentes usa una línea representativa, no suma
            // todas las jornadas. No afirmar cobertura en este caso ambiguo.
            $horas = 0;
            $ambigua = 'Asistente con varias líneas sin horas declaradas: debe resolverse la jornada representativa, no se suman automáticamente.';
        }
        $excluidas = 0;
        if ($estamento === 'docente') {
            $excluidas = max(0, (float) ($snapshot['exclusiones_por_rut'][$estId.'|'.$rut]['horas'] ?? 0));
        }
        return ['horas' => round(max(0, $horas - $excluidas), 2), 'horas_archivo' => $horasArchivo,
            'horas_declaradas' => $declaracion['horas_contratadas'] ?? null, 'excluidas' => $excluidas,
            'fuente' => $rows && $horasDeclaradas > 0 ? 'Declaración de Sostenedores' : 'Padrón propuesto',
            'estamento' => $estamento, 'ambigua' => $ambigua];
    }

    private function estamentoDeclarado(string $value): ?string
    {
        $value = PadronConciliador::text($value);
        if ($value === 'ASISTENTE' || str_contains($value, 'ASISTENTE DE LA EDUCACION') || str_contains($value, 'AAEE')) {
            return 'asistente';
        }
        return $value === 'DOCENTE' || str_contains($value, 'PROFESOR') || str_contains($value, 'EDUCADOR') ? 'docente' : null;
    }

    private function estamentoPersonal(array $row): ?string
    {
        $value = PadronConciliador::text(($row['estatuto'] ?? '').' '.($row['escalafon'] ?? ''));
        // Los estamentos contradictorios requieren revisión, no cobertura presunta.
        $docente = str_contains($value, 'DOCENTE') || str_contains($value, 'PROFESOR') || str_contains($value, 'EDUCADOR');
        if (str_contains($value, 'PARADOCENTE') || ($docente && (str_contains($value, 'ASISTENTE') || str_contains($value, 'AAEE')))) {
            return null;
        }
        if (PadronConciliador::docente($row)) {
            return 'docente';
        }
        foreach (['ASISTENTE', 'AAEE', 'ASIST EDUC', 'CODIGO DEL TRABAJO', 'LEY 19464', 'LEY 19.464', 'AUXILIAR', 'ADMINISTRATIVO', 'TECNICO', 'PROFESIONAL'] as $tipo) {
            if (str_contains($value, $tipo)) {
                return 'asistente';
            }
        }
        return null;
    }
}
