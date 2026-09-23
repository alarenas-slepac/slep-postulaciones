<?php

namespace App\Services\IdoneidadPsicologica;

use Dompdf\Canvas;
use Dompdf\Dompdf;
use Illuminate\Support\Collection;

class IdoneidadPsicologicaNominaPdfRenderer
{
    private const PAGE_WIDTH = 612.0;
    private const PAGE_HEIGHT = 964.0;
    private const MARGIN_LEFT = 85.0;
    private const MARGIN_RIGHT = 85.0;
    private const TABLE_TOP = 72.0;
    private const TABLE_BOTTOM = 874.0;
    private const BLACK = [0, 0, 0];
    private const HEADER_BACKGROUND = [0.94, 0.95, 0.97];

    /**
     * @param Collection<int, object> $funcionarios
     * @param array<string, string> $datosOficio
     */
    public function agregarNominaYFirma(Dompdf $dompdf, Collection $funcionarios, array $datosOficio): void
    {
        $canvas = $dompdf->getCanvas();
        $fontMetrics = $dompdf->getFontMetrics();
        $fontRegular = $fontMetrics->getFont('Century Gothic', 'normal') ?? $fontMetrics->getFont('serif', 'normal');
        $fontBold = $fontMetrics->getFont('Century Gothic', 'bold') ?? $fontMetrics->getFont('serif', 'bold');

        $this->dibujarNomina($canvas, $funcionarios, $fontRegular, $fontBold);
        $this->dibujarCierre($canvas, $datosOficio, $fontRegular, $fontBold);
    }

    /**
     * @param Collection<int, object> $funcionarios
     */
    private function dibujarNomina(Canvas $canvas, Collection $funcionarios, string $fontRegular, string $fontBold): void
    {
        $columnas = [
            ['titulo' => 'Nro.', 'ancho' => 25.0, 'alineacion' => 'center'],
            ['titulo' => 'Nombre', 'ancho' => 83.0, 'alineacion' => 'left'],
            ['titulo' => 'RUT', 'ancho' => 55.0, 'alineacion' => 'center'],
            ['titulo' => 'Cargo', 'ancho' => 68.0, 'alineacion' => 'left'],
            ['titulo' => 'Establecimiento', 'ancho' => 82.0, 'alineacion' => 'left'],
            ['titulo' => 'Tipo de contrato', 'ancho' => 68.0, 'alineacion' => 'left'],
            ['titulo' => 'Comuna', 'ancho' => 61.0, 'alineacion' => 'left'],
        ];

        $canvas->new_page();
        $y = $this->dibujarEncabezadoNomina($canvas, $columnas, $fontBold);
        $numero = 1;

        foreach ($funcionarios as $funcionario) {
            $valores = [
                (string) $numero,
                (string) $funcionario->nombre,
                (string) $funcionario->rut,
                (string) ($funcionario->cargo_funcion ?: 'Sin cargo informado'),
                (string) ($funcionario->establecimiento_nombre ?: 'Sin establecimiento informado'),
                (string) ($funcionario->tipo_contrato ?: 'Sin contrato informado'),
                (string) ($funcionario->comuna ?: 'Sin comuna informada'),
            ];
            $lineas = [];
            $maximoLineas = 1;
            foreach ($columnas as $indice => $columna) {
                $lineas[$indice] = $this->envolverTexto(
                    $canvas,
                    $valores[$indice],
                    $fontRegular,
                    6.2,
                    $columna['ancho'] - 5
                );
                $maximoLineas = max($maximoLineas, count($lineas[$indice]));
            }

            $altoFila = max(17.0, ($maximoLineas * 7.4) + 6.0);
            if ($y + $altoFila > self::TABLE_BOTTOM) {
                $canvas->new_page();
                $y = $this->dibujarEncabezadoNomina($canvas, $columnas, $fontBold);
            }

            $x = self::MARGIN_LEFT;
            foreach ($columnas as $indice => $columna) {
                $this->dibujarCelda(
                    $canvas,
                    $x,
                    $y,
                    $columna['ancho'],
                    $altoFila,
                    $lineas[$indice],
                    $fontRegular,
                    6.2,
                    $columna['alineacion']
                );
                $x += $columna['ancho'];
            }

            $y += $altoFila;
            $numero++;
        }
    }

    /**
     * @param array<int, array{titulo: string, ancho: float, alineacion: string}> $columnas
     */
    private function dibujarEncabezadoNomina(Canvas $canvas, array $columnas, string $fontBold): float
    {
        $x = self::MARGIN_LEFT;
        $y = self::TABLE_TOP;
        $alto = 20.0;

        foreach ($columnas as $columna) {
            $canvas->filled_rectangle($x, $y, $columna['ancho'], $alto, self::HEADER_BACKGROUND);
            $canvas->rectangle($x, $y, $columna['ancho'], $alto, self::BLACK, 0.55);
            $anchoTexto = $canvas->get_text_width($columna['titulo'], $fontBold, 6.1);
            $canvas->text(
                $x + max(2.0, ($columna['ancho'] - $anchoTexto) / 2),
                $y + 7.1,
                $columna['titulo'],
                $fontBold,
                6.1,
                self::BLACK
            );
            $x += $columna['ancho'];
        }

        return $y + $alto;
    }

