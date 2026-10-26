<?php

namespace App\Exports;

use DOMDocument;
use DOMElement;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/** Hoja adicional escrita por tramos; nunca acumula la nómina del consolidado. */
class DotacionPalExclusiones2027Export
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private string $path;
    private $handle;
    private int $numero = 5;
    private array $estilos;

    public function __construct(DOMDocument $styles)
    {
        $path = tempnam(sys_get_temp_dir(), 'sga-pal-excl-');
        if ($path === false) { throw new \RuntimeException('No se pudo preparar el detalle de exclusiones PAL.'); }
        $this->path = $path;
        $this->handle = fopen($path, 'wb');
        if (! $this->handle) { $this->cerrar(); throw new \RuntimeException('No se pudo escribir el detalle de exclusiones PAL.'); }
        $this->estilos = $this->estilos($styles);
        $this->escribir('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="'.self::NS.'">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="4" topLeftCell="A5" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="30"/><cols>');
        foreach ([14, 35, 18, 40, 18, 45, 18, 100, 27, 12] as $i => $ancho) {
            $this->escribir('<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$ancho.'" customWidth="1"/>');
        }
        $this->escribir('</cols><sheetData>');
        $this->fila(1, ['Excluidos PAL 2027'], $this->estilos['titulo'], 30);
        $this->fila(2, ['Separar por base de referencia: cupos/plazas sin persona y asignaciones excedentes no se restan del padrón docente. Las horas incorporadas en J no se repiten como excluidas.'], $this->estilos['texto'], 44);
        $this->fila(4, ['RBD', 'Establecimiento', 'RUT', 'Nombre', 'Calidad contractual', 'Motivo', 'Horas excluidas', 'Detalle', 'Base de referencia', 'Año base'], $this->estilos['cabecera'], 40);
    }

    public function agregar(iterable $excluidos): void
    {
        foreach ($excluidos as $fila) {
            if ($this->numero > 100004) {
                throw ValidationException::withMessages(['pal' => 'El detalle de excluidos supera 100.000 filas. No se generó un archivo incompleto.']);
            }
            $horas = (float) ($fila['horas'] ?? 0);
            if (! is_finite($horas) || $horas < 0) { throw new \RuntimeException('Las horas excluidas del PAL no son válidas.'); }
            $valores = [$fila['rbd'] ?? '', $fila['establecimiento'] ?? '', $fila['rut'] ?? '', $fila['nombre'] ?? '',
                $fila['calidad'] ?? '', $fila['motivo'] ?? '', $horas, $fila['detalle'] ?? '', $fila['base'] ?? 'Contrato docente', (int) ($fila['anio_base'] ?? 2027)];
            $altura = min(180, max(30, ceil(mb_strlen((string) $valores[7]) / 95) * 15));
            $this->fila($this->numero++, $valores, $this->estilos['texto'], $altura);
        }
    }

    public function incorporar(ZipArchive $zip): void
    {
        $this->escribir('</sheetData><autoFilter ref="A4:J'.max(4, $this->numero - 1).'"/>'
            .'<mergeCells count="2"><mergeCell ref="A1:J1"/><mergeCell ref="A2:J2"/></mergeCells></worksheet>');
        fclose($this->handle); $this->handle = null;
        $workbook = $this->xml($zip, 'xl/workbook.xml');
        $rels = $this->xml($zip, 'xl/_rels/workbook.xml.rels');
        $types = $this->xml($zip, '[Content_Types].xml');
        $id = 1;
        foreach ($workbook->getElementsByTagNameNS(self::NS, 'sheet') as $sheet) { $id = max($id, (int) $sheet->getAttribute('sheetId') + 1); }
        while ($zip->locateName('xl/worksheets/sheet'.$id.'.xml') !== false) { $id++; }
        $rid = 'rIdPalExclusiones';
        $ids = [];
        foreach ($rels->documentElement->childNodes as $rel) { if ($rel instanceof DOMElement) { $ids[] = $rel->getAttribute('Id'); } }
        while (in_array($rid, $ids, true)) { $rid .= 'x'; }
        $sheet = $workbook->createElementNS(self::NS, 'sheet');
        $sheet->setAttribute('name', 'Excluidos PAL 2027'); $sheet->setAttribute('sheetId', (string) $id);
        $sheet->setAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'r:id', $rid);
        $workbook->getElementsByTagNameNS(self::NS, 'sheets')->item(0)->appendChild($sheet);
        $rel = $rels->createElementNS($rels->documentElement->namespaceURI, 'Relationship');
        $rel->setAttribute('Id', $rid); $rel->setAttribute('Type', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet');
        $rel->setAttribute('Target', 'worksheets/sheet'.$id.'.xml'); $rels->documentElement->appendChild($rel);
        $type = $types->createElementNS($types->documentElement->namespaceURI, 'Override');
        $type->setAttribute('PartName', '/xl/worksheets/sheet'.$id.'.xml');
        $type->setAttribute('ContentType', 'application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml'); $types->documentElement->appendChild($type);
        foreach (['xl/workbook.xml' => $workbook, 'xl/_rels/workbook.xml.rels' => $rels, '[Content_Types].xml' => $types] as $part => $doc) {
            if (! $zip->addFromString($part, $doc->saveXML())) { throw new \RuntimeException('No se pudo registrar la hoja de excluidos PAL.'); }
        }
        if (! $zip->addFile($this->path, 'xl/worksheets/sheet'.$id.'.xml')) { throw new \RuntimeException('No se pudo incorporar la hoja de excluidos PAL.'); }
    }

    /** Llamar después de cerrar ZipArchive, que conserva la referencia al temporal. */
    public function cerrar(): void
    {
        if (is_resource($this->handle)) { fclose($this->handle); $this->handle = null; }
        if (isset($this->path) && is_file($this->path)) { unlink($this->path); }
    }

    private function fila(int $numero, array $valores, int $estilo, float $altura): void
    {
        $xml = '<row r="'.$numero.'" ht="'.$altura.'" customHeight="1">';
        foreach ($valores as $i => $valor) {
            $ref = chr(65 + $i).$numero;
            $style = $numero >= 5 && $i === 6 ? $this->estilos['horas'] : $estilo;
            $xml .= '<c r="'.$ref.'" s="'.$style.'"';
            if (is_int($valor) || is_float($valor)) { $xml .= '><v>'.$valor.'</v></c>'; }
            else {
                $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', ' ', (string) $valor);
                if (mb_strlen($texto) > 32767) { $texto = mb_substr($texto, 0, 32720).' [Detalle abreviado; revisar SGA]'; }
                $xml .= ' t="inlineStr"><is><t xml:space="preserve">'.htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
            }
        }
        $this->escribir($xml.'</row>');
    }

    private function estilos(DOMDocument $doc): array
    {
        $fonts = $doc->getElementsByTagNameNS(self::NS, 'fonts')->item(0);
        $font = $doc->createElementNS(self::NS, 'font'); $font->appendChild($doc->createElementNS(self::NS, 'b'));
        $size = $doc->createElementNS(self::NS, 'sz'); $size->setAttribute('val', '11'); $font->appendChild($size);
        $color = $doc->createElementNS(self::NS, 'color'); $color->setAttribute('rgb', 'FFFFFFFF'); $font->appendChild($color);
        $fontId = $fonts->childElementCount; $fonts->appendChild($font); $fonts->setAttribute('count', (string) $fonts->childElementCount);
        $fills = $doc->getElementsByTagNameNS(self::NS, 'fills')->item(0);
        $fill = $doc->createElementNS(self::NS, 'fill'); $pattern = $doc->createElementNS(self::NS, 'patternFill'); $pattern->setAttribute('patternType', 'solid');
        $color = $doc->createElementNS(self::NS, 'fgColor'); $color->setAttribute('rgb', 'FF17365D'); $pattern->appendChild($color); $fill->appendChild($pattern);
        $fillId = $fills->childElementCount; $fills->appendChild($fill); $fills->setAttribute('count', (string) $fills->childElementCount);
        $xfs = $doc->getElementsByTagNameNS(self::NS, 'cellXfs')->item(0); $ids = [];
        foreach (['titulo', 'cabecera', 'texto', 'horas'] as $tipo) {
            $xf = $doc->createElementNS(self::NS, 'xf');
            foreach (['numFmtId' => $tipo === 'horas' ? 4 : 0, 'fontId' => in_array($tipo, ['titulo', 'cabecera']) ? $fontId : 0,
                'fillId' => in_array($tipo, ['titulo', 'cabecera']) ? $fillId : 0, 'borderId' => 0, 'xfId' => 0, 'applyAlignment' => 1] as $k => $v) { $xf->setAttribute($k, (string) $v); }
            $xf->setAttribute('applyNumberFormat', '1'); $xf->setAttribute('applyFont', '1'); $xf->setAttribute('applyFill', '1');
            $a = $doc->createElementNS(self::NS, 'alignment'); $a->setAttribute('vertical', 'top'); $a->setAttribute('wrapText', '1'); $xf->appendChild($a);
            $ids[$tipo] = $xfs->childElementCount; $xfs->appendChild($xf);
        }
        $xfs->setAttribute('count', (string) $xfs->childElementCount);
        return $ids;
    }

    private function xml(ZipArchive $zip, string $part): DOMDocument
    {
        $doc = new DOMDocument; $bytes = $zip->getFromName($part);
        if ($bytes === false || ! $doc->loadXML($bytes, LIBXML_NONET)) { throw new \RuntimeException('La estructura PAL no es válida.'); }
        return $doc;
    }

    private function escribir(string $text): void
    {
        if (! is_resource($this->handle) || fwrite($this->handle, $text) !== strlen($text)) { throw new \RuntimeException('No se pudo escribir el detalle PAL completo.'); }
    }
}
