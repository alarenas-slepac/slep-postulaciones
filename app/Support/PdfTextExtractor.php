<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class PdfTextExtractor
{
    public function extractDirectText(string $absolutePath, ?int $page = null): array
    {
        $pdftotext = $this->findBinary('pdftotext');
        if ($pdftotext === null) {
            return [
                'text' => '',
                'warnings' => ['La herramienta pdftotext no está instalada en el servidor.'],
            ];
        }

        $args = [$pdftotext, '-layout'];
        if ($page !== null) {
            $args[] = '-f';
            $args[] = (string) $page;
            $args[] = '-l';
            $args[] = (string) $page;
        } else {
            $args[] = '-nopgbrk';
        }
        $args[] = $absolutePath;
        $args[] = '-';

        $process = new Process($args);
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

    public function extractTextUsingOcr(string $absolutePath): array
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

    public function extractTextAuto(string $absolutePath, ?int $page = null, int $minTextLength = 120): array
    {
        $direct = $this->extractDirectText($absolutePath, $page);
        $text = (string) ($direct['text'] ?? '');
        $warnings = $direct['warnings'] ?? [];

        if (mb_strlen(trim(preg_replace('/\s+/u', ' ', $text) ?? '')) >= $minTextLength) {
            return [
                'text' => $text,
                'method' => 'pdf_texto',
                'warnings' => $warnings,
                'meta' => [],
            ];
        }

        if ($page !== null) {
            $warnings[] = 'La página no tiene texto embebido suficiente; OCR por página no está habilitado para este flujo.';

            return [
                'text' => $text,
                'method' => 'pdf_texto',
                'warnings' => $warnings,
                'meta' => [],
            ];
        }

        $ocr = $this->extractTextUsingOcr($absolutePath);

        return [
            'text' => (string) ($ocr['text'] ?? ''),
            'method' => 'ocr',
            'warnings' => array_merge($warnings, $ocr['warnings'] ?? []),
            'meta' => $ocr['meta'] ?? [],
        ];
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

        try {
            $pages = $this->renderPdfPages($absolutePath, $tempDir);
            if (empty($pages)) {
                throw new \RuntimeException('No fue posible convertir el PDF en imagen para OCR.');
            }

            $tesseract = $this->findBinary('tesseract');
            if ($tesseract === null) {
                throw new \RuntimeException('No se encontró tesseract para OCR local.');
            }

            $language = (string) config('tramites.captura.ocr_language', 'spa+eng');
            $chunks = [];
            $warnings = [];

            foreach ($pages as $pagePath) {
                $ocr = new Process([$tesseract, $pagePath, 'stdout', '-l', $language, '--psm', '6']);
                $ocr->setTimeout((float) config('tramites.captura.ocr_timeout_seconds', 180));
                $ocr->run();

                if (!$ocr->isSuccessful()) {
                    $warnings[] = $this->normalizeProcessError($ocr, 'Tesseract no pudo procesar una página.');
                    continue;
                }

                $chunks[] = (string) $ocr->getOutput();
            }

            $text = trim(implode("\n\n", $chunks));
            if ($text === '') {
                throw new \RuntimeException('El OCR local finalizó sin texto utilizable.');
            }

            return [
                'text' => $text,
                'warnings' => array_merge(['Se utilizó OCR local para capturar texto desde el PDF aprobado.'], $warnings),
                'meta' => [
                    'driver' => 'tesseract',
                    'pages_processed' => count($pages),
                ],
            ];
        } finally {
            $this->deleteDirectory($tempDir);
        }
    }

    private function renderPdfPages(string $absolutePath, string $tempDir): array
    {
        $maxPages = max(1, (int) config('tramites.captura.max_pages_ocr', 3));
        $dpi = max(72, (int) config('tramites.captura.ocr_dpi', 96));

        $pdftoppm = $this->findBinary('pdftoppm');
        if ($pdftoppm !== null) {
            $process = new Process([$pdftoppm, '-f', '1', '-l', (string) $maxPages, '-r', (string) $dpi, '-png', $absolutePath, $tempDir . DIRECTORY_SEPARATOR . 'page']);
            $process->setTimeout((float) config('tramites.captura.pdf_timeout_seconds', 60));
            $process->run();

            if ($process->isSuccessful()) {
                $pages = glob($tempDir . DIRECTORY_SEPARATOR . 'page-*.png') ?: [];
                sort($pages);
                if (!empty($pages)) {
                    return $pages;
                }
            }

            Log::warning('[PDF][OCR] pdftoppm no pudo renderizar PDF, se intentará Imagick si existe.', [
                'error' => $this->normalizeProcessError($process, 'pdftoppm falló.'),
            ]);
        }

        return $this->renderPdfPagesUsingImagick($absolutePath, $tempDir, $dpi, $maxPages);
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

        return $configured !== '' ? $configured : null;
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

    private function limitText(string $text, int $limit = 1800): string
    {
        $text = trim($text);
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return mb_substr($text, 0, $limit) . '...';
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($full)) {
                $this->deleteDirectory($full);
            } elseif (is_file($full)) {
                @unlink($full);
            }
        }

        @rmdir($path);
    }
}
