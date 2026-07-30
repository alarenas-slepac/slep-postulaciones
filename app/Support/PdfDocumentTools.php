<?php

namespace App\Support;

use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class PdfDocumentTools
{
    public function __construct(private readonly PdfTextExtractor $textExtractor)
    {
    }

    public function assertAvailable(array $binaries): void
    {
        foreach ($binaries as $binary) {
            if ($this->findBinary((string) $binary) === null) {
                throw new RuntimeException("Falta la dependencia del servidor: {$binary}. Este componente reutiliza las herramientas PDF ya usadas por Trámites/OCR.");
            }
        }
    }

    public function pageCount(string $absolutePath): int
    {
        $pdfinfo = $this->requireBinary('pdfinfo');
        $process = new Process([$pdfinfo, $absolutePath]);
        $process->setTimeout((float) config('tramites.captura.pdf_timeout_seconds', 60));
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException('No fue posible leer la cantidad de páginas del PDF.');
        }

        if (!preg_match('/^Pages:\s+(\d+)/mi', $process->getOutput(), $matches)) {
            throw new RuntimeException('pdfinfo no retornó la cantidad de páginas del PDF.');
        }

        return max(0, (int) $matches[1]);
    }

    public function extractPageText(string $absolutePath, int $page): array
    {
        return $this->textExtractor->extractDirectText($absolutePath, $page);
    }

    public function separatePage(string $absolutePath, int $page, string $outputPath): void
    {
        $pdfseparate = $this->requireBinary('pdfseparate');
        $process = new Process([$pdfseparate, '-f', (string) $page, '-l', (string) $page, $absolutePath, $outputPath]);
        $process->setTimeout((float) config('tramites.captura.pdf_timeout_seconds', 60));
        $process->run();

        if (!$process->isSuccessful() || !is_file($outputPath)) {
            throw new RuntimeException('No fue posible separar la página del PDF.');
        }
    }

    private function requireBinary(string $binary): string
    {
        $path = $this->findBinary($binary);
        if ($path === null) {
            throw new RuntimeException("Falta la dependencia del servidor: {$binary}.");
        }

        return $path;
    }

    private function findBinary(string $binary): ?string
    {
        $finder = new ExecutableFinder();

        return $finder->find($binary);
    }
}