    /**
     * @param array<int, string> $lineas
     */
    private function dibujarCelda(
        Canvas $canvas,
        float $x,
        float $y,
        float $ancho,
        float $alto,
        array $lineas,
        string $font,
        float $tamano,
        string $alineacion
    ): void {
        $canvas->rectangle($x, $y, $ancho, $alto, self::BLACK, 0.45);
        foreach ($lineas as $indice => $linea) {
            $anchoTexto = $canvas->get_text_width($linea, $font, $tamano);
            $posicionX = match ($alineacion) {
                'center' => $x + max(2.0, ($ancho - $anchoTexto) / 2),
                'right' => $x + $ancho - $anchoTexto - 2.5,
                default => $x + 2.5,
            };
            $canvas->text($posicionX, $y + 4.2 + ($indice * 7.4), $linea, $font, $tamano, self::BLACK);
        }
    }

    /**
     * @return array<int, string>
     */
    private function envolverTexto(Canvas $canvas, string $texto, string $font, float $tamano, float $anchoMaximo): array
    {
        $palabras = preg_split('/\s+/u', trim($texto), -1, PREG_SPLIT_NO_EMPTY) ?: [''];
        $lineas = [];
        $linea = '';

        foreach ($palabras as $palabra) {
            $propuesta = $linea === '' ? $palabra : $linea . ' ' . $palabra;
            if ($linea !== '' && $canvas->get_text_width($propuesta, $font, $tamano) > $anchoMaximo) {
                $lineas[] = $linea;
                $linea = $palabra;
                continue;
            }
            $linea = $propuesta;
        }

        if ($linea !== '') {
            $lineas[] = $linea;
        }

        return $lineas ?: [''];
    }

    /**
     * @param array<string, string> $datosOficio
     */
    private function dibujarCierre(Canvas $canvas, array $datosOficio, string $fontRegular, string $fontBold): void
    {
        $canvas->new_page();
        $ancho = self::PAGE_WIDTH - self::MARGIN_LEFT - self::MARGIN_RIGHT;
        $y = 94.0;
        $partes = [
            ['texto' => '8. Teniendo en cuenta lo expuesto en los numerales anteriores, y a fin de plasmar el principio de coordinación entre los servicios públicos, dejo el contacto del funcionario(a) de la Subdirección de Gestión de Personas del Servicio Local de Educación Pública de Andalién Costa, Sr(a). ', 'font' => $fontRegular],
            ['texto' => $datosOficio['contacto_nombre'], 'font' => $fontBold],
            ['texto' => ', mail ', 'font' => $fontRegular],
            ['texto' => $datosOficio['contacto_email'] . '.', 'font' => $fontBold],
        ];
        $y = $this->dibujarTextoSegmentado($canvas, $partes, self::MARGIN_LEFT, $y, $ancho, 11.5, 15.0);
        $y += 18.0;
        $y = $this->dibujarTextoSegmentado($canvas, [[
            'texto' => 'Esperando una buena recepción a la presente solicitud, saluda atentamente,',
            'font' => $fontRegular,
        ]], self::MARGIN_LEFT, $y, $ancho, 11.5, 15.0);

        $y += 94.0;
        $this->dibujarCentrado($canvas, mb_strtoupper($datosOficio['director_ejecutivo_nombre']), $fontBold, 12.0, $y);
        $y += 17.0;
        $this->dibujarCentrado($canvas, mb_strtoupper($datosOficio['director_ejecutivo_cargo_firma']), $fontRegular, 10.5, $y);
        $y += 16.0;
        $this->dibujarCentrado($canvas, 'SERVICIO LOCAL DE EDUCACIÓN PÚBLICA DE ANDALIÉN COSTA', $fontRegular, 10.5, $y);
        $y += 34.0;
        $canvas->text(self::MARGIN_LEFT, $y, $datosOficio['iniciales_firmantes_visadores'], $fontBold, 8.5, self::BLACK);
        $y += 34.0;
        $canvas->text(self::MARGIN_LEFT, $y, 'Distribución:', $fontBold, 8.5, self::BLACK);
        $canvas->line(self::MARGIN_LEFT, $y + 2.0, self::MARGIN_LEFT + 48.0, $y + 2.0, self::BLACK, 0.45);
        $canvas->text(self::MARGIN_LEFT, $y + 17.0, '- Destinatario', $fontRegular, 8.5, self::BLACK);
        $canvas->text(self::MARGIN_LEFT, $y + 32.0, '- Archivo', $fontRegular, 8.5, self::BLACK);
    }

    /**
     * @param array<int, array{texto: string, font: string}> $partes
     */
    private function dibujarTextoSegmentado(
        Canvas $canvas,
        array $partes,
        float $x,
        float $y,
        float $anchoMaximo,
        float $tamano,
        float $interlineado
    ): float {
        $cursorX = $x;
        foreach ($partes as $parte) {
            $palabras = preg_split('/(\s+)/u', $parte['texto'], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($palabras as $palabra) {
                $anchoPalabra = $canvas->get_text_width($palabra, $parte['font'], $tamano);
                if (trim($palabra) !== '' && $cursorX > $x && $cursorX + $anchoPalabra > $x + $anchoMaximo) {
                    $y += $interlineado;
                    $cursorX = $x;
                }
                $canvas->text($cursorX, $y, $palabra, $parte['font'], $tamano, self::BLACK);
                $cursorX += $anchoPalabra;
            }
        }

        return $y + $interlineado;
    }

    private function dibujarCentrado(Canvas $canvas, string $texto, string $font, float $tamano, float $y): void
    {
        $anchoTexto = $canvas->get_text_width($texto, $font, $tamano);
        $canvas->text((self::PAGE_WIDTH - $anchoTexto) / 2, $y, $texto, $font, $tamano, self::BLACK);
    }
}
