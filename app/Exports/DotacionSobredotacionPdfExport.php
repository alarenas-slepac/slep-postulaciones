<?php

namespace App\Exports;

use App\Models\Establecimiento;
use Barryvdh\DomPDF\Facade\Pdf as PdfFacade;
use Barryvdh\DomPDF\PDF;
use Symfony\Component\HttpFoundation\Response;

class DotacionSobredotacionPdfExport
{
    public function download(Establecimiento $establecimiento, int $anio, array $informe): Response
    {
        return $this->documento($establecimiento, $anio, $informe)
            ->download('detalle_sobredotacion_rbd_'.(int) $establecimiento->rbd.'_'.$anio.'.pdf')
            ->withHeaders(['Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function documento(Establecimiento $establecimiento, int $anio, array $informe): PDF
    {
        $pdf = PdfFacade::loadView('admin.dotacion-establecimiento.sobredotacion-pdf', compact('establecimiento', 'anio', 'informe'))
            ->setOptions(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isFontSubsettingEnabled' => true])
            ->setPaper(DotacionNominaDocentesPdfExport::PAPEL, 'landscape');
        $pdf->render();
        $canvas = $pdf->getDomPDF()->getCanvas();
        $font = $pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text(28, $canvas->get_height() - 20, 'RBD '.(int) $establecimiento->rbd.' - Dotación '.$anio.' - México Oficio horizontal', $font, 7, [0.2, 0.25, 0.33]);
        $canvas->page_text($canvas->get_width() - 125, $canvas->get_height() - 20, 'Página {PAGE_NUM} de {PAGE_COUNT}', $font, 7, [0.2, 0.25, 0.33]);
        return $pdf;
    }
}
