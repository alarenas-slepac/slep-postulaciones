<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

class StreamingXlsxWriter
{
    public const STYLE_DEFAULT = 0;
    public const STYLE_HEADER = 1;
    public const STYLE_HIGHLIGHT_DANGER = 2;
    public const STYLE_HIGHLIGHT_WARNING = 3;
    public const STYLE_HIGHLIGHT_NEUTRAL = 4;
    public const STYLE_HIGHLIGHT_REVIEW = 5;

    private string $outputPath;
    private string $tempDir;

    /** @var array<int, array{name:string,path:string,handle:resource,row:int}> */
    private array $sheets = [];

    private bool $closed = false;
    private bool $assembled = false;

    public function __construct(string $outputPath)
    {
        $this->outputPath = $outputPath;

        $baseDir = dirname($outputPath);
        if (!is_dir($baseDir) && !mkdir($baseDir, 0775, true) && !is_dir($baseDir)) {
            throw new RuntimeException('No se pudo crear el directorio temporal para la exportación de topes.');
        }

        $this->tempDir = rtrim($baseDir, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'xlsx_stream_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6));

        if (!mkdir($this->tempDir, 0775, true) && !is_dir($this->tempDir)) {
            throw new RuntimeException('No se pudo crear el directorio temporal del archivo XLSX.');
        }
    }

    public function addSheet(string $name, array $headers, array $widths = []): int
    {
        $this->assertOpen();

        $sheetIndex = count($this->sheets);
        $sheetName = $this->sanitizeSheetName($name, $sheetIndex + 1);
        $sheetPath = $this->tempDir . DIRECTORY_SEPARATOR . 'sheet' . ($sheetIndex + 1) . '.xml';
        $handle = fopen($sheetPath, 'wb');

        if ($handle === false) {
            throw new RuntimeException('No se pudo abrir el archivo temporal de la hoja XLSX.');
        }

        fwrite($handle, $this->sheetXmlStart($widths));

        $this->sheets[$sheetIndex] = [
            'name' => $sheetName,
            'path' => $sheetPath,
            'handle' => $handle,
            'row' => 0,
        ];

        $this->appendRow($sheetIndex, $headers, array_fill(1, count($headers), self::STYLE_HEADER));

        return $sheetIndex;
    }

    public function appendRow(int $sheetIndex, array $values, array $styleMap = []): void
    {
        $this->assertOpen();

        if (!array_key_exists($sheetIndex, $this->sheets)) {
            throw new RuntimeException('La hoja XLSX solicitada no existe.');
        }

        $this->sheets[$sheetIndex]['row']++;
        $rowNumber = $this->sheets[$sheetIndex]['row'];
        $xml = '<row r="' . $rowNumber . '">';

        foreach (array_values($values) as $offset => $value) {
            $columnIndex = $offset + 1;
            $styleIndex = (int) ($styleMap[$columnIndex] ?? self::STYLE_DEFAULT);
            $xml .= $this->buildCellXml($columnIndex, $rowNumber, $value, $styleIndex);
        }

        $xml .= '</row>';
        fwrite($this->sheets[$sheetIndex]['handle'], $xml);
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $exception = null;

        try {
            foreach ($this->sheets as $index => $sheet) {
                if (is_resource($sheet['handle'])) {
                    fwrite($sheet['handle'], '</sheetData></worksheet>');
                    fclose($sheet['handle']);
                    $this->sheets[$index]['handle'] = null;
                }
            }

            if (!$this->assembled) {
                $this->assembleWorkbook();
                $this->assembled = true;
            }
        } catch (\Throwable $e) {
            $exception = $e;
        } finally {
            $this->closed = true;
            $this->cleanup();
        }

        if ($exception !== null) {
            throw $exception;
        }
    }

    public function __destruct()
    {
        if (!$this->closed) {
            $this->cleanup();
        }
    }

    private function assembleWorkbook(): void
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('La extensión ZipArchive no está disponible para generar el XLSX.');
        }

        $zip = new ZipArchive();
        if ($zip->open($this->outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo XLSX de topes imponibles.');
        }

        try {
            $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
            $zip->addFromString('_rels/.rels', $this->rootRelsXml());
            $zip->addFromString('docProps/app.xml', $this->appPropertiesXml());
            $zip->addFromString('docProps/core.xml', $this->corePropertiesXml());
            $zip->addFromString('xl/workbook.xml', $this->workbookXml());
            $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
            $zip->addFromString('xl/styles.xml', $this->stylesXml());

            foreach ($this->sheets as $index => $sheet) {
                if (!$zip->addFile($sheet['path'], 'xl/worksheets/sheet' . ($index + 1) . '.xml')) {
                    throw new RuntimeException('No se pudo agregar la hoja ' . $sheet['name'] . ' al XLSX.');
                }
            }
        } finally {
            $zip->close();
        }
    }

    private function sheetXmlStart(array $widths = []): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>';

