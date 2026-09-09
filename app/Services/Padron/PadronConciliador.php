<?php

namespace App\Services\Padron;

use Illuminate\Support\Str;

class PadronConciliador
{
    private const IDENTITY = ['rbd', 'fecha_ingreso', 'tipocontrato', 'financiamiento', 'estatuto', 'escalafon', 'jornada', 'jornada_basica', 'jornada_media'];

    public static function rut(?string $value): string
    {
        return strtoupper(preg_replace('/[^0-9Kk]/', '', (string) $value));
    }

    public static function text(?string $value): string
    {
        return Str::of((string) $value)->ascii()->upper()->squish()->toString();
    }

    public static function tipo(array $data): string
    {
        $tipo = self::text($data['tipocontrato'] ?? '');
        if (str_contains($tipo, 'REEMPLAZ') || str_contains($tipo, 'SUPLEN')) {
            return 'reemplazo_suplencia';
        }
        if (in_array($tipo, ['CONTRATA', 'TITULAR', 'PLANTA', 'INDEFINIDO', 'INDEFINIDA', 'PLAZO FIJO', 'CONTRATO INDEFINIDO'], true)) {
            return 'regular';
        }
        return 'por_clasificar';
    }

    public static function docente(array $data): bool
    {
        $text = self::text(($data['estatuto'] ?? '').' '.($data['escalafon'] ?? ''));
        if (str_contains($text, 'ASISTENTE') || str_contains($text, 'AAEE') || str_contains($text, 'PARADOCENTE')) {
            return false;
        }
        return str_contains($text, 'DOCENTE') || str_contains($text, 'PROFESOR') || str_contains($text, 'EDUCADOR');
    }

