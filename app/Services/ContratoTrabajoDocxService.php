<?php

namespace App\Services;

use App\Models\SolicitudReemplazo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class ContratoTrabajoDocxService
{
    public function generateAndStore(SolicitudReemplazo $s, array $values, ?Carbon $fechaContrato = null): string
    {
        $fechaContrato ??= now();

        $templateRelative = 'templates/contrato_reemplazo_aaee.docx';

        // Plantilla:
        // 1) storage/app/templates/contrato_reemplazo_aaee.docx (editable por despliegue)
        // 2) resources/templates/contrato_reemplazo_aaee.docx (fallback incluido en el repo)
        if (!Storage::disk('local')->exists($templateRelative)) {
            $fallback = resource_path('templates/contrato_reemplazo_aaee.docx');

            if (!is_file($fallback)) {
                throw new \RuntimeException(
                    "No existe la plantilla DOCX. Copia el archivo a storage/app/{$templateRelative} o agrega el fallback en resources/templates/contrato_reemplazo_aaee.docx"
                );
            }

            Storage::disk('local')->makeDirectory('templates');
            Storage::disk('local')->put($templateRelative, file_get_contents($fallback));
        }

        $templatePath = Storage::disk('local')->path($templateRelative);

        $tmp = tempnam(sys_get_temp_dir(), 'ctr_');
        $tmpDocx = $tmp . '.docx';
        @unlink($tmp);
        copy($templatePath, $tmpDocx);

        $zip = new \ZipArchive();
        if ($zip->open($tmpDocx) !== true) {
            throw new \RuntimeException('No se pudo abrir el DOCX (ZipArchive).');
        }

        // Reemplazar marcadores «Campo» en TODOS los XML relevantes (document + headers + footers)
        // Nota: los placeholders en el pie suelen estar en word/footer*.xml
        $this->replaceMarkersInDocx($zip, $values, $fechaContrato);
        $zip->close();

        $outDir = "contratos-trabajo/solicitudes/{$s->id}";
        Storage::disk('local')->makeDirectory($outDir);

        $outName = "CONTRATO_TRABAJO_{$s->numero_solicitud}.docx";
        $outRelative = "{$outDir}/{$outName}";

        Storage::disk('local')->put($outRelative, file_get_contents($tmpDocx));
        @unlink($tmpDocx);

        return $outRelative;
    }

    private function replaceMarkersInDocx(\ZipArchive $zip, array $values, Carbon $fechaContrato): void
    {
        // Reemplazar marcadores «Campo» incluso cuando Word los parte en múltiples <w:t> (runs).
        // Aplicar en document.xml + headers + footers.
        $targets = ['word/document.xml'];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!is_string($name)) continue;
            if (preg_match('#^word/(header\d+|footer\d+)\.xml$#', $name)) {
                $targets[] = $name;
            }
        }
        $targets = array_values(array_unique($targets));

        foreach ($targets as $part) {
            $xml = $zip->getFromName($part);
            if ($xml === false) continue;
            // 0) Reemplazo para campos Word MERGEFIELD (si existen)
            $xml = $this->replaceMergeFields($xml, $values);

            // Primero: reemplazo simple (cuando no está partido)
            foreach ($values as $key => $value) {
                $token = '«' . $key . '»';
                $safe = htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
                $xml = str_replace($token, $safe, $xml);
            }

            // Segundo: reemplazo robusto sobre <w:t> (cuando está partido)
            $xml = $this->replaceGuillemetTokensByParagraph($xml, $values);

            // Ajuste de fecha SOLO en document.xml
            if ($part === 'word/document.xml') {
                $xml = $this->replaceFechaContratoEnParrafo($xml, $fechaContrato);
            }

            $zip->addFromString($part, $xml);
        }
    }

    private function replaceGuillemetTokensByParagraph(string $xml, array $values): string
    {
        // Armamos el map «Campo» => valor
        $repl = [];
        foreach ($values as $k => $v) {
            $repl['«' . $k . '»'] = htmlspecialchars((string) $v, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        }

        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        if (!@$dom->loadXML($xml)) {
            // fallback textual
            return strtr($xml, $repl);
        }

        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $paragraphs = $xp->query('//w:p');
        if (!$paragraphs) return $xml;

        foreach ($paragraphs as $p) {
            $tNodes = $xp->query('.//w:t', $p);
            if (!$tNodes || $tNodes->length === 0) continue;

            $plain = '';
            foreach ($tNodes as $t) {
                $plain .= $t->textContent;
            }

            $replaced = strtr($plain, $repl);
            if ($replaced === $plain) continue;

            // escribimos todo en el primer w:t y vaciamos el resto
            $tNodes->item(0)->nodeValue = $replaced;
            for ($i = 1; $i < $tNodes->length; $i++) {
                $tNodes->item($i)->nodeValue = '';
            }
        }

        return $dom->saveXML();
    }

    private function replaceFechaContratoEnParrafo(string $xml, Carbon $fecha): string
    {
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        if (!@$dom->loadXML($xml)) {
            return $xml;
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $parrafos = $xpath->query('//w:p');
        if (!$parrafos) return $xml;

        $fechaLarga = $this->fechaLargaEs($fecha);

        foreach ($parrafos as $p) {
            $tNodes = $xpath->query('.//w:t', $p);
            $plain = '';
            foreach ($tNodes as $t) {
                $plain .= $t->textContent;
            }

            if (!str_contains($plain, 'En Coronel') || !str_contains($plain, 'entre el Servicio Local')) {
                continue;
            }

            $newPlain = preg_replace(
                '/En\s+Coronel,\s+a\s+.*?,\s+entre\s+el\s+Servicio\s+Local/su',
                'En Coronel, a ' . $fechaLarga . ', entre el Servicio Local',
                $plain,
                1
            );

            if (!$newPlain || $newPlain === $plain) {
                continue;
            }

            // Mantener pPr y reemplazar el resto del contenido
            for ($child = $p->firstChild; $child !== null;) {
                $next = $child->nextSibling;
                if (!($child->localName === 'pPr' && $child->namespaceURI === 'http://schemas.openxmlformats.org/wordprocessingml/2006/main')) {
                    $p->removeChild($child);
                }
                $child = $next;
            }

            $wNs = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
            $r = $dom->createElementNS($wNs, 'w:r');
            $t = $dom->createElementNS($wNs, 'w:t');
            $t->appendChild($dom->createTextNode($newPlain));
            $r->appendChild($t);
            $p->appendChild($r);

            break;
        }

        return $dom->saveXML();
    }
    private function replaceMergeFields(string $xml, array $values): string
    {
        // Si no hay MERGEFIELD, salimos rápido
        if (!str_contains($xml, 'MERGEFIELD')) {
            return $xml;
        }

        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        if (!@$dom->loadXML($xml)) {
            return $xml;
        }

        foreach ($values as $k => $v) {
            $this->replaceSingleMergeField($dom, (string)$k, (string)$v);
        }

        return $dom->saveXML();
    }

    private function replaceSingleMergeField(\DOMDocument $dom, string $fieldName, string $value): void
    {
        $wNs = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('w', $wNs);

        $query = sprintf("//w:instrText[contains(., 'MERGEFIELD %s')]", $fieldName);
        $instrNodes = $xp->query($query);
        if (!$instrNodes || $instrNodes->length === 0) return;

        foreach ($instrNodes as $instr) {
            if (!$instr instanceof \DOMNode) continue;

            // 1) Encontrar el fldChar separate que viene después de este instrText
            $separate = $xp->query(
                "following::w:fldChar[@w:fldCharType='separate' or @fldCharType='separate'][1]",
                $instr
            )->item(0);

            if (!$separate instanceof \DOMElement) {
                continue;
            }

            // 2) Desde separate, recorrer nodos en orden hasta fldChar end y recolectar w:t
            $texts = [];
            $node = $this->nextNode($separate);

            while ($node) {
                // ¿llegamos al end?
                if ($node instanceof \DOMElement && $node->namespaceURI === $wNs && $node->localName === 'fldChar') {
                    $type = $node->getAttributeNS($wNs, 'fldCharType');
                    if ($type === '') $type = $node->getAttribute('fldCharType');
                    if ($type === '') $type = $node->getAttribute('w:fldCharType');

                    if ($type === 'end') {
                        break;
                    }
                }

                // recolectar texto visible del campo
                if ($node instanceof \DOMElement && $node->namespaceURI === $wNs && $node->localName === 't') {
                    $texts[] = $node;
                }

                $node = $this->nextNode($node);
            }

            if (count($texts) > 0) {
                $texts[0]->nodeValue = $value;
                for ($i = 1; $i < count($texts); $i++) {
                    $texts[$i]->nodeValue = '';
                }
            }
        }
    }

    /**
     * Siguiente nodo en orden de documento (DFS).
     */
    private function nextNode(\DOMNode $node): ?\DOMNode
    {
        if ($node->firstChild) return $node->firstChild;

        while ($node) {
            if ($node->nextSibling) return $node->nextSibling;
            $node = $node->parentNode;
        }

        return null;
    }

    private function fechaLargaEs(Carbon $fecha): string
    {
        $meses = [
            1 => 'enero',
            2 => 'febrero',
            3 => 'marzo',
            4 => 'abril',
            5 => 'mayo',
            6 => 'junio',
            7 => 'julio',
            8 => 'agosto',
            9 => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre',
        ];

        $d = (int) $fecha->format('j');
        $m = (int) $fecha->format('n');
        $y = (int) $fecha->format('Y');

        return "{$d} de " . ($meses[$m] ?? $fecha->format('F')) . " de {$y}";
    }
    private function formatRutCL(?string $rut): string
    {
        $rut = trim((string)$rut);
        if ($rut === '') return '';

        // Quitar todo excepto dígitos y K/k
        $clean = preg_replace('/[^0-9kK]/', '', $rut) ?? '';
        if ($clean === '') return '';

        $clean = strtoupper($clean);

        // Si viene sin DV, lo calculamos
        if (strlen($clean) >= 2 && preg_match('/^[0-9]+[0-9K]$/', $clean)) {
            $num = substr($clean, 0, -1);
            $dv  = substr($clean, -1);
            return ltrim($num, '0') . '-' . $dv;
        }

        // Caso: solo números (sin DV)
        if (preg_match('/^[0-9]+$/', $clean)) {
            $num = ltrim($clean, '0');
            if ($num === '') return '';
            $dv = $this->calcRutDv($num);
            return $num . '-' . $dv;
        }

        // Si no matchea, devuélvelo lo más limpio posible
        return $clean;
    }
}
