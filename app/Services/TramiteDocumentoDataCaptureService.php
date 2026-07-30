<?php

namespace App\Services;

use App\Models\TramiteDocumento;
use App\Support\PdfTextExtractor;
use App\Support\RutChile;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class TramiteDocumentoDataCaptureService
{
    private const CAPTURE_ERROR_SNIPPET_LIMIT = 1800;

    public function __construct(private readonly PdfTextExtractor $pdfTextExtractor)
    {
    }

    public function capture(TramiteDocumento $documento): array
    {
        $documento->loadMissing('tramite');

        if (!$documento->supportsDataCapture()) {
            return $this->persistNotApplicable($documento, 'El tipo de documento no participa en captura de datos automática.');
        }

        if ((string) $documento->estado_revision !== 'aprobado') {
            return $this->persistNotApplicable($documento, 'La captura sólo está disponible para documentos aprobados.');
        }

        if (!$documento->path || !Storage::disk('local')->exists($documento->path)) {
            return $this->persistError($documento, 'No se encontró el archivo PDF aprobado para procesar la captura de datos.');
        }

        try {
            $absolutePath = Storage::disk('local')->path($documento->path);
            $directText = $this->pdfTextExtractor->extractDirectText($absolutePath);
            $method = 'pdf_texto';
            $text = $directText['text'];
            $warnings = $directText['warnings'];
            $ocrMeta = [];

            if (!$this->hasUsefulText($text)) {
                $ocrText = $this->pdfTextExtractor->extractTextUsingOcr($absolutePath);
                $text = $ocrText['text'];
                $method = 'ocr';
                $warnings = array_merge($warnings, $ocrText['warnings']);
                $ocrMeta = $ocrText['meta'] ?? [];
            }

            $parsed = $this->parseDocumentText($text, $documento);
            $comparison = $this->compareAgainstAssociatedPeriod($documento, $parsed['periodos']);
            $rutCoincide = $this->compareRutWithTramite($documento, $parsed['rut']);
            $dateAnalysis = $parsed['date_analysis'] ?? [];
            $documentMetadata = $parsed['document_metadata'] ?? [];
            $expectsPeriods = $this->documentTypeRequiresPeriods($documento);
            $hasDocumentSignals = !empty($dateAnalysis['document_date'])
                || !empty($dateAnalysis['certification_date'])
                || !empty($documentMetadata['issuer_name'])
                || !empty($documentMetadata['document_number'])
                || !empty($documentMetadata['detected_label']);
            $hasDateSignals = $hasDocumentSignals
                || !empty($dateAnalysis['labor_start'])
                || !empty($dateAnalysis['labor_end'])
                || !empty($dateAnalysis['labor_start_partial_text'])
                || !empty($dateAnalysis['labor_end_partial_text'])
                || !empty($parsed['periodos']);
            $hasPartialDates = $expectsPeriods
                && (!empty($dateAnalysis['labor_start_partial_text']) || !empty($dateAnalysis['labor_end_partial_text']));
            $comparisonNeedsReview = $expectsPeriods
                && in_array($comparison['code'], ['rango_resumen_con_interrupciones', 'coincidencia_parcial', 'no_coincide', 'sin_periodos_detectados'], true);
            $missingExpectedPeriods = $expectsPeriods && empty($parsed['periodos']);

            $estado = 'procesado';
            if (($parsed['rut'] ?? null) === null && !$hasDateSignals) {
                $estado = 'sin_resultado';
            } elseif (
                $hasPartialDates
                || $rutCoincide === false
                || $comparisonNeedsReview
                || $missingExpectedPeriods
            ) {
                $estado = 'requiere_revision';
            }

            $message = match ($estado) {
                'procesado' => 'Captura de datos ejecutada correctamente.',
                'sin_resultado' => 'Se aprobó el documento, pero no fue posible detectar datos estructurados en el archivo.',
                default => 'Captura ejecutada. Requiere revisión manual de los datos detectados.',
            };

            if ($hasPartialDates) {
                $partials = [];
                if (!empty($dateAnalysis['labor_start_partial_text']) && empty($dateAnalysis['labor_start'])) {
                    $partials[] = 'inicio parcial: ' . $dateAnalysis['labor_start_partial_text'];
                }
                if (!empty($dateAnalysis['labor_end_partial_text']) && empty($dateAnalysis['labor_end'])) {
                    $partials[] = 'término parcial: ' . $dateAnalysis['labor_end_partial_text'];
                }
                if (!empty($partials)) {
                    $message .= ' Se detectaron fechas parciales (' . implode('; ', $partials) . ').';
                }
            }

            $payload = [
                'snippet' => $this->limitText($text),
                'normalized_snippet' => $this->limitText((string) ($parsed['normalized_text'] ?? '')),
                'warnings' => array_values(array_unique(array_filter($warnings))),
                'comparison' => $comparison,
                'rut_coincide_con_tramite' => $rutCoincide,
                'rut_analysis' => $parsed['rut_analysis'] ?? null,
                'date_analysis' => $dateAnalysis,
                'document_metadata' => $documentMetadata,
                'source' => $method,
                'ocr_meta' => $ocrMeta,
            ];

            $firstPeriod = $parsed['periodos'][0] ?? null;
            $lastPeriod = !empty($parsed['periodos']) ? $parsed['periodos'][count($parsed['periodos']) - 1] : null;

            $documento->forceFill([
                'captura_estado' => $estado,
                'captura_metodo' => $method,
                'captura_ejecutada_at' => now(),
                'captura_rut' => $parsed['rut'],
                'captura_periodos' => $parsed['periodos'],
                'captura_rango_inicio' => $firstPeriod['inicio'] ?? null,
                'captura_rango_termino' => $lastPeriod['termino'] ?? null,
                'captura_total_periodos' => count($parsed['periodos']),
                'captura_tiene_interrupciones' => $parsed['tiene_interrupciones'],
                'captura_comparacion_periodo' => $comparison['code'],
                'captura_observaciones' => $message,
                'captura_payload' => $payload,
            ])->save();

            return [
                'ok' => $estado !== 'sin_resultado',
                'status' => $estado,
                'message' => $message,
                'method' => $method,
                'rut' => $parsed['rut'],
                'periodos' => $parsed['periodos'],
                'comparison' => $comparison,
                'rut_coincide' => $rutCoincide,
            ];
        } catch (\Throwable $e) {
            Log::warning('[TRAMITES][CAPTURA] Error al capturar datos de documento aprobado', [
                'documento_id' => $documento->id,
                'tramite_id' => $documento->tramite_id,
                'error' => $e->getMessage(),
            ]);

            return $this->persistError($documento, 'La captura de datos falló: ' . $e->getMessage());
        }
    }


    public function applyManualDateCompletion(TramiteDocumento $documento, array $inputs): array
    {
        $documento->loadMissing('tramite');

        if (!$this->documentTypeRequiresPeriods($documento)) {
            return [
                'ok' => false,
                'status' => 'no_aplica',
                'message' => 'Este tipo de documento no admite fechas laborales manuales de inicio y término.',
            ];
        }

        $payload = is_array($documento->captura_payload) ? $documento->captura_payload : [];
        $dateAnalysis = is_array(data_get($payload, 'date_analysis')) ? data_get($payload, 'date_analysis') : [];
        $manualInputs = [
            'labor_start' => trim((string) ($inputs['labor_start_text'] ?? '')),
            'labor_end' => trim((string) ($inputs['labor_end_text'] ?? '')),
        ];

        data_set($payload, 'manual_date_inputs', $manualInputs);

        $startInput = $manualInputs['labor_start'];
        if ($startInput !== '') {
            $startInfo = $this->parseDateValueInfo($startInput);
            $dateAnalysis['labor_start_raw'] = $startInfo['raw'] ?? $startInput;
            if (!empty($startInfo['date'])) {
                $dateAnalysis['labor_start'] = $startInfo['date'];
                $dateAnalysis['labor_start_partial_text'] = null;
                $dateAnalysis['labor_start_manual_text'] = $startInput;
            } else {
                $dateAnalysis['labor_start'] = null;
                $dateAnalysis['labor_start_partial_text'] = $startInput;
            }
        }

        $endInput = $manualInputs['labor_end'];
        if ($endInput !== '') {
            $endInfo = $this->parseDateValueInfo($endInput);
            $dateAnalysis['labor_end_raw'] = $endInfo['raw'] ?? $endInput;
            if (!empty($endInfo['date'])) {
                $dateAnalysis['labor_end'] = $endInfo['date'];
                $dateAnalysis['labor_end_partial_text'] = null;
                $dateAnalysis['labor_end_manual_text'] = $endInput;
            } else {
                $dateAnalysis['labor_end'] = null;
                $dateAnalysis['labor_end_partial_text'] = $endInput;
            }
        }

        $periodos = $this->normalizePeriods($documento->captura_periodos ?? []);
        $isSinglePeriodType = in_array((string) $documento->tipo_documento, ['finiquitos'], true);
        if (!empty($dateAnalysis['labor_start']) && !empty($dateAnalysis['labor_end'])) {
            if ($isSinglePeriodType || count($periodos) <= 1) {
                $periodos = [[
                    'inicio' => $dateAnalysis['labor_start'],
                    'termino' => $dateAnalysis['labor_end'],
                ]];
                $dateAnalysis['selected_period_source'] = ($dateAnalysis['selected_period_source'] ?? 'manual_completion') . '_manual';
            }
        }

        if (!empty($periodos)) {
            $dateAnalysis['periodos'] = $periodos;
        }

        data_set($payload, 'date_analysis', $dateAnalysis);

        $comparison = $this->compareAgainstAssociatedPeriod($documento, $periodos);
        data_set($payload, 'comparison', $comparison);
        $rutCoincide = $this->compareRutWithTramite($documento, $documento->captura_rut);
        data_set($payload, 'rut_coincide_con_tramite', $rutCoincide);

        $documentMetadata = is_array(data_get($payload, 'document_metadata')) ? data_get($payload, 'document_metadata') : [];
        $expectsPeriods = $this->documentTypeRequiresPeriods($documento);
        $hasPartialDates = $expectsPeriods && (!empty($dateAnalysis['labor_start_partial_text']) || !empty($dateAnalysis['labor_end_partial_text']));
        $hasDocumentSignals = !empty($dateAnalysis['document_date']) || !empty($dateAnalysis['certification_date']) || !empty($documentMetadata['issuer_name']) || !empty($documentMetadata['document_number']) || !empty($documentMetadata['detected_label']);
        $hasDateSignals = $hasDocumentSignals || !empty($dateAnalysis['labor_start']) || !empty($dateAnalysis['labor_end']) || !empty($periodos) || $hasPartialDates;
        $comparisonNeedsReview = $expectsPeriods && in_array($comparison['code'], ['rango_resumen_con_interrupciones', 'coincidencia_parcial', 'no_coincide', 'sin_periodos_detectados'], true);
        $missingExpectedPeriods = $expectsPeriods && empty($periodos);
        $estado = 'procesado';
        if (!$documento->captura_rut && !$hasDateSignals) {
            $estado = 'sin_resultado';
        } elseif (
            $hasPartialDates
            || $rutCoincide === false
            || $comparisonNeedsReview
            || $missingExpectedPeriods
        ) {
            $estado = 'requiere_revision';
        }

        $message = 'Captura actualizada con revisión manual de fechas.';
        if ($hasPartialDates) {
            $partials = [];
            if (!empty($dateAnalysis['labor_start_partial_text']) && empty($dateAnalysis['labor_start'])) {
                $partials[] = 'inicio parcial: ' . $dateAnalysis['labor_start_partial_text'];
            }
            if (!empty($dateAnalysis['labor_end_partial_text']) && empty($dateAnalysis['labor_end'])) {
                $partials[] = 'término parcial: ' . $dateAnalysis['labor_end_partial_text'];
            }
            if (!empty($partials)) {
                $message .= ' Persisten fechas incompletas (' . implode('; ', $partials) . ').';
            }
        }

        $firstPeriod = $periodos[0] ?? null;
        $lastPeriod = !empty($periodos) ? $periodos[count($periodos) - 1] : null;

        $documento->forceFill([
            'captura_estado' => $estado,
            'captura_ejecutada_at' => now(),
            'captura_periodos' => $periodos,
            'captura_rango_inicio' => $firstPeriod['inicio'] ?? null,
            'captura_rango_termino' => $lastPeriod['termino'] ?? null,
            'captura_total_periodos' => count($periodos),
            'captura_tiene_interrupciones' => $this->hasInterruptions($periodos),
            'captura_comparacion_periodo' => $comparison['code'],
            'captura_observaciones' => $message,
            'captura_payload' => $payload,
        ])->save();

        return [
            'ok' => $estado !== 'sin_resultado',
            'status' => $estado,
            'message' => $message,
        ];
    }

    private function persistNotApplicable(TramiteDocumento $documento, string $message): array
    {
        $documento->forceFill([
            'captura_estado' => 'no_aplica',
            'captura_metodo' => null,
            'captura_ejecutada_at' => now(),
            'captura_rut' => null,
            'captura_periodos' => null,
            'captura_rango_inicio' => null,
            'captura_rango_termino' => null,
            'captura_total_periodos' => null,
            'captura_tiene_interrupciones' => null,
            'captura_comparacion_periodo' => null,
            'captura_observaciones' => $message,
            'captura_payload' => null,
        ])->save();

        return [
            'ok' => false,
            'status' => 'no_aplica',
            'message' => $message,
        ];
    }

    private function persistError(TramiteDocumento $documento, string $message): array
    {
        $documento->forceFill([
            'captura_estado' => 'error',
            'captura_metodo' => null,
            'captura_ejecutada_at' => now(),
            'captura_observaciones' => $message,
        ])->save();

        return [
            'ok' => false,
            'status' => 'error',
            'message' => $message,
        ];
    }

    private function extractDirectText(string $absolutePath): array
    {
        $pdftotext = $this->findBinary('pdftotext');
        if ($pdftotext === null) {
            return [
                'text' => '',
                'warnings' => ['La herramienta pdftotext no está instalada en el servidor; se intentará OCR si está disponible.'],
            ];
        }

        $process = new Process([$pdftotext, '-layout', '-nopgbrk', $absolutePath, '-']);
        $process->setTimeout((float) config('tramites.captura.pdf_timeout_seconds', 60));
        $process->run();

        $warnings = [];
        if (!$process->isSuccessful()) {
            $warnings[] = 'No fue posible leer texto embebido del PDF.';
        }

        return [
            'text' => (string) $process->getOutput(),
            'warnings' => $warnings,
        ];
    }

    private function extractTextUsingOcr(string $absolutePath): array
    {
        if (!(bool) config('tramites.captura.enabled', true)) {
            throw new \RuntimeException('La captura OCR está deshabilitada en la configuración del sistema.');
        }

        $driver = (string) config('tramites.captura.ocr_driver', 'auto');

        return match ($driver) {
            'python_easyocr' => $this->extractTextUsingPythonBridge($absolutePath),
            'tesseract' => $this->extractTextUsingLocalTesseract($absolutePath),
            default => $this->extractTextUsingConfiguredDriver($absolutePath),
        };
    }

    private function extractTextUsingConfiguredDriver(string $absolutePath): array
    {
        try {
            return $this->extractTextUsingPythonBridge($absolutePath);
        } catch (\Throwable $pythonException) {
            try {
                $legacy = $this->extractTextUsingLocalTesseract($absolutePath);
                $legacy['warnings'][] = 'Se utilizó OCR local como respaldo porque la integración Python no estuvo disponible: ' . $pythonException->getMessage();

                return $legacy;
            } catch (\Throwable $legacyException) {
                throw new \RuntimeException(
                    'No fue posible ejecutar OCR. Python OCR: ' . $pythonException->getMessage()
                    . ' | OCR local: ' . $legacyException->getMessage(),
                    0,
                    $legacyException
                );
            }
        }
    }

    private function extractTextUsingPythonBridge(string $absolutePath): array
    {
        $pythonBin = $this->resolvePythonBinary();
        $scriptPath = $this->resolvePythonScriptPath();
        $maxPages = max(1, (int) config('tramites.captura.max_pages_ocr', 3));
        $dpi = max(72, (int) config('tramites.captura.ocr_dpi', 96));
        $maxWidth = max(900, (int) config('tramites.captura.ocr_max_width', 1400));

        if ($pythonBin === null) {
            throw new \RuntimeException('No se encontró el binario Python configurado para OCR.');
        }

        if ($scriptPath === null || !is_file($scriptPath)) {
            throw new \RuntimeException('No se encontró el script puente de OCR Python configurado para Trámites.');
        }

        $process = new Process([
            $pythonBin,
            $scriptPath,
            $absolutePath,
            (string) $maxPages,
            (string) $dpi,
            (string) $maxWidth,
        ]);
        $process->setTimeout((float) config('tramites.captura.ocr_timeout_seconds', 180));

        $tmpDir = config('tramites.captura.python_tmp_dir');
        if (is_string($tmpDir) && trim($tmpDir) !== '') {
            $env = $process->getEnv();
            $env['TMPDIR'] = $tmpDir;
            $process->setEnv($env);
        }

        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException($this->normalizeProcessError($process, 'El OCR Python no pudo procesar el PDF escaneado.'));
        }

        $payload = $this->decodeOcrJsonOutput((string) $process->getOutput());
        $text = trim((string) data_get($payload, 'text', ''));
        if ($text === '') {
            throw new \RuntimeException('El OCR Python finalizó sin texto utilizable en el documento.');
        }

        $warnings = ['Se utilizó OCR Python (EasyOCR) para capturar texto desde el PDF aprobado.'];
        $stderr = trim((string) $process->getErrorOutput());
        if ($stderr !== '') {
            $warnings[] = $this->limitText($stderr);
        }

        return [
            'text' => $text,
            'warnings' => $warnings,
            'meta' => [
                'driver' => 'python_easyocr',
                'pages_processed' => (int) data_get($payload, 'pages_processed', 0),
                'dpi' => $dpi,
                'max_pages' => $maxPages,
                'max_width' => $maxWidth,
                'stderr' => $stderr !== '' ? $this->limitText($stderr) : null,
            ],
        ];
    }

    private function extractTextUsingLocalTesseract(string $absolutePath): array
    {
        $tempDir = storage_path('app/tmp/tramites-captura-' . uniqid('', true));
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }

        $maxPages = max(1, (int) config('tramites.captura.max_pages_ocr', 3));
        $dpi = max(72, (int) config('tramites.captura.ocr_dpi', 96));
        $language = (string) config('tramites.captura.ocr_language', 'spa+eng');
        $tesseract = $this->findBinary('tesseract');

        if ($tesseract === null) {
            throw new \RuntimeException('No se pudo ejecutar OCR local porque la herramienta tesseract no está instalada en el servidor.');
        }

        try {
            $pages = $this->renderPdfPagesForOcr($absolutePath, $tempDir, $dpi, $maxPages);
            if (empty($pages)) {
                throw new \RuntimeException('No fue posible rasterizar el PDF aprobado para aplicar OCR local.');
            }

            $text = '';
            foreach ($pages as $pagePath) {
                $ocr = new Process([$tesseract, $pagePath, 'stdout', '-l', $language, '--psm', '6']);
                $ocr->setTimeout((float) config('tramites.captura.ocr_timeout_seconds', 180));
                $ocr->run();

                if ($ocr->isSuccessful()) {
                    $text .= "\n" . $ocr->getOutput();
                }
            }

            return [
                'text' => $text,
                'warnings' => ['Se utilizó OCR local (Tesseract) para capturar texto desde el PDF aprobado.'],
                'meta' => [
                    'driver' => 'tesseract',
                    'dpi' => $dpi,
                    'max_pages' => $maxPages,
                ],
            ];
        } finally {
            $this->deleteDirectory($tempDir);
        }
    }

    private function renderPdfPagesForOcr(string $absolutePath, string $tempDir, int $dpi, int $maxPages): array
    {
        $pdftoppm = $this->findBinary('pdftoppm');
        if ($pdftoppm !== null) {
            $render = new Process([$pdftoppm, '-f', '1', '-l', (string) $maxPages, '-r', (string) $dpi, '-png', $absolutePath, $tempDir . DIRECTORY_SEPARATOR . 'page']);
            $render->setTimeout((float) config('tramites.captura.ocr_timeout_seconds', 180));
            $render->mustRun();

            $pages = glob($tempDir . DIRECTORY_SEPARATOR . 'page-*.png') ?: [];
            natcasesort($pages);

            return array_values($pages);
        }

        if (extension_loaded('imagick') && class_exists('Imagick')) {
            return $this->renderPdfPagesUsingImagick($absolutePath, $tempDir, $dpi, $maxPages);
        }

        throw new \RuntimeException('El servidor no tiene pdftoppm (poppler-utils) ni la extensión Imagick disponibles para convertir PDFs escaneados antes del OCR local.');
    }

    private function renderPdfPagesUsingImagick(string $absolutePath, string $tempDir, int $dpi, int $maxPages): array
    {
        $pages = [];

        for ($page = 0; $page < $maxPages; $page++) {
            try {
                $image = new \Imagick();
                $image->setResolution($dpi, $dpi);
                $image->readImage($absolutePath . '[' . $page . ']');
                $image->setImageFormat('png');
                if (method_exists($image, 'setImageAlphaChannel') && defined('Imagick::ALPHACHANNEL_REMOVE')) {
                    $image->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
                }
                $image->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);

                $targetPath = $tempDir . DIRECTORY_SEPARATOR . 'page-' . str_pad((string) ($page + 1), 4, '0', STR_PAD_LEFT) . '.png';
                $image->writeImage($targetPath);
                $image->clear();
                $image->destroy();

                if (is_file($targetPath)) {
                    $pages[] = $targetPath;
                }
            } catch (\Throwable $e) {
                if (empty($pages)) {
                    throw new \RuntimeException('Imagick no pudo convertir el PDF para OCR: ' . $e->getMessage(), 0, $e);
                }

                break;
            }
        }

        return $pages;
    }

    private function findBinary(string $binary): ?string
    {
        $finder = new ExecutableFinder();

        return $finder->find($binary);
    }

    private function resolvePythonBinary(): ?string
    {
        $configured = trim((string) config('tramites.captura.python_bin', ''));
        if ($configured !== '') {
            if ($this->isExecutableFile($configured)) {
                return $configured;
            }

            $found = $this->findBinary($configured);
            if ($found !== null) {
                return $found;
            }
        }

        return $this->findBinary('python3') ?: $this->findBinary('python');
    }

    private function resolvePythonScriptPath(): ?string
    {
        $configured = trim((string) config('tramites.captura.python_script', ''));
        if ($configured === '') {
            return null;
        }

        return $configured;
    }

    private function isExecutableFile(string $path): bool
    {
        return is_file($path) && is_readable($path);
    }

    private function decodeOcrJsonOutput(string $output): array
    {
        $trimmed = trim($output);
        if ($trimmed === '') {
            throw new \RuntimeException('El OCR Python no retornó salida utilizable.');
        }

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $lines = preg_split('/\R/u', $trimmed) ?: [];
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $candidate = trim((string) $lines[$i]);
            if ($candidate === '') {
                continue;
            }

            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new \RuntimeException('La respuesta del OCR Python no pudo interpretarse como JSON.');
    }

    private function normalizeProcessError(Process $process, string $fallbackMessage): string
    {
        $stderr = trim((string) $process->getErrorOutput());
        $stdout = trim((string) $process->getOutput());
        $message = $stderr !== '' ? $stderr : ($stdout !== '' ? $stdout : $fallbackMessage);

        return $this->limitText($message);
    }

    private function parseDocumentText(string $text, TramiteDocumento $documento): array
    {
        $normalizedText = $this->normalizeTextForParsing($text);
        $rutAnalysis = $this->extractRut($normalizedText, $documento);
        $dateExtraction = $this->extractPeriods($normalizedText, $documento);
        $periodos = $this->normalizePeriods($dateExtraction['periodos'] ?? []);
        $dateAnalysis = $dateExtraction['analysis'] ?? [];
        $dateAnalysis['periodos'] = $periodos;
        $documentMetadata = $this->extractDocumentMetadata($normalizedText, $documento, $dateAnalysis, $periodos);
        if ($this->documentTypeRequiresPeriods($documento) && empty($dateAnalysis['labor_start']) && !empty($periodos[0]['inicio'])) {
            $dateAnalysis['labor_start'] = $periodos[0]['inicio'];
        }
        if ($this->documentTypeRequiresPeriods($documento) && empty($dateAnalysis['labor_end']) && !empty($periodos[count($periodos) - 1]['termino'])) {
            $dateAnalysis['labor_end'] = $periodos[count($periodos) - 1]['termino'];
        }

        return [
            'rut' => $rutAnalysis['selected'] ?? null,
            'rut_analysis' => $rutAnalysis,
            'normalized_text' => $normalizedText,
            'periodos' => $periodos,
            'date_analysis' => $dateAnalysis,
            'document_metadata' => $documentMetadata,
            'tiene_interrupciones' => $this->hasInterruptions($periodos),
            'tipo_documento' => $documento->tipo_documento,
        ];
    }

    private function extractRut(string $text, TramiteDocumento $documento): array
    {
        $matches = [];
        if (!preg_match_all('/\b[0-9OIL]{1,2}(?:[\.\s]?[0-9OIL]{3}){2}[-‐‑–—]?[0-9KkOo]\b/u', $text, $matches, PREG_OFFSET_CAPTURE)) {
            return [
                'selected' => null,
                'selected_role' => null,
                'roles' => [],
                'role_contexts' => [],
                'candidates' => [],
            ];
        }

        $tramiteRut = RutChile::normalize((string) $documento->tramite?->rut_snapshot);
        $tramiteRut = $tramiteRut ? strtoupper((string) $tramiteRut['rut']) : null;
        $nameTokens = $this->extractRelevantNameTokens((string) $documento->tramite?->nombre_completo_snapshot);
        $explicitRoleMatches = $this->extractExplicitRutRoleMatches($text);
        $explicitRoles = [];
        foreach ($explicitRoleMatches as $role => $detail) {
            if (!empty($detail['rut'])) {
                $explicitRoles[$role] = $detail['rut'];
            }
        }

        $candidates = [];

        foreach ($matches[0] as [$rawCandidate, $offset]) {
            $normalized = RutChile::normalize($this->sanitizeRutCandidate((string) $rawCandidate));
            if (!$normalized) {
                continue;
            }

            $rut = strtoupper((string) ($normalized['rut'] ?? ''));
            if ($rut === '') {
                continue;
            }

            $context = $this->extractTextWindow($text, (int) $offset, mb_strlen((string) $rawCandidate), 110);
            $scores = $this->scoreRutContext($context, $nameTokens, $rut === $tramiteRut, $explicitRoles, $rut);

            if (!isset($candidates[$rut])) {
                $candidates[$rut] = [
                    'rut' => $rut,
                    'raw' => (string) $rawCandidate,
                    'status' => (string) ($normalized['status'] ?? 'unknown'),
                    'context' => $this->limitText($context),
                    'scores' => $scores,
                    'matches_tramite' => $rut === $tramiteRut,
                ];
                continue;
            }

            foreach (['trabajador', 'empleador', 'representante', 'priority'] as $key) {
                $candidates[$rut]['scores'][$key] = max((int) ($candidates[$rut]['scores'][$key] ?? 0), (int) ($scores[$key] ?? 0));
            }
            if ($rut === $tramiteRut) {
                $candidates[$rut]['matches_tramite'] = true;
            }
            if (strlen((string) $context) > strlen((string) ($candidates[$rut]['context'] ?? ''))) {
                $candidates[$rut]['context'] = $this->limitText($context);
            }
        }

        $ordered = array_values($candidates);
        usort($ordered, function (array $a, array $b): int {
            return $this->candidatePriorityScore($b) <=> $this->candidatePriorityScore($a);
        });

        [$roles, $roleContexts] = $this->assignRutRoles($ordered, $explicitRoleMatches);

        $selected = $roles['trabajador'] ?? ($ordered[0]['rut'] ?? null);
        $selectedRole = null;
        if ($selected !== null) {
            foreach (['trabajador', 'empleador', 'representante'] as $role) {
                if (($roles[$role] ?? null) === $selected) {
                    $selectedRole = $role;
                    break;
                }
            }
        }

        return [
            'selected' => $selected,
            'selected_role' => $selectedRole,
            'roles' => $roles,
            'role_contexts' => $roleContexts,
            'candidates' => array_map(function (array $candidate): array {
                $candidate['role'] = $this->inferRutRole($candidate);
                return $candidate;
            }, $ordered),
        ];
    }

    private function sanitizeRutCandidate(string $value): string
    {
        return strtr($value, [
            'O' => '0',
            'o' => '0',
            'I' => '1',
            'l' => '1',
            '–' => '-',
            '—' => '-',
            '‐' => '-',
            '‑' => '-',
        ]);
    }

    private function extractPeriods(string $text, TramiteDocumento $documento): array
    {
        $analysis = $this->analyzeDatesByContext($text, $documento);
        $periods = $analysis['periodos'] ?? [];
        $datePattern = $this->buildDatePattern();
        $type = (string) $documento->tipo_documento;
        $selectedSource = (string) ($analysis['selected_period_source'] ?? '');
        $hasContextualLaborSignal = in_array($selectedSource, [
            'finiquito_clause',
            'laboral_presto_servicios',
            'laboral_desde_hasta',
            'orden_trabajo_vigencia',
            'orden_trabajo_a_contar_termino',
            'decreto_desde_hasta_estructurado',
        ], true);

        if (!$this->documentTypeRequiresPeriods($documento)) {
            return [
                'periodos' => [],
                'analysis' => $analysis,
            ];
        }

        if (in_array($type, ['finiquitos', 'decretos'], true) && $hasContextualLaborSignal) {
            if (!empty($analysis['labor_start']) && !empty($analysis['labor_end'])) {
                $periods = [[
                    'inicio' => $analysis['labor_start'],
                    'termino' => $analysis['labor_end'],
                ]];
            } else {
                $periods = $this->normalizePeriods(array_filter($periods, static function (array $period): bool {
                    return !empty($period['inicio']) && !empty($period['termino']);
                }));
            }

            $analysis['periodos'] = $periods;

            return [
                'periodos' => $periods,
                'analysis' => $analysis,
            ];
        }

        $pairPatterns = [
            '/(?P<inicio>' . $datePattern . ')\s*(?:,|\s)*(?:al|hasta)\s+(?:el\s+)?(?P<termino>' . $datePattern . ')/iu',
            '/(?:desde|a\s+contar\s+del?)\s+(?:el\s+)?(?P<inicio>' . $datePattern . ').{0,120}?(?:al|hasta)\s+(?:el\s+)?(?P<termino>' . $datePattern . ')/iu',
        ];

        if ($type === 'finiquitos') {
            $pairPatterns[] = '/prest[oó]\s+servicios.{0,260}?(?:desde|a\s+contar\s+del?)\s+(?:el\s+)?(?P<inicio>' . $datePattern . ').{0,140}?(?:hasta|al)\s+(?:el\s+)?(?P<termino>' . $datePattern . ')/iu';
        }

        if ($type === 'orden_trabajo') {
            $pairPatterns[] = '/(?:orden\s+de\s+trabajo|vigencia|periodo|reemplazo|inicio\s+de\s+funciones).{0,220}?(?:desde|a\s+contar\s+del?)\s+(?:el\s+)?(?P<inicio>' . $datePattern . ').{0,120}?(?:hasta|al)\s+(?:el\s+)?(?P<termino>' . $datePattern . ')/iu';
            $pairPatterns[] = '/a\s+contar\s+de\s*:?[\s]*(?P<inicio>' . $datePattern . ').{0,180}?fecha\s+de\s+t[eé]rmino\s*:?[\s]*(?P<termino>' . $datePattern . ')/iu';
        }

        foreach ($pairPatterns as $pattern) {
            if (!preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($matches as $match) {
                $inicio = $this->parseDateString((string) ($match['inicio'] ?? ''));
                $termino = $this->parseDateString((string) ($match['termino'] ?? ''));
                if ($inicio && $termino) {
                    $periods[] = ['inicio' => $inicio, 'termino' => $termino];
                }
            }
        }

        if ($type !== 'finiquitos' && preg_match_all('/' . $datePattern . '/iu', $text, $dates, PREG_OFFSET_CAPTURE)) {
            $tokens = [];
            foreach ($dates[0] as [$rawDate, $offset]) {
                $normalized = $this->parseDateString((string) $rawDate);
                if ($normalized) {
                    $tokens[] = [
                        'raw' => (string) $rawDate,
                        'offset' => (int) $offset,
                        'date' => $normalized,
                    ];
                }
            }

            for ($i = 0; $i < count($tokens) - 1; $i++) {
                $current = $tokens[$i];
                $next = $tokens[$i + 1];
                $betweenStart = $current['offset'] + strlen((string) $current['raw']);
                $betweenLength = max(0, $next['offset'] - $betweenStart);
                $between = mb_strtolower(mb_substr($text, $betweenStart, $betweenLength));

                if (preg_match('/\b(al|hasta)\b/u', $between) === 1) {
                    $periods[] = [
                        'inicio' => $current['date'],
                        'termino' => $next['date'],
                    ];
                    $i++;
                }
            }
        }

        if (!empty($analysis['labor_start']) && !empty($analysis['labor_end'])) {
            $periods[] = [
                'inicio' => $analysis['labor_start'],
                'termino' => $analysis['labor_end'],
            ];
        }

        $periods = $this->normalizePeriods($periods);
        if (in_array($type, ['finiquitos', 'decretos'], true) && !empty($analysis['labor_start']) && !empty($analysis['labor_end']) && in_array((string) ($analysis['selected_period_source'] ?? ''), ['finiquito_clause', 'decreto_desde_hasta_estructurado'], true)) {
            $periods = [[
                'inicio' => $analysis['labor_start'],
                'termino' => $analysis['labor_end'],
            ]];
            $analysis['periodos'] = $periods;
        }

        return [
            'periodos' => $periods,
            'analysis' => $analysis,
        ];
    }

    private function analyzeDatesByContext(string $text, TramiteDocumento $documento): array
    {
        $type = (string) $documento->tipo_documento;
        $analysis = [
            'document_date' => null,
            'document_date_raw' => null,
            'certification_date' => null,
            'certification_date_raw' => null,
            'labor_start' => null,
            'labor_end' => null,
            'labor_start_raw' => null,
            'labor_end_raw' => null,
            'labor_start_partial_text' => null,
            'labor_end_partial_text' => null,
            'selected_period_source' => null,
            'context_matches' => [],
            'periodos' => [],
        ];

        $flexibleDatePattern = $this->buildFlexibleDateExpression();
        $bestMatchScore = PHP_INT_MIN;
        $requiresPeriods = $this->documentTypeRequiresPeriods($documento);

        $periodPatterns = [
            ['source' => 'laboral_presto_servicios', 'pattern' => '/prest[oó]\s+servicios.{0,280}?(?:desde|a\s+contar\s+del?)\s+(?:el\s+)?(?P<inicio>' . $flexibleDatePattern . ').{0,180}?(?:hasta|al)\s+(?:el\s+)?(?P<termino>' . $flexibleDatePattern . ')/iu', 'base' => 120],
            ['source' => 'laboral_desde_hasta', 'pattern' => '/\b(?:desde|a\s+contar\s+del?)\s+(?:el\s+)?(?P<inicio>' . $flexibleDatePattern . ').{0,140}?(?:hasta|al)\s+(?:el\s+)?(?P<termino>' . $flexibleDatePattern . ')/iu', 'base' => 80],
            ['source' => 'documento_listado_periodos', 'pattern' => '/(?P<inicio>' . $flexibleDatePattern . ')\s*(?:,|\s)*(?:al|hasta)\s+(?:el\s+)?(?P<termino>' . $flexibleDatePattern . ')/iu', 'base' => 60],
        ];

        if ($type === 'finiquitos') {
            array_unshift($periodPatterns, ['source' => 'finiquito_clause', 'pattern' => '/finiquito.{0,260}?prest[oó]\s+servicios.{0,260}?(?:desde|a\s+contar\s+del?)\s+(?:el\s+)?(?P<inicio>' . $flexibleDatePattern . ').{0,180}?(?:hasta|al)\s+(?:el\s+)?(?P<termino>' . $flexibleDatePattern . ')/iu', 'base' => 150]);
        }

        if ($type === 'decretos') {
            $decretoPair = $this->extractDecretoStructuredPeriod($text, $flexibleDatePattern);
            if ($decretoPair !== null) {
                $analysis['context_matches'][] = $decretoPair;
                if (!empty($decretoPair['inicio']['date']) && !empty($decretoPair['termino']['date'])) {
                    $analysis['periodos'][] = [
                        'inicio' => $decretoPair['inicio']['date'],
                        'termino' => $decretoPair['termino']['date'],
                    ];
                }

                $analysis['selected_period_source'] = $decretoPair['source'];
                $analysis['labor_start'] = $decretoPair['inicio']['date'] ?? null;
                $analysis['labor_end'] = $decretoPair['termino']['date'] ?? null;
                $analysis['labor_start_raw'] = $decretoPair['inicio']['raw'] ?? null;
                $analysis['labor_end_raw'] = $decretoPair['termino']['raw'] ?? null;
                $analysis['labor_start_partial_text'] = (($decretoPair['inicio']['granularity'] ?? null) === 'partial') ? ($decretoPair['inicio']['text'] ?? null) : null;
                $analysis['labor_end_partial_text'] = (($decretoPair['termino']['granularity'] ?? null) === 'partial') ? ($decretoPair['termino']['text'] ?? null) : null;
                $bestMatchScore = max($bestMatchScore, (int) ($decretoPair['score'] ?? 0));
            }
        }

        if ($type === 'orden_trabajo') {
            array_unshift($periodPatterns, ['source' => 'orden_trabajo_vigencia', 'pattern' => '/(?:orden\s+de\s+trabajo|vigencia|periodo|reemplazo|inicio\s+de\s+funciones).{0,240}?(?:desde|a\s+contar\s+del?)\s+(?:el\s+)?(?P<inicio>' . $flexibleDatePattern . ').{0,140}?(?:hasta|al)\s+(?:el\s+)?(?P<termino>' . $flexibleDatePattern . ')/iu', 'base' => 130]);
            array_unshift($periodPatterns, ['source' => 'orden_trabajo_a_contar_termino', 'pattern' => '/a\s+contar\s+de\s*:?[\s]*(?P<inicio>' . $flexibleDatePattern . ').{0,220}?fecha\s+de\s+t[eé]rmino\s*:?[\s]*(?P<termino>' . $flexibleDatePattern . ')/iu', 'base' => 155]);
        }

        if ($requiresPeriods) {
            foreach ($periodPatterns as $entry) {
                if (!preg_match_all($entry['pattern'], $text, $matches, PREG_SET_ORDER)) {
                    continue;
                }

                foreach ($matches as $match) {
                    $startInfo = $this->parseDateValueInfo((string) ($match['inicio'] ?? ''));
                    $endInfo = $this->parseDateValueInfo((string) ($match['termino'] ?? ''));
                    $context = (string) ($match[0] ?? '');
                    $score = (int) $entry['base'] + $this->scoreDateContext($context, $type)
                        + (($startInfo['granularity'] ?? '') === 'full' ? 15 : 0)
                        + (($endInfo['granularity'] ?? '') === 'full' ? 15 : 0);

                    $analysis['context_matches'][] = [
                        'source' => $entry['source'],
                        'score' => $score,
                        'context' => $this->limitText($context),
                        'inicio' => $startInfo,
                        'termino' => $endInfo,
                    ];

                    if (!empty($startInfo['date']) && !empty($endInfo['date'])) {
                        $analysis['periodos'][] = [
                            'inicio' => $startInfo['date'],
                            'termino' => $endInfo['date'],
                        ];
                    }

                    if ($score > $bestMatchScore) {
                        $bestMatchScore = $score;
                        $analysis['selected_period_source'] = $entry['source'];
                        $analysis['labor_start'] = $startInfo['date'] ?? null;
                        $analysis['labor_end'] = $endInfo['date'] ?? null;
                        $analysis['labor_start_raw'] = $startInfo['raw'] ?? null;
                        $analysis['labor_end_raw'] = $endInfo['raw'] ?? null;
                        $analysis['labor_start_partial_text'] = (($startInfo['granularity'] ?? null) === 'partial') ? ($startInfo['text'] ?? null) : null;
                        $analysis['labor_end_partial_text'] = (($endInfo['granularity'] ?? null) === 'partial') ? ($endInfo['text'] ?? null) : null;
                    }
                }
            }
        }

        $documentContext = null;

        if ($type === 'carta_reconocimiento_director_ejecutivo') {
            $documentContext = $this->extractDirectorLetterDocumentDate($text);
        }

        if ($documentContext === null) {
            $documentContext = $this->extractSingleContextDate(
                $text,
                '/\bcon\s+fecha\s+(?P<fecha>' . $flexibleDatePattern . ')/iu',
                'fecha',
                'documento_con_fecha'
            );
        }

        if ($documentContext === null) {
            foreach ($this->documentDatePatterns($type, $flexibleDatePattern) as $documentDatePattern) {
                $documentContext = $this->extractSingleContextDate(
                    $text,
                    $documentDatePattern['pattern'],
                    $documentDatePattern['group'] ?? 'fecha',
                    $documentDatePattern['source'] ?? 'documento_contextual'
                );
                if ($documentContext !== null) {
                    break;
                }
            }
        }

        if ($documentContext !== null) {
            $analysis['document_date'] = $documentContext['date'] ?? null;
            $analysis['document_date_raw'] = $documentContext['raw'] ?? null;
            $analysis['context_matches'][] = $documentContext;
        }

        if (preg_match('/\bfecha\s+hora\s+de\s+certificaci[oó]n(?:\s+dt)?\s*:\s*(?P<fecha>\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{2,4})(?:\s+(?P<hora>\d{1,2}[\.:]\d{2}(?:[\.:]\d{2})?))?/iu', $text, $match)) {
            $date = $this->parseDateString((string) ($match['fecha'] ?? ''));
            $time = trim(str_replace('.', ':', (string) ($match['hora'] ?? '')));
            $analysis['certification_date_raw'] = trim((string) ($match[0] ?? ''));
            if ($date !== null) {
                $analysis['certification_date'] = $time !== '' ? trim($date . ' ' . $time) : $date;
            }
            $analysis['context_matches'][] = [
                'source' => 'fecha_certificacion',
                'score' => 95,
                'context' => $this->limitText((string) ($match[0] ?? '')),
                'date' => $analysis['certification_date'],
                'raw' => $analysis['certification_date_raw'],
            ];
        }

        if (!$requiresPeriods) {
            $analysis['labor_start'] = null;
            $analysis['labor_end'] = null;
            $analysis['labor_start_raw'] = null;
            $analysis['labor_end_raw'] = null;
            $analysis['labor_start_partial_text'] = null;
            $analysis['labor_end_partial_text'] = null;
            $analysis['selected_period_source'] = null;
            $analysis['periodos'] = [];
        }

        return $analysis;
    }

    private function extractDocumentMetadata(string $text, TramiteDocumento $documento, array $dateAnalysis, array $periodos = []): array
    {
        $detectedKind = $this->detectDocumentKind($text, $documento);
        $detectedLabel = $this->resolveDetectedDocumentLabel($detectedKind, $documento);
        $issuerName = $this->extractIssuerName($text, $documento, $detectedKind);
        $documentNumber = $this->extractDocumentNumber($text, $documento, $detectedKind);

        return [
            'detected_kind' => $detectedKind,
            'detected_label' => $detectedLabel,
            'issuer_name' => $issuerName,
            'document_number' => $documentNumber,
            'document_date' => data_get($dateAnalysis, 'document_date'),
            'certification_date' => data_get($dateAnalysis, 'certification_date'),
            'periods_detected' => count($periodos),
        ];
    }

    private function detectDocumentKind(string $text, TramiteDocumento $documento): string
    {
        $type = (string) $documento->tipo_documento;
        $normalized = mb_strtolower($this->replaceAccents($text));

        if ($type === 'carta_reconocimiento_director_ejecutivo') {
            return 'carta_director';
        }
        if (str_contains($normalized, 'carta de recomendacion')) {
            return 'carta_recomendacion';
        }
        if (str_contains($normalized, 'certificado de cotizaciones')) {
            return 'certificado_cotizaciones';
        }
        if (str_contains($normalized, 'finiquito laboral') || str_contains($normalized, 'finiquito del trabajador')) {
            return 'finiquito';
        }
        if (str_contains($normalized, 'orden de trabajo')) {
            return 'orden_trabajo';
        }
        if (preg_match('/\bdecreto\b/u', $normalized)) {
            return 'decreto';
        }
        if (preg_match('/\bcertificado\b/u', $normalized)) {
            return 'certificado';
        }
        if (str_contains($normalized, 'liquidacion de remuneraciones')) {
            return 'liquidacion';
        }

        return $type;
    }

    private function resolveDetectedDocumentLabel(string $detectedKind, TramiteDocumento $documento): string
    {
        return match ($detectedKind) {
            'carta_director' => 'Carta dirigida al Director Ejecutivo',
            'carta_recomendacion' => 'Carta de recomendación',
            'certificado_cotizaciones' => 'Certificado de cotizaciones previsionales',
            'finiquito' => 'Finiquito laboral',
            'orden_trabajo' => 'Orden de trabajo',
            'decreto' => 'Decreto',
            'certificado' => 'Certificado',
            'liquidacion' => 'Liquidación de remuneraciones',
            default => trim((string) ($documento->tipo_documento_label ?: 'Documento')),
        };
    }

    private function extractDocumentNumber(string $text, TramiteDocumento $documento, string $detectedKind): ?string
    {
        $patterns = [];

        if (in_array($detectedKind, ['orden_trabajo', 'orden_trabajo'], true) || (string) $documento->tipo_documento === 'orden_trabajo') {
            $patterns[] = '/orden\s+de\s+trabajo\s*(?:n[°ºo]?)?\s*[:.]?\s*(?P<number>[0-9A-Z.\/-]+)/iu';
        }
        if ($detectedKind === 'decreto' || (string) $documento->tipo_documento === 'decretos') {
            $patterns[] = '/decreto\s*:?\s*(?:n[°ºo]?)?\s*(?P<number>[0-9A-Z.\/-]+)/iu';
        }
        if (in_array($detectedKind, ['certificado', 'certificado_cotizaciones'], true) || (string) $documento->tipo_documento === 'certificado_antiguedad') {
            $patterns[] = '/certificado\s*(?:n[°ºo]?)\s*(?P<number>[0-9A-Z.\/-]+)/iu';
            $patterns[] = '/folio(?:\s+de\s+certificaci[oó]n|\s+n\.)?\s*:?\s*(?P<number>[0-9A-Z.\/-]{4,})/iu';
        }

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $number = trim((string) ($match['number'] ?? ''));
                if ($number !== '') {
                    return rtrim($number, '.,;');
                }
            }
        }

        return null;
    }

    private function extractIssuerName(string $text, TramiteDocumento $documento, string $detectedKind): ?string
    {
        if ((string) $documento->tipo_documento === 'carta_reconocimiento_director_ejecutivo' || $detectedKind === 'carta_director') {
            return null;
        }

        $patterns = [];

        if ($detectedKind === 'certificado_cotizaciones' || (string) $documento->tipo_documento === 'certificado_cotizaciones_afp_historico_tipo_b') {
            $patterns = [
                '/\b(AFP\s+[A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑa-záéíóúñ]+(?:\s+S\.?A\.?)?)\b/u',
                '/\b(AFP\s+PlanVital\s+S\.?A\.?)\b/u',
                '/\b(AFP\s+Capital\s+S\.?A\.?)\b/u',
            ];
        } elseif ($detectedKind === 'finiquito' || (string) $documento->tipo_documento === 'finiquitos') {
            $patterns = [
                '/empleador(?:\(a\))?\s*:\s*(?P<issuer>.+?)\s+rut\s*:/iu',
                '/con\s+fecha\s+' . $this->buildFlexibleDateExpression() . ',?\s+entre\s+(?P<issuer>.+?),\s+rut\s*[:]?/iu',
                '/entre\s+(?P<issuer>fundaci[oó]n\s+educaci[oó]n\s+para\s+el\s+desarrollo,?\s+educades)/iu',
            ];
        } else {
            $patterns = [
                '/servicio\s+local\s+de\s+educaci[oó]n\s+p[uú]blica\s+andalien\s+costa/iu',
                '/direcci[oó]n\s+de\s+educaci[oó]n\s+municipal\s+de\s+coronel/iu',
                '/dem\s+coronel/iu',
                '/daem\s+san\s+pedro\s+de\s+la\s+paz/iu',
                '/municipalidad\s+de\s+san\s+pedro\s+de\s+la\s+paz/iu',
                '/corporacion\s+educacional\s+[a-záéíóúñ\s]+/iu',
                '/fundacion\s+educaci[oó]n\s+para\s+el\s+desarrollo,?\s+educades/iu',
                '/escuela\s+v[ií]ctor\s+domingo\s+silva/iu',
            ];
        }

        foreach ($patterns as $pattern) {
            if (!preg_match($pattern, $text, $match)) {
                continue;
            }

            $issuer = trim((string) (($match['issuer'] ?? '') !== '' ? $match['issuer'] : ($match[0] ?? '')));
            $issuer = preg_replace('/\s+/u', ' ', $issuer) ?: $issuer;
            $issuer = trim($issuer, " ,.;:-\t\n\r\x0B");
            if ($issuer !== '') {
                return $this->normalizeIssuerDisplay($issuer);
            }
        }

        return null;
    }

    private function normalizeIssuerDisplay(string $issuer): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($issuer)) ?: trim($issuer);
        $replacements = [
            'Dem Coronel' => 'Dirección de Educación Municipal de Coronel',
            'Daem San Pedro De La Paz' => 'DAEM San Pedro de la Paz',
            'Municipalidad De San Pedro De La Paz' => 'Municipalidad de San Pedro de la Paz',
            'Servicio Local De Educación Pública Andalien Costa' => 'Servicio Local de Educación Pública Andalién Costa',
            'Servicio Local De Educacion Publica Andalien Costa' => 'Servicio Local de Educación Pública Andalién Costa',
            'Fundacion Educacion Para El Desarrollo Educades' => 'Fundación Educación para el Desarrollo, EDUCADES',
            'Afp Planvital S.a' => 'AFP PlanVital S.A.',
            'Afp Capital S.a' => 'AFP Capital S.A.',
        ];

        $title = mb_convert_case($normalized, MB_CASE_TITLE, 'UTF-8');

        return $replacements[$title] ?? $title;
    }

    private function documentTypeRequiresPeriods(TramiteDocumento $documento): bool
    {
        return $documento->requiresPeriod();
    }

    private function extractDirectorLetterDocumentDate(string $text): ?array
    {
        $fullDatePattern = $this->buildDatePattern();
        $patterns = [
            [
                'source' => 'carta_director_fecha_encabezado',
                'pattern' => '/^\s*(?:[[:alpha:]\x{00C0}-\x{017F} ]+,\s*)?(?P<fecha>' . $fullDatePattern . ')\s*(?:$|\R|\s+(?:se[nñ]or|sr\.?|don|presente|estimado)\b)/imu',
            ],
            [
                'source' => 'carta_director_fecha_antes_destinatario',
                'pattern' => '/(?P<fecha>' . $fullDatePattern . ')(?=\s+(?:se[nñ]or|sr\.?|don|presente|estimado)\b)/iu',
            ],
        ];

        foreach ($patterns as $entry) {
            $match = $this->extractSingleContextDate($text, $entry['pattern'], 'fecha', $entry['source']);
            if ($match !== null) {
                return $match;
            }
        }

        $lines = preg_split('/\R/u', $text) ?: [];
        $headerLines = [];
        foreach ($lines as $line) {
            $trimmed = trim((string) $line);
            if ($trimmed === '') {
                continue;
            }
            $headerLines[] = $trimmed;
            if (count($headerLines) >= 6) {
                break;
            }
        }

        if (!empty($headerLines)) {
            $header = implode("\n", $headerLines);
            if (preg_match('/(?P<fecha>' . $fullDatePattern . ')/iu', $header, $match)) {
                return $this->extractSingleContextDate($header, '/(?P<fecha>' . $fullDatePattern . ')/iu', 'fecha', 'carta_director_fecha_primer_bloque');
            }
        }

        return null;
    }

    private function documentDatePatterns(string $type, string $flexibleDatePattern): array
    {
        return [
            ['source' => 'documento_lugar_fecha', 'pattern' => '/(?:coronel|concepci[oó]n|santiago|san\s+pedro\s+de\s+la\s+paz|lota|arauco)\s*,?\s*(?P<fecha>' . $flexibleDatePattern . ')(?:\s+entre\b|\s*$|\s+señor|\s+sr\.?|\s+don\b|\s+presente|\s+de\s+mi\s+consideraci[oó]n)/iu'],
            ['source' => 'documento_fecha_superior', 'pattern' => '/^(?:[^\n]{0,80})?(?P<fecha>' . $flexibleDatePattern . ')/iu'],
            ['source' => 'documento_fecha_inferior', 'pattern' => '/(?:coronel|concepci[oó]n|santiago|san\s+pedro\s+de\s+la\s+paz)\s*,?\s*(?P<fecha>' . $flexibleDatePattern . ')\s*[-.]/iu'],
        ];
    }

    private function scoreDateContext(string $context, string $type): int
    {
        $normalized = mb_strtolower($this->replaceAccents($context));
        $score = 0;

        if (preg_match('/\b(desde|hasta|al|a contar del)\b/u', $normalized)) {
            $score += 10;
        }
        if (preg_match('/\b(presto servicios|contrato de trabajo|terminacion de sus servicios|vigencia|periodo|periodos|inicio de funciones)\b/u', $normalized)) {
            $score += 18;
        }
        if ($type === 'finiquitos' && preg_match('/\b(finiquito|trabajador|empleador|causal|prest[oó] servicios)\b/u', $normalized)) {
            $score += 25;
        }
        if ($type === 'orden_trabajo' && preg_match('/\b(orden de trabajo|reemplazo|vigencia|establecimiento|funciones)\b/u', $normalized)) {
            $score += 25;
        }
        if ($type === 'decretos' && preg_match('/\b(decreto|designase|profesional de la educacion|establecimiento|cargo|en calidad|jornada|razon de contratacion|fianza|desde|hasta)\b/u', $normalized)) {
            $score += 30;
        }
        if ($type === 'decretos' && preg_match('/\b(vistos|decreto alcaldicio|ley n|organica constitucional|municipalidades|san pedro de la paz)\b/u', $normalized)) {
            $score -= 30;
        }

        return $score;
    }


    private function extractDecretoStructuredPeriod(string $text, string $flexibleDatePattern): ?array
    {
        $blockPattern = '/(?:designase\s+al\s+profesional\s+de\s+la\s+educaci[oó]n|decreto\s*:\s*1\s*[.\-]?|1\s*[.\-]\s*designase).{0,2200}?(?:\b2\s*[.\-]\s*|\bimp[uú]tese\b|\ban[oó]tese\b|\bcomuniquese\b|\barchivese\b|$)/iu';
        if (!preg_match($blockPattern, $text, $blockMatch)) {
            return null;
        }

        $context = (string) ($blockMatch[0] ?? '');
        $startInfo = null;
        $endInfo = null;

        if (preg_match('/\bdesde\b\s*:?\s*(?P<inicio>' . $flexibleDatePattern . ')/iu', $context, $startMatch)) {
            $startInfo = $this->parseDateValueInfo((string) ($startMatch['inicio'] ?? ''));
        }
        if (preg_match('/\bhasta\b\s*:?\s*(?P<termino>' . $flexibleDatePattern . ')/iu', $context, $endMatch)) {
            $endInfo = $this->parseDateValueInfo((string) ($endMatch['termino'] ?? ''));
        }

        if (($startInfo === null || empty($startInfo['date'])) || ($endInfo === null || empty($endInfo['date']))) {
            $segment = $context;
            if (preg_match('/\bjornada\b(?P<segmento>[\s\S]{0,600}?)(?:\bfianza\b|\braz[oó]n\s+de\s+contrataci[oó]n\b|\bno\s+rinde\b|\b2\s*[.\-]\s*|\bimp[uú]tese\b|$)/iu', $context, $segmentMatch)) {
                $segment = (string) ($segmentMatch['segmento'] ?? $context);
            }

            if (preg_match_all('/' . $this->buildDatePattern() . '/iu', $segment, $dateMatches) && count($dateMatches[0]) >= 2) {
                $startInfo ??= $this->parseDateValueInfo((string) ($dateMatches[0][0] ?? ''));
                $endInfo ??= $this->parseDateValueInfo((string) ($dateMatches[0][1] ?? ''));
            }
        }

        if (($startInfo === null || (empty($startInfo['date']) && empty($startInfo['text']))) || ($endInfo === null || (empty($endInfo['date']) && empty($endInfo['text'])))) {
            return null;
        }

        return [
            'source' => 'decreto_desde_hasta_estructurado',
            'score' => 220,
            'context' => $this->limitText($context),
            'inicio' => $startInfo,
            'termino' => $endInfo,
        ];
    }

    private function extractSingleContextDate(string $text, string $pattern, string $groupName = 'fecha', ?string $source = null): ?array
    {
        if (!preg_match($pattern, $text, $match)) {
            return null;
        }

        $info = $this->parseDateValueInfo((string) ($match[$groupName] ?? ''));
        if (empty($info['date'])) {
            return null;
        }

        return [
            'source' => $source,
            'score' => 70,
            'context' => $this->limitText((string) ($match[0] ?? '')),
            'date' => $info['date'],
            'raw' => $info['raw'] ?? null,
        ];
    }

    private function buildFlexibleDateExpression(): string
    {
        $months = $this->buildMonthsRegex();
        $full = $this->buildDatePattern();
        $partial = '(?:(?:de\s+)?(?:' . $months . ')\s+de\s+\d{4}|(?:' . $months . ')\s+\d{4})';

        return '(?:' . $full . '|' . $partial . ')';
    }

    private function parseDateValueInfo(string $value): array
    {
        $raw = trim($value);
        $full = $this->parseDateString($raw);
        if ($full !== null) {
            return [
                'raw' => $raw,
                'date' => $full,
                'granularity' => 'full',
                'text' => $raw,
            ];
        }

        $normalized = $this->replaceAccents(mb_strtolower(trim($raw)));
        $normalized = strtr($normalized, [
            '–' => '-',
            '—' => '-',
            '‐' => '-',
            '‑' => '-',
            ',' => ' ',
            ';' => ' ',
            '_' => ' ',
        ]);
        $normalized = $this->normalizeDateArtifacts($normalized, $this->buildMonthsRegex());
        $normalized = $this->normalizeCommonOcrWords($normalized);
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?: $normalized;
        $normalized = trim($normalized);

        if (preg_match('/^(?:de\s+)?([a-z]+)\s+de\s+(\d{4})$/u', $normalized, $match) || preg_match('/^([a-z]+)\s+(\d{4})$/u', $normalized, $match)) {
            $month = $this->resolveMonth($match[1]);
            if ($month !== null) {
                return [
                    'raw' => $raw,
                    'date' => null,
                    'granularity' => 'partial',
                    'text' => trim($match[1] . ' de ' . $match[2]),
                    'month' => $month,
                    'year' => (int) $match[2],
                ];
            }
        }

        return [
            'raw' => $raw,
            'date' => null,
            'granularity' => 'unknown',
            'text' => $normalized,
        ];
    }

    private function normalizePeriods(array $periods): array
    {
        $unique = [];

        foreach ($periods as $period) {
            $inicio = $period['inicio'] ?? null;
            $termino = $period['termino'] ?? null;
            if (!$inicio || !$termino) {
                continue;
            }

            if ($inicio > $termino) {
                [$inicio, $termino] = [$termino, $inicio];
            }

            $key = $inicio . '|' . $termino;
            $unique[$key] = [
                'inicio' => $inicio,
                'termino' => $termino,
            ];
        }

        $normalized = array_values($unique);
        usort($normalized, static function (array $a, array $b): int {
            return strcmp($a['inicio'], $b['inicio']) ?: strcmp($a['termino'], $b['termino']);
        });

        return $normalized;
    }

    private function hasInterruptions(array $periods): bool
    {
        if (count($periods) <= 1) {
            return false;
        }

        $merged = $this->mergePeriods($periods);

        return count($merged) > 1;
    }

    private function compareAgainstAssociatedPeriod(TramiteDocumento $documento, array $periodos): array
    {
        $associatedStart = $documento->fecha_inicio?->format('Y-m-d');
        $associatedEnd = $documento->fecha_termino?->format('Y-m-d');

        if (!$associatedStart && !$associatedEnd) {
            return [
                'code' => 'sin_periodo_asociado',
                'label' => 'Sin período asociado en el formulario',
            ];
        }

        if (empty($periodos)) {
            return [
                'code' => 'sin_periodos_detectados',
                'label' => 'No se detectaron períodos en el documento',
            ];
        }

        foreach ($periodos as $periodo) {
            if (($associatedStart === null || $periodo['inicio'] === $associatedStart)
                && ($associatedEnd === null || $periodo['termino'] === $associatedEnd)) {
                return [
                    'code' => 'exacto',
                    'label' => 'Coincide exactamente con un período detectado',
                ];
            }
        }

        $merged = $this->mergePeriods($periodos);
        foreach ($merged as $periodo) {
            if (($associatedStart === null || $associatedStart >= $periodo['inicio'])
                && ($associatedEnd === null || $associatedEnd <= $periodo['termino'])) {
                return [
                    'code' => 'contenido_continuo',
                    'label' => 'El período asociado queda cubierto de forma continua por el documento',
                ];
            }
        }

        $globalStart = $periodos[0]['inicio'] ?? null;
        $globalEnd = $periodos[count($periodos) - 1]['termino'] ?? null;
        $insideGlobal = ($associatedStart === null || ($globalStart !== null && $associatedStart >= $globalStart))
            && ($associatedEnd === null || ($globalEnd !== null && $associatedEnd <= $globalEnd));

        if ($insideGlobal) {
            return [
                'code' => 'rango_resumen_con_interrupciones',
                'label' => 'El período asociado cae dentro del rango resumen, pero con interrupciones entre períodos detectados',
            ];
        }

        foreach ($merged as $periodo) {
            $startsBeforeEnd = $associatedEnd === null || $periodo['inicio'] <= $associatedEnd;
            $endsAfterStart = $associatedStart === null || $periodo['termino'] >= $associatedStart;
            if ($startsBeforeEnd && $endsAfterStart) {
                return [
                    'code' => 'coincidencia_parcial',
                    'label' => 'Existe coincidencia parcial entre el período asociado y los períodos detectados',
                ];
            }
        }

        return [
            'code' => 'no_coincide',
            'label' => 'El período asociado no coincide con los períodos detectados en el documento',
        ];
    }

    private function compareRutWithTramite(TramiteDocumento $documento, ?string $capturedRut): ?bool
    {
        if ($capturedRut === null) {
            return null;
        }

        $documentRut = RutChile::normalize($capturedRut);
        $tramiteRut = RutChile::normalize((string) $documento->tramite?->rut_snapshot);
        if (!$documentRut || !$tramiteRut) {
            return null;
        }

        return strtoupper((string) $documentRut['rut']) === strtoupper((string) $tramiteRut['rut']);
    }

    private function mergePeriods(array $periods): array
    {
        if (empty($periods)) {
            return [];
        }

        $merged = [];
        foreach ($periods as $period) {
            if (empty($merged)) {
                $merged[] = $period;
                continue;
            }

            $lastIndex = count($merged) - 1;
            $last = $merged[$lastIndex];
            $lastEnd = Carbon::parse($last['termino']);
            $currentStart = Carbon::parse($period['inicio']);

            if ($currentStart->lessThanOrEqualTo($lastEnd->copy()->addDay())) {
                if ($period['termino'] > $last['termino']) {
                    $merged[$lastIndex]['termino'] = $period['termino'];
                }
                continue;
            }

            $merged[] = $period;
        }

        return $merged;
    }

    private function normalizeTextForParsing(string $text): string
    {
        $normalized = strtr($text, [
            '’' => "'",
            '‘' => "'",
            '´' => "'",
            '`' => "'",
            '“' => ' ',
            '”' => ' ',
            '_' => ' ',
            '–' => '-',
            '—' => '-',
            '‐' => '-',
            '‑' => '-',
            ';' => ', ',
        ]);

        $months = $this->buildMonthsRegex();
        $normalized = $this->normalizeDateArtifacts($normalized, $months);
        $normalized = $this->normalizeCommonOcrWords($normalized);
        $normalized = preg_replace('/(?<=\pL)[_\|]+(?=\pL)/u', ' ', $normalized) ?: $normalized;
        $normalized = preg_replace('/(\pL)(?=\d{1,2}\s+de\s+)/u', '$1 ', $normalized) ?: $normalized;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?: $normalized;

        return trim($normalized);
    }

    private function normalizeDateArtifacts(string $text, string $months): string
    {
        $text = preg_replace("/\b(de)\s*['\.,;:]+\s*((?:{$months})\b)/iu", '$1 $2', $text) ?: $text;
        $text = preg_replace("/(\b\d{1,2})\s*['\.,;:]+\s*((?:{$months})\b)/iu", '$1 $2', $text) ?: $text;
        $text = preg_replace("/\b((?:{$months}))\b\s*['\.,;:]+\s*(\d{4}\b)/iu", '$1 $2', $text) ?: $text;
        $text = preg_replace("/\b(el|de|del)\s*['\.,;:]+\s+/iu", '$1 ', $text) ?: $text;

        return $text;
    }

    private function normalizeCommonOcrWords(string $text): string
    {
        $replacements = [
            '/\bdc\b/iu' => 'de',
            '/\bdcl\b/iu' => 'del',
            '/\bcl\b/iu' => 'el',
            '/\bcntre\b/iu' => 'entre',
            '/\bfccha\b/iu' => 'fecha',
            '/\bfcbrero\b|\bfcbrcro\b|\bfehrero\b|\bfehrcro\b|\bfebrcro\b/iu' => 'febrero',
            '/\bahril\b|\babrii\b|\babrll\b/iu' => 'abril',
            '/\bseticmbre\b|\bsepticmbre\b/iu' => 'septiembre',
            '/\bnovicmbre\b/iu' => 'noviembre',
            '/\bdiciembrc\b/iu' => 'diciembre',
            '/\bjunlo\b/iu' => 'junio',
            '/\bjullo\b/iu' => 'julio',
        ];

        foreach ($replacements as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?: $text;
        }

        return $text;
    }

    private function extractRelevantNameTokens(string $name): array
    {
        $normalized = mb_strtolower($this->replaceAccents(trim($name)));
        if ($normalized === '') {
            return [];
        }

        $tokens = preg_split('/\s+/u', $normalized) ?: [];
        $tokens = array_filter($tokens, static function (string $token): bool {
            return mb_strlen($token) >= 3;
        });

        return array_values(array_unique($tokens));
    }

    private function extractTextWindow(string $text, int $offset, int $length, int $radius = 120): string
    {
        $start = max(0, $offset - $radius);
        $windowLength = $length + ($radius * 2);

        return (string) mb_substr($text, $start, $windowLength);
    }

    private function scoreRutContext(string $context, array $nameTokens, bool $matchesTramite, array $explicitRoles = [], ?string $rut = null): array
    {
        $normalized = mb_strtolower($this->replaceAccents($context));
        $scores = [
            'trabajador' => 0,
            'empleador' => 0,
            'representante' => 0,
            'priority' => 0,
        ];

        if (preg_match('/\b(trabajador(?:\(?a\)?)?|trabajadorla|ex\s+trabajador(?:\(?a\)?)?|funcionario(?:\(?a\)?)?|empleado(?:\(?a\)?)?|docente|cedula de identidad|c[eé]dula de identidad)\b/u', $normalized)) {
            $scores['trabajador'] += 6;
        }
        if (preg_match('/\b(rut|run|r\.?u\.?t\.?|r\.?u\.?n\.?)\b/u', $normalized)) {
            $scores['priority'] += 1;
        }
        if (preg_match('/\b(empleador(?:\(?a\)?)?|empleadorla|empresa|corporacion|corporacion educacional|razon social|fundacion)\b/u', $normalized)) {
            $scores['empleador'] += 6;
        }
        if (preg_match('/\b(representad[oa] legalmente|representante legal|apoderad[oa]|ambos con domicilio|don\(?n?a\)?)\b/u', $normalized)) {
            $scores['representante'] += 6;
        }

        $tokenHits = 0;
        foreach ($nameTokens as $token) {
            if (preg_match('/\b' . preg_quote($token, '/') . '\b/u', $normalized)) {
                $tokenHits++;
            }
        }
        if ($tokenHits >= 2) {
            $scores['trabajador'] += 4;
        } elseif ($tokenHits === 1) {
            $scores['trabajador'] += 2;
        }

        if ($matchesTramite) {
            $scores['trabajador'] += 10;
            $scores['priority'] += 5;
        }

        if ($rut !== null) {
            foreach (['trabajador', 'empleador', 'representante'] as $role) {
                if (($explicitRoles[$role] ?? null) === $rut) {
                    $scores[$role] += 20;
                    $scores['priority'] += 8;
                }
            }
        }

        return $scores;
    }

    private function candidatePriorityScore(array $candidate): int
    {
        $scores = $candidate['scores'] ?? [];

        return ((int) ($scores['trabajador'] ?? 0) * 100)
            + ((int) ($scores['priority'] ?? 0) * 20)
            - ((int) ($scores['empleador'] ?? 0) * 10)
            - ((int) ($scores['representante'] ?? 0) * 5)
            + (!empty($candidate['matches_tramite']) ? 1000 : 0);
    }

    private function inferRutRole(array $candidate): ?string
    {
        $scores = $candidate['scores'] ?? [];
        $roleScores = [
            'trabajador' => (int) ($scores['trabajador'] ?? 0),
            'empleador' => (int) ($scores['empleador'] ?? 0),
            'representante' => (int) ($scores['representante'] ?? 0),
        ];
        arsort($roleScores);
        $role = array_key_first($roleScores);

        return ($role !== null && ($roleScores[$role] ?? 0) > 0) ? $role : null;
    }


    private function extractExplicitRutRoleMatches(string $text): array
    {
        $rutPattern = '(?P<rut>[0-9OIL]{1,2}(?:[\.\s]?[0-9OIL]{3}){2}(?:[-‐‑–—]?[0-9KkOo])?)';
        $definitions = [
            'trabajador' => [
                ['source' => 'encabezado_trabajador', 'pattern' => '/trabajador(?:\(?a\)?)?\s*:\s*[\s\S]{0,160}?\brut\b\s*:?\s*' . $rutPattern . '/iu'],
                ['source' => 'cedula_identidad', 'pattern' => '/c[eé]dula\s+de\s+identidad\s*(?:n[°ºo]\s*)?\s*' . $rutPattern . '/iu'],
                ['source' => 'ex_trabajador', 'pattern' => '/ex\s+trabajador(?:\(?a\)?)?[\s\S]{0,120}?\brut\b\s*:?\s*' . $rutPattern . '/iu'],
            ],
            'empleador' => [
                ['source' => 'encabezado_empleador', 'pattern' => '/empleador(?:\(?a\)?)?\s*:\s*[\s\S]{0,180}?\brut\b\s*:?\s*' . $rutPattern . '/iu'],
                ['source' => 'entre_empleador', 'pattern' => '/\bentre\s+[\s\S]{0,220}?\brut\b\s*:?\s*' . $rutPattern . '/iu'],
                ['source' => 'ex_empleador', 'pattern' => '/ex\s+empleador(?:\(?a\)?)?[\s\S]{0,120}?\brut\b\s*:?\s*' . $rutPattern . '/iu'],
            ],
            'representante' => [
                ['source' => 'representante_legal', 'pattern' => '/representad[oa]\s+legalmente\s+por\s+[\s\S]{0,180}?\brut\b\s*:?\s*' . $rutPattern . '/iu'],
            ],
        ];

        $matches = [];
        foreach ($definitions as $role => $patterns) {
            foreach ($patterns as $definition) {
                if (!preg_match($definition['pattern'], $text, $match)) {
                    continue;
                }

                $rawRut = trim((string) ($match['rut'] ?? ''));
                $normalized = RutChile::normalize($this->sanitizeRutCandidate($rawRut));
                $matches[$role] = [
                    'rut' => $normalized && !empty($normalized['rut']) ? strtoupper((string) $normalized['rut']) : null,
                    'raw_rut' => $rawRut !== '' ? $rawRut : null,
                    'context' => $this->limitText((string) ($match[0] ?? '')),
                    'source' => $definition['source'],
                    'status' => $normalized['status'] ?? null,
                ];
                break;
            }
        }

        return $matches;
    }

    private function assignRutRoles(array $ordered, array $explicitRoleMatches): array
    {
        $roles = [];
        $roleContexts = [];
        $usedRuts = [];

        foreach (['trabajador', 'empleador', 'representante'] as $role) {
            $detail = $explicitRoleMatches[$role] ?? null;
            if (is_array($detail)) {
                $roleContexts[$role] = $detail;
                if (!empty($detail['rut'])) {
                    $roles[$role] = $detail['rut'];
                    $usedRuts[] = $detail['rut'];
                }
            }
        }

        foreach (['trabajador', 'empleador', 'representante'] as $role) {
            if (!empty($roles[$role])) {
                continue;
            }

            foreach ($ordered as $candidate) {
                if (!$this->candidateCanFillRole($candidate, $role, $usedRuts)) {
                    continue;
                }

                $roles[$role] = $candidate['rut'];
                $usedRuts[] = $candidate['rut'];
                $roleContexts[$role] = [
                    'rut' => $candidate['rut'],
                    'raw_rut' => $candidate['raw'] ?? $candidate['rut'],
                    'context' => $candidate['context'] ?? null,
                    'source' => 'contexto_' . $role,
                    'status' => $candidate['status'] ?? null,
                ];
                break;
            }
        }

        if (empty($roles['trabajador']) && !empty($ordered[0]['rut'])) {
            $roles['trabajador'] = $ordered[0]['rut'];
            $roleContexts['trabajador'] = [
                'rut' => $ordered[0]['rut'],
                'raw_rut' => $ordered[0]['raw'] ?? $ordered[0]['rut'],
                'context' => $ordered[0]['context'] ?? null,
                'source' => 'fallback_prioridad',
                'status' => $ordered[0]['status'] ?? null,
            ];
        }

        return [$roles, $roleContexts];
    }

    private function candidateCanFillRole(array $candidate, string $role, array $usedRuts = []): bool
    {
        $rut = (string) ($candidate['rut'] ?? '');
        $scores = $candidate['scores'] ?? [];
        $roleScore = (int) ($scores[$role] ?? 0);
        $trabajadorScore = (int) ($scores['trabajador'] ?? 0);
        $empleadorScore = (int) ($scores['empleador'] ?? 0);
        $representanteScore = (int) ($scores['representante'] ?? 0);

        if ($rut === '' || $roleScore <= 0) {
            return false;
        }

        if (in_array($rut, $usedRuts, true)) {
            return false;
        }

        return match ($role) {
            'trabajador' => !empty($candidate['matches_tramite']) || $roleScore >= max($empleadorScore, $representanteScore) || $roleScore >= 8,
            'empleador' => $roleScore >= 5 && $roleScore > $trabajadorScore && $roleScore >= $representanteScore,
            'representante' => $roleScore >= 5 && $roleScore > $trabajadorScore && $roleScore >= $empleadorScore,
            default => false,
        };
    }

    private function buildMonthsRegex(): string
    {
        return 'enero|ene|febrero|feb|marzo|mar|abril|abr|mayo|may|junio|jun|julio|jul|agosto|ago|septiembre|setiembre|sep|octubre|oct|noviembre|nov|diciembre|dic';
    }

    private function buildDatePattern(): string
    {
        $months = $this->buildMonthsRegex();

        return '(?:\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{2,4}|\d{4}[\/\-.]\d{1,2}[\/\-.]\d{1,2}|\d{1,2}\s+de\s+(?:' . $months . ')\s+de\s+\d{4}|\d{1,2}\s+(?:' . $months . ')\s+\d{4})';
    }

    private function parseDateString(string $value): ?string
    {
        $raw = trim(mb_strtolower($value));
        if ($raw === '') {
            return null;
        }

        $raw = strtr($raw, [
            '–' => '-',
            '—' => '-',
            '‐' => '-',
            '‑' => '-',
            ',' => ' ',
            ';' => ' ',
            '_' => ' ',
        ]);
        $months = $this->buildMonthsRegex();
        $raw = $this->normalizeDateArtifacts($raw, $months);
        $raw = $this->normalizeCommonOcrWords($raw);
        $raw = preg_replace('/\s+/u', ' ', $raw) ?: $raw;
        $normalized = $this->replaceAccents($raw);
        $normalized = preg_replace('/^(lunes|martes|miercoles|miércoles|jueves|viernes|sabado|sábado|domingo)\s*,?\s*/u', '', $normalized) ?: $normalized;

        if (preg_match('/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})$/', $normalized, $match)) {
            return $this->createDate((int) $match[1], (int) $match[2], (int) $match[3]);
        }

        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})$/', $normalized, $match)) {
            $year = (int) $match[3];
            $year = $year < 100 ? 2000 + $year : $year;
            return $this->createDate($year, (int) $match[2], (int) $match[1]);
        }

        if (preg_match('/^(\d{1,2})\s+de\s+([a-z]+)\s+de\s+(\d{4})$/u', $normalized, $match)) {
            $month = $this->resolveMonth($match[2]);
            return $month ? $this->createDate((int) $match[3], $month, (int) $match[1]) : null;
        }

        if (preg_match('/^(\d{1,2})\s+([a-z]+)\s+(\d{4})$/u', $normalized, $match)) {
            $month = $this->resolveMonth($match[2]);
            return $month ? $this->createDate((int) $match[3], $month, (int) $match[1]) : null;
        }

        return null;
    }

    private function createDate(int $year, int $month, int $day): ?string
    {
        if (!checkdate($month, $day, $year)) {
            return null;
        }

        return Carbon::create($year, $month, $day)->format('Y-m-d');
    }

    private function resolveMonth(string $month): ?int
    {
        $month = $this->replaceAccents(mb_strtolower(trim($month)));
        $month = $this->normalizeCommonOcrWords($month);

        $map = [
            'enero' => 1,
            'ene' => 1,
            'febrero' => 2,
            'feb' => 2,
            'fcbrero' => 2,
            'fcbrcro' => 2,
            'fehrero' => 2,
            'fehrcro' => 2,
            'marzo' => 3,
            'mar' => 3,
            'abril' => 4,
            'abr' => 4,
            'ahril' => 4,
            'mayo' => 5,
            'may' => 5,
            'junio' => 6,
            'jun' => 6,
            'julio' => 7,
            'jul' => 7,
            'agosto' => 8,
            'ago' => 8,
            'septiembre' => 9,
            'setiembre' => 9,
            'sep' => 9,
            'octubre' => 10,
            'oct' => 10,
            'noviembre' => 11,
            'nov' => 11,
            'diciembre' => 12,
            'dic' => 12,
        ];

        return $map[$month] ?? null;
    }

    private function replaceAccents(string $value): string
    {
        return strtr($value, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);
    }

    private function hasUsefulText(string $text): bool
    {
        $compact = preg_replace('/\s+/u', '', $text) ?: '';

        return mb_strlen($compact) >= max(40, (int) config('tramites.captura.min_text_length', 120));
    }

    private function limitText(string $text): string
    {
        $clean = trim(preg_replace('/\s+/u', ' ', $text) ?: '');

        return mb_substr($clean, 0, self::CAPTURE_ERROR_SNIPPET_LIMIT);
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = scandir($path) ?: [];
        foreach ($files as $file) {
            if (in_array($file, ['.', '..'], true)) {
                continue;
            }

            $current = $path . DIRECTORY_SEPARATOR . $file;
            if (is_dir($current)) {
                $this->deleteDirectory($current);
            } elseif (is_file($current)) {
                @unlink($current);
            }
        }

        @rmdir($path);
    }
}
