<?php

namespace App\Services\Liquidaciones;

use App\Models\LiquidacionCarga;
use App\Models\LiquidacionFuncionario;
use App\Support\PdfDocumentTools;
use App\Support\RutChile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class LiquidacionPdfImportService
{
    public function __construct(private readonly PdfDocumentTools $pdfTools)
    {
    }

    public function process(LiquidacionCarga $carga): void
    {
        $this->pdfTools->assertAvailable(['pdfinfo', 'pdftotext', 'pdfseparate']);

        $disk = Storage::disk('local');
        $sourcePath = $disk->path($carga->archivo_original_path);

        if (!$disk->exists($carga->archivo_original_path) || !is_file($sourcePath)) {
            throw new RuntimeException('No se encontró el PDF original de la carga.');
        }

        $carga->update([
            'estado' => 'procesando',
            'errores' => null,
            'total_paginas' => 0,
            'total_con_rut' => 0,
            'total_reemplazos' => 0,
            'total_publicadas' => 0,
            'total_errores' => 0,
        ]);

        $pages = $this->pdfTools->pageCount($sourcePath);
        $totalConRut = 0;
        $totalReemplazos = 0;
        $totalPublicadas = 0;
        $errores = [];

        for ($page = 1; $page <= $pages; $page++) {
            try {
                $textPayload = $this->pdfTools->extractPageText($sourcePath, $page);
                $text = (string) ($textPayload['text'] ?? '');
                $rutOriginal = $this->extractRut($text);

                if (!$rutOriginal) {
                    $errores[] = "Página {$page}: no se detectó RUT.";
                    continue;
                }

                $totalConRut++;
                $rutNormalizado = self::normalizeRut($rutOriginal);
                if ($rutNormalizado === '') {
                    $errores[] = "Página {$page}: el RUT detectado no pudo normalizarse.";
                    continue;
                }

                $nombre = $this->extractNombre($text, $rutOriginal);
                $tipoContrato = $this->extractTipoContrato($text);
                $esReemplazo = $this->isReplacement($text, $tipoContrato);

                if (!$esReemplazo) {
                    continue;
                }

                $totalReemplazos++;
                $relativePath = $this->buildIndividualPath($carga, $rutNormalizado, $page);
                $fullOutputPath = $disk->path($relativePath);
                if (!is_dir(dirname($fullOutputPath))) {
                    mkdir(dirname($fullOutputPath), 0775, true);
                }

                $this->pdfTools->separatePage($sourcePath, $page, $fullOutputPath);

                LiquidacionFuncionario::query()->updateOrCreate(
                    [
                        'rut_normalizado' => $rutNormalizado,
                        'anio' => $carga->anio,
                        'mes' => $carga->mes,
                        'dominio' => $carga->dominio,
                        'pagina_origen' => $page,
                    ],
                    [
                        'liquidacion_carga_id' => $carga->id,
                        'rut_original' => $rutOriginal,
                        'nombre' => $nombre,
                        'archivo_pdf_path' => $relativePath,
                        'es_reemplazo' => true,
                        'tipo_contrato_detectado' => $tipoContrato,
                        'fecha_inicio' => $this->extractFirstDateAfterContracts($text),
                        'fecha_termino' => $this->extractLastDateAfterContracts($text),
                        'texto_detectado_resumen' => Str::limit($this->compactText($text), 1500, ''),
                    ]
                );

                $totalPublicadas++;
            } catch (\Throwable $e) {
                $errores[] = "Página {$page}: " . $e->getMessage();
                Log::warning('Error procesando página de liquidación', [
                    'carga_id' => $carga->id,
                    'pagina' => $page,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $estado = count($errores) > 0 ? 'procesado_con_observaciones' : 'procesado';

        $carga->update([
            'estado' => $estado,
            'total_paginas' => $pages,
            'total_con_rut' => $totalConRut,
            'total_reemplazos' => $totalReemplazos,
            'total_publicadas' => $totalPublicadas,
            'total_errores' => count($errores),
            'errores' => array_slice($errores, 0, 200),
            'procesada_at' => now(),
        ]);
    }

    public static function normalizeRut(?string $rut): string
    {
        $normalized = RutChile::normalize((string) $rut);
        if (is_array($normalized) && !empty($normalized['rut'])) {
            return strtoupper(preg_replace('/[^0-9K]/', '', (string) $normalized['rut']));
        }

        return strtoupper(preg_replace('/[^0-9Kk]/', '', (string) $rut));
    }

    private function extractRut(string $text): ?string
    {
        if (preg_match('/\b[0-9OIL]{1,2}(?:[\.\s]?[0-9OIL]{3}){2}[-‐‑–—]?[0-9KkOo]\b/u', $text, $matches)) {
            return strtoupper($this->sanitizeRutCandidate($matches[0]));
        }

        if (preg_match('/\b\d{7,8}-[0-9Kk]\b/u', $text, $matches)) {
            return strtoupper($matches[0]);
        }

        return null;
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

    private function extractNombre(string $text, string $rutOriginal): ?string
    {
        $lines = preg_split('/\R/u', $text) ?: [];
        $rutVariants = array_unique([
            $rutOriginal,
            str_replace(['.', ' '], '', $rutOriginal),
            preg_replace('/[^0-9Kk]/', '', $rutOriginal),
        ]);

        foreach ($lines as $line) {
            foreach ($rutVariants as $variant) {
                if ($variant !== null && $variant !== '' && str_contains($line, $variant)) {
                    $candidate = trim(str_replace($variant, '', $line));
                    $candidate = preg_replace('/\s+/', ' ', $candidate);
                    $candidate = trim((string) $candidate);

                    return $candidate !== '' ? Str::limit($candidate, 250, '') : null;
                }
            }
        }

        return null;
    }

    private function extractTipoContrato(string $text): ?string
    {
        $compact = $this->compactText($text);
        $patterns = [
            '/\bCONTRATA\s+(?:PIE|SEP|PRO\s+RET)?\s*\(S\)/iu',
            '/\bCONTRATA[^\n]{0,80}\(S\)/iu',
            '/\bPLAZO\s+FIJO\s*\(S\)/iu',
            '/\bPLAZO\s+FIJO\b/iu',
            '/\bCONTRATA\s*\(S\)/iu',
        ];

        $found = [];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $compact, $matches)) {
                foreach ($matches[0] as $match) {
                    $found[] = trim(preg_replace('/\s+/', ' ', $match));
                }
            }
        }

        $found = array_values(array_unique($found));

        return $found ? Str::limit(implode(' / ', $found), 250, '') : null;
    }

    private function isReplacement(string $text, ?string $tipoContrato): bool
    {
        $compact = $this->compactText($text);

        if ($tipoContrato !== null && $tipoContrato !== '') {
            return true;
        }

        if (preg_match('/\b(?:CONTRATA|PLAZO\s+FIJO)[^\n]{0,120}\(S\)/iu', $compact)) {
            return true;
        }

        return false;
    }

    private function extractFirstDateAfterContracts(string $text): ?string
    {
        $dates = $this->extractDates($text);

        return $dates[0] ?? null;
    }

    private function extractLastDateAfterContracts(string $text): ?string
    {
        $dates = $this->extractDates($text);

        return count($dates) > 1 ? end($dates) : null;
    }

    /** @return array<int, string> */
    private function extractDates(string $text): array
    {
        if (!preg_match_all('/\b(\d{2})-(\d{2})-(\d{4})\b/', $text, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $dates = [];
        foreach ($matches as $match) {
            $dates[] = sprintf('%04d-%02d-%02d', (int) $match[3], (int) $match[2], (int) $match[1]);
        }

        return array_values(array_unique($dates));
    }

    private function compactText(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    private function buildIndividualPath(LiquidacionCarga $carga, string $rutNormalizado, int $page): string
    {
        $dominioSlug = Str::slug($carga->dominio ?: 'dominio');
        $file = sprintf('%04d-%02d-%s-%s-p%04d.pdf', $carga->anio, $carga->mes, $dominioSlug, $rutNormalizado, $page);

        return sprintf('liquidaciones/individuales/%04d/%02d/%s/%s', $carga->anio, $carga->mes, $dominioSlug, $file);
    }
}
