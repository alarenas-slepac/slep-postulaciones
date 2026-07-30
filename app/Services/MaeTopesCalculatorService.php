<?php

namespace App\Services;

use App\Models\MaeCarga;
use App\Models\MaeRegistro;
use App\Models\MaeRegistroDescuento;
use App\Models\MaeRegistroOtroDescuento;
use Illuminate\Database\Eloquent\Builder;
use App\Support\MaeColumnNormalizer;
use App\Support\StreamingXlsxWriter;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class MaeTopesCalculatorService
{
    public function filters(): array
    {
        return [
            'anios' => MaeCarga::query()->distinct()->orderByDesc('anio')->pluck('anio'),
            'dominios' => MaeCarga::query()->distinct()->orderBy('dominio')->pluck('dominio'),
            'cargas' => MaeCarga::query()
                ->orderByDesc('anio')
                ->orderByDesc('mes')
                ->orderBy('dominio')
                ->orderByDesc('version')
                ->get(['id', 'anio', 'mes', 'dominio', 'version', 'es_vigente']),
        ];
    }

    public function paginate(array $filters, int $perPage = 40): array
    {
        $baseQuery = $this->buildRegistroQuery($filters);
        $summary = $this->summarizeQuery(clone $baseQuery, $filters);
        $analizados = $this->collectFilteredAnalyses(clone $baseQuery, $filters);

        $page = max(1, (int) request()->integer('page', 1));
        $total = $analizados->count();
        $items = $analizados->slice(($page - 1) * $perPage, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );

        return [
            'paginator' => $paginator,
            'summary' => $summary,
        ];
    }

    public function export(array $filters, string $outputPath): void
    {
        $writer = new StreamingXlsxWriter($outputPath);

        $sheetResumen = $writer->addSheet('Resumen topes', [
            'Periodo', 'Dominio', 'Version', 'Vigente', 'RUT-DV', 'Nombre', 'Total haberes',
            'Monto maximo 45%', 'Total descuentos', '% total descuento', 'Monto excedido',
            'Patronales excluidos', 'Estado', 'Observaciones',
        ], [12, 18, 10, 10, 15, 36, 18, 18, 18, 18, 18, 18, 18, 48]);

        $sheetDetalle = $writer->addSheet('Detalle descuentos', [
            'Periodo', 'Dominio', 'Version', 'RUT-DV', 'Nombre', 'Orden', 'Descuento / columna',
            'Grupo', 'Subgrupo', 'Monto', 'Cuota actual', 'Total cuotas', 'Mes inicio cuota', 'Prioridad', 'Estado', 'Motivo',
        ], [12, 18, 10, 15, 36, 10, 30, 18, 18, 16, 14, 14, 18, 18, 20, 52]);

        try {
            $this->processFilteredAnalyses($this->buildRegistroQuery($filters), $filters, function (array $analysis) use ($writer, $sheetResumen, $sheetDetalle) {
                $writer->appendRow($sheetResumen, [
                    sprintf('%02d/%04d', $analysis['registro']->mes, $analysis['registro']->anio),
                    $analysis['registro']->dominio,
                    'v' . ($analysis['registro']->carga?->version ?? 0),
                    $analysis['registro']->carga?->es_vigente ? 'Si' : 'No',
                    $analysis['registro']->rut_dv,
                    $analysis['registro']->nombre_completo,
                    $analysis['base_calculo'],
                    $analysis['monto_maximo_endeudamiento'],
                    $analysis['total_descuentos'],
                    $analysis['porcentaje_total_descuento'],
                    $analysis['monto_excedido'],
                    $analysis['totales']['patronal'],
                    $analysis['estado'],
                    $analysis['observaciones_export'],
                ]);

                foreach ($analysis['detalles'] as $detalle) {
                    $detailStyle = match ($detalle['estado_aplicacion']) {
                        'eliminar' => $detalle['primero_sobre_tope'] ? StreamingXlsxWriter::STYLE_HIGHLIGHT_WARNING : StreamingXlsxWriter::STYLE_HIGHLIGHT_DANGER,
                        'revision' => StreamingXlsxWriter::STYLE_HIGHLIGHT_REVIEW,
                        'patronal_excluido' => StreamingXlsxWriter::STYLE_HIGHLIGHT_NEUTRAL,
                        default => StreamingXlsxWriter::STYLE_DEFAULT,
                    };

                    $styleMap = [];
                    if ($detailStyle !== StreamingXlsxWriter::STYLE_DEFAULT) {
                        foreach (range(7, 15) as $columnIndex) {
                            $styleMap[$columnIndex] = $detailStyle;
                        }
                    }

                    $writer->appendRow($sheetDetalle, [
                        sprintf('%02d/%04d', $analysis['registro']->mes, $analysis['registro']->anio),
                        $analysis['registro']->dominio,
                        'v' . ($analysis['registro']->carga?->version ?? 0),
                        $analysis['registro']->rut_dv,
                        $analysis['registro']->nombre_completo,
                        $detalle['orden_resolucion'],
                        $detalle['columna_origen'],
                        $detalle['grupo'],
                        $detalle['subgrupo'],
                        $detalle['valor_original'],
                        $this->cuotaActualExport($detalle['cuota_actual']),
                        $this->totalCuotasExport($detalle['total_cuotas']),
                        $detalle['mes_inicio_cuota_label'],
                        $detalle['prioridad_label'],
                        $detalle['estado_aplicacion'],
                        $detalle['motivo'],
                    ], $styleMap);
                }
            });
        } finally {
            $writer->close();
        }
    }

    public function analyzeRegistro(MaeRegistro $registro): array
    {
        $montoImponible = round(max(0, (float) $registro->monto_imponible), 2);
        $baseCalculo = round(max(0, (float) $registro->total_haberes), 2);
        $montoMaximo = round($baseCalculo * 0.45, 2);
        $observaciones = [];

        $ordered = $this->orderCandidateItems($this->buildCandidateItems($registro));

        $totales = [
            'patronal' => 0.0,
            'aplicable_total' => 0.0,
            'no_aplicable_total' => 0.0,
        ];

        $totalDescuentos = 0.0;
        foreach ($ordered as $item) {
            $descuento = $item['descuento'];
            $valor = round((float) $descuento['valor'], 2);
            if ($item['priority']['patronal']) {
                $totales['patronal'] += $valor;
                continue;
            }
            $totalDescuentos += $valor;
        }
        $totalDescuentos = round($totalDescuentos, 2);
        $porcentaje = $baseCalculo > 0 ? round(($totalDescuentos / $baseCalculo) * 100, 2) : 0.0;
        $montoExcedido = round(max(0, $totalDescuentos - $montoMaximo), 2);

        if ($baseCalculo <= 0) {
            $observaciones[] = 'Registro sin total haberes válido para cálculo.';
        }
        if ($montoExcedido > 0) {
            $observaciones[] = 'El total de descuentos supera el 45% del total haberes.';
        }

        $detalles = [];
        $acumuladoAplicable = 0.0;
        $overflowTriggered = false;
        $firstOverflowMarked = false;
        $ordenResolucion = 1;

        foreach ($ordered as $item) {
            $descuento = $item['descuento'];
            $priority = $item['priority'];
            $valor = round((float) $descuento['valor'], 2);

            $esDescuentoConCuota = $this->hasSortableInstallment($descuento);
            $estado = 'aplicar';
            $motivo = $esDescuentoConCuota
                ? 'Descuento con cuota dentro del 45%, aplicado según el inicio calculado desde el más antiguo al más reciente.'
                : 'Descuento dentro del 45% según el orden de prelación configurado.';
            $valorAplicable = $valor;
            $valorNoAplicable = 0.0;
            $primeroSobreTope = false;

            if ($priority['patronal']) {
                $estado = 'patronal_excluido';
                $motivo = 'Aporte patronal: se excluye del total de descuentos y del análisis del 45%.';
                $valorAplicable = 0.0;
                $valorNoAplicable = 0.0;
            } else {
                if ($baseCalculo <= 0) {
                    $estado = 'revision';
                    $motivo = 'Sin total haberes válido no es posible calcular el 45%; el descuento queda en revisión.';
                    $valorAplicable = 0.0;
                    $valorNoAplicable = $valor;
                } elseif (!$overflowTriggered && $acumuladoAplicable + $valor <= $montoMaximo + 0.0001) {
                    $acumuladoAplicable = round($acumuladoAplicable + $valor, 2);
                } else {
                    $overflowTriggered = true;
                    $estado = 'eliminar';
                    $motivo = $esDescuentoConCuota
                        ? 'Este descuento con cuota queda sobre el 45% después de ordenar las cuotas por su inicio calculado.'
                        : 'Este descuento queda sobre el 45% del total haberes según la prelación definida.';
                    $valorAplicable = 0.0;
                    $valorNoAplicable = $valor;
                    if (!$firstOverflowMarked) {
                        $primeroSobreTope = true;
                        $firstOverflowMarked = true;
                        $motivo = $esDescuentoConCuota
                            ? 'Esta es la primera cuota que empuja el acumulado por sobre el 45%, luego de ordenar todas las cuotas por su inicio calculado.'
                            : 'Este es el primer descuento que empuja el acumulado por sobre el 45% del total haberes.';
                    }
                }
            }

            $totales['aplicable_total'] += $valorAplicable;
            $totales['no_aplicable_total'] += $valorNoAplicable;

            $detalles[] = [
                'id' => $descuento['id'],
                'orden_resolucion' => $ordenResolucion++,
                'columna_origen' => $descuento['columna_origen'],
                'campo_canonico' => $descuento['campo_canonico'],
                'grupo' => $descuento['grupo'],
                'subgrupo' => $descuento['subgrupo'],
                'valor_original' => $valor,
                'valor_aplicable' => round($valorAplicable, 2),
                'valor_no_aplicable' => round($valorNoAplicable, 2),
                'cuota_actual' => $descuento['cuota_actual'] ?? null,
                'total_cuotas' => $descuento['total_cuotas'] ?? null,
                'cuota_observacion' => $descuento['cuota_observacion'] ?? null,
                'cuota_label' => $descuento['cuota_label'] ?? null,
                'mes_inicio_cuota' => $descuento['mes_inicio_cuota'] ?? null,
                'mes_inicio_cuota_label' => $descuento['mes_inicio_cuota_label'] ?? null,
                'estado_aplicacion' => $estado,
                'motivo' => $motivo,
                'prioridad' => $priority['priority'],
                'prioridad_label' => $priority['label'],
                'primero_sobre_tope' => $primeroSobreTope,
                'clasificacion' => $priority,
            ];
        }

        $estadoGeneral = 'cumple';
        if ($baseCalculo <= 0) {
            $estadoGeneral = 'requiere_revision';
        } elseif ($montoExcedido > 0) {
            $estadoGeneral = 'excede_tope';
        }

        $observacionesExport = $this->buildObservacionesExport($detalles);
        if ($observacionesExport !== '') {
            $observaciones[] = $observacionesExport;
        }

        return [
            'registro' => $registro,
            'monto_imponible' => $montoImponible,
            'total_haberes' => $baseCalculo,
            'base_calculo' => $baseCalculo,
            'monto_maximo_endeudamiento' => $montoMaximo,
            'total_descuentos' => $totalDescuentos,
            'porcentaje_total_descuento' => $porcentaje,
            'monto_excedido' => $montoExcedido,
            'totales' => array_map(fn ($value) => round((float) $value, 2), $totales),
            'descuentos_legales' => [
                'imposiciones' => round((float) ($registro->imposiciones ?? 0), 2),
                'salud' => round((float) ($registro->salud ?? 0), 2),
                'impuesto' => round((float) ($registro->impuesto ?? 0), 2),
            ],
            'detalles' => $detalles,
            'estado' => $estadoGeneral,
            'observaciones' => array_values(array_unique($observaciones)),
            'observaciones_export' => $observacionesExport,
        ];
    }

    public function summarizeQuery(Builder $query, array $filters = []): array
    {
        $summary = [
            'registros' => 0,
            'monto_imponible' => 0.0,
            'total_haberes' => 0.0,
            'base_calculo' => 0.0,
            'monto_maximo_endeudamiento' => 0.0,
            'total_descuentos' => 0.0,
            'monto_excedido' => 0.0,
            'porcentaje_total_descuento_promedio' => 0.0,
            'patronal' => 0.0,
            'con_exceso' => 0,
            'con_revision' => 0,
        ];

        $this->processFilteredAnalyses($query, $filters, function (array $analysis) use (&$summary) {
            $summary['registros']++;
            $summary['monto_imponible'] += (float) $analysis['monto_imponible'];
            $summary['total_haberes'] += (float) $analysis['total_haberes'];
            $summary['base_calculo'] += (float) $analysis['base_calculo'];
            $summary['monto_maximo_endeudamiento'] += (float) $analysis['monto_maximo_endeudamiento'];
            $summary['total_descuentos'] += (float) $analysis['total_descuentos'];
            $summary['monto_excedido'] += (float) $analysis['monto_excedido'];
            $summary['patronal'] += (float) $analysis['totales']['patronal'];
            $summary['porcentaje_total_descuento_promedio'] += (float) $analysis['porcentaje_total_descuento'];
            if ($analysis['estado'] === 'excede_tope') {
                $summary['con_exceso']++;
            }
            if ($analysis['estado'] === 'requiere_revision') {
                $summary['con_revision']++;
            }
        });

        foreach (['monto_imponible', 'total_haberes', 'base_calculo', 'monto_maximo_endeudamiento', 'total_descuentos', 'monto_excedido', 'patronal'] as $field) {
            $summary[$field] = round((float) $summary[$field], 2);
        }
        $summary['porcentaje_total_descuento_promedio'] = $summary['registros'] > 0
            ? round($summary['porcentaje_total_descuento_promedio'] / $summary['registros'], 2)
            : 0.0;

        return $summary;
    }

    public function analyzeById(int $registroId): ?array
    {
        $registro = MaeRegistro::query()->with(['carga', 'descuentos', 'otrosDescuentos'])->find($registroId);

        return $registro ? $this->analyzeRegistro($registro) : null;
    }

    private function collectFilteredAnalyses(Builder $query, array $filters = []): Collection
    {
        $analyses = collect();

        $this->processFilteredAnalyses($query, $filters, function (array $analysis) use ($analyses) {
            $analyses->push($analysis);
        });

        return $analyses;
    }

    private function processFilteredAnalyses(Builder $query, array $filters = [], callable $consumer): void
    {
        $estado = trim((string) ($filters['estado'] ?? ''));

        $query
            ->with(['carga'])
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->orderBy('dominio')
            ->orderBy('nombre_completo')
            ->chunk(150, function (Collection $registros) use ($consumer, $estado) {
                $registros->load(['descuentos', 'otrosDescuentos']);

                foreach ($registros as $registro) {
                    $analysis = $this->analyzeRegistro($registro);
                    if (!$this->matchesEstadoFilter($analysis, $estado)) {
                        continue;
                    }

                    $consumer($analysis);
                }

                unset($registros);
            });
    }

    private function matchesEstadoFilter(array $analysis, string $estado): bool
    {
        if ($estado === '') {
            return true;
        }

        return match ($estado) {
            'dentro_tope' => $analysis['estado'] === 'cumple',
            'con_exceso' => $analysis['estado'] === 'excede_tope',
            'revision' => $analysis['estado'] === 'requiere_revision',
            default => true,
        };
    }

    private function buildRegistroQuery(array $filters): Builder
    {
        $anio = (int) ($filters['anio'] ?? 0);
        $mes = (int) ($filters['mes'] ?? 0);
        $dominio = trim((string) ($filters['dominio'] ?? ''));
        $cargaId = (int) ($filters['carga_id'] ?? 0);
        $q = trim((string) ($filters['q'] ?? ''));
        $rut = preg_replace('/[^0-9kK]/', '', trim((string) ($filters['rut'] ?? '')));
        $nombre = trim((string) ($filters['nombre'] ?? ''));
        $soloVigentes = (bool) ($filters['solo_vigentes'] ?? true);

        return MaeRegistro::query()
            ->when($cargaId > 0, fn (Builder $query) => $query->where('mae_carga_id', $cargaId))
            ->when($cargaId <= 0 && $soloVigentes, fn (Builder $query) => $query->whereHas('carga', fn (Builder $q2) => $q2->where('es_vigente', true)))
            ->when($anio > 0, fn (Builder $query) => $query->where('anio', $anio))
            ->when($mes > 0, fn (Builder $query) => $query->where('mes', $mes))
            ->when($dominio !== '', fn (Builder $query) => $query->where('dominio', $dominio))
            ->when($rut !== '', fn (Builder $query) => $query->where('rut', 'like', '%' . $rut . '%'))
            ->when($nombre !== '', fn (Builder $query) => $query->where('nombre_completo', 'like', '%' . $nombre . '%'))
            ->when($q !== '', function (Builder $query) use ($q) {
                $query->where(function (Builder $qq) use ($q) {
                    $qq->where('rut', 'like', '%' . $q . '%')
                        ->orWhere('nombre_completo', 'like', '%' . $q . '%');
                });
            });
    }

    /**
     * Conserva la ubicación relativa de todos los descuentos sin cuota según
     * la prelación vigente. Los descuentos que poseen una cuota actual positiva
     * se extraen como un único conjunto, sin agruparlos por tipo, institución o
     * subgrupo, y se reinsertan en sus posiciones de cuota ordenados por el mes
     * de inicio calculado desde el más antiguo al más reciente.
     */
    private function orderCandidateItems(Collection $candidates): Collection
    {
        $baseOrder = $candidates
            ->map(function (array $descuento) {
                return [
                    'descuento' => $descuento,
                    'priority' => $this->resolvePriority($descuento),
                ];
            })
            ->sortBy(fn (array $item) => [
                (int) $item['priority']['priority'],
                (int) ($item['descuento']['orden_columna'] ?? PHP_INT_MAX),
                mb_strtolower((string) ($item['descuento']['columna_origen'] ?? ''), 'UTF-8'),
                (string) ($item['descuento']['id'] ?? ''),
            ])
            ->values();

        $installments = $baseOrder
            ->filter(fn (array $item) => $this->hasSortableInstallment($item['descuento']))
            ->sortBy(fn (array $item) => $this->installmentSortKey($item))
            ->values();

        if ($installments->count() < 2) {
            return $baseOrder;
        }

        $installmentIndex = 0;

        return $baseOrder
            ->map(function (array $item) use ($installments, &$installmentIndex): array {
                if (!$this->hasSortableInstallment($item['descuento'])) {
                    return $item;
                }

                return $installments->get($installmentIndex++, $item);
            })
            ->values();
    }

    private function hasSortableInstallment(array $descuento): bool
    {
        return isset($descuento['cuota_actual'])
            && is_numeric($descuento['cuota_actual'])
            && (int) $descuento['cuota_actual'] > 0;
    }

    /**
     * La fecha de inicio inferida es el criterio principal: una fecha menor
     * corresponde a una obligación más antigua. La cuota actual descendente
     * sirve como respaldo cuando no se dispone de una fecha inferida válida.
     */
    private function installmentSortKey(array $item): array
    {
        $descuento = $item['descuento'] ?? $item;
        $startMonth = trim((string) ($descuento['mes_inicio_cuota'] ?? ''));
        $hasStartMonth = preg_match('/^\d{4}-\d{2}-\d{2}$/', $startMonth) === 1;

        return [
            $hasStartMonth ? 0 : 1,
            $hasStartMonth ? $startMonth : '9999-12-31',
            (int) ($item['priority']['priority'] ?? PHP_INT_MAX),
            -1 * (int) ($descuento['cuota_actual'] ?? 0),
            (int) ($descuento['orden_columna'] ?? PHP_INT_MAX),
            mb_strtolower((string) ($descuento['columna_origen'] ?? ''), 'UTF-8'),
            (string) ($descuento['id'] ?? ''),
        ];
    }

    private function buildCandidateItems(MaeRegistro $registro): Collection
    {
        $items = collect();
        $ordenBase = 1;

        foreach ($registro->descuentos as $descuento) {
            /** @var MaeRegistroDescuento $descuento */
            $mesInicioCuota = $descuento->mesInicioCuota((int) $registro->anio, (int) $registro->mes);

            $items->push([
                'id' => $descuento->id,
                'columna_origen' => (string) $descuento->columna_origen,
                'campo_canonico' => (string) $descuento->campo_canonico,
                'grupo' => (string) $descuento->grupo,
                'subgrupo' => (string) $descuento->subgrupo,
                'valor' => (float) $descuento->valor,
                'orden_columna' => (int) ($descuento->orden_columna ?: $ordenBase++),
                'es_aporte_patronal' => (bool) $descuento->es_aporte_patronal,
                'tipo_movimiento' => (string) $descuento->tipo_movimiento,
                'cuota_actual' => $descuento->cuota_actual !== null ? (int) $descuento->cuota_actual : null,
                'total_cuotas' => $descuento->total_cuotas !== null ? (int) $descuento->total_cuotas : null,
                'cuota_observacion' => $descuento->cuota_observacion,
                'cuota_label' => $descuento->cuotaEtiqueta(),
                'mes_inicio_cuota' => $mesInicioCuota?->toDateString(),
                'mes_inicio_cuota_label' => $mesInicioCuota?->format('m/Y'),
            ]);
        }

        foreach ($registro->otrosDescuentos as $otroDescuento) {
            /** @var MaeRegistroOtroDescuento $otroDescuento */
            $metadata = MaeColumnNormalizer::inferDiscountMetadata(
                (string) $otroDescuento->columna_origen,
                (string) $otroDescuento->columna_normalizada
            );

            $items->push([
                'id' => 'otro_' . $otroDescuento->id,
                'columna_origen' => (string) $otroDescuento->columna_origen,
                'campo_canonico' => $metadata['campo_canonico'],
                'grupo' => $metadata['grupo'],
                'subgrupo' => $metadata['subgrupo'],
                'valor' => (float) $otroDescuento->valor,
                'orden_columna' => $ordenBase++ + 10000,
                'es_aporte_patronal' => (bool) $metadata['es_aporte_patronal'],
                'tipo_movimiento' => $metadata['tipo_movimiento'],
                'cuota_actual' => null,
                'total_cuotas' => null,
                'cuota_observacion' => null,
                'cuota_label' => null,
                'mes_inicio_cuota' => null,
                'mes_inicio_cuota_label' => null,
            ]);
        }

        $ordenColumna = ((int) $items->max('orden_columna')) + 1;
        foreach ($this->syntheticMandatoryDiscounts($registro, $items, $ordenColumna) as $synthetic) {
            $items->push($synthetic);
        }

        return $items;
    }

    private function syntheticMandatoryDiscounts(MaeRegistro $registro, Collection $existingItems, int $ordenColumna): array
    {
        $synthetics = [];
        $definitions = [
            'imposiciones' => ['Imposiciones', 'descuentos_legales', 'imposiciones'],
            'salud' => ['Salud', 'descuentos_legales', 'salud'],
            'impuesto' => ['Impuesto', 'descuentos_legales', 'impuesto'],
        ];

        foreach ($definitions as $field => [$label, $group, $subgroup]) {
            $value = round((float) ($registro->{$field} ?? 0), 2);
            if ($value <= 0 || $this->hasExistingEquivalent($existingItems, $field)) {
                continue;
            }

            $synthetics[] = [
                'id' => 'synthetic_' . $field,
                'columna_origen' => $label,
                'campo_canonico' => $field,
                'grupo' => $group,
                'subgrupo' => $subgroup,
                'valor' => $value,
                'orden_columna' => $ordenColumna++,
                'es_aporte_patronal' => false,
                'tipo_movimiento' => 'descuento',
                'cuota_actual' => null,
                'total_cuotas' => null,
                'cuota_observacion' => null,
                'cuota_label' => null,
                'mes_inicio_cuota' => null,
                'mes_inicio_cuota_label' => null,
            ];
        }

        return $synthetics;
    }

    private function hasExistingEquivalent(Collection $items, string $field): bool
    {
        return $items->contains(function (array $item) use ($field) {
            $campoCanonico = $this->normalizeText((string) ($item['campo_canonico'] ?? ''));
            $grupo = $this->normalizeText((string) ($item['grupo'] ?? ''));
            $subgrupo = $this->normalizeText((string) ($item['subgrupo'] ?? ''));
            $columna = $this->normalizeText((string) ($item['columna_origen'] ?? ''));

            if ($campoCanonico === $field || $subgrupo === $field) {
                return true;
            }

            if ($grupo !== 'descuentos_legales') {
                return false;
            }

            return match ($field) {
                'imposiciones' => $columna === 'imposiciones',
                'salud' => $columna === 'salud',
                'impuesto' => $columna === 'impuesto',
                default => false,
            };
        });
    }

    private function buildObservacionesExport(array $detalles): string
    {
        $parts = [];
        foreach ($detalles as $detalle) {
            if (!in_array($detalle['estado_aplicacion'], ['eliminar', 'revision'], true)) {
                continue;
            }

            $prefix = $detalle['estado_aplicacion'] === 'revision' ? 'Revision' : 'Exceso';
            $cuota = trim((string) ($detalle['cuota_label'] ?? ''));
            $mesInicio = trim((string) ($detalle['mes_inicio_cuota_label'] ?? ''));

            $texto = sprintf(
                '%s: %s ($%s)',
                $prefix,
                $detalle['columna_origen'],
                number_format((float) $detalle['valor_original'], 0, ',', '.')
            );

            if ($cuota !== '') {
                $texto .= ' - ' . $cuota;
            }
            if ($mesInicio !== '') {
                $texto .= ' - Inicio ' . $mesInicio;
            }

            $parts[] = $texto;
        }

        return implode(' | ', $parts);
    }

    private function cuotaActualExport(mixed $value): mixed
    {
        if ($value === null) {
            return '';
        }

        return (int) $value === 0 ? '0 - sin inicio' : (int) $value;
    }

    private function totalCuotasExport(mixed $value): mixed
    {
        if ($value === null) {
            return '';
        }

        return (int) $value === 0 ? '0 - indefinido' : (int) $value;
    }

    private function resolvePriority(array $descuento): array
    {
        $full = $this->normalizeText(
            implode(' ', [
                (string) ($descuento['columna_origen'] ?? ''),
                (string) ($descuento['campo_canonico'] ?? ''),
                (string) ($descuento['grupo'] ?? ''),
                (string) ($descuento['subgrupo'] ?? ''),
            ])
        );

        if (($descuento['es_aporte_patronal'] ?? false)
            || (($descuento['tipo_movimiento'] ?? '') === 'aporte_patronal')
            || str_contains($full, 'empleador')
            || str_contains($full, '(emp')
            || preg_match('/\bemp\b/', $full)
            || str_contains($full, 'ap fondo retiro')
            || str_contains($full, 'aporte fondo retiro')
            || str_contains($full, 'aporte patronal fondo retiro')
            || str_contains($full, 'cotizacion expectativa de vi')
            || str_contains($full, 'cotizacion expectativa de vida')) {
            return [
                'priority' => 999,
                'label' => 'Patronal excluido',
                'patronal' => true,
            ];
        }

        $group = $this->normalizeText((string) ($descuento['grupo'] ?? ''));
        $subgroup = $this->normalizeText((string) ($descuento['subgrupo'] ?? ''));

        $contains = fn (string ...$needles) => collect($needles)->contains(fn ($needle) => str_contains($full, $this->normalizeText($needle)));

        if ($contains('imposiciones')) {
            return ['priority' => 10, 'label' => 'Legal - Imposiciones', 'patronal' => false];
        }
        if ($contains('salud') && !str_contains($group, 'salud_complementaria')) {
            return ['priority' => 20, 'label' => 'Legal - Salud', 'patronal' => false];
        }
        if ($contains('impuesto')) {
            return ['priority' => 30, 'label' => 'Legal - Impuesto', 'patronal' => false];
        }
        if (str_contains($group, 'cesantia') || $contains('cesantia')) {
            return ['priority' => 40, 'label' => 'Legal - Cesantía', 'patronal' => false];
        }
        if (str_contains($group, 'judicial') || str_contains($subgroup, 'judicial') || $contains('ret.judicial', 'judicial')) {
            return ['priority' => 50, 'label' => 'Legal - Judicial', 'patronal' => false];
        }
        if (str_contains($group, 'administrativo') || str_contains($subgroup, 'administrativo') || $contains('rex', 'contraloria', 'contraloría', 'resolucion', 'resolución')) {
            return ['priority' => 60, 'label' => 'Legal - Administrativo', 'patronal' => false];
        }
        if (str_contains($group, 'reintegro') || str_contains($subgroup, 'reintegro') || $contains('reintegro')) {
            return ['priority' => 70, 'label' => 'Legal - Reintegro', 'patronal' => false];
        }
        if ($contains(
            'inasist o atraso',
            'inasistencias y atrasos',
            'inasistencia y atraso',
            'inasistencia',
            'inasist.',
            'atraso',
            'horas no trabajadas',
            'dias no trabajados',
            'días no trabajados'
        )) {
            return ['priority' => 75, 'label' => 'Legal - Inasistencias y atrasos', 'patronal' => false];
        }
        if (str_contains($group, 'apv') || str_contains($subgroup, 'apv') || preg_match('/\bapv\b/', $full)) {
            return ['priority' => 80, 'label' => 'Legal - APV', 'patronal' => false];
        }
        if (str_contains($group, 'gremial') || $contains('colegio de profesores', 'sute', 'sindicato', 'afe', 'afpae', 'asoc.')) {
            return ['priority' => 90, 'label' => 'Sindical / gremial', 'patronal' => false];
        }
        if (str_contains($group, 'ahorro') || str_contains($subgroup, 'ahorro') || $contains('ahorro')) {
            return ['priority' => 100, 'label' => 'Ahorro', 'patronal' => false];
        }
        if (str_contains($group, 'credito') || str_contains($subgroup, 'credito') || $contains('credito', 'prestamo', 'prest.')) {
            if ($contains('ahorrocoop')) {
                return ['priority' => 110, 'label' => 'Crédito - Ahorrocoop', 'patronal' => false];
            }
            if ($contains('coopeuch')) {
                return ['priority' => 120, 'label' => 'Crédito - Coopeuch', 'patronal' => false];
            }
            if ($contains('oriencoop')) {
                return ['priority' => 130, 'label' => 'Crédito - Oriencoop', 'patronal' => false];
            }
            if ($contains('fonasa')) {
                return ['priority' => 140, 'label' => 'Crédito - Préstamos Fonasa', 'patronal' => false];
            }
            if ($contains('caja 18', '18 sept')) {
                return ['priority' => 150, 'label' => 'Crédito - Caja 18', 'patronal' => false];
            }
            if ($contains('los andes')) {
                return ['priority' => 160, 'label' => 'Crédito - Caja Los Andes', 'patronal' => false];
            }
            return ['priority' => 170, 'label' => 'Crédito - Otros', 'patronal' => false];
        }

        return ['priority' => 180, 'label' => 'Otros descuentos', 'patronal' => false];
    }

    private function normalizeText(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $search = ['á', 'é', 'í', 'ó', 'ú', 'ñ'];
        $replace = ['a', 'e', 'i', 'o', 'u', 'n'];
        return str_replace($search, $replace, $value);
    }
}