    public function reconcile(array $incoming, array $current, array $establishments, array $assignments = [], array $declarations = []): array
    {
        $incoming = array_values($incoming);
        $rows = [];
        $errors = [];
        $periods = [];
        $byRut = collect($current)->groupBy(fn ($row) => self::rut($row['rut'] ?? ''));
        $seen = [];
        $indicesByRut = [];
        $assignmentsByRut = [];
        $assignmentsById = [];
        foreach ($assignments as $assignment) {
            $assignmentRut = self::rut(($assignment['docente_rut_normalizado'] ?? null) ?: ($assignment['docente_rut'] ?? ''));
            $assignmentsByRut[$assignmentRut][$assignment['id']] = $assignment;
            if (! empty($assignment['reemplazos_personal_id'])) {
                $assignmentsById[$assignment['reemplazos_personal_id']][$assignment['id']] = $assignment;
            }
        }
        $linkedAssignments = fn ($rut, $id) => array_values(($assignmentsByRut[$rut] ?? []) + ($assignmentsById[$id] ?? []));
        foreach ($incoming as $item) {
            $data = $item['datos'];
            $observations = $item['observaciones'];
            $periods[($data['anio'] ?? '').'-'.($data['mes'] ?? '')] = true;
            if (! isset($establishments[$data['rbd'] ?? 0])) {
                $observations[] = 'RBD no encontrado en establecimientos.';
            }
            $fingerprint = hash('sha256', json_encode($data));
            $seen[$fingerprint][] = count($rows);
            $indicesByRut[$data['rut']][] = count($rows);
            $rows[] = [
                'fila_excel' => $item['fila_excel'], 'rut' => $data['rut'], 'nombre' => $data['nombre'] ?? '',
                'accion' => $observations ? 'error' : 'pendiente', 'personal_id' => null,
                'datos' => $data, 'anterior' => null, 'candidatos' => [],
                'asignaciones' => [], 'observaciones' => $observations,
            ];
        }
        foreach ($seen as $indices) {
            if (count($indices) < 2) {
                continue;
            }
            foreach ($indices as $index) {
                $rows[$index]['accion'] = 'error';
                $rows[$index]['observaciones'][] = 'Fila duplicada en el archivo; revisar, no se descarta automáticamente.';
            }
        }
        if (count($periods) !== 1) {
            $errors[] = 'El archivo debe contener un único año y mes. No se pueden confirmar bajas.';
        }
        $seleccion = count($periods) === 1 ? (new PadronReemplazosVigentes)->evaluar($rows) : ['omitidas' => [], 'bloqueos' => []];
        foreach ($seleccion['omitidas'] as $index => $motivo) {
            $rows[$index]['accion'] = PadronReemplazosVigentes::OMITIDO;
            $rows[$index]['observaciones'][] = $motivo;
        }
        foreach ($seleccion['bloqueos'] as $index => $motivo) {
            $rows[$index]['accion'] = 'error';
            $rows[$index]['observaciones'][] = $motivo;
        }
        $matched = [];
        $mentioned = [];
        foreach (collect($rows)->groupBy('rut') as $rut => $group) {
            $mentioned[$rut] = true;
            $old = collect($byRut->get($rut, []))->keyBy('id');
            $indices = $indicesByRut[$rut];
            // Solo se emparejan firmas inequívocas y únicas en ambos lados.
            foreach ($indices as $index) {
                if ($rows[$index]['accion'] !== 'pendiente') {
                    continue;
                }
                $matches = $old->reject(fn ($row) => isset($matched[$row['id']]))
                    ->filter(fn ($row) => $this->signature($row) === $this->signature($rows[$index]['datos']));
                if ($matches->count() === 1) {
                    $candidate = $matches->first();
                    // Una etiqueta canónica no tiene prioridad sobre otro ID
                    // equivalente con sufijo histórico: sigue siendo ambiguo.
                    $equivalents = $old->reject(fn ($row) => isset($matched[$row['id']]))
                        ->filter(fn ($row) => $this->firmaDenominacionHistorica($row) === $this->signature($rows[$index]['datos']));
                    if ($equivalents->count() > 1) {
                        continue;
                    }
                    $incomingMatches = $group->filter(fn ($row) => $row['accion'] !== PadronReemplazosVigentes::OMITIDO
                        && $this->firmaDenominacionHistorica($row['datos']) === $this->firmaDenominacionHistorica($candidate));
                    if ($incomingMatches->count() === 1) {
                        $this->match($rows[$index], $candidate);
                        $matched[$candidate['id']] = true;
                    }
                }
            }
            $pending = array_values(array_filter($indices, fn ($index) => $rows[$index]['accion'] === 'pendiente'));
            $remaining = $old->reject(fn ($row) => isset($matched[$row['id']]));
            $hasInvalid = $group->contains(fn ($row) => $row['accion'] === 'error');
            if (! $hasInvalid && count($pending) === 1 && $remaining->count() === 1) {
                $candidate = $remaining->first();
                $this->match($rows[$pending[0]], $candidate);
                $matched[$candidate['id']] = true;
            } else {
                $redistribucion = ! $hasInvalid && count($pending) === 1 && count($periods) === 1
                    && ! $group->contains(fn ($r) => $r['accion'] === PadronReemplazosVigentes::OMITIDO)
                    ? (new PadronRedistribucionService)->proponer($rows[$pending[0]]['datos'], $remaining->values()->all(), $group->pluck('datos')->all(), $old->values()->all())
                    : null;
                foreach ($pending as $index) {
                    $rows[$index]['accion'] = $old->isEmpty() ? 'nueva_incorporacion' : 'revision_manual';
                    $rows[$index]['candidatos'] = $remaining->values()->all();
                    if ($redistribucion) {
                        foreach ($rows[$index]['candidatos'] as &$candidato) {
                            if ((int) $candidato['id'] === $redistribucion['receptor_id']) {
                                $candidato['_redistribucion'] = $redistribucion;
                            }
                        }
                        unset($candidato);
                        $rows[$index]['observaciones'][] = 'Posible redistribución sin aumento total: conservar ID '.$redistribucion['receptor_id'].' y revisar baja del ID '.$redistribucion['absorbido_id'].'. Requiere confirmar ambas decisiones por separado; no se aplica automáticamente.';
                    }
                    if ($old->isNotEmpty()) {
                        $rows[$index]['observaciones'][] = 'Varias líneas, cambio de composición o coincidencia incierta. No se propone reemplazar ningún ID.';
                    }
                }
            }
            // Extensión acotada, después de la conciliación existente: no vuelve
            // a ejecutar el emparejamiento residual 1:1 ni resuelve por descarte
            // otros cambios de contrato. Solo corrige etiquetas históricas.
            foreach ($pending as $index) {
                if ($rows[$index]['accion'] !== 'revision_manual' || self::tipo($rows[$index]['datos']) !== 'regular') {
                    continue;
                }
                $matches = $remaining->reject(fn ($candidate) => isset($matched[$candidate['id']]))
                    ->filter(fn ($candidate) => $this->firmaDenominacionHistorica($candidate) === $this->signature($rows[$index]['datos']));
                if ($matches->count() !== 1) {
                    continue;
                }
                $candidate = $matches->first();
                if ($this->contratoHistoricoSinFinanciamiento($candidate) === null) {
                    continue;
                }
                $incomingMatches = $group->filter(fn ($row) => $row['accion'] !== PadronReemplazosVigentes::OMITIDO
                    && $this->firmaDenominacionHistorica($row['datos']) === $this->firmaDenominacionHistorica($candidate));
                if ($incomingMatches->count() !== 1) {
                    continue;
                }
                $rows[$index]['observaciones'] = array_values(array_filter($rows[$index]['observaciones'],
                    fn ($message) => $message !== 'Varias líneas, cambio de composición o coincidencia incierta. No se propone reemplazar ningún ID.'));
                $rows[$index]['observaciones'][] = 'Corrección de denominación histórica: el sufijo SEP/PIE coincide con el financiamiento. Se conserva el ID; no cambia el tipo contractual base ni se omiten diferencias de escalafón, fechas o jornadas.';
                $rows[$index]['candidatos'] = [];
                $this->match($rows[$index], $candidate);
                $matched[$candidate['id']] = true;
            }
        }
        foreach ($rows as &$row) {
            $data = $row['datos'];
            $row['asignaciones'] = $linkedAssignments($row['rut'], $row['personal_id']);
            if (self::tipo($data) === 'por_clasificar') {
                $row['observaciones'][] = 'Tipo de contrato no clasificado: requiere revisión; no se presume regular.';
                if ($row['accion'] !== 'error') {
                    $row['accion'] = 'revision_manual';
                }
            } elseif (self::tipo($data) === 'reemplazo_suplencia') {
                if ($row['accion'] !== PadronReemplazosVigentes::OMITIDO) {
                    $row['observaciones'][] = 'Reemplazo/suplencia: se conserva en padrón, excluido de Dotación y de la selección de titulares.';
                }
            }
            if ($row['asignaciones'] && ($row['anterior']['rbd'] ?? $data['rbd']) != $data['rbd']) {
                $row['observaciones'][] = 'Traslado con asignaciones vinculadas: no se trasladan ni eliminan automáticamente.';
            }
            $declaracion = $declarations[$row['rut']] ?? null;
            if ($declaracion !== null) {
                $row['observaciones'][] = 'Declaración de Sostenedores: '.$declaracion.' h. Mantiene prioridad; el padrón no la sobrescribe.';
            }
        }
        unset($row);
        $invalid = collect($rows)->where('accion', 'error')->count();
        if ($invalid > 0) {
            $errors[] = 'Hay '.$invalid.' fila(s) inválida(s). Las ausencias quedan solo para revisión, nunca como bajas confirmadas.';
        }
        foreach ($current as $old) {
            if (isset($matched[$old['id']]) || ! ($old['vigente'] ?? true)) {
                continue;
            }
            $rut = self::rut($old['rut']);
            $rows[] = [
                'fila_excel' => null, 'rut' => $rut, 'nombre' => $old['nombre'],
                'accion' => isset($mentioned[$rut]) || $errors ? 'ausencia_por_revisar' : 'baja_propuesta',
                'personal_id' => $old['id'], 'datos' => [], 'anterior' => $old, 'candidatos' => [],
                'asignaciones' => $linkedAssignments($rut, $old['id']),
                'observaciones' => ['Ausente o sin correspondencia inequívoca en el archivo. Solo se desactiva al confirmar la aplicación definitiva.'],
            ];
        }
        $excesses = [];
        foreach (collect($incoming)->reject(fn ($item, $index) => isset($seleccion['omitidas'][$index]))->groupBy('datos.rut') as $rut => $group) {
            if (! $group->contains(fn ($item) => self::docente($item['datos']))) {
                continue;
            }
            // Todos los RBD y financiamientos del RUT; Básica/Media no se suman otra vez.
            $total = (float) $group->sum('datos.jornada');
            if ($total > 44) {
                $excesses[(string) $rut] = ['total' => $total, 'exceso' => $total - 44, 'filas' => $group->pluck('fila_excel')->all()];
            }
        }
        return ['filas' => $rows, 'errores' => $errors, 'excesos' => $excesses,
            'resumen' => collect($rows)->countBy('accion')->all()];
    }