        if ($widths !== []) {
            $xml .= '<cols>';
            foreach (array_values($widths) as $offset => $width) {
                $columnIndex = $offset + 1;
                $widthValue = max(8, (float) $width);
                $xml .= '<col min="' . $columnIndex . '" max="' . $columnIndex . '" width="' . $this->formatNumber($widthValue) . '" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }

        return $xml . '<sheetData>';
    }

    private function buildCellXml(int $columnIndex, int $rowNumber, mixed $value, int $styleIndex = self::STYLE_DEFAULT): string
    {
        $coordinate = $this->columnLetter($columnIndex) . $rowNumber;
        $style = $styleIndex > self::STYLE_DEFAULT ? ' s="' . $styleIndex . '"' : '';

        if ($value === null) {
            return '<c r="' . $coordinate . '"' . $style . '/>';
        }

        if (is_bool($value)) {
            return '<c r="' . $coordinate . '" t="b"' . $style . '><v>' . ($value ? '1' : '0') . '</v></c>';
        }

        if (is_int($value) || is_float($value)) {
            return '<c r="' . $coordinate . '"' . $style . '><v>' . $this->formatNumber($value) . '</v></c>';
        }

        $text = $this->sanitizeText((string) $value);

        return '<c r="' . $coordinate . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">'
            . htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8')
            . '</t></is></c>';
    }

    private function sanitizeText(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';

        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }

        return $value;
    }

    private function sanitizeSheetName(string $name, int $fallbackIndex): string
    {
        $name = str_replace(['\\', '/', '*', '?', ':', '[', ']'], ' ', trim($name));
        $name = preg_replace('/\s+/u', ' ', $name) ?? '';
        $name = trim($name);

        if ($name === '') {
            $name = 'Hoja ' . $fallbackIndex;
        }

        if (function_exists('mb_substr')) {
            return mb_substr($name, 0, 31, 'UTF-8');
        }

        return substr($name, 0, 31);
    }

    private function contentTypesXml(): string
    {
        $sheetOverrides = '';
        foreach ($this->sheets as $index => $sheet) {
            $sheetOverrides .= '<Override PartName="/xl/worksheets/sheet' . ($index + 1) . '.xml" '
                . 'ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . $sheetOverrides
            . '</Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private function appPropertiesXml(): string
    {
        $titles = '';
        foreach ($this->sheets as $sheet) {
            $titles .= '<vt:lpstr>' . htmlspecialchars($sheet['name'], ENT_QUOTES | ENT_XML1, 'UTF-8') . '</vt:lpstr>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
            . 'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>SLEP Andalién Costa</Application>'
            . '<HeadingPairs><vt:vector size="2" baseType="variant">'
            . '<vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant>'
            . '<vt:variant><vt:i4>' . count($this->sheets) . '</vt:i4></vt:variant>'
            . '</vt:vector></HeadingPairs>'
            . '<TitlesOfParts><vt:vector size="' . count($this->sheets) . '" baseType="lpstr">'
            . $titles
            . '</vt:vector></TitlesOfParts>'
            . '</Properties>';
    }

    private function corePropertiesXml(): string
    {
        $now = date(DATE_ATOM);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            . 'xmlns:dc="http://purl.org/dc/elements/1.1/" '
            . 'xmlns:dcterms="http://purl.org/dc/terms/" '
            . 'xmlns:dcmitype="http://purl.org/dc/dcmitype/" '
            . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:creator>SLEP Andalién Costa</dc:creator>'
            . '<cp:lastModifiedBy>SLEP Andalién Costa</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified>'
            . '</cp:coreProperties>';
    }

    private function workbookXml(): string
    {
        $sheetsXml = '';
        foreach ($this->sheets as $index => $sheet) {
            $sheetsXml .= '<sheet name="' . htmlspecialchars($sheet['name'], ENT_QUOTES | ENT_XML1, 'UTF-8') . '" '
                . 'sheetId="' . ($index + 1) . '" r:id="rId' . ($index + 1) . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<bookViews><workbookView xWindow="0" yWindow="0" windowWidth="24000" windowHeight="12000"/></bookViews>'
            . '<sheets>' . $sheetsXml . '</sheets>'
            . '</workbook>';
    }

    private function workbookRelsXml(): string
    {
        $relationships = '';
        foreach ($this->sheets as $index => $sheet) {
            $relationships .= '<Relationship Id="rId' . ($index + 1) . '" '
                . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" '
                . 'Target="worksheets/sheet' . ($index + 1) . '.xml"/>';
        }

        $relationships .= '<Relationship Id="rId' . (count($this->sheets) + 1) . '" '
            . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" '
            . 'Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $relationships
            . '</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            . '<font><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
            . '</fonts>'
            . '<fills count="6">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF4CCCC"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFFFF2CC"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFE7E6E6"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFFCE5CD"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="6">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="0" fontId="0" fillId="2" borderId="0" xfId="0" applyFill="1"/>'
            . '<xf numFmtId="0" fontId="0" fillId="3" borderId="0" xfId="0" applyFill="1"/>'
            . '<xf numFmtId="0" fontId="0" fillId="4" borderId="0" xfId="0" applyFill="1"/>'
            . '<xf numFmtId="0" fontId="0" fillId="5" borderId="0" xfId="0" applyFill="1"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function columnLetter(int $columnIndex): string
    {
        $letter = '';

        while ($columnIndex > 0) {
            $columnIndex--;
            $letter = chr(65 + ($columnIndex % 26)) . $letter;
            $columnIndex = intdiv($columnIndex, 26);
        }

        return $letter;
    }

    private function formatNumber(int|float $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        $formatted = rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');

        return $formatted === '-0' ? '0' : $formatted;
    }

    private function assertOpen(): void
    {
        if ($this->closed) {
            throw new RuntimeException('El escritor XLSX ya fue cerrado.');
        }
    }

    private function cleanup(): void
    {
        foreach ($this->sheets as $sheet) {
            if (isset($sheet['handle']) && is_resource($sheet['handle'])) {
                fclose($sheet['handle']);
            }

            if (!empty($sheet['path']) && is_file($sheet['path'])) {
                @unlink($sheet['path']);
            }
        }

        if (is_dir($this->tempDir)) {
            @rmdir($this->tempDir);
        }
    }
}
