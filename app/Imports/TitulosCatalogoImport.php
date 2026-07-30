<?php

namespace App\Imports;

use App\Models\TituloCatalogo;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TitulosCatalogoImport
{
    public function import(UploadedFile|string $file): array
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : (string) $file;
        $spreadsheet = IOFactory::load($path);

        [$sheetTitle, $rows, $header, $tituloIndex, $diagnostics] = $this->resolveSheetAndColumn($spreadsheet);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        if ($rows === []) {
            throw new \RuntimeException('El archivo no contiene datos.');
        }

        if ($tituloIndex === null) {
            $message = 'No se encontró una columna válida para títulos. Se esperaba una columna como: Titulos, Titulo o Nombre.';
            if ($diagnostics !== []) {
                $message .= ' Hojas revisadas: ' . implode(' | ', $diagnostics) . '.';
            }
            throw new \RuntimeException($message);
        }

        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $nombre = trim((string) ($row[$tituloIndex] ?? ''));
            if ($nombre === '') {
                $skipped++;
                continue;
            }

            $existing = TituloCatalogo::where('nombre', $nombre)->first();
            if ($existing) {
                $existing->update(['nombre' => $nombre]);
                $updated++;
            } else {
                TituloCatalogo::create(['nombre' => $nombre]);
                $inserted++;
            }
        }

        return [
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'sheet' => $sheetTitle,
        ];
    }

    private function resolveSheetAndColumn($spreadsheet): array
    {
        $accepted = ['titulos', 'titulo', 'nombre', 'nombre_titulo'];
        $diagnostics = [];
        $candidate = [null, [], [], null];

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $rows = $sheet->toArray(null, true, true, false);
            if (empty($rows)) {
                $diagnostics[] = $sheet->getTitle() . ': sin datos';
                continue;
            }

            $best = $this->findBestHeaderRow($rows, $accepted);
            if ($best === null) {
                $diagnostics[] = $sheet->getTitle() . ': sin encabezado util';
                continue;
            }

            [$headerIndex, $headerRow] = $best;
            $normalizedHeader = array_map([$this, 'normalize'], $headerRow);
            $tituloIndex = $this->resolveColumnIndex($normalizedHeader, $accepted);
            $diagnostics[] = $sheet->getTitle() . ': fila ' . ($headerIndex + 1) . ' [' . $this->headerPreview($headerRow) . ']';
            $dataRows = array_slice($rows, $headerIndex + 1);

            if ($tituloIndex !== null) {
                return [$sheet->getTitle(), $dataRows, $headerRow, $tituloIndex, $diagnostics];
            }

            if ($candidate[0] === null) {
                $candidate = [$sheet->getTitle(), $dataRows, $headerRow, null];
            }
        }

        return [$candidate[0], $candidate[1], $candidate[2], $candidate[3], $diagnostics];
    }

    private function findBestHeaderRow(array $rows, array $accepted): ?array
    {
        $limit = min(count($rows), 10);
        $best = null;
        $bestScore = -1;

        for ($i = 0; $i < $limit; $i++) {
            $row = $rows[$i] ?? [];
            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $normalizedHeader = array_map([$this, 'normalize'], $row);
            $score = 0;
            foreach ($accepted as $name) {
                if (in_array($this->normalize($name), $normalizedHeader, true)) {
                    $score++;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = [$i, $row];
            }
        }

        return $best;
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function headerPreview(array $headerRow): string
    {
        $values = [];
        foreach ($headerRow as $value) {
            $text = trim((string) $value);
            if ($text !== '') {
                $values[] = $text;
            }
            if (count($values) >= 8) {
                break;
            }
        }

        return implode(', ', $values);
    }

    private function resolveColumnIndex(array $normalizedHeader, array $acceptedNames): ?int
    {
        foreach ($normalizedHeader as $index => $name) {
            foreach ($acceptedNames as $accepted) {
                if ($name === $this->normalize($accepted)) {
                    return $index;
                }
            }
        }

        return null;
    }

    private function normalize(mixed $value): string
    {
        $text = trim((string) $value);
        $text = preg_replace('/^\xEF\xBB\xBF/u', '', $text) ?? $text;
        $text = strtolower($text);
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        $text = preg_replace('/[^a-z0-9]+/', '_', $text) ?: '';
        return trim($text, '_');
    }
}
