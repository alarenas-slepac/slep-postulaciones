<?php

namespace App\Services\Remuneraciones;

use App\Models\DescuentoCgr;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use NumberFormatter;
use RuntimeException;
use ZipArchive;
use Illuminate\Validation\ValidationException;

class DescuentoCgrCertificadoService
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    public function __construct(private readonly CronogramaDescuentoCgrService $cronograma) {}

    public function generar(DescuentoCgr $descuento, User $auditor): string
    {
        $origen = resource_path('templates/descuentos-cgr/certificado-auditoria.docx');
        $temporal = tempnam(sys_get_temp_dir(), 'cgr_cert_');
        if (! $temporal || ! copy($origen, $temporal)) {
            throw new RuntimeException('No fue posible preparar la plantilla del certificado.');
        }

        try {
            $zip = new ZipArchive;
            if ($zip->open($temporal) !== true) {
                throw new RuntimeException('No fue posible abrir la plantilla del certificado.');
            }
            try {
                $xml = $zip->getFromName('word/document.xml');
                if ($xml === false) {
                    throw new RuntimeException('La plantilla no contiene el documento principal.');
                }
                $doc = new DOMDocument('1.0', 'UTF-8');
                $doc->loadXML($xml, LIBXML_NONET);
                $xpath = new DOMXPath($doc);
                $xpath->registerNamespace('w', self::W);
                $calculo = $this->cronograma->calcular($descuento);
                if ($calculo['utm_faltantes'] !== [] || $calculo['saldo_final_utm'] > 0.00005) {
                    throw ValidationException::withMessages(['cronograma' => 'Completa los valores UTM y verifica que las cuotas extingan la deuda antes de generar el certificado.']);
                }
                $filas = $calculo['filas'];
                $this->expandirCronograma($doc, $xpath, $filas);

                $hoy = now();
                $inicio = $descuento->fecha_primer_descuento;
                $fin = $inicio->copy()->addMonthsNoOverflow($descuento->numero_cuotas - 1);
                $periodoInicio = $inicio->copy()->locale('es')->translatedFormat('F Y');
                $periodoFin = $fin->locale('es')->translatedFormat('F Y');
                $documentos = 'liquidaciones verificadas por Auditoría, comprobantes de reintegro SIGFE y comprobantes de reintegro a TGR correspondientes a los períodos de '.$periodoInicio.' a '.$periodoFin;
                $cuotasTransferencia = $descuento->archivos()->where('tipo', 'transferencia_institucion')
                    ->pluck('numero_cuota')->unique()->sort()->values();
                if ($cuotasTransferencia->isNotEmpty()) {
                    $institucion = trim((string) $descuento->institucion_reintegro) ?: 'otra institución';
                    $mesesTransferencia = $cuotasTransferencia->map(fn ($cuota) =>
                        $inicio->copy()->addMonthsNoOverflow((int) $cuota - 1)->locale('es')->translatedFormat('F Y')
                    )->implode(', ');
                    $documentos .= ', y comprobantes de transferencia a '.$institucion.' correspondientes a '.$mesesTransferencia;
                }
                $monto = (int) $descuento->deuda_definitiva_pesos;
                $palabras = (new NumberFormatter('es_CL', NumberFormatter::SPELLOUT))->format($monto);
                $reemplazos = [
                    '{XX-XX-XXXX}' => $hoy->format('d-m-Y'),
                    '{N° DE REGISTRO}' => (string) $descuento->id,
                    '{DIA}' => $hoy->format('d'),
                    '{MES}' => $hoy->locale('es')->translatedFormat('F'),
                    '{AÑO}' => $hoy->format('Y'),
                    '{Nombre completo}' => $descuento->nombre,
                    '{RUT}' => $descuento->rut,
                    '{ESTAMENTO O ESCALAFON DE FUNCIONARIO}' => $descuento->estamento_funcionario ?: 'no informado',
                    '{N° dictamen o resolución}' => $descuento->numero_resolucion,
                    '{Fecha resolución}' => $descuento->fecha_resolucion?->format('d-m-Y') ?? 'fecha no informada',
                    '{DEUDA DEFINITIVA}' => number_format($monto, 0, ',', '.'),
                    '{Deuda equivalente (UTM)}' => number_format((float) $descuento->deuda_equivalente_utm, 4, ',', '.'),
                    '{Primer descuento}' => $periodoInicio,
                    '{Deuda definitiva (EN LETRA)}' => ($palabras ?: (string) $monto).' pesos',
                    '{N° de cuotas}' => (string) $descuento->numero_cuotas,
                    '{Tasa interés anual en porcentaje con un decimal}' => number_format((float) $descuento->tasa_interes_anual, 1, ',', '.').'%',
                    '{liquidaciones verificadas por auditoria indicando mes y año, comprobantes de reintegro de SIGFE indicando mes y año, Comprobante de Reintegro a TGR indicando mes y año}' => $documentos,
                    '{mes y año Primer descuento}' => $periodoInicio,
                    '{mes y año Último descuento}' => $periodoFin,
                    '{Año Fecha resolución}' => $descuento->fecha_resolucion?->format('Y') ?? 'año no informado',
                    '{NOMBRE USUARIO AUDITORIA_SLEP}' => $auditor->nombre_completo ?: $auditor->name ?: 'Auditoría Interna',
                    '{Insertar cronograma con lo solicitado en tabla inferior, crear tantas filas como cuotas de cronograma}' => '',
                ];
                foreach ($xpath->query('//w:p') as $parrafo) {
                    foreach ($reemplazos as $buscar => $valor) {
                        $this->reemplazarEnParrafo($xpath, $parrafo, $buscar, $valor);
                    }
                }
                $this->campoNumeroPaginas($doc, $xpath);
                $zip->addFromString('word/document.xml', $doc->saveXML());
                $settings = $zip->getFromName('word/settings.xml');
                if ($settings !== false && ! str_contains($settings, 'w:updateFields')) {
                    $settings = str_replace('</w:settings>', '<w:updateFields w:val="true"/></w:settings>', $settings);
                    $zip->addFromString('word/settings.xml', $settings);
                }
            } finally {
                $zip->close();
            }

            return file_get_contents($temporal) ?: throw new RuntimeException('No fue posible leer el certificado generado.');
        } finally {
            @unlink($temporal);
        }
    }

    private function expandirCronograma(DOMDocument $doc, DOMXPath $xpath, array $filas): void
    {
        foreach ($xpath->query('//w:tr') as $fila) {
            if (! str_contains($fila->textContent, '{N°}')) {
                continue;
            }
            foreach ($filas as $cuota) {
                $nueva = $fila->cloneNode(true);
                $fila->parentNode->insertBefore($nueva, $fila);
                foreach ($xpath->query('.//w:p', $nueva) as $parrafo) {
                    foreach ([
                        '{N°}' => (string) $cuota['numero'],
                        '{Mes}' => $cuota['periodo']->format('m-Y'),
                        '{Capital $}' => $this->pesos($cuota['capital_pesos']),
                        '{Interés mes $}' => $this->pesos($cuota['interes_pesos']),
                        '{Descuento total $}' => $this->pesos($cuota['descuento_pesos']),
                    ] as $buscar => $valor) {
                        $this->reemplazarEnParrafo($xpath, $parrafo, $buscar, $valor);
                    }
                }
            }
            $fila->parentNode->removeChild($fila);

            return;
        }
        throw new RuntimeException('La plantilla no contiene la fila de cuotas esperada.');
    }

    private function reemplazarEnParrafo(DOMXPath $xpath, \DOMNode $parrafo, string $buscar, string $valor): void
    {
        $desde = 0;
        while (true) {
            $nodos = iterator_to_array($xpath->query('.//w:t', $parrafo));
            $texto = implode('', array_map(fn ($nodo) => $nodo->textContent, $nodos));
            $inicio = mb_strpos($texto, $buscar, $desde);
            if ($inicio === false) {
                return;
            }
            $fin = $inicio + mb_strlen($buscar);
            $posicion = 0;
            $primero = null;
            foreach ($nodos as $nodo) {
                $contenido = $nodo->textContent;
                $limite = $posicion + mb_strlen($contenido);
                if ($primero === null && $inicio < $limite) {
                    $primero = $nodo;
                    $prefijo = mb_substr($contenido, 0, $inicio - $posicion);
                }
                if ($primero !== null) {
                    if ($fin <= $limite) {
                        $sufijo = mb_substr($contenido, $fin - $posicion);
                        $primero->textContent = $prefijo.$valor.($primero === $nodo ? $sufijo : '');
                        if ($primero !== $nodo) {
                            $nodo->textContent = $sufijo;
                        }
                        $desde = $inicio + mb_strlen($valor);
                        break;
                    }
                    if ($primero !== $nodo) {
                        $nodo->textContent = '';
                    }
                }
                $posicion = $limite;
            }
        }
    }

    private function campoNumeroPaginas(DOMDocument $doc, DOMXPath $xpath): void
    {
        foreach ($xpath->query('//w:p') as $parrafo) {
            if (! str_contains($parrafo->textContent, '{INDICAR N° DE PÁGINAS}')) {
                continue;
            }
            $this->reemplazarEnParrafo($xpath, $parrafo, '{INDICAR N° DE PÁGINAS}', '');
            $field = $doc->createElementNS(self::W, 'w:fldSimple');
            $field->setAttributeNS(self::W, 'w:instr', 'NUMPAGES');
            $run = $doc->createElementNS(self::W, 'w:r');
            $run->appendChild($doc->createElementNS(self::W, 'w:t', '1'));
            $field->appendChild($run);
            $parrafo->appendChild($field);

            return;
        }
    }

    private function pesos(?float $valor): string
    {
        return $valor === null ? 'Pendiente UTM' : number_format($valor, 0, ',', '.');
    }
}
