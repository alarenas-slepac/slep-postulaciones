<?php

namespace App\Imports;

use App\Models\DeclaracionSostenedor;
use App\Models\FuncionCatalogo;
use App\Models\InstitucionCatalogo;
use App\Models\TituloCatalogo;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SostenedoresImport
{
    public function import(UploadedFile|string $file): array
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : (string) $file;
        $spreadsheet = IOFactory::load($path);

        [$sheetTitle, $rows, $headerRow, $headerMap, $diagnostics] = $this->resolveSheetAndHeader($spreadsheet);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        if ($rows === []) {
            throw new \RuntimeException('El archivo no contiene datos importables. ' . $this->diagnosticsText($diagnostics));
        }

        $requiredAliases = ['rbd', 'rut', 'nombres'];
        $missing = [];
        foreach ($requiredAliases as $alias) {
            if (!array_key_exists($alias, $headerMap)) {
                $missing[] = $alias;
            }
        }

        if (!empty($missing)) {
            throw new \RuntimeException(
                'Faltan columnas requeridas en el archivo: ' . implode(', ', $missing) . '. ' .
                $this->diagnosticsText($diagnostics)
            );
        }

        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $rowIndex => $row) {
            $payload = [
                'rbd' => $this->stringAt($row, $headerMap, 'rbd'),
                'rut' => $this->stringAt($row, $headerMap, 'rut'),
                'nombres' => $this->stringAt($row, $headerMap, 'nombres'),
                'apellido_paterno' => $this->stringAt($row, $headerMap, 'apellido_paterno'),
                'apellido_materno' => $this->stringAt($row, $headerMap, 'apellido_materno'),
                'horas_contratadas' => $this->intAt($row, $headerMap, 'horas_contratadas'),
                'educacion_parvularia' => $this->boolAt($row, $headerMap, 'educacion_parvularia'),
                'ensenanza_basica' => $this->boolAt($row, $headerMap, 'ensenanza_basica'),
                'ensenanza_media' => $this->boolAt($row, $headerMap, 'ensenanza_media'),
                'nombre_funcion' => $this->stringAt($row, $headerMap, 'nombre_funcion'),
                'tipo_titulo' => $this->stringAt($row, $headerMap, 'tipo_titulo'),
                'nombre_titulo' => $this->stringAt($row, $headerMap, 'nombre_titulo'),
                'institucion_educacional' => $this->stringAt($row, $headerMap, 'institucion_educacional'),
                'fecha_titulacion' => $this->dateAt($row, $headerMap, 'fecha_titulacion'),
                'pais_titulo' => $this->stringAt($row, $headerMap, 'pais_titulo'),
                'estamento' => $this->stringAt($row, $headerMap, 'estamento'),
            ];

            if (blank($payload['rut']) && blank($payload['rbd']) && blank($payload['nombres'])) {
                $skipped++;
                continue;
            }

            $payload['rbd'] = $payload['rbd'] !== null ? trim((string) $payload['rbd']) : null;
            $payload['rut'] = $payload['rut'] !== null ? trim((string) $payload['rut']) : null;

            $payload['nombre_funcion'] = $this->normalizeFuncion($payload['nombre_funcion']);
            $payload['tipo_titulo'] = $this->normalizeTipoTitulo($payload['tipo_titulo']);
            $payload['nombre_titulo'] = $this->normalizeCatalogText($payload['nombre_titulo']);
            $payload['institucion_educacional'] = $this->normalizeText($payload['institucion_educacional']);
            $payload['estamento'] = $this->normalizeEstamento($payload['estamento']);
            $payload['funcion_catalogo_id'] = $this->resolveFuncionCatalogoId($payload['nombre_funcion']);
            $payload['titulo_catalogo_id'] = $this->resolveTituloCatalogoId($payload['nombre_titulo']);
            $payload['institucion_catalogo_id'] = $this->resolveInstitucionCatalogoId($payload['institucion_educacional']);

            if ($payload['estamento'] === 'ASISTENTE' && $payload['tipo_titulo'] === 'Ninguno') {
                $payload['nombre_titulo'] = null;
                $payload['titulo_catalogo_id'] = null;
                $payload['institucion_educacional'] = null;
                $payload['institucion_catalogo_id'] = null;
                $payload['fecha_titulacion'] = null;
            }

            $existing = DeclaracionSostenedor::where('rut', (string) ($payload['rut'] ?? ''))
                ->where('rbd', (string) ($payload['rbd'] ?? ''))
                ->first();

            if ($existing) {
                $existing->update($payload);
                $updated++;
            } else {
                DeclaracionSostenedor::create($payload);
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

    private function resolveSheetAndHeader($spreadsheet): array
    {
        $diagnostics = [];
        $bestCandidate = null;
        $bestScore = -1;

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $rows = $sheet->toArray(null, true, true, false);
            if (empty($rows)) {
                $diagnostics[] = $sheet->getTitle() . ': sin datos';
                continue;
            }

            $candidate = $this->findCandidateHeader($rows, $sheet);
            if ($candidate === null) {
                $diagnostics[] = $sheet->getTitle() . ': sin encabezado reconocible';
                continue;
            }

            [$headerIndex, $headerRow, $headerMap, $score, $mode] = $candidate;
            $dataRows = array_slice($rows, $headerIndex + 1);
            $preview = $this->headerPreview($headerRow);
            $diagnostics[] = $sheet->getTitle() . ': fila ' . ($headerIndex + 1) . ' (' . $mode . ') [' . $preview . ']';

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestCandidate = [
                    'sheet' => $sheet->getTitle(),
                    'rows' => $dataRows,
                    'headerRow' => $headerRow,
                    'headerMap' => $headerMap,
                ];
            }

            if ($this->hasRequiredAliases($headerMap)) {
                return [$sheet->getTitle(), $dataRows, $headerRow, $headerMap, $diagnostics];
            }
        }

        if ($bestCandidate !== null) {
            return [
                $bestCandidate['sheet'],
                $bestCandidate['rows'],
                $bestCandidate['headerRow'],
                $bestCandidate['headerMap'],
                $diagnostics,
            ];
        }

        return [null, [], [], [], $diagnostics];
    }

    private function findCandidateHeader(array $rows, Worksheet $sheet): ?array
    {
        $limit = min(count($rows), 20);
        $best = null;
        $bestScore = -1;

        for ($i = 0; $i < $limit; $i++) {
            $row = $rows[$i] ?? [];
            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $sampleRow = $this->findNextDataRow($rows, $i + 1);
            $headerMap = $this->buildHeaderMap($row, $sampleRow);
            $score = count($headerMap);
            $mode = 'alias';

            if ($score < 3 || !$this->hasRequiredAliases($headerMap)) {
                $positional = $this->buildTemplateFallbackMap($row, $sampleRow);
                if (count($positional) > $score || ($this->hasRequiredAliases($positional) && !$this->hasRequiredAliases($headerMap))) {
                    $headerMap = $positional;
                    $score = count($headerMap);
                    $mode = 'plantilla';
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = [$i, $row, $headerMap, $score, $mode];
            }
        }

        return $best;
    }

    private function buildTemplateFallbackMap(array $headerRow, ?array $sampleRow = null): array
    {
        $normalized = array_map(fn ($value) => $this->normalize((string) $value), $headerRow);
        $map = [];

        // Plantilla tipo: N°, RBD, RUT, Nombres, Apellido Paterno, Apellido Materno, Horas, ...
        if (($normalized[1] ?? null) === 'rbd' && in_array(($normalized[2] ?? null), ['rut', 'run'], true) && in_array(($normalized[3] ?? null), ['nombres', 'nombre'], true)) {
            $map['rbd'] = 1;
            $map['rut'] = 2;
            $map['nombres'] = 3;
            $map['apellido_paterno'] = 4;
            $map['apellido_materno'] = 5;
            $map['horas_contratadas'] = 6;
            $map['educacion_parvularia'] = 7;
            $map['ensenanza_basica'] = 8;
            $map['ensenanza_media'] = 9;
            if (($normalized[10] ?? null) === 'funcion' || ($normalized[10] ?? null) === 'funcion_asistente') {
                $map['nombre_funcion'] = 10;
            }
            if (in_array(($normalized[11] ?? null), ['tipo_de_titulo', 'tipo_titulo'], true)) {
                $map['tipo_titulo'] = 11;
            }
            if (in_array(($normalized[14] ?? null), ['nombre_titulo', 'titulo'], true)) {
                $map['nombre_titulo'] = 14;
            }
            if (Str::contains((string) ($normalized[15] ?? ''), 'institucion')) {
                $map['institucion_educacional'] = 15;
            }
            if (Str::contains((string) ($normalized[16] ?? ''), 'fecha_titulacion')) {
                $map['fecha_titulacion'] = 16;
            }
            if (in_array(($normalized[17] ?? null), ['pais', 'pais_titulo'], true)) {
                $map['pais_titulo'] = 17;
            }
            if (($normalized[18] ?? null) === 'estamento') {
                $map['estamento'] = 18;
            }

            return $map;
        }

        // Plantilla tipo: RBD, RBD, RUT, Nombres, ... (primer RBD en realidad es nombre establecimiento).
        if (($normalized[0] ?? null) === 'rbd' && ($normalized[1] ?? null) === 'rbd' && in_array(($normalized[2] ?? null), ['rut', 'run'], true)) {
            $map['rbd'] = 1;
            $map['rut'] = 2;
            $map['nombres'] = 3;
            $map['apellido_paterno'] = 4;
            $map['apellido_materno'] = 5;
            $map['horas_contratadas'] = 6;
            $map['educacion_parvularia'] = 7;
            $map['ensenanza_basica'] = 8;
            $map['ensenanza_media'] = 9;
            if (($normalized[10] ?? null) === 'funcion') {
                $map['nombre_funcion'] = 10;
            }
            if (in_array(($normalized[11] ?? null), ['tipo_de_titulo', 'tipo_titulo'], true)) {
                $map['tipo_titulo'] = 11;
            }
            if (in_array(($normalized[14] ?? null), ['nombre_titulo', 'titulo'], true)) {
                $map['nombre_titulo'] = 14;
            }
            if (Str::contains((string) ($normalized[15] ?? ''), 'institucion')) {
                $map['institucion_educacional'] = 15;
            }
            if (Str::contains((string) ($normalized[16] ?? ''), 'fecha_titulacion')) {
                $map['fecha_titulacion'] = 16;
            }
            if (in_array(($normalized[17] ?? null), ['pais', 'pais_titulo'], true)) {
                $map['pais_titulo'] = 17;
            }
            if (($normalized[18] ?? null) === 'estamento') {
                $map['estamento'] = 18;
            }

            return $map;
        }

        // Plantilla simple: RBD, RUT, Nombres, ...
        if (($normalized[0] ?? null) === 'rbd' && in_array(($normalized[1] ?? null), ['rut', 'run'], true) && in_array(($normalized[2] ?? null), ['nombres', 'nombre'], true)) {
            $map['rbd'] = 0;
            $map['rut'] = 1;
            $map['nombres'] = 2;
            $map['apellido_paterno'] = 3;
            $map['apellido_materno'] = 4;
            $map['horas_contratadas'] = 5;
            $map['educacion_parvularia'] = 6;
            $map['ensenanza_basica'] = 7;
            $map['ensenanza_media'] = 8;
            if (($normalized[9] ?? null) === 'funcion') {
                $map['nombre_funcion'] = 9;
            }
            if (in_array(($normalized[10] ?? null), ['tipo_de_titulo', 'tipo_titulo'], true)) {
                $map['tipo_titulo'] = 10;
            }
            if (in_array(($normalized[13] ?? null), ['nombre_titulo', 'titulo'], true)) {
                $map['nombre_titulo'] = 13;
            }
            if (Str::contains((string) ($normalized[14] ?? ''), 'institucion')) {
                $map['institucion_educacional'] = 14;
            }
            if (Str::contains((string) ($normalized[15] ?? ''), 'fecha_titulacion')) {
                $map['fecha_titulacion'] = 15;
            }
            if (in_array(($normalized[16] ?? null), ['pais', 'pais_titulo'], true)) {
                $map['pais_titulo'] = 16;
            }
            if (($normalized[17] ?? null) === 'estamento') {
                $map['estamento'] = 17;
            }
        }

        return $map;
    }

    private function hasRequiredAliases(array $headerMap): bool
    {
        foreach (['rbd', 'rut', 'nombres'] as $alias) {
            if (!array_key_exists($alias, $headerMap)) {
                return false;
            }
        }

        return true;
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
            if (count($values) >= 12) {
                break;
            }
        }

        return implode(', ', $values);
    }

    private function diagnosticsText(array $diagnostics): string
    {
        if ($diagnostics === []) {
            return 'No fue posible identificar una hoja ni fila de encabezado compatible.';
        }

        return 'Hojas revisadas: ' . implode(' | ', $diagnostics) . '.';
    }

    private function buildHeaderMap(array $headerRow, ?array $sampleRow = null): array
    {
        $aliases = [
            'rbd' => ['rbd', 'cod_estab', 'codigo_establecimiento', 'codigo establecimiento'],
            'rut' => ['rut', 'run', 'rut_docente', 'rut_profesor', 'rut_asistente'],
            'nombres' => ['nombres', 'nombre', 'nombres_docente', 'nombre_docente', 'nombres_asistente'],
            'apellido_paterno' => ['apellido_paterno', 'apellido paterno', 'ap_paterno', 'apellido1'],
            'apellido_materno' => ['apellido_materno', 'apellido materno', 'ap_materno', 'apellido2'],
            'horas_contratadas' => ['horas_contratadas', 'horas', 'horas contratadas', 'jornada'],
            'educacion_parvularia' => ['educacion_parvularia', 'parvularia', 'educacion parvularia'],
            'ensenanza_basica' => ['ensenanza_basica', 'basica', 'básica', 'ensenanza basica', 'enseñanza básica'],
            'ensenanza_media' => ['ensenanza_media', 'media', 'ensenanza media', 'enseñanza media'],
            'nombre_funcion' => ['nombre_funcion', 'nombre funcion', 'funcion', 'función', 'funciones'],
            'tipo_titulo' => ['tipo_titulo', 'tipo titulo', 'tipo título', 'tipo de titulo', 'tipo de título'],
            'nombre_titulo' => ['nombre_titulo', 'nombre titulo', 'nombre del titulo', 'titulo obtenido'],
            'institucion_educacional' => ['institucion_educacional', 'institucion', 'institución', 'institucion educacional'],
            'fecha_titulacion' => ['fecha_titulacion', 'fecha titulacion', 'fecha titulación'],
            'pais_titulo' => ['pais_titulo', 'pais', 'país'],
            'estamento' => ['estamento', 'tipo_estamento'],
        ];

        $normalized = [];
        foreach ($headerRow as $index => $header) {
            $normalized[$index] = $this->normalize((string) $header);
        }

        $matchIndexes = [];
        foreach ($aliases as $alias => $acceptedNames) {
            foreach ($normalized as $index => $headerName) {
                foreach ($acceptedNames as $accepted) {
                    $acceptedNormalized = $this->normalize($accepted);
                    if ($headerName === $acceptedNormalized || Str::contains($headerName, $acceptedNormalized)) {
                        $matchIndexes[$alias][] = $index;
                        break;
                    }
                }
            }
        }

        $map = [];
        foreach ($matchIndexes as $alias => $candidateIndexes) {
            $resolved = $this->resolveMatchedIndex($alias, $candidateIndexes, $normalized, $sampleRow);
            if ($resolved !== null) {
                $map[$alias] = $resolved;
            }
        }

        return $map;
    }

    private function resolveMatchedIndex(string $alias, array $candidateIndexes, array $normalizedHeaders, ?array $sampleRow = null): ?int
    {
        if ($candidateIndexes === []) {
            return null;
        }

        if ($alias === 'rbd') {
            foreach ($candidateIndexes as $index) {
                $value = $sampleRow[$index] ?? null;
                if ($this->looksLikeRbd($value)) {
                    return $index;
                }
            }

            return max($candidateIndexes);
        }

        if ($alias === 'nombre_titulo') {
            foreach ($candidateIndexes as $index) {
                $headerName = $normalizedHeaders[$index] ?? '';
                if (Str::contains($headerName, 'nombre_titulo')) {
                    return $index;
                }
            }
        }

        return $candidateIndexes[0];
    }

    private function findNextDataRow(array $rows, int $startIndex): ?array
    {
        for ($i = $startIndex; $i < count($rows); $i++) {
            if (!$this->rowIsEmpty($rows[$i] ?? [])) {
                return $rows[$i];
            }
        }

        return null;
    }

    private function looksLikeRbd(mixed $value): bool
    {
        $text = trim((string) $value);
        if ($text === '') {
            return false;
        }

        $digits = preg_replace('/\D+/', '', $text);
        return $digits !== '' && strlen($digits) >= 3 && strlen($digits) <= 8;
    }

    private function stringAt(array $row, array $headerMap, string $alias): ?string
    {
        if (!array_key_exists($alias, $headerMap)) {
            return null;
        }
        $value = $row[$headerMap[$alias]] ?? null;
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    private function intAt(array $row, array $headerMap, string $alias): ?int
    {
        $value = $this->stringAt($row, $headerMap, $alias);
        if ($value === null) {
            return null;
        }
        $clean = preg_replace('/[^0-9\-]/', '', $value);
        return $clean === '' ? null : (int) $clean;
    }

    private function dateAt(array $row, array $headerMap, string $alias): ?string
    {
        if (!array_key_exists($alias, $headerMap)) {
            return null;
        }

        $value = $row[$headerMap[$alias]] ?? null;
        return $this->normalizeDateValue($value);
    }

    private function normalizeDateValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable $e) {
                // seguir a parseo textual
            }
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        $text = preg_replace('/\s+/', ' ', $text) ?: $text;
        $text = preg_replace('/\s+00:00:00$/', '', $text) ?: $text;

        $formats = [
            'Y-m-d',
            'Y/m/d',
            'd/m/Y',
            'j/n/Y',
            'd-m-Y',
            'j-n-Y',
            'm/d/Y',
            'n/j/Y',
            'm-d-Y',
            'n-j-Y',
            'd.m.Y',
            'j.n.Y',
            'Y-m-d H:i:s',
            'Y/m/d H:i:s',
            'd/m/Y H:i:s',
            'm/d/Y H:i:s',
        ];

        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat('!' . $format, $text);
            $errors = \DateTime::getLastErrors();
            $warningCount = is_array($errors) ? ($errors['warning_count'] ?? 0) : 0;
            $errorCount = is_array($errors) ? ($errors['error_count'] ?? 0) : 0;

            if ($date !== false && $warningCount === 0 && $errorCount === 0) {
                return $date->format('Y-m-d');
            }
        }

        try {
            return Carbon::parse($text)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private function normalizeCatalogText(?string $value): ?string
    {
        $value = $this->normalizeText($value);
        if ($value === null) {
            return null;
        }

        $normalized = $this->normalize($value);
        if (in_array($normalized, ['0', 'seleccione', 'sin_informacion', 'sin_info', 'na', 'n_a'], true)) {
            return null;
        }

        return preg_replace('/\s+/', ' ', $value) ?: $value;
    }

    private function normalizeFuncion(?string $value): ?string
    {
        return $this->normalizeCatalogText($value);
    }

    private function normalizeTipoTitulo(?string $value): ?string
    {
        $value = $this->normalizeText($value);
        if ($value === null) {
            return null;
        }

        $normalized = $this->normalize($value);
        if (in_array($normalized, ['n', 'ninguno', 'sin_titulo', 'no_tiene'], true)) {
            return 'Ninguno';
        }
        if (in_array($normalized, ['p', 'profesional', 'prof'], true)) {
            return 'Profesional';
        }
        if (in_array($normalized, ['t', 'tecnico', 'tec', 'tecnico_profesional'], true)) {
            return 'Técnico';
        }

        return null;
    }

    private function normalizeEstamento(?string $value): ?string
    {
        $value = $this->normalizeText($value);
        if ($value === null) {
            return null;
        }
        $normalized = $this->normalize($value);
        if (Str::contains($normalized, 'docente')) {
            return 'DOCENTE';
        }
        if (Str::contains($normalized, ['asistente', 'asistente_de_la_educacion'])) {
            return 'ASISTENTE';
        }
        return mb_strtoupper($value);
    }

    private function resolveFuncionCatalogoId(?string $nombreFuncion): ?int
    {
        if ($nombreFuncion === null) {
            return null;
        }

        $funcion = FuncionCatalogo::query()->get(['id', 'nombre'])->first(function ($item) use ($nombreFuncion) {
            return $this->normalize((string) $item->nombre) === $this->normalize($nombreFuncion);
        });

        return $funcion?->id;
    }

    private function resolveTituloCatalogoId(?string $nombreTitulo): ?int
    {
        if ($nombreTitulo === null) {
            return null;
        }

        $titulo = TituloCatalogo::query()->get(['id', 'nombre'])->first(function ($item) use ($nombreTitulo) {
            return $this->normalize((string) $item->nombre) === $this->normalize($nombreTitulo);
        });

        return $titulo?->id;
    }

    private function resolveInstitucionCatalogoId(?string $nombreInstitucion): ?int
    {
        if ($nombreInstitucion === null) {
            return null;
        }

        $institucion = InstitucionCatalogo::query()->get(['id', 'nombre'])->first(function ($item) use ($nombreInstitucion) {
            return $this->normalize((string) $item->nombre) === $this->normalize($nombreInstitucion);
        });

        return $institucion?->id;
    }

    private function boolAt(array $row, array $headerMap, string $alias): bool
    {
        $value = $this->stringAt($row, $headerMap, $alias);
        if ($value === null) {
            return false;
        }
        $normalized = Str::lower(trim((string) $value));
        return in_array($normalized, ['1', 'si', 'sí', 's', 'true', 'x', 'yes'], true);
    }

    private function normalize(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/u', '', $value) ?? $value;
        return Str::of($value)
            ->lower()
            ->ascii()
            ->replace(['_', '-', '.', 'º', '°', '#'], ' ')
            ->squish()
            ->replace(' ', '_')
            ->value();
    }
}
