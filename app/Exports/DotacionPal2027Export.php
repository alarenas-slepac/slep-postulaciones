<?php

namespace App\Exports;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use ZipArchive;

/** Edita únicamente entradas y extensiones de rangos; conserva las fórmulas y objetos nativos. */
class DotacionPal2027Export
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    public function template(): string
    {
        return resource_path('templates/dotacion/resumen_dotacion_pal_2027.xlsx');
    }

    public function tablas(): array
    {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true)->setLoadSheetsOnly(['Tabla 65-35', 'Tabla 60-40']);
        $reader->setReadFilter(new class implements IReadFilter {
            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row <= 48 && strlen($columnAddress) === 1 && $columnAddress <= 'J';
            }
        });
        $book = $reader->load($this->template());
        try {
            $tablas = [];
            foreach (['65/35' => 'Tabla 65-35', '60/40' => 'Tabla 60-40'] as $proporcion => $name) {
                $sheet = $book->getSheetByName($name);
                // Es el mismo rango exacto de BUSCARV que utiliza la plantilla.
                for ($i = 4; $i <= 47; $i++) {
                    $aula = $sheet->getCell('A'.$i)->getCalculatedValue();
                    $lectivas = $sheet->getCell('B'.$i)->getCalculatedValue();
                    $noLectivas = $sheet->getCell('C'.$i)->getCalculatedValue();
                    if (! is_numeric($aula) || ! is_numeric($lectivas) || ! is_numeric($noLectivas)) { continue; }
                    $key = number_format((float) $aula, 4, '.', '');
                    // BUSCARV exacto toma la primera coincidencia.
                    $tablas[$proporcion][$key] ??= ['contrato' => (float) $lectivas + (float) $noLectivas];
                }
            }
            return $tablas;
        } finally { $book->disconnectWorksheets(); }
    }

    public function download(array $reporte, string $rbd)
    {
        // Se prepara antes de enviar cabeceras: un fallo no entrega un archivo parcial.
        $path = tempnam(sys_get_temp_dir(), 'sga-pal-');
        if ($path === false) { throw new \RuntimeException('No se pudo preparar la descarga PAL.'); }
        // tempnam crea el archivo con 0600 en Unix; cerrar antes de ZipArchive
        // permite el reemplazo atómico también en Windows.
        $limpiar = static function () use ($path): void { if (is_file($path)) { unlink($path); } };
        register_shutdown_function($limpiar);
        try { $this->guardar($reporte, $path); }
        catch (\Throwable $error) { $limpiar(); throw $error; }
        return response()->streamDownload(function () use ($path, $limpiar): void {
            try { readfile($path); }
            finally { $limpiar(); }
        }, 'borrador_pal_2027_rbd_'.preg_replace('/[^0-9]/', '', $rbd).'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store, max-age=0', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function guardar(array $reporte, string $path): void
    {
        if (count($reporte['docentes']) > 10000 || count($reporte['cursos']) > 1000) {
            throw ValidationException::withMessages(['pal' => 'La cantidad de filas supera el límite de esta descarga por establecimiento.']);
        }
        if (! copy($this->template(), $path)) { throw new \RuntimeException('No se pudo leer la plantilla PAL.'); }
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) { throw new \RuntimeException('No se pudo abrir el archivo PAL.'); }
        try {
            $partes = $this->partes($zip);
            $styles = $this->xml($zip, 'xl/styles.xml');
            $last = max(94, count($reporte['docentes']) + 2);
            $doc = $this->xml($zip, $partes['Info Funcionario (DOCENTE)']);
            $this->rellenar($doc, $reporte['docentes'], 3, 94, ['J','N','O']);
            $this->validaciones($doc, $last);
            $zip->addFromString($partes['Info Funcionario (DOCENTE)'], $doc->saveXML());
            $ee = $this->xml($zip, $partes['Info Establecimiento']);
            $this->rellenar($ee, $reporte['cursos'], 4, 34, ['G'], $styles);
            $zip->addFromString($partes['Info Establecimiento'], $ee->saveXML());
            $zip->addFromString('xl/styles.xml', $styles->saveXML());
            if ($last > 94) {
                $table = $this->xml($zip, 'xl/tables/table1.xml');
                $table->documentElement->setAttribute('ref', 'A1:S'.$last);
                foreach ($table->getElementsByTagNameNS(self::NS, 'autoFilter') as $filter) { $filter->setAttribute('ref', 'A1:S'.$last); }
                $zip->addFromString('xl/tables/table1.xml', $table->saveXML());
            }
            if ($last > 176) {
                $summary = $this->xml($zip, $partes['Resumen']);
                foreach ($summary->getElementsByTagNameNS(self::NS, 'f') as $formula) {
                    $formula->nodeValue = str_replace(["'Info Funcionario (DOCENTE)'!$".'A$3:$A$176', "'Info Funcionario (DOCENTE)'!$".'C$3:$C$176', "'Info Funcionario (DOCENTE)'!$".'G$3:$G$176'],
                        ["'Info Funcionario (DOCENTE)'!$".'A$3:$A$'.$last, "'Info Funcionario (DOCENTE)'!$".'C$3:$C$'.$last, "'Info Funcionario (DOCENTE)'!$".'G$3:$G$'.$last], $formula->textContent);
                }
                $zip->addFromString($partes['Resumen'], $summary->saveXML());
            }
        } finally { $zip->close(); }
    }

    private function partes(ZipArchive $zip): array
    {
        $rels = $this->xml($zip, 'xl/_rels/workbook.xml.rels');
        $targets = [];
        foreach ($rels->documentElement->childNodes as $rel) {
            if ($rel instanceof DOMElement) { $targets[$rel->getAttribute('Id')] = ltrim($rel->getAttribute('Target'), '/'); }
        }
        $result = [];
        foreach ($this->xml($zip, 'xl/workbook.xml')->getElementsByTagNameNS(self::NS, 'sheet') as $sheet) {
            $target = $targets[$sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id')];
            $result[$sheet->getAttribute('name')] = str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
        }
        return $result;
    }

    private function xml(ZipArchive $zip, string $part): DOMDocument
    {
        $bytes = $zip->getFromName($part);
        $doc = new DOMDocument;
        if ($bytes === false || ! $doc->loadXML($bytes, LIBXML_NONET)) { throw new \RuntimeException('La plantilla PAL no contiene una estructura válida.'); }
        return $doc;
    }

    private function rellenar(DOMDocument $doc, array $datos, int $inicio, int $originalFin, array $calculadas, ?DOMDocument $styles = null): void
    {
        $xpath = new DOMXPath($doc); $xpath->registerNamespace('m', self::NS);
        $sheetData = $xpath->query('//m:sheetData')->item(0);
        $filas = [];
        foreach ($sheetData->childNodes as $row) {
            if ($row instanceof DOMElement) { $filas[(int) $row->getAttribute('r')] = $row; }
        }
        $modelo = $filas[$originalFin];
        $wrapped = [];
        $fin = max($originalFin, $inicio + count($datos) - 1);
        for ($numero = $inicio; $numero <= $fin; $numero++) {
            $nuevo = $numero > $originalFin;
            $row = $nuevo ? $modelo->cloneNode(true) : $filas[$numero];
            $row->setAttribute('r', (string) $numero);
            if ($nuevo) {
                if (isset($filas[$numero])) { $sheetData->replaceChild($row, $filas[$numero]); }
                else {
                    $next = null;
                    foreach ($sheetData->childNodes as $candidate) {
                        if ($candidate instanceof DOMElement && (int) $candidate->getAttribute('r') > $numero) { $next = $candidate; break; }
                    }
                    $sheetData->insertBefore($row, $next);
                }
            }
            $entries = $datos[$numero - $inicio] ?? [];
            foreach ($row->childNodes as $cell) {
                if (! $cell instanceof DOMElement || $cell->localName !== 'c') { continue; }
                $column = preg_replace('/\d+/', '', $cell->getAttribute('r'));
                $cell->setAttribute('r', $column.$numero);
                if (in_array($column, $calculadas, true)) {
                    if ($nuevo && $column === 'G') {
                        while ($cell->firstChild) { $cell->removeChild($cell->firstChild); }
                        $cell->appendChild($doc->createElementNS(self::NS, 'f', '+E'.$numero.'*F'.$numero));
                    }
                    continue;
                }
                while ($cell->firstChild) { $cell->removeChild($cell->firstChild); }
                $cell->removeAttribute('t');
                $value = $entries[$column] ?? null;
                if ($value === null) { continue; }
                if (is_int($value) || is_float($value)) {
                    $cell->appendChild($doc->createElementNS(self::NS, 'v', (string) $value));
                } else {
                    $cell->setAttribute('t', 'inlineStr');
                    $is = $doc->createElementNS(self::NS, 'is');
                    $text = $doc->createElementNS(self::NS, 't');
                    $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', ' ', (string) $value);
                    if (mb_strlen($clean) > 32767) { $clean = mb_substr($clean, 0, 32720).' [Detalle abreviado; revisar SGA]'; }
                    $text->appendChild($doc->createTextNode($clean));
                    $is->appendChild($text); $cell->appendChild($is);
                    if ($column === 'S' || ($styles && in_array($column, ['A','C'], true))) {
                        $porLinea = $column === 'A' ? 13 : ($column === 'C' ? 25 : 50);
                        $height = min(409, max((float) $row->getAttribute('ht'), ceil(mb_strlen($clean) / $porLinea) * 14 + 6));
                        $row->setAttribute('ht', (string) $height); $row->setAttribute('customHeight', '1');
                        if ($styles) {
                            $id = (int) $cell->getAttribute('s');
                            if (! isset($wrapped[$id])) {
                                $xfs = $styles->getElementsByTagNameNS(self::NS, 'cellXfs')->item(0);
                                $style = $xfs->getElementsByTagNameNS(self::NS, 'xf')->item($id)->cloneNode(true);
                                $alignment = $style->getElementsByTagNameNS(self::NS, 'alignment')->item(0);
                                if (! $alignment) { $alignment = $styles->createElementNS(self::NS, 'alignment'); $style->appendChild($alignment); }
                                $alignment->setAttribute('wrapText', '1'); $style->setAttribute('applyAlignment', '1');
                                $wrapped[$id] = $xfs->getElementsByTagNameNS(self::NS, 'xf')->length;
                                $xfs->appendChild($style); $xfs->setAttribute('count', (string) ($wrapped[$id] + 1));
                            }
                            $cell->setAttribute('s', (string) $wrapped[$id]);
                        }
                    }
                }
            }
        }
        $dimension = $xpath->query('//m:dimension')->item(0);
        if ($fin > $originalFin) { $dimension->setAttribute('ref', 'A1:'.($inicio === 3 ? 'S' : 'H').max($fin, (int) preg_replace('/.*[A-Z]/', '', $dimension->getAttribute('ref')))); }
    }

    private function validaciones(DOMDocument $doc, int $last): void
    {
        foreach ($doc->getElementsByTagNameNS(self::NS, 'dataValidation') as $rule) {
            $range = $rule->getAttribute('sqref');
            if (preg_match('/^([DEGHM])3:\1\d+$/', $range, $m)) { $rule->setAttribute('sqref', $m[1].'3:'.$m[1].$last); }
        }
    }
}
