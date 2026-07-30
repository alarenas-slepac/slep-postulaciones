<?php

namespace App\Services;

use App\Models\Tramite;
use App\Models\TramiteDocumento;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class ResolucionReconocimientoBieniosService
{
    public function generateAndStore(Tramite $tramite, string|Carbon|null $recognitionDate = null): array
    {
        $tramite->loadMissing(['documentos', 'user']);

        $data = $this->buildData($tramite, $recognitionDate);

        $resourceTemplatePath = resource_path('templates/resolucion_reconocimiento_bienios_base.docx');
        $templateRelative = 'templates/resolucion_reconocimiento_bienios_base.docx';

        if (is_file($resourceTemplatePath)) {
            $templatePath = $resourceTemplatePath;
        } elseif (Storage::disk('local')->exists($templateRelative)) {
            $templatePath = Storage::disk('local')->path($templateRelative);
        } else {
            throw new \RuntimeException('No existe la plantilla base de Resolución de Reconocimiento de Bienios.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'rex_');
        $tmpDocx = $tmp . '.docx';
        @unlink($tmp);
        copy($templatePath, $tmpDocx);

        $zip = new ZipArchive();
        if ($zip->open($tmpDocx) !== true) {
            throw new \RuntimeException('No se pudo abrir la plantilla DOCX de la resolución.');
        }

        $documentXml = $zip->getFromName('word/document.xml');
        if ($documentXml === false) {
            $zip->close();
            throw new \RuntimeException('No se encontró word/document.xml dentro de la plantilla.');
        }

        $patched = $this->patchDocumentXml($documentXml, $data);
        $zip->addFromString('word/document.xml', $patched);
        $zip->close();

        $outDir = "tramites/{$tramite->id}/resolucion-bienios";
        Storage::disk('local')->makeDirectory($outDir);
        $filename = 'RESOLUCION_RECONOCIMIENTO_BIENIOS_' . $tramite->id . '.docx';
        $relativePath = $outDir . '/' . $filename;
        Storage::disk('local')->put($relativePath, file_get_contents($tmpDocx));
        @unlink($tmpDocx);

        return [
            'path' => $relativePath,
            'data' => $data,
        ];
    }

    public function buildData(Tramite $tramite, string|Carbon|null $recognitionDate = null): array
    {
        $tramite->loadMissing(['documentos', 'user']);
        $summary = $tramite->calculo_periodos_resumen;
        $periods = $tramite->calculo_periodos_flattened_collection;
        $documents = $this->buildDocumentItems($tramite, $periods);
        $referenceDate = $this->resolveRecognitionDate($tramite, $recognitionDate);
        $seniorityDate = $this->resolveSeniorityDate($summary, $referenceDate);
        $displayName = trim((string) ($tramite->nombre_completo_snapshot ?: $tramite->user?->nombre_completo ?: $tramite->user?->email ?: 'PROFESIONAL DE LA EDUCACIÓN'));

        return [
            'tramite_id' => $tramite->id,
            'nombre' => $displayName,
            'rut' => (string) ($tramite->rut_snapshot ?: ''),
            'fecha_emision_corta' => now()->format('d/m/Y'),
            'fecha_emision_larga' => $this->fechaLargaEs(now()),
            'fecha_reconocimiento' => $referenceDate->toDateString(),
            'fecha_reconocimiento_larga' => $this->fechaLargaEs($referenceDate),
            'fecha_antiguedad' => $seniorityDate?->toDateString(),
            'fecha_antiguedad_corta' => $seniorityDate?->format('d/m/Y'),
            'fecha_antiguedad_larga' => $seniorityDate ? $this->fechaLargaEs($seniorityDate) : null,
            'resumen' => $summary,
            'periodos' => $periods->values()->all(),
            'documentos' => $documents->values()->all(),
        ];
    }

    private function resolveRecognitionDate(Tramite $tramite, string|Carbon|null $recognitionDate = null): Carbon
    {
        if ($recognitionDate instanceof Carbon) {
            return $recognitionDate->copy()->startOfDay();
        }

        if (is_string($recognitionDate) && trim($recognitionDate) !== '') {
            return Carbon::parse($recognitionDate)->startOfDay();
        }

        if ($tramite->rex_fecha_reconocimiento) {
            return Carbon::parse($tramite->rex_fecha_reconocimiento)->startOfDay();
        }

        if ($tramite->enviado_at) {
            return $tramite->enviado_at->copy()->startOfDay();
        }

        return now()->startOfDay();
    }

    private function resolveSeniorityDate(array $summary, Carbon $referenceDate): ?Carbon
    {
        $duration = (array) data_get($summary, 'duracion', []);
        $years = max((int) data_get($duration, 'years', 0), 0);
        $months = max((int) data_get($duration, 'months', 0), 0);
        $days = max((int) data_get($duration, 'days', 0), 0);

        return $referenceDate->copy()
            ->subYears($years)
            ->subMonths($months)
            ->subDays($days)
            ->startOfDay();
    }

    private function buildDocumentItems(Tramite $tramite, Collection $periods): Collection
    {
        $documentConfig = (array) data_get(config('tramites.tipos'), $tramite->tipo . '.documentos', []);
        $documentOrder = array_values(array_keys($documentConfig));

        return $tramite->documentos
            ->filter(fn ($documento) => (string) $documento->estado_revision === 'aprobado')
            ->sortBy(function (TramiteDocumento $documento) use ($documentOrder) {
                $typeOrder = array_search((string) $documento->tipo_documento, $documentOrder, true);
                $typeOrder = $typeOrder === false ? 999 : $typeOrder;

                return sprintf('%05d-%010d', $typeOrder, (int) $documento->id);
            })
            ->values()
            ->map(function (TramiteDocumento $documento, $index) {
                $documentDate = $this->resolveDocumentDate($documento);
                $metadata = (array) data_get($documento->captura_payload, 'document_metadata', []);
                $label = $this->resolveDocumentLabel($documento, $metadata);
                $issuer = $this->resolveDocumentIssuer($documento, $metadata);
                $documentNumber = trim((string) data_get($metadata, 'document_number', ''));
                $itemText = $label;

                if ($documentNumber !== '' && !preg_match('/n[°ºo]?\s*' . preg_quote($documentNumber, '/') . '/iu', $itemText)) {
                    $itemText .= ' N° ' . $documentNumber;
                }

                if ($issuer !== null && $issuer !== '') {
                    $itemText .= ', emitido por ' . $issuer;
                }

                if ($documentDate !== '') {
                    $itemText .= ', fecha ' . $this->formatDate($documentDate) . '.';
                } else {
                    $itemText .= ', fecha no detectada.';
                }

                return [
                    'letra' => chr(97 + $index),
                    'documento_id' => $documento->id,
                    'tipo' => $label,
                    'nombre' => trim((string) ($documento->original_name ?: '')),
                    'emisor' => $issuer,
                    'numero_documento' => $documentNumber,
                    'fecha_documento' => $documentDate,
                    'texto' => $itemText,
                ];
            })
            ->values();
    }

    private function resolveDocumentLabel(TramiteDocumento $documento, array $metadata = []): string
    {
        $label = trim((string) data_get($metadata, 'detected_label', ''));

        return $label !== '' ? $label : trim((string) ($documento->tipo_documento_label ?: 'Documento'));
    }

    private function resolveDocumentIssuer(TramiteDocumento $documento, array $metadata = []): ?string
    {
        if ((string) $documento->tipo_documento === 'carta_reconocimiento_director_ejecutivo') {
            return null;
        }

        $issuer = trim((string) data_get($metadata, 'issuer_name', ''));

        return $issuer !== '' ? $issuer : null;
    }

    private function resolveDocumentDate(TramiteDocumento $documento): string
    {
        $dateAnalysis = (array) data_get($documento->captura_payload, 'date_analysis', []);
        $documentDate = (string) (data_get($dateAnalysis, 'document_date') ?: '');
        if ($documentDate !== '') {
            return substr($documentDate, 0, 10);
        }

        $certificationDate = (string) data_get($dateAnalysis, 'certification_date', '');
        if ($certificationDate !== '') {
            return substr($certificationDate, 0, 10);
        }

        if ($documento->fecha_termino) {
            return $documento->fecha_termino->format('Y-m-d');
        }

        if ($documento->fecha_inicio) {
            return $documento->fecha_inicio->format('Y-m-d');
        }

        return '';
    }

    private function patchDocumentXml(string $xml, array $data): string
    {
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        if (!@$dom->loadXML($xml)) {
            throw new \RuntimeException('No fue posible procesar el XML de la plantilla DOCX.');
        }

        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $paragraphs = $xp->query('//w:body/w:p');
        if (!$paragraphs) {
            return $xml;
        }

        $replaceMap = [
            'RECONOCE BIENIOS A FUNCIONARIO' => 'RECONOCE BIENIOS A FUNCIONARIO ' . mb_strtoupper((string) $data['nombre']),
            'Coronel,' => 'Coronel, ' . $data['fecha_emision_larga'],
            '13° Que, en relación a lo anterior,' => '13° Que, en relación a lo anterior, se deja expresa constancia de que, para efectos de determinar su experiencia profesional docente, se consideró además del tiempo trabajado en nuestro servicio, la documentación provista por el profesional de la educación, ' . $data['nombre'] . ', RUN: ' . $data['rut'] . ', a partir de la cual es posible acreditar periodos de servicio docente, correspondientes a:',
            'RECONÓZCASE, conforme la revisión de la documentación del profesional de la educación' => 'RECONÓZCASE, conforme la revisión de la documentación del profesional de la educación ' . $data['nombre'] . ', RUN: ' . $data['rut'] . ':',
            'PRIMERO: Reconocimiento por el cumplimiento de' => $this->buildPrimeroText($data),
            'SEGUNDO: Páguese la cantidad de' => $this->buildSegundoText($data),
        ];

        $paragraph13 = null;
        $paragraph14 = null;
        $listTemplate = null;

        foreach ($paragraphs as $paragraph) {
            $plain = $this->paragraphText($xp, $paragraph);
            $normalized = $this->normalizeText($plain);

            foreach ($replaceMap as $startsWith => $replacement) {
                if (Str::startsWith($normalized, $this->normalizeText($startsWith))) {
                    $this->setParagraphText($dom, $paragraph, $replacement);
                    break;
                }
            }

            if ($paragraph13 === null && $this->startsWithNumeral($normalized, 13)) {
                $paragraph13 = $paragraph;
                continue;
            }

            if ($paragraph13 !== null && $paragraph14 === null) {
                if ($this->startsWithNumeral($normalized, 14)) {
                    $paragraph14 = $paragraph;
                    continue;
                }

                if ($listTemplate === null && $normalized !== '') {
                    $listTemplate = $paragraph;
                }
            }
        }

        if ($paragraph13 && $paragraph14) {
            $parent = $paragraph13->parentNode;
            $current = $paragraph13->nextSibling;

            while ($current && $current !== $paragraph14) {
                $next = $current->nextSibling;
                $parent->removeChild($current);
                $current = $next;
            }

            $items = collect((array) ($data['documentos'] ?? []))
                ->map(fn ($item) => (string) data_get($item, 'texto', ''))
                ->filter(fn ($value) => trim($value) !== '')
                ->values();

            if ($items->isEmpty()) {
                $items = collect(['Documento aprobado del expediente, fecha no detectada.']);
            }

            $baseParagraph = $listTemplate ?: $paragraph13;
            foreach ($items as $text) {
                $clone = $baseParagraph->cloneNode(true);
                $this->setParagraphText($dom, $clone, $text);
                $parent->insertBefore($clone, $paragraph14);
            }
        }

        return $dom->saveXML();
    }

    private function startsWithNumeral(string $normalized, int $numeral): bool
    {
        return (bool) preg_match('/^' . preg_quote((string) $numeral, '/') . '\D*que\b/u', $normalized);
    }

    private function buildPrimeroText(array $data): string
    {
        $duration = (array) data_get($data, 'resumen.duracion', []);
        $bienios = (int) data_get($data, 'resumen.bienios', 0);

        return sprintf(
            'PRIMERO: Reconocimiento por el cumplimiento de %d año%s, %d mes%s y %d día%s de experiencia cumplidos al %s, que equivalen a %d bienio%s.',
            (int) data_get($duration, 'years', 0),
            (int) data_get($duration, 'years', 0) === 1 ? '' : 's',
            (int) data_get($duration, 'months', 0),
            (int) data_get($duration, 'months', 0) === 1 ? '' : 'es',
            (int) data_get($duration, 'days', 0),
            (int) data_get($duration, 'days', 0) === 1 ? '' : 's',
            $this->fechaLargaEs(Carbon::parse((string) data_get($data, 'fecha_reconocimiento'))),
            $bienios,
            $bienios === 1 ? '' : 's'
        );
    }

    private function buildSegundoText(array $data): string
    {
        $bienios = (int) data_get($data, 'resumen.bienios', 0);

        return sprintf(
            'SEGUNDO: Páguese la cantidad de %d bienio%s a partir del día %s, fecha en que el profesional de la educación solicitó el reconocimiento.',
            $bienios,
            $bienios === 1 ? '' : 's',
            $this->fechaLargaEs(Carbon::parse((string) data_get($data, 'fecha_reconocimiento')))
        );
    }

    private function setParagraphText(\DOMDocument $dom, \DOMNode $paragraph, string $text): void
    {
        $textNodes = [];
        $this->collectTextNodes($paragraph, $textNodes);

        if ($textNodes === []) {
            $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
            $run = $dom->createElementNS($ns, 'w:r');
            $t = $dom->createElementNS($ns, 'w:t');
            $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
            $t->appendChild($dom->createTextNode($text));
            $run->appendChild($t);
            $paragraph->appendChild($run);

            return;
        }

        $remaining = $text;
        $lastIndex = count($textNodes) - 1;

        foreach ($textNodes as $index => $node) {
            $originalLength = mb_strlen($node->textContent ?? '');

            if ($index === $lastIndex) {
                $chunk = $remaining;
                $remaining = '';
            } elseif ($remaining === '') {
                $chunk = '';
            } else {
                $take = max($originalLength, 1);
                $chunk = mb_substr($remaining, 0, $take);
                $remaining = mb_substr($remaining, mb_strlen($chunk));
            }

            while ($node->firstChild) {
                $node->removeChild($node->firstChild);
            }

            $node->appendChild($dom->createTextNode($chunk));

            if ($chunk === '' || preg_match('/^\s|\s$|\s{2,}/u', $chunk)) {
                $node->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
            } else {
                $node->removeAttributeNS('http://www.w3.org/XML/1998/namespace', 'space');
            }
        }
    }

    private function collectTextNodes(\DOMNode $node, array &$textNodes): void
    {
        if ($node->namespaceURI === 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' && $node->localName === 't') {
            $textNodes[] = $node;
        }

        for ($child = $node->firstChild; $child !== null; $child = $child->nextSibling) {
            $this->collectTextNodes($child, $textNodes);
        }
    }

    private function paragraphText(\DOMXPath $xp, \DOMNode $paragraph): string
    {
        $texts = $xp->query('.//w:t', $paragraph);
        if (!$texts) {
            return '';
        }

        $plain = '';
        foreach ($texts as $text) {
            $plain .= $text->textContent;
        }

        return trim($plain);
    }

    private function normalizeText(string $value): string
    {
        $value = Str::ascii($value);
        $value = preg_replace('/\s+/u', ' ', mb_strtolower(trim($value))) ?: '';

        return $value;
    }

    private function fechaLargaEs(Carbon $date): string
    {
        return ucfirst($date->locale('es')->translatedFormat('d \d\e F \d\e Y'));
    }

    private function formatDate(?string $date): string
    {
        if (!$date) {
            return 'no detectada';
        }

        try {
            return Carbon::parse($date)->format('d/m/Y');
        } catch (\Throwable $e) {
            return $date;
        }
    }
}
