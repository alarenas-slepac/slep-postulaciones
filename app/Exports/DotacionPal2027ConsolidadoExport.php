<?php

namespace App\Exports;

use DOMDocument;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/** Un archivo para todo el proceso; escribe filas sin acumular las nóminas. */
class DotacionPal2027ConsolidadoExport extends DotacionPal2027Export
{
    public function descargar(iterable $reportes)
    {
        $path = $this->temporal();
        $limpiar = static function () use ($path): void { if (is_file($path)) { unlink($path); } };
        register_shutdown_function($limpiar);
        try { $this->guardarTodos($reportes, $path); }
        catch (\Throwable $error) { $limpiar(); throw $error; }
        return response()->streamDownload(function () use ($path, $limpiar): void {
            try { readfile($path); }
            finally { $limpiar(); }
        }, 'borrador_pal_2027_todos_establecimientos.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store, max-age=0', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function guardarTodos(iterable $reportes, string $path): array
    {
        $temporales = [];
        $zip = new ZipArchive;
        $abierto = false;
        $eeHandle = $docHandle = null;
        $exclusiones = null;
        try {
            if (! copy($this->template(), $path) || $zip->open($path) !== true) { throw new \RuntimeException('No se pudo abrir la plantilla PAL.'); }
            $abierto = true;
            $partes = $this->partes($zip);
            $ee = $this->esquema($zip, $partes['Info Establecimiento'], 4, 34);
            $docs = $this->esquema($zip, $partes['Info Funcionario (DOCENTE)'], 3, 94);
            $styles = $this->xml($zip, 'xl/styles.xml');
            $exclusiones = new DotacionPalExclusiones2027Export($styles);
            $eeRows = $temporales[] = $this->temporal();
            $docRows = $temporales[] = $this->temporal();
            $eeHandle = fopen($eeRows, 'wb'); $docHandle = fopen($docRows, 'wb');
            if (! $eeHandle || ! $docHandle) { throw new \RuntimeException('No se pudieron preparar las hojas PAL.'); }
            foreach ($ee['encabezados'] as $row) { $this->escribir($eeHandle, $ee['doc']->saveXML($row)); }
            foreach ($docs['encabezados'] as $row) { $this->escribir($docHandle, $docs['doc']->saveXML($row)); }
            $eeNumero = 4; $docNumero = 3; $cantidad = 0; $wrapped = []; $wrappedDocs = [];
            foreach ($reportes as $reporte) {
                $cantidad++;
                $exclusiones->agregar($reporte['excluidos'] ?? []);
                foreach ($reporte['cursos'] as $entry) {
                    $this->fila($eeHandle, $ee, $eeNumero++, $entry, ['G'], $styles, $wrapped);
                }
                foreach ($reporte['docentes'] as $entry) {
                    $this->fila($docHandle, $docs, $docNumero++, $entry, ['J','N','O'], $styles, $wrappedDocs);
                }
                if ($eeNumero > 10004 || $docNumero > 100003 || $cantidad > 10000) {
                    throw ValidationException::withMessages(['pal' => 'El consolidado supera el límite de 100.000 filas docentes, 10.000 niveles o 10.000 establecimientos. No se generó un archivo incompleto.']);
                }
            }
            if ($cantidad === 0) { throw ValidationException::withMessages(['pal' => 'No hay establecimientos participantes en Dotación para generar el PAL.']); }
            $eeDatos = $eeNumero - 4; $docDatos = $docNumero - 3;
            while ($eeNumero <= 34) { $this->fila($eeHandle, $ee, $eeNumero++, [], ['G'], $styles, $wrapped); }
            while ($docNumero <= 94) { $this->fila($docHandle, $docs, $docNumero++, [], ['J','N','O'], $styles, $wrappedDocs); }
            fclose($eeHandle); $eeHandle = null; fclose($docHandle); $docHandle = null;
            $eeLast = $eeNumero - 1; $docLast = $docNumero - 1;
            $eeSheet = $temporales[] = $this->temporal(); $docSheet = $temporales[] = $this->temporal();
            $this->componer($ee, $eeRows, $eeSheet, 'H', $eeLast);
            $this->validaciones($docs['doc'], $docLast);
            $this->componer($docs, $docRows, $docSheet, 'S', $docLast);
            if (! $zip->addFile($eeSheet, $partes['Info Establecimiento']) || ! $zip->addFile($docSheet, $partes['Info Funcionario (DOCENTE)'])) {
                throw new \RuntimeException('No se pudieron incorporar las hojas PAL.');
            }
            $this->reemplazar($zip, 'xl/styles.xml', $styles);
            $table = $this->xml($zip, 'xl/tables/table1.xml');
            $table->documentElement->setAttribute('ref', 'A1:S'.$docLast);
            foreach ($table->getElementsByTagNameNS(self::NS, 'autoFilter') as $filter) { $filter->setAttribute('ref', 'A1:S'.$docLast); }
            $this->reemplazar($zip, 'xl/tables/table1.xml', $table);
            $this->resumen($zip, $partes['Resumen'], $cantidad, $eeLast, $docLast);
            $exclusiones->incorporar($zip);
            if (! $zip->close()) { throw new \RuntimeException('No se pudo finalizar el consolidado PAL.'); }
            $abierto = false;
            return ['establecimientos' => $cantidad, 'niveles' => $eeDatos, 'filas_docentes' => $docDatos];
        } finally {
            if (is_resource($eeHandle)) { fclose($eeHandle); }
            if (is_resource($docHandle)) { fclose($docHandle); }
            if ($abierto) { $zip->close(); }
            $exclusiones?->cerrar();
            foreach ($temporales as $temporal) { if (is_file($temporal)) { unlink($temporal); } }
        }
    }

    private function temporal(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sga-pal-');
        if ($path === false) { throw new \RuntimeException('No se pudo crear un archivo temporal privado para PAL.'); }
        return $path;
    }

    /** Carga únicamente las filas modelo; descarta las miles de filas vacías de formato. */
    private function esquema(ZipArchive $zip, string $part, int $inicio, int $fin): array
    {
        $xml = $zip->getFromName($part);
        if ($xml === false || ! preg_match('/<sheetData\b[^>]*>/', $xml, $match, PREG_OFFSET_CAPTURE)) { throw new \RuntimeException('Falta una hoja del formato PAL.'); }
        $hasta = $match[0][1] + strlen($match[0][0]);
        $cierre = strpos($xml, '</sheetData>', $hasta);
        if ($cierre === false) { throw new \RuntimeException('La estructura de filas PAL no es válida.'); }
        $offset = $hasta; $modelos = '';
        while (preg_match('/<row\b[^>]*?\/>|<row\b[^>]*>.*?<\/row>/s', $xml, $row, PREG_OFFSET_CAPTURE, $offset)) {
            if ($row[0][1] >= $cierre) { break; }
            preg_match('/\br="(\d+)"/', $row[0][0], $numero);
            if ((int) $numero[1] > $fin) { break; }
            $modelos .= $row[0][0]; $offset = $row[0][1] + strlen($row[0][0]);
        }
        $doc = new DOMDocument;
        if (! $doc->loadXML(substr($xml, 0, $hasta).$modelos.substr($xml, $cierre), LIBXML_NONET)) { throw new \RuntimeException('No se pudieron leer las filas modelo PAL.'); }
        $rows = []; $headers = [];
        foreach ($doc->getElementsByTagNameNS(self::NS, 'row') as $row) {
            $n = (int) $row->getAttribute('r');
            if ($n < $inicio) { $headers[] = $row; } else { $rows[$n] = $row; }
        }
        return ['doc' => $doc, 'filas' => $rows, 'encabezados' => $headers, 'fin' => $fin];
    }

    private function fila($handle, array $esquema, int $numero, array $entry, array $calculadas, ?DOMDocument $styles, array &$wrapped): void
    {
        $row = $esquema['filas'][min($numero, $esquema['fin'])]->cloneNode(true);
        $row->setAttribute('r', (string) $numero);
        $this->rellenarFila($esquema['doc'], $row, $entry, $calculadas, $styles, $wrapped);
        $this->escribir($handle, $esquema['doc']->saveXML($row));
    }

    private function componer(array $esquema, string $body, string $path, string $column, int $last): void
    {
        $doc = $esquema['doc'];
        $sheetData = $doc->getElementsByTagNameNS(self::NS, 'sheetData')->item(0);
        while ($sheetData->firstChild) { $sheetData->removeChild($sheetData->firstChild); }
        $doc->getElementsByTagNameNS(self::NS, 'dimension')->item(0)->setAttribute('ref', 'A1:'.$column.$last);
        $xml = $doc->saveXML();
        $pos = strpos($xml, '<sheetData/>');
        if ($pos === false) { throw new \RuntimeException('No se pudo componer la hoja PAL.'); }
        $out = fopen($path, 'wb'); $in = fopen($body, 'rb');
        try {
            $this->escribir($out, substr($xml, 0, $pos).'<sheetData>');
            if (stream_copy_to_stream($in, $out) === false) { throw new \RuntimeException('No se pudieron escribir todas las filas PAL.'); }
            $this->escribir($out, '</sheetData>'.substr($xml, $pos + strlen('<sheetData/>')));
        } finally { if (is_resource($in)) { fclose($in); } if (is_resource($out)) { fclose($out); } }
    }

    private function resumen(ZipArchive $zip, string $part, int $cantidad, int $eeLast, int $docLast): void
    {
        $doc = $this->xml($zip, $part);
        $sheetData = $doc->getElementsByTagNameNS(self::NS, 'sheetData')->item(0);
        $rows = [];
        foreach ($sheetData->getElementsByTagNameNS(self::NS, 'row') as $row) { $rows[(int) $row->getAttribute('r')] = $row; }
        $last = max(14, $cantidad + 2);
        for ($n = 15; $n <= $last; $n++) {
            $row = $rows[14]->cloneNode(true); $row->setAttribute('r', (string) $n);
            foreach ($row->getElementsByTagNameNS(self::NS, 'c') as $cell) {
                $cell->setAttribute('r', preg_replace('/\d+/', '', $cell->getAttribute('r')).$n);
                foreach ($cell->getElementsByTagNameNS(self::NS, 'f') as $formula) {
                    $formula->nodeValue = preg_replace_callback('/\$A14(?!\d)/', fn () => '$A'.$n, $formula->textContent);
                    if ($formula->hasAttribute('ref')) { $formula->setAttribute('ref', $cell->getAttribute('r')); }
                }
            }
            $sheetData->appendChild($row);
        }
        $docEnd = max(176, $docLast);
        foreach ($doc->getElementsByTagNameNS(self::NS, 'c') as $cell) {
            if (preg_match('/(\d+)$/', $cell->getAttribute('r'), $fila) && (int) $fila[1] >= 3) {
                // Incluye los valores guardados del derrame A4:A8 del ejemplo;
                // no son entradas ni RBD vigentes del consolidado.
                foreach (iterator_to_array($cell->childNodes) as $child) {
                    if (in_array($child->localName, ['v','is'], true)) { $cell->removeChild($child); }
                }
                $cell->removeAttribute('t');
            }
            foreach ($cell->getElementsByTagNameNS(self::NS, 'f') as $formula) {
                if ($cell->getAttribute('r') === 'A3') {
                    // Decisión del usuario: incluir también RBD sin funcionarios.
                    $range = "'Info Establecimiento'!\$B\$4:\$B\$".$eeLast;
                    $formula->nodeValue = '_xlfn.UNIQUE(_xlfn._xlws.FILTER('.$range.','.$range.'<>""))';
                    $formula->setAttribute('ref', 'A3:A'.($cantidad + 2));
                } else {
                    $text = $formula->textContent;
                    $text = str_replace(['$A$176','$C$176','$G$176'], ['$A$'.$docEnd,'$C$'.$docEnd,'$G$'.$docEnd], $text);
                    foreach (['J','C','G'] as $col) {
                        $text = str_replace("'Info Funcionario (DOCENTE)'!\$".$col.':$'.$col,
                            "'Info Funcionario (DOCENTE)'!\$".$col.'$3:$'.$col.'$'.$docEnd, $text);
                    }
                    $formula->nodeValue = $text;
                }
            }
        }
        $doc->getElementsByTagNameNS(self::NS, 'dimension')->item(0)->setAttribute('ref', 'A1:S'.$last);
        $this->reemplazar($zip, $part, $doc);
    }

    private function reemplazar(ZipArchive $zip, string $part, DOMDocument $doc): void
    {
        if (! $zip->addFromString($part, $doc->saveXML())) { throw new \RuntimeException('No se pudo actualizar la estructura PAL.'); }
    }

    private function escribir($handle, string $text): void
    {
        if (! is_resource($handle) || fwrite($handle, $text) !== strlen($text)) { throw new \RuntimeException('No se pudo escribir el archivo PAL completo.'); }
    }
}
