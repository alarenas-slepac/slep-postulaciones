<?php

namespace App\Exports;

use App\Models\Establecimiento;
use Barryvdh\DomPDF\Facade\Pdf as PdfFacade;
use Barryvdh\DomPDF\PDF;
use Symfony\Component\HttpFoundation\Response;

class DotacionSobredotacionNominaPdfExport
{
    public const HORAS = ['contrato_original', 'contrato_considerado', 'aula_asignada', 'contrato_asignado',
        'reservadas', 'pendientes', 'contrato_bloque', 'saldo_bloque', 'horas_titular', 'horas_contrata', 'con_justificacion', 'sin_justificacion'];

    public function download(Establecimiento $establecimiento, int $anio, array $informe): Response
    {
        return $this->documento($establecimiento, $anio, $informe)
            ->download('nomina_sobredotacion_prelacion_rbd_'.(int) $establecimiento->rbd.'_'.$anio.'.pdf')
            ->withHeaders(['Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function documento(Establecimiento $establecimiento, int $anio, array $informe): PDF
    {
        $bloques = [];
        foreach (DotacionSobredotacionExport::BLOQUES as $clave => $titulo) {
            $filas = collect($informe['bloques'][$clave] ?? [])->values();
            $totales = [];
            foreach (self::HORAS as $campo) {
                $totales[$campo] = $filas->contains(fn ($fila) => !array_key_exists($campo, $fila) || $fila[$campo] === null)
                    ? null : round((float) $filas->sum($campo), 2);
            }
            $bloques[$clave] = compact('titulo', 'filas', 'totales');
        }
        $pdf = PdfFacade::loadView('admin.dotacion-establecimiento.sobredotacion-nomina-pdf', compact('establecimiento', 'anio', 'informe', 'bloques'))
            ->setOptions(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isFontSubsettingEnabled' => true])
            ->setPaper(DotacionNominaDocentesPdfExport::PAPEL, 'portrait');
        $pdf->render();
        $canvas = $pdf->getDomPDF()->getCanvas();
        $font = $pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text(26, $canvas->get_height() - 18, 'RBD '.(int) $establecimiento->rbd.' - Dotación '.$anio.' - México Oficio vertical', $font, 6.8, [0.2, 0.25, 0.33]);
        $canvas->page_text($canvas->get_width() - 112, $canvas->get_height() - 18, 'Página {PAGE_NUM} de {PAGE_COUNT}', $font, 6.8, [0.2, 0.25, 0.33]);

        return $pdf;
    }
}