    private function contratoHistoricoSinFinanciamiento(array $data): ?string
    {
        $financiamiento = self::text($data['financiamiento'] ?? '');
        if (! in_array($financiamiento, ['SEP', 'PIE'], true)) {
            return null;
        }
        $contrato = self::text($data['tipocontrato'] ?? '');
        foreach (['CONTRATA', 'INDEFINIDO', 'PLAZO FIJO'] as $base) {
            if ($contrato === $base.' '.$financiamiento) {
                return $base;
            }
        }
        return null;
    }

    private function firmaDenominacionHistorica(array $data): string
    {
        $data['tipocontrato'] = $this->contratoHistoricoSinFinanciamiento($data) ?? ($data['tipocontrato'] ?? '');
        return $this->signature($data);
    }

    private function signature(array $data): string
    {
        if ($this->plantaConFinanciamientoSeparado($data)) {
            // Compatibilidad con la denominación antigua: el financiamiento
            // está en su propia columna. Escalafón es un dato a actualizar,
            // no una clave de correspondencia en estas líneas PLANTA SEP/PIE.
            $data['tipocontrato'] = 'PLANTA';
            $data['escalafon'] = '';
        }
        return implode('|', array_map(fn ($key) => self::text((string) ($data[$key] ?? '')), self::IDENTITY));
    }

