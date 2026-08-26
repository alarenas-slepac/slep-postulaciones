<?php

namespace App\Services\LicenciasMedicas;

use App\Models\LicenciaMedica;
use App\Models\LicenciaMedicaHistorial;
use App\Models\LicenciaMedicaImportacion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use ZipArchive;
use XMLReader;

class LicenciaSeguimientoImportService
{
    private array $resolverCache = [];

    public function __construct(
        private readonly LicenciaFuncionarioResolver $resolver,
        private readonly LicenciaDiasLaboralesService $diasLaboralesService,
        private readonly LicenciaEstadoService $estados
    ) {
    }

    public function import(string $path, int $userId, string $originalName, string $storedPath, string $tipoIngresoDefault = '3'): array
    {
        $this->prepareLongRunningImport();

        $tipoIngresoDefault = LicenciaFolio::normalizeTipo($tipoIngresoDefault) ?: '3';

        $importacion = LicenciaMedicaImportacion::create([
            'tipo' => 'seguimiento_excel',
            'nombre_archivo' => $originalName,
            'archivo_path' => $storedPath,
            'periodo' => null,
            'estado' => 'procesando',
            'subido_por' => $userId,
        ]);

        try {
            if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'xlsx' && class_exists(ZipArchive::class) && class_exists(XMLReader::class)) {
                return $this->importStreamingXlsx($path, $importacion, $userId, $tipoIngresoDefault, $originalName);
            }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        if (method_exists($reader, 'setReadEmptyCells')) {
            $reader->setReadEmptyCells(false);
        }
        if (method_exists($reader, 'setLoadSheetsOnly')) {
            $reader->setLoadSheetsOnly(['2026', '2025', 'datos']);
        }
        $spreadsheet = $reader->load($path);

        $datosFuncionarios = $this->leerDatosFuncionarios($spreadsheet);
        $resumen = [
            'archivo' => $originalName,
            'tipo_ingreso_default' => $tipoIngresoDefault,
            'hojas' => [],
            'inconsistencias' => [],
        ];

        $totales = [
            'filas' => 0,
            'importadas' => 0,
            'actualizadas' => 0,
            'omitidas' => 0,
            'duplicadas' => 0,
            'inconsistencias' => 0,
        ];

        foreach (['2026', '2025'] as $sheetName) {
            $sheet = $spreadsheet->getSheetByName($sheetName);
            if (! $sheet) {
                $resumen['hojas'][$sheetName] = ['estado' => 'no_encontrada'];
                continue;
            }

            $resultadoHoja = $this->procesarHoja($sheet, $sheetName, $tipoIngresoDefault, $datosFuncionarios, $importacion->id, $userId);
            $resumen['hojas'][$sheetName] = $resultadoHoja;

            foreach ($totales as $key => $value) {
                $totales[$key] += (int) ($resultadoHoja[$key] ?? 0);
            }

            foreach (($resultadoHoja['muestras_inconsistencias'] ?? []) as $item) {
                if (count($resumen['inconsistencias']) < 50) {
                    $resumen['inconsistencias'][] = $item;
                }
            }
        }

        $spreadsheet->disconnectWorksheets();

        $importacion->update([
            'total_filas' => $totales['filas'],
            'total_importadas' => $totales['importadas'],
            'total_actualizadas' => $totales['actualizadas'],
            'total_omitidas' => $totales['omitidas'],
            'total_duplicadas' => $totales['duplicadas'],
            'total_inconsistencias' => $totales['inconsistencias'],
            'resumen_json' => $resumen,
            'estado' => 'procesado',
        ]);

            return [
                'importacion_id' => $importacion->id,
                'totales' => $totales,
                'resumen' => $resumen,
            ];
        } catch (\Throwable $e) {
            $importacion->update([
                'estado' => 'fallido',
                'resumen_json' => ['error' => 'La importación no pudo completarse.'],
            ]);

            throw $e;
        }
    }

    private function procesarHoja($sheet, string $sheetName, string $tipoIngresoDefault, array $datosFuncionarios, int $importacionId, int $userId): array
    {
        $headerRow = $this->detectarFilaCabecera($sheet);
        if (! $headerRow) {
            return [
                'estado' => 'sin_cabecera',
                'filas' => 0,
                'importadas' => 0,
                'actualizadas' => 0,
                'omitidas' => 0,
                'duplicadas' => 0,
                'inconsistencias' => 1,
                'muestras_inconsistencias' => [['hoja' => $sheetName, 'fila' => null, 'motivo' => 'No se encontró cabecera válida.']],
            ];
        }

        $highestColumn = $sheet->getHighestDataColumn();
        $headers = $sheet->rangeToArray('A' . $headerRow . ':' . $highestColumn . $headerRow, null, true, false)[0] ?? [];
        $map = $this->mapearCabeceras($headers);
        $highestRow = min((int) $sheet->getHighestDataRow(), $headerRow + 60000);

        $stats = [
            'estado' => 'procesado',
            'header_row' => $headerRow,
            'filas' => 0,
            'importadas' => 0,
            'actualizadas' => 0,
            'omitidas' => 0,
            'duplicadas' => 0,
            'inconsistencias' => 0,
            'muestras_inconsistencias' => [],
        ];

        $blankStreak = 0;

        $chunkSize = 500;

        for ($startRow = $headerRow + 1; $startRow <= $highestRow; $startRow += $chunkSize) {
            $endRow = min($highestRow, $startRow + $chunkSize - 1);
            $rows = $sheet->rangeToArray('A' . $startRow . ':' . $highestColumn . $endRow, null, true, false);

            foreach ($rows as $offset => $values) {
                $row = $startRow + $offset;
                if ($this->filaVacia($values, $map)) {
                    $blankStreak++;
                    if ($blankStreak >= 80) {
                        break 2;
                    }
                    continue;
                }
                $blankStreak = 0;
                $stats['filas']++;

                $parsed = $this->parsearFila($values, $map, $sheetName, $tipoIngresoDefault, $datosFuncionarios);
                if (! empty($parsed['error'])) {
                    $stats['omitidas']++;
                    $stats['inconsistencias']++;
                    $this->addInconsistencia($stats, $sheetName, $row, $parsed['error']);
                    continue;
                }

                $resultado = $this->guardarLicencia($parsed['data'], $importacionId, $userId);
                $stats[$resultado]++;
            }

            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        return $stats;
    }

    private function parsearFila(array $row, array $map, string $sheetName, string $tipoIngresoDefault, array $datosFuncionarios): array
    {
        $licenciaRaw = $this->value($row, $map, 'licencia');
        $dvRaw = $this->value($row, $map, 'dv');

        $folioParts = LicenciaFolio::split((string) $licenciaRaw);
        $tipo = $folioParts['tipo_ingreso_licencia'] ?: $tipoIngresoDefault;
        $cuerpo = $folioParts['cuerpo_licencia'] ?: LicenciaFolio::normalizeCuerpo((string) $licenciaRaw);
        $dv = $folioParts['dv_licencia'] ?: LicenciaFolio::normalizeDv((string) $dvRaw);
        $folio = LicenciaFolio::build($tipo, $cuerpo, $dv);

        if (! $folio) {
            return ['error' => 'Folio inválido o incompleto.'];
        }

        $rut = RutNormalizer::normalize((string) $this->value($row, $map, 'rut'));
        if (! $rut['valido']) {
            return ['error' => 'RUT del funcionario inválido o vacío.'];
        }

        $datos = $datosFuncionarios[$rut['normalizado']] ?? [];
        $nombre = $this->clean($this->value($row, $map, 'nombre')) ?: ($datos['nombre'] ?? null);
        if (! $nombre) {
            return ['error' => 'Nombre del funcionario vacío.'];
        }

        $establecimientoManual = $this->clean($this->value($row, $map, 'dependencia')) ?: ($datos['ubicacion'] ?? null);
        $comunaManual = $this->clean($this->value($row, $map, 'comuna')) ?: ($datos['comuna'] ?? null);
        $asociacion = $this->resolveFuncionarioCached($rut['normalizado'], $rut['rut'], $establecimientoManual, $comunaManual);

        $tipoLmRaw = $this->clean($this->value($row, $map, 'tipo_lm'));
        $tipoLm = null;
        if ($tipoLmRaw && preg_match('/([1-7])/', $tipoLmRaw, $m)) {
            $tipoLm = $m[1];
        }

        $fechaInicio = $this->date($this->value($row, $map, 'fecha_inicio'));
        $fechaTermino = $this->date($this->value($row, $map, 'fecha_termino'));
        $diasSolicitados = $this->int($this->value($row, $map, 'dias_lic'));
        if (! $fechaTermino && $fechaInicio && $diasSolicitados) {
            $fechaTermino = Carbon::parse($fechaInicio)->addDays($diasSolicitados - 1)->format('Y-m-d');
        }

        $primerEstado = $this->clean($this->value($row, $map, 'primer_estado'));
        $estadoActual = $primerEstado ?: 'Importada seguimiento';
        $estadoAdministrativoCodigo = $this->estados->normalizar(LicenciaEstadoService::ADMINISTRATIVO, $estadoActual) ?: 'otro';
        $estadoCompinCodigo = $this->estados->normalizar(LicenciaEstadoService::COMPIN, $primerEstado) ?: 'sin_informacion';
        $recuperabilidad = mb_strtoupper((string) $this->clean($this->value($row, $map, 'se_puede_recuperar')));
        $gestionCobro = $this->clean($this->value($row, $map, 'gestion_cobro'));
        $estadoRecuperacionCodigo = str_starts_with($recuperabilidad, 'NO')
            ? 'no_recuperable'
            : ($gestionCobro ? 'en_cobro' : 'no_evaluada');
        $diasCorridos = null;
        $diasLaborales = $this->int($this->value($row, $map, 'dias_lab'));
        if ($fechaInicio && $fechaTermino) {
            $calculoDias = $this->diasLaboralesService->calcular($fechaInicio, $fechaTermino);
            $diasCorridos = $calculoDias['dias_corridos'] ?? null;
            if (! $diasLaborales) {
                $diasLaborales = $calculoDias['dias_laborales'] ?? null;
            }
        }

        return [
            'data' => [
                'tipo_ingreso_licencia' => $tipo,
                'cuerpo_licencia' => $cuerpo,
                'dv_licencia' => $dv,
                'folio_licencia' => $folio,
                'rut_funcionario' => $rut['rut'],
                'dv_funcionario' => $rut['dv'],
                'rut_normalizado' => $rut['normalizado'],
                'rut_formateado' => $rut['formateado'],
                'nombre_funcionario' => $nombre,
                'tipo_dependencia' => $asociacion['tipo_dependencia'] ?? 'sin_asociacion',
                'establecimiento_id' => $asociacion['establecimiento_id'] ?? null,
                'establecimiento_nombre' => $asociacion['establecimiento_nombre'] ?? $establecimientoManual,
                'comuna' => $asociacion['comuna'] ?? $comunaManual,
                'subdireccion' => $asociacion['subdireccion'] ?? null,
                'unidad_departamento' => $asociacion['unidad_departamento'] ?? null,
                'cargo' => $asociacion['cargo'] ?? null,
                'grado' => $asociacion['grado'] ?? null,
                'escalafon' => $asociacion['escalafon'] ?? null,
                'calidad_juridica' => ($asociacion['calidad_juridica'] ?? null) ?: ($this->clean($this->value($row, $map, 'calidad_juridica')) ?: ($datos['tipo_contrato'] ?? null)),
                'estamento' => ($asociacion['estamento'] ?? null) ?: ($this->clean($this->value($row, $map, 'estamento')) ?: ($datos['planta'] ?? null)),
                'sistema_salud' => $this->resolverSistemaSalud($this->clean($this->value($row, $map, 'salud')) ?: ($datos['salud'] ?? null)),
                'institucion_salud' => $this->resolverInstitucionSalud($this->clean($this->value($row, $map, 'salud')) ?: ($datos['salud'] ?? null)),
                'vigencia' => $this->clean($this->value($row, $map, 'vigencia')),
                'fecha_emision' => $this->date($this->value($row, $map, 'fecha_emision')),
                'fecha_recepcion' => $this->date($this->value($row, $map, 'fecha_recepcion')),
                'fecha_inicio' => $fechaInicio,
                'fecha_termino' => $fechaTermino,
                'dias_solicitados' => $diasSolicitados,
                'dias_corridos' => $diasCorridos,
                'dias_laborales' => $diasLaborales,
                'tipo_licencia' => $tipoLm,
                'tipo_licencia_glosa' => $tipoLmRaw,
                'valor_licencia' => $this->money($this->value($row, $map, 'valor_licencia')),
                'se_puede_recuperar' => $this->clean($this->value($row, $map, 'se_puede_recuperar')),
                'primer_estado' => $primerEstado,
                'segundo_estado' => $this->clean($this->value($row, $map, 'segundo_estado')),
                'fecha_revision' => $this->date($this->value($row, $map, 'fecha_revision')),
                'gestion_cobro' => $this->clean($this->value($row, $map, 'gestion_cobro')),
                'numero_ord' => $this->clean($this->value($row, $map, 'numero_ord')),
                'fecha_cobro' => $this->date($this->value($row, $map, 'fecha_cobro')),
                'numero_ord_nuevo_cobro' => $this->clean($this->value($row, $map, 'numero_ord_nuevo_cobro')),
                'fecha_nuevo_cobro' => $this->date($this->value($row, $map, 'fecha_nuevo_cobro')),
                'ingresar_siaper' => $this->clean($this->value($row, $map, 'ingresar_siaper')),
                'rex_siaper' => $this->clean($this->value($row, $map, 'rex_siaper')),
                'realizo_apelacion' => $this->clean($this->value($row, $map, 'realizo_apelacion')),
                'estado_actual' => $estadoActual,
                'estado_compin' => $primerEstado,
                'estado_administrativo_codigo' => $estadoAdministrativoCodigo,
                'estado_compin_codigo' => $estadoCompinCodigo,
                'estado_recuperacion_codigo' => $estadoRecuperacionCodigo,
                'dias_autorizados' => $this->int($this->value($row, $map, 'dias_autorizados')),
                'estado_notificacion' => 'sin_notificacion',
                'estado_alerta' => in_array(mb_strtoupper($primerEstado ?? ''), ['RECHAZADA', 'REDUCIDA', 'REDUCCION', 'REDUCCIÓN'], true) ? 'alerta_revision' : 'sin_alerta',
                'origen_ingreso' => 'importacion_excel_seguimiento',
                'tipo_documento_ingreso' => 'importacion_excel',
                'fuente_asociacion_funcionario' => $asociacion['fuente'] ?? 'sin_asociacion',
                'periodo_reemplazos_usado' => $asociacion['periodo'] ?? null,
                'origen_planilla_anio' => $sheetName,
            ],
        ];
    }

    private function guardarLicencia(array $data, int $importacionId, int $userId): string
    {
        $existing = LicenciaMedica::where('tipo_ingreso_licencia', $data['tipo_ingreso_licencia'])
            ->where('cuerpo_licencia', $data['cuerpo_licencia'])
            ->where('dv_licencia', $data['dv_licencia'])
            ->first();

        if ($existing) {
            $anteriores = $this->estadosAnteriores($existing);
            $existing->fill(array_filter($data, fn ($value) => ! is_null($value)));

            if (! $existing->isDirty()) {
                return 'duplicadas';
            }

            $existing->importacion_id = $importacionId;
            $existing->updated_by = $userId;
            $existing->save();

            $this->registrarCambiosEstado($existing, $anteriores, $userId, $importacionId);
            return 'actualizadas';
        }

        $data['importacion_id'] = $importacionId;
        $data['updated_by'] = $userId;
        $data['created_by'] = $userId;
        $licencia = LicenciaMedica::create($data);
        $this->registrarCambiosEstado($licencia, [], $userId, $importacionId);

        return 'importadas';
    }

    private function leerDatosFuncionarios($spreadsheet): array
    {
        $sheet = $spreadsheet->getSheetByName('datos');
        if (! $sheet) return [];

        $headerRow = $this->detectarFilaCabecera($sheet) ?: 1;
        $highestColumn = $sheet->getHighestDataColumn();
        $headers = $sheet->rangeToArray('A' . $headerRow . ':' . $highestColumn . $headerRow, null, true, false)[0] ?? [];
        $map = $this->mapearCabeceras($headers);
        $highestRow = min((int) $sheet->getHighestDataRow(), $headerRow + 60000);
        $out = [];
        $blankStreak = 0;

        $chunkSize = 500;

        for ($startRow = $headerRow + 1; $startRow <= $highestRow; $startRow += $chunkSize) {
            $endRow = min($highestRow, $startRow + $chunkSize - 1);
            $rows = $sheet->rangeToArray('A' . $startRow . ':' . $highestColumn . $endRow, null, true, false);

            foreach ($rows as $offset => $values) {
                $row = $startRow + $offset;
                if ($this->filaVacia($values, $map)) {
                    $blankStreak++;
                    if ($blankStreak >= 50) {
                        break 2;
                    }
                    continue;
                }
                $blankStreak = 0;
                $rut = RutNormalizer::normalize((string) $this->value($values, $map, 'rut'));
                if (! $rut['normalizado']) continue;
                $out[$rut['normalizado']] = [
                'nombre' => $this->clean($this->value($values, $map, 'nombre')),
                'ubicacion' => $this->clean($this->value($values, $map, 'dependencia')),
                'comuna' => $this->clean($this->value($values, $map, 'comuna')),
                'tipo_contrato' => $this->clean($this->value($values, $map, 'calidad_juridica')),
                'planta' => $this->clean($this->value($values, $map, 'estamento')),
                'salud' => $this->clean($this->value($values, $map, 'salud')),
                ];
            }
        }

        return $out;
    }




    private function importStreamingXlsx(string $path, LicenciaMedicaImportacion $importacion, int $userId, string $tipoIngresoDefault, string $originalName): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('No se pudo abrir el archivo XLSX como ZIP interno.');
        }

        $sharedStrings = $this->xlsxSharedStrings($zip);
        $sheets = $this->xlsxSheets($zip);

        $datosFuncionarios = [];
        if (isset($sheets['datos'])) {
            $datosFuncionarios = $this->leerDatosFuncionariosStreaming($zip, $sheets['datos'], $sharedStrings);
        }

        $resumen = [
            'archivo' => $originalName,
            'tipo_ingreso_default' => $tipoIngresoDefault,
            'modo' => 'xlsx_streaming_xmlreader',
            'hojas' => [],
            'inconsistencias' => [],
        ];

        $totales = [
            'filas' => 0,
            'importadas' => 0,
            'actualizadas' => 0,
            'omitidas' => 0,
            'duplicadas' => 0,
            'inconsistencias' => 0,
        ];

        foreach (['2026', '2025'] as $sheetName) {
            if (! isset($sheets[$sheetName])) {
                $resumen['hojas'][$sheetName] = ['estado' => 'no_encontrada'];
                continue;
            }

            $resultadoHoja = $this->procesarHojaStreaming($zip, $sheets[$sheetName], $sheetName, $sharedStrings, $tipoIngresoDefault, $datosFuncionarios, $importacion->id, $userId);
            $resumen['hojas'][$sheetName] = $resultadoHoja;

            foreach ($totales as $key => $value) {
                $totales[$key] += (int) ($resultadoHoja[$key] ?? 0);
            }

            foreach (($resultadoHoja['muestras_inconsistencias'] ?? []) as $item) {
                if (count($resumen['inconsistencias']) < 50) {
                    $resumen['inconsistencias'][] = $item;
                }
            }
        }

        $zip->close();

        $importacion->update([
            'total_filas' => $totales['filas'],
            'total_importadas' => $totales['importadas'],
            'total_actualizadas' => $totales['actualizadas'],
            'total_omitidas' => $totales['omitidas'],
            'total_duplicadas' => $totales['duplicadas'],
            'total_inconsistencias' => $totales['inconsistencias'],
            'resumen_json' => $resumen,
            'estado' => 'procesado',
        ]);

        return [
            'importacion_id' => $importacion->id,
            'totales' => $totales,
            'resumen' => $resumen,
        ];
    }

    private function procesarHojaStreaming(ZipArchive $zip, string $entry, string $sheetName, array $sharedStrings, string $tipoIngresoDefault, array $datosFuncionarios, int $importacionId, int $userId): array
    {
        $stats = [
            'estado' => 'procesado_streaming',
            'header_row' => null,
            'filas' => 0,
            'importadas' => 0,
            'actualizadas' => 0,
            'omitidas' => 0,
            'duplicadas' => 0,
            'inconsistencias' => 0,
            'muestras_inconsistencias' => [],
        ];

        $headerRow = null;
        $map = [];
        $blankStreak = 0;

        foreach ($this->xlsxRows($zip, $entry, $sharedStrings) as $rowNumber => $values) {
            if ($rowNumber > 60000) {
                break;
            }

            if ($headerRow === null) {
                if ($rowNumber <= 20) {
                    $normalized = array_map(fn ($v) => $this->normalizarCabecera($v), $values);
                    if ((in_array('RUT', $normalized, true) || in_array('RUN', $normalized, true)) && (in_array('NLICENCIA', $normalized, true) || in_array('NDELICENCIA', $normalized, true) || in_array('LICENCIA', $normalized, true))) {
                        $headerRow = $rowNumber;
                        $stats['header_row'] = $rowNumber;
                        $map = $this->mapearCabeceras($values);
                    }
                    continue;
                }

                $stats['estado'] = 'sin_cabecera';
                $stats['inconsistencias'] = 1;
                $this->addInconsistencia($stats, $sheetName, null, 'No se encontró cabecera válida en las primeras 20 filas.');
                return $stats;
            }

            if ($rowNumber <= $headerRow) {
                continue;
            }

            if ($this->filaVacia($values, $map)) {
                $blankStreak++;
                if ($blankStreak >= 80) {
                    break;
                }
                continue;
            }
            $blankStreak = 0;
            $stats['filas']++;

            $parsed = $this->parsearFila($values, $map, $sheetName, $tipoIngresoDefault, $datosFuncionarios);
            if (! empty($parsed['error'])) {
                $stats['omitidas']++;
                $stats['inconsistencias']++;
                $this->addInconsistencia($stats, $sheetName, $rowNumber, $parsed['error']);
                continue;
            }

            $resultado = $this->guardarLicencia($parsed['data'], $importacionId, $userId);
            $stats[$resultado]++;
        }

        return $stats;
    }

    private function leerDatosFuncionariosStreaming(ZipArchive $zip, string $entry, array $sharedStrings): array
    {
        $out = [];
        $headerRow = null;
        $map = [];
        $blankStreak = 0;

        foreach ($this->xlsxRows($zip, $entry, $sharedStrings) as $rowNumber => $values) {
            if ($rowNumber > 60000) {
                break;
            }

            if ($headerRow === null) {
                if ($rowNumber <= 20) {
                    $normalized = array_map(fn ($v) => $this->normalizarCabecera($v), $values);
                    if ((in_array('RUT', $normalized, true) || in_array('RUN', $normalized, true)) && in_array('NOMBRE', $normalized, true)) {
                        $headerRow = $rowNumber;
                        $map = $this->mapearCabeceras($values);
                    }
                    continue;
                }
                return [];
            }

            if ($this->filaVacia($values, $map)) {
                $blankStreak++;
                if ($blankStreak >= 50) {
                    break;
                }
                continue;
            }
            $blankStreak = 0;

            $rut = RutNormalizer::normalize((string) $this->value($values, $map, 'rut'));
            if (! $rut['normalizado']) {
                continue;
            }

            $out[$rut['normalizado']] = [
                'nombre' => $this->clean($this->value($values, $map, 'nombre')),
                'ubicacion' => $this->clean($this->value($values, $map, 'dependencia')),
                'comuna' => $this->clean($this->value($values, $map, 'comuna')),
                'tipo_contrato' => $this->clean($this->value($values, $map, 'calidad_juridica')),
                'planta' => $this->clean($this->value($values, $map, 'estamento')),
                'salud' => $this->clean($this->value($values, $map, 'salud')),
            ];
        }

        return $out;
    }

    private function xlsxSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false || trim($xml) === '') {
            return [];
        }

        $reader = new XMLReader();
        $reader->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT);
        $strings = [];
        $current = '';
        $inSi = false;

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'si') {
                $inSi = true;
                $current = '';
            } elseif ($inSi && $reader->nodeType === XMLReader::ELEMENT && $reader->localName === 't') {
                $current .= $reader->readString();
            } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'si') {
                $strings[] = $current;
                $inSi = false;
            }
        }
        $reader->close();

        return $strings;
    }

    private function xlsxSheets(ZipArchive $zip): array
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbook === false || $relsXml === false) {
            return [];
        }

        $rels = [];
        $reader = new XMLReader();
        $reader->XML($relsXml, null, LIBXML_NONET | LIBXML_COMPACT);
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'Relationship') {
                $id = $reader->getAttribute('Id');
                $target = $reader->getAttribute('Target');
                if ($id && $target) {
                    $rels[$id] = str_starts_with($target, 'xl/') ? $target : 'xl/' . ltrim($target, '/');
                }
            }
        }
        $reader->close();

        $sheets = [];
        $reader = new XMLReader();
        $reader->XML($workbook, null, LIBXML_NONET | LIBXML_COMPACT);
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'sheet') {
                $name = trim((string) $reader->getAttribute('name'));
                $rid = $reader->getAttribute('r:id') ?: $reader->getAttribute('id');
                if ($name !== '' && $rid && isset($rels[$rid])) {
                    $sheets[$name] = $rels[$rid];
                }
            }
        }
        $reader->close();

        return $sheets;
    }

    private function xlsxRows(ZipArchive $zip, string $entry, array $sharedStrings): \Generator
    {
        $xml = $zip->getFromName($entry);
        if ($xml === false || trim($xml) === '') {
            return;
        }

        $reader = new XMLReader();
        $reader->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT);

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') {
                continue;
            }

            $rowNumber = (int) ($reader->getAttribute('r') ?: 0);
            $values = [];
            $rowDepth = $reader->depth;

            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'row' && $reader->depth === $rowDepth) {
                    break;
                }

                if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'c') {
                    $cellRef = (string) $reader->getAttribute('r');
                    $type = (string) $reader->getAttribute('t');
                    $index = $this->columnIndexFromCellRef($cellRef);
                    if ($index === null) {
                        $index = count($values);
                    }
                    $values[$index] = $this->xlsxCellValue($reader, $type, $sharedStrings);
                }
            }

            if ($rowNumber <= 0) {
                $rowNumber = 1;
            }
            ksort($values);
            yield $rowNumber => $values;
        }

        $reader->close();
    }

    private function xlsxCellValue(XMLReader $reader, string $type, array $sharedStrings): ?string
    {
        $value = null;
        $text = '';
        $depth = $reader->depth;

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'c' && $reader->depth === $depth) {
                break;
            }

            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'v') {
                $value = $reader->readString();
            } elseif ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 't') {
                $text .= $reader->readString();
            }
        }

        if ($type === 's') {
            $idx = is_numeric($value) ? (int) $value : null;
            return $idx !== null ? ($sharedStrings[$idx] ?? null) : null;
        }

        if ($type === 'inlineStr') {
            return $text !== '' ? $text : null;
        }

        if ($type === 'b') {
            return $value === '1' ? 'SI' : 'NO';
        }

        return $value !== null ? (string) $value : ($text !== '' ? $text : null);
    }

    private function columnIndexFromCellRef(string $cellRef): ?int
    {
        if (! preg_match('/^([A-Z]+)/i', $cellRef, $m)) {
            return null;
        }

        $letters = strtoupper($m[1]);
        $num = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $num = ($num * 26) + (ord($letters[$i]) - 64);
        }

        return $num - 1;
    }

    private function prepareLongRunningImport(): void
    {
        @ini_set('memory_limit', '1024M');
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        try {
            DB::connection()->disableQueryLog();
        } catch (\Throwable $e) {
            // No bloquear importación si el driver no permite desactivar query log.
        }
    }

    private function resolveFuncionarioCached(?string $rutNormalizado, ?string $rut, ?string $establecimientoManual, ?string $comunaManual): array
    {
        $cacheKey = (string) $rutNormalizado . '|' . mb_strtoupper(trim((string) $establecimientoManual)) . '|' . mb_strtoupper(trim((string) $comunaManual));
        if (array_key_exists($cacheKey, $this->resolverCache)) {
            return $this->resolverCache[$cacheKey];
        }

        $this->resolverCache[$cacheKey] = $this->resolver->resolve($rutNormalizado, $rut, $establecimientoManual, $comunaManual);
        return $this->resolverCache[$cacheKey];
    }

    private function estadosAnteriores(LicenciaMedica $licencia): array
    {
        return [
            LicenciaEstadoService::ADMINISTRATIVO => $licencia->estado_administrativo_codigo,
            LicenciaEstadoService::COMPIN => $licencia->estado_compin_codigo,
            LicenciaEstadoService::RECUPERACION => $licencia->estado_recuperacion_codigo,
        ];
    }

    private function registrarCambiosEstado(
        LicenciaMedica $licencia,
        array $anteriores,
        int $userId,
        int $importacionId
    ): void {
        foreach ($this->estados->dimensiones() as $dimension) {
            $columna = $this->estados->columna($dimension);
            $anterior = $anteriores[$dimension] ?? null;
            $nuevo = $licencia->{$columna};

            if ($anterior === $nuevo || $nuevo === null) {
                continue;
            }

            LicenciaMedicaHistorial::create([
                'licencia_medica_id' => $licencia->id,
                'accion' => 'cambio_estado',
                'descripcion' => 'Estado registrado por importación de seguimiento.',
                'estado_dimension' => $dimension,
                'estado_anterior' => $anterior,
                'estado_nuevo' => $nuevo,
                'datos_anteriores' => [$columna => $anterior],
                'datos_nuevos' => [$columna => $nuevo],
                'origen' => 'importacion_excel',
                'importacion_id' => $importacionId,
                'user_id' => $userId,
                'created_at' => now(),
            ]);
        }
    }

    private function resolverSistemaSalud(?string $salud): ?string
    {
        $salud = mb_strtoupper(trim((string) $salud));
        if ($salud === '') {
            return null;
        }
        if (str_contains($salud, 'FONASA')) {
            return 'FONASA';
        }
        if (str_contains($salud, 'ISAPRE')) {
            return 'ISAPRE';
        }
        return null;
    }

    private function resolverInstitucionSalud(?string $salud): ?string
    {
        $salud = trim((string) $salud);
        if ($salud === '') {
            return null;
        }

        $upper = mb_strtoupper($salud);
        if (str_contains($upper, 'FONASA')) {
            return 'FONASA';
        }

        $upper = preg_replace('/^ISAPRE\s+/i', '', $salud) ?: $salud;
        return trim($upper) ?: null;
    }

    private function detectarFilaCabecera($sheet): ?int
    {
        $highestColumn = $sheet->getHighestDataColumn();
        $max = min((int) $sheet->getHighestDataRow(), 20);
        for ($row = 1; $row <= $max; $row++) {
            $values = $sheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, null, true, false)[0] ?? [];
            $normalized = array_map(fn ($v) => $this->normalizarCabecera($v), $values);
            if (in_array('RUT', $normalized, true) && (in_array('NLICENCIA', $normalized, true) || in_array('NDELICENCIA', $normalized, true) || in_array('LICENCIA', $normalized, true))) {
                return $row;
            }
            if (in_array('RUT', $normalized, true) && in_array('NOMBRE', $normalized, true) && (in_array('UBICACION', $normalized, true) || in_array('COMUNA', $normalized, true))) {
                return $row;
            }
        }
        return null;
    }

    private function mapearCabeceras(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $i => $header) {
            $normalized[$this->normalizarCabecera($header)] = $i;
        }

        return [
            'licencia' => $this->find($normalized, ['NLICENCIA', 'NDELICENCIA', 'NOLICENCIA', 'NUMEROLICENCIA', 'LICENCIA']),
            'dv' => $this->find($normalized, ['DV']),
            'vigencia' => $this->find($normalized, ['VIGENCIA']),
            'rut' => $this->find($normalized, ['RUT', 'RUN']),
            'nombre' => $this->find($normalized, ['NOMBRE', 'NOMBRES']),
            'dependencia' => $this->find($normalized, ['SUBDIRECCIONEEJVTF', 'SUBDIRECCIONEEJVTF', 'UBICACION', 'ESTABLECIMIENTO', 'DEPENDENCIA']),
            'comuna' => $this->find($normalized, ['COMUNA']),
            'calidad_juridica' => $this->find($normalized, ['CALIDADJURIDICA', 'TIPODECONTRATO']),
            'estamento' => $this->find($normalized, ['ESTAMENTO', 'PLANTA']),
            'salud' => $this->find($normalized, ['INSTITUCIONSALUDACTUAL', 'SALUD']),
            'fecha_emision' => $this->find($normalized, ['FECHEMISION', 'FECHAEMISION', 'FECHAEMISIONLICENCIA']),
            'fecha_recepcion' => $this->find($normalized, ['FECHRECEPCION', 'FECHARECEPCION']),
            'fecha_inicio' => $this->find($normalized, ['FECHAINICIO', 'FECHAINICIOLICENCIA', 'FECHAINICIOREPOSO']),
            'fecha_termino' => $this->find($normalized, ['FECHTERMINO', 'FECHATERMINO', 'FECHATERMINOLICENCIA']),
            'dias_lic' => $this->find($normalized, ['DIASLIC', 'NDIAS', 'DIASLICENCIA']),
            'dias_lab' => $this->find($normalized, ['DIASLAB', 'DIASLABORALES']),
            'tipo_lm' => $this->find($normalized, ['TIPODELM', 'TIPOLM', 'TIPOLICENCIA']),
            'valor_licencia' => $this->find($normalized, ['VALORLICENCIA', 'MONTOLICENCIA']),
            'se_puede_recuperar' => $this->find($normalized, ['SEPUEDERECUPERAR']),
            'primer_estado' => $this->find($normalized, ['PRIMERESTADO']),
            'dias_autorizados' => $this->find($normalized, ['DIASAUTORIZADOS']),
            'segundo_estado' => $this->find($normalized, ['SEGUNDOESTADO']),
            'fecha_revision' => $this->find($normalized, ['FECHAREVISION']),
            'gestion_cobro' => $this->find($normalized, ['GESTIONDECOBRO']),
            'numero_ord' => $this->find($normalized, ['NUMERODEORD', 'NUMEROORD']),
            'fecha_cobro' => $this->find($normalized, ['FECHADELCOBRO', 'FECHACOBRO']),
            'numero_ord_nuevo_cobro' => $this->find($normalized, ['NUMEROORDNUEVOCOBRO', 'NUMEROORDNUEVOCOBRO']),
            'fecha_nuevo_cobro' => $this->find($normalized, ['FECHANUEVOCOBRO', 'FECHADELNUEVOCOBRO']),
            'ingresar_siaper' => $this->find($normalized, ['INGRESARASIAPER']),
            'rex_siaper' => $this->find($normalized, ['REXSIAPER']),
            'realizo_apelacion' => $this->find($normalized, ['REALIZOAPELACION']),
        ];
    }

    private function find(array $normalizedHeaders, array $candidates): ?int
    {
        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $normalizedHeaders)) return $normalizedHeaders[$candidate];
        }
        return null;
    }

    private function normalizarCabecera($value): string
    {
        $value = mb_strtoupper(trim((string) $value));
        $value = strtr($value, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N', 'º' => '', '°' => '', 'ª' => '',
        ]);
        return preg_replace('/[^A-Z0-9]/', '', $value) ?: '';
    }

    private function filaVacia(array $row, array $map): bool
    {
        foreach (['licencia', 'rut', 'nombre'] as $key) {
            $value = $this->value($row, $map, $key);
            if ($this->clean($value) !== null) return false;
        }
        return true;
    }

    private function value(array $row, array $map, string $key)
    {
        $idx = $map[$key] ?? null;
        return $idx === null ? null : ($row[$idx] ?? null);
    }

    private function clean($value): ?string
    {
        if ($value instanceof \DateTimeInterface) return $value->format('Y-m-d');
        $value = trim((string) $value);
        if ($value === '' || mb_strtoupper($value) === 'NULL') return null;
        return preg_replace('/\s+/', ' ', $value) ?: $value;
    }

    private function date($value): ?string
    {
        if ($value instanceof \DateTimeInterface) return Carbon::instance($value)->format('Y-m-d');
        if (is_numeric($value) && (float) $value > 20000) {
            try { return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->format('Y-m-d'); } catch (\Throwable $e) {}
        }
        $value = $this->clean($value);
        if (! $value) return null;
        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'd-m-y', 'd/m/y'] as $format) {
            try { return Carbon::createFromFormat($format, $value)->format('Y-m-d'); } catch (\Throwable $e) {}
        }
        try { return Carbon::parse($value)->format('Y-m-d'); } catch (\Throwable $e) { return null; }
    }

    private function int($value): ?int
    {
        if ($value === null || $value === '') return null;
        $value = preg_replace('/[^0-9-]/', '', (string) $value);
        return $value === '' ? null : (int) $value;
    }

    private function money($value): ?float
    {
        if ($value === null || $value === '') return null;
        $clean = preg_replace('/[^0-9,-]/', '', (string) $value) ?: '';
        $clean = str_replace(',', '.', $clean);
        return $clean === '' ? null : (float) $clean;
    }

    private function calcularDiasLaborales(Carbon $inicio, Carbon $termino): int
    {
        $dias = 0;
        $cursor = $inicio->copy()->startOfDay();
        $end = $termino->copy()->startOfDay();
        while ($cursor->lte($end)) {
            if (! $cursor->isWeekend()) $dias++;
            $cursor->addDay();
        }
        return $dias;
    }

    private function addInconsistencia(array &$stats, string $sheetName, ?int $row, string $motivo): void
    {
        if (count($stats['muestras_inconsistencias']) >= 25) return;
        $stats['muestras_inconsistencias'][] = [
            'hoja' => $sheetName,
            'fila' => $row,
            'motivo' => $motivo,
        ];
    }
}
