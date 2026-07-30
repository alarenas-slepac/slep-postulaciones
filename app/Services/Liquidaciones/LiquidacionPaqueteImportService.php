<?php

namespace App\Services\Liquidaciones;

use App\Models\LiquidacionCarga;
use App\Models\LiquidacionFuncionario;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class LiquidacionPaqueteImportService
{
    /**
     * Importa un ZIP generado fuera del servidor con estructura:
     * - manifest.csv
     * - pdfs/<rut>.pdf
     */
    public function process(LiquidacionCarga $carga): void
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Falta la extensión PHP ZipArchive. Activa la extensión zip en cPanel para importar paquetes ZIP.');
        }

        $disk = Storage::disk('local');
        $sourcePath = $disk->path($carga->archivo_original_path);

        if (!$disk->exists($carga->archivo_original_path) || !is_file($sourcePath)) {
            throw new RuntimeException('No se encontró el paquete ZIP de liquidaciones.');
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

        $zip = new ZipArchive();
        if ($zip->open($sourcePath) !== true) {
            throw new RuntimeException('No se pudo abrir el paquete ZIP de liquidaciones.');
        }

        try {
            $manifest = $zip->getFromName('manifest.csv');
            if ($manifest === false) {
                throw new RuntimeException('El ZIP no contiene manifest.csv en la raíz.');
            }

            $rows = $this->readManifest($manifest);
            if (empty($rows)) {
                throw new RuntimeException('El manifest.csv no contiene registros para importar.');
            }

            $errores = [];
            $totalConRut = 0;
            $totalReemplazos = 0;
            $totalPublicadas = 0;
            $totalPaginas = 0;

            foreach ($rows as $index => $row) {
                $linea = $index + 2;

                try {
                    $rutOriginal = trim((string) ($row['rut_original'] ?? ''));
                    $rutNormalizado = LiquidacionPdfImportService::normalizeRut((string) ($row['rut_normalizado'] ?? $rutOriginal));
                    $archivoPdf = trim((string) ($row['archivo_pdf'] ?? ''));
                    $paginaOrigen = max(0, (int) ($row['pagina_origen'] ?? 0));
                    $esReemplazo = $this->toBool($row['es_reemplazo'] ?? true);

                    if ($rutNormalizado === '') {
                        $errores[] = "Línea {$linea}: RUT vacío o inválido.";
                        continue;
                    }

                    $totalConRut++;
                    $totalPaginas = max($totalPaginas, $paginaOrigen);

                    if (!$esReemplazo) {
                        continue;
                    }

                    $totalReemplazos++;

                    if ($archivoPdf === '' || str_contains($archivoPdf, '..') || !str_starts_with($archivoPdf, 'pdfs/')) {
                        $errores[] = "Línea {$linea}: ruta de PDF inválida.";
                        continue;
                    }

                    $pdfBytes = $zip->getFromName($archivoPdf);
                    if ($pdfBytes === false) {
                        $errores[] = "Línea {$linea}: no se encontró {$archivoPdf} dentro del ZIP.";
                        continue;
                    }

                    $relativePath = $this->buildIndividualPath($carga, $rutNormalizado, $paginaOrigen, basename($archivoPdf));
                    $disk->put($relativePath, $pdfBytes);

                    LiquidacionFuncionario::query()->updateOrCreate(
                        [
                            'rut_normalizado' => $rutNormalizado,
                            'anio' => $carga->anio,
                            'mes' => $carga->mes,
                            'dominio' => $carga->dominio,
                            'pagina_origen' => $paginaOrigen,
                        ],
                        [
                            'liquidacion_carga_id' => $carga->id,
                            'rut_original' => $rutOriginal ?: null,
                            'nombre' => $this->cleanNullable($row['nombre'] ?? null, 255),
                            'archivo_pdf_path' => $relativePath,
                            'es_reemplazo' => true,
                            'tipo_contrato_detectado' => $this->cleanNullable($row['tipo_contrato_detectado'] ?? null, 255),
                            'fecha_inicio' => $this->normalizeDate($row['fecha_inicio'] ?? null),
                            'fecha_termino' => $this->normalizeDate($row['fecha_termino'] ?? null),
                            'texto_detectado_resumen' => 'Importado desde paquete ZIP procesado localmente.',
                        ]
                    );

                    $totalPublicadas++;
                } catch (\Throwable $e) {
                    $errores[] = "Línea {$linea}: " . $e->getMessage();
                    Log::warning('Error importando liquidación desde ZIP', [
                        'carga_id' => $carga->id,
                        'linea' => $linea,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            $estado = count($errores) > 0 ? 'procesado_con_observaciones' : 'procesado';

            $carga->update([
                'estado' => $estado,
                'total_paginas' => $totalPaginas,
                'total_con_rut' => $totalConRut,
                'total_reemplazos' => $totalReemplazos,
                'total_publicadas' => $totalPublicadas,
                'total_errores' => count($errores),
                'errores' => array_slice($errores, 0, 200),
                'procesada_at' => now(),
            ]);
        } finally {
            $zip->close();
        }
    }

    /** @return array<int, array<string, string|null>> */
    private function readManifest(string $csv): array
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new RuntimeException('No se pudo preparar la lectura de manifest.csv.');
        }

        fwrite($stream, $csv);
        rewind($stream);

        $headers = fgetcsv($stream);
        if ($headers === false) {
            fclose($stream);
            return [];
        }

        $headers = array_map(function ($header) {
            $header = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header);
            return trim((string) $header);
        }, $headers);

        $rows = [];
        while (($data = fgetcsv($stream)) !== false) {
            if ($data === [null] || $data === false) {
                continue;
            }

            $row = [];
            foreach ($headers as $i => $header) {
                if ($header === '') {
                    continue;
                }
                $row[$header] = $data[$i] ?? null;
            }
            $rows[] = $row;
        }

        fclose($stream);

        return $rows;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower(trim((string) $value));

        return in_array($value, ['1', 'true', 'si', 'sí', 'yes', 'y'], true);
    }

    private function cleanNullable(mixed $value, int $limit): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return Str::limit(preg_replace('/\s+/', ' ', $value), $limit, '');
    }

    private function normalizeDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $value, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        return null;
    }

    private function buildIndividualPath(LiquidacionCarga $carga, string $rutNormalizado, int $page, string $originalName): string
    {
        $dominioSlug = Str::slug($carga->dominio ?: 'dominio');
        $pageSuffix = $page > 0 ? sprintf('-p%04d', $page) : '';
        $safeName = pathinfo($originalName, PATHINFO_FILENAME);
        $safeName = Str::slug($safeName) ?: $rutNormalizado;
        $file = sprintf('%04d-%02d-%s-%s%s-%s.pdf', $carga->anio, $carga->mes, $dominioSlug, $rutNormalizado, $pageSuffix, $safeName);

        return sprintf('liquidaciones/individuales/%04d/%02d/%s/%s', $carga->anio, $carga->mes, $dominioSlug, $file);
    }
}
