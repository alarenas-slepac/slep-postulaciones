<?php

namespace App\Services;

use App\Models\Tramite;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class ResolucionBieniosDocxService
{
    public function generateAndStore(Tramite $tramite): string
    {
        $templateRelative = 'templates/rex_reconocimiento_bienios.docx';

        if (!Storage::disk('local')->exists($templateRelative)) {
            $fallback = resource_path('templates/rex_reconocimiento_bienios.docx');
            if (!is_file($fallback)) {
                throw new \RuntimeException('No existe la plantilla DOCX de resolución de bienios.');
            }
            Storage::disk('local')->makeDirectory('templates');
            Storage::disk('local')->put($templateRelative, file_get_contents($fallback));
        }

        $templatePath = Storage::disk('local')->path($templateRelative);
        $tmp = tempnam(sys_get_temp_dir(), 'rex_');
        $tmpDocx = $tmp . '.docx';
        @unlink($tmp);
        copy($templatePath, $tmpDocx);

        $zip = new \ZipArchive();
        if ($zip->open($tmpDocx) !== true) {
            throw new \RuntimeException('No se pudo abrir el DOCX de resolución (ZipArchive).');
        }

        $this->replaceInDocx($zip, $tramite);
        $zip->close();

        $outDir = "tramites/{$tramite->id}/resolucion-bienios";
        Storage::disk('local')->makeDirectory($outDir);
        $outName = 'REX_RECONOCIMIENTO_BIENIOS_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $tramite->rut_snapshot) . '.docx';
        $relative = $outDir . '/' . $outName;
        Storage::disk('local')->put($relative, file_get_contents($tmpDocx));
        @unlink($tmpDocx);

        return $relative;
    }

    private function replaceInDocx(\ZipArchive $zip, Tramite $tramite): void
    {
        $targets = ['word/document.xml'];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (is_string($name) && preg_match('#^word/(header\d+|footer\d+)\.xml$#', $name)) {
                $targets[] = $name;
            }
        }

        foreach (array_unique($targets) as $part) {
            $xml = $zip->getFromName($part);
            if ($xml === false) {
                continue;
            }
            if ($part === 'word/document.xml') {
                $xml = $this->replaceDocumentParagraphs($xml, $tramite);
            }
            $xml = $this->replaceCommonStrings($xml, $tramite);
            $zip->addFromString($part, $xml);
        }
    }

    private function replaceCommonStrings(string $xml, Tramite $tramite): string
    {
        $summary = $tramite->calculo_periodos_resumen;
        $duration = Tramite::formatDurationText((array) ($summary['duracion'] ?? []));
        $bienios = (int) ($summary['bienios'] ?? 0);
        $run = (string) $tramite->rut_snapshot;
        $name = (string) $tramite->nombre_completo_snapshot;
        $requestDate = optional($tramite->enviado_at)->format('d \d\e F \d\e Y') ?: now()->translatedFormat('d \d\e F \d\e Y');

        $pairs = [
            'ROBERTO CARLOS TOLOZA TOLOZA' => $name,
            '18.301.968-6' => $run,
            '3 años, 5 meses y 12 días' => $duration,
            '1 bienio' => $bienios . ' bienio' . ($bienios === 1 ? '' : 's'),
            '10 de diciembre de 2025' => $requestDate,
        ];

        foreach ($pairs as $search => $replace) {
            $xml = str_replace($search, htmlspecialchars((string) $replace, ENT_XML1 | ENT_COMPAT, 'UTF-8'), $xml);
        }

        return $xml;
    }

    private function replaceDocumentParagraphs(string $xml, Tramite $tramite): string
    {
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        if (!@$dom->loadXML($xml)) {
            return $xml;
        }
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $paragraphs = $xp->query('//w:p');
        if (!$paragraphs) {
            return $xml;
        }

        $summary = $tramite->calculo_periodos_resumen;
        $documentsText = $this->buildDocumentsText($tramite);
        $requestDate = optional($tramite->enviado_at)?->format('d-m-Y') ?: now()->format('d-m-Y');
        $durationText = Tramite::formatDurationText((array) ($summary['duracion'] ?? []));
        $bienios = (int) ($summary['bienios'] ?? 0);
        $requestDateLong = optional($tramite->enviado_at)?->translatedFormat('d \d\e F \d\e Y') ?: now()->translatedFormat('d \d\e F \d\e Y');

        foreach ($paragraphs as $p) {
            $plain = '';
            foreach ($xp->query('.//w:t', $p) as $t) {
                $plain .= $t->textContent;
            }
            $plainTrim = preg_replace('/\s+/u', ' ', trim($plain));
            if ($plainTrim === '') {
                continue;
            }

            $replacement = null;
            if (str_contains($plainTrim, 'RECONOCE BIENIOS A FUNCIONARIO')) {
                $replacement = 'RECONOCE BIENIOS A FUNCIONARIO ' . (string) $tramite->nombre_completo_snapshot;
            } elseif (str_contains($plainTrim, '13° Que, en relación a lo anterior')) {
                $replacement = '13° Que, en relación a lo anterior, se deja expresa constancia de que, para efectos de determinar su experiencia profesional docente, se consideró además del tiempo trabajado en nuestro servicio, la documentación provista por el profesional de la educación, ' . (string) $tramite->nombre_completo_snapshot . ', RUN: ' . (string) $tramite->rut_snapshot . ', a partir de la cual es posible acreditar periodos de servicio docente, correspondientes a: ' . $documentsText;
            } elseif (preg_match('/^PRIMERO:/u', $plainTrim)) {
                $replacement = 'PRIMERO: Reconocimiento por el cumplimiento de ' . $durationText . ' de experiencia cumplidos al ' . $requestDate . ', que equivalen a ' . $bienios . ' bienio' . ($bienios === 1 ? '' : 's') . '.';
            } elseif (preg_match('/^SEGUNDO:/u', $plainTrim)) {
                $replacement = 'SEGUNDO: Páguese la cantidad de ' . $bienios . ' bienio' . ($bienios === 1 ? '' : 's') . ' a partir del día ' . $requestDateLong . ', fecha en que el profesional de la educación solicitó el reconocimiento.';
            }

            if ($replacement !== null) {
                $this->replaceParagraphText($dom, $p, $replacement);
            }
        }

        return $dom->saveXML();
    }

    private function buildDocumentsText(Tramite $tramite): string
    {
        $items = collect(data_get($tramite->rex_data, 'documentos', []));
        if ($items->isEmpty()) {
            $items = $tramite->calculo_periodos_blocks_collection->map(function ($block) {
                return [
                    'tipo_documento' => data_get($block, 'documento_label', 'Documento'),
                    'documento_nombre' => data_get($block, 'documento_nombre', 'Documento'),
                    'fecha_documento' => data_get($block, 'documento_fecha', ''),
                ];
            });
        }

        return $items->values()->map(function ($item, $index) {
            $letter = chr(97 + $index);
            $tipo = trim((string) data_get($item, 'tipo_documento', data_get($item, 'documento_label', 'Documento')));
            $nombre = trim((string) data_get($item, 'documento_nombre', ''));
            $fecha = trim((string) data_get($item, 'fecha_documento', ''));
            $desc = $tipo;
            if ($nombre !== '' && stripos($nombre, $tipo) === false) {
                $desc .= ' de ' . $nombre;
            }
            if ($fecha !== '') {
                $desc .= ', fecha ' . $fecha;
            }
            return $letter . '. ' . $desc . '.';
        })->implode(' ');
    }

    private function replaceParagraphText(\DOMDocument $dom, \DOMElement $p, string $text): void
    {
        $wNs = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        for ($child = $p->firstChild; $child !== null;) {
            $next = $child->nextSibling;
            if (!($child->localName === 'pPr' && $child->namespaceURI === $wNs)) {
                $p->removeChild($child);
            }
            $child = $next;
        }
        $r = $dom->createElementNS($wNs, 'w:r');
        $t = $dom->createElementNS($wNs, 'w:t');
        if (preg_match('/^\s|\s$/u', $text)) {
            $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
        }
        $t->appendChild($dom->createTextNode($text));
        $r->appendChild($t);
        $p->appendChild($r);
    }
}