    private function plantaConFinanciamientoSeparado(array $data): bool
    {
        $financiamiento = self::text($data['financiamiento'] ?? '');
        $contrato = self::text($data['tipocontrato'] ?? '');

        return in_array($financiamiento, ['SEP', 'PIE'], true)
            && in_array($contrato, ['PLANTA', 'PLANTA '.$financiamiento], true);
    }

    private function match(array &$row, array $old): void
    {
        $row['personal_id'] = (int) $old['id'];
        $row['anterior'] = $old;
        if ($this->plantaConFinanciamientoSeparado($old)
            && $this->plantaConFinanciamientoSeparado($row['datos'])
            && $this->signature($old) === $this->signature($row['datos'])
            && (self::text($old['tipocontrato'] ?? '') !== self::text($row['datos']['tipocontrato'] ?? '')
                || self::text($old['escalafon'] ?? '') !== self::text($row['datos']['escalafon'] ?? ''))) {
            $row['observaciones'][] = 'Correspondencia PLANTA con financiamiento SEP/PIE separado: se conserva el ID y se propone actualizar contrato y escalafón con los valores del archivo.';
        }
        $changed = false;
        foreach ($row['datos'] as $key => $value) {
            if (in_array($key, ['anio', 'mes', 'rut'], true) || ($key === 'fecha_antiguedad' && $value === null)) {
                continue;
            }
            if (self::text((string) $value) !== self::text((string) ($old[$key] ?? ''))) {
                $changed = true;
            }
        }
        $row['accion'] = ! ($old['vigente'] ?? true) ? 'reactivacion_propuesta'
            : (($old['rbd'] ?? null) != $row['datos']['rbd'] ? 'traslado_propuesto' : ($changed ? 'actualizacion_propuesta' : 'sin_cambios'));
    }
}
