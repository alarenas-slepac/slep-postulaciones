<?php

namespace App\Exports;

use App\Models\Establecimiento;
use App\Support\DotacionNominaAsignaturas;
use Barryvdh\DomPDF\Facade\Pdf as PdfFacade;
use Barryvdh\DomPDF\PDF;
use Symfony\Component\HttpFoundation\Response;

class DotacionNominaAsignaturasPdfExport
{
    public function download(Establecimiento $establecimiento, int $anio, array $informe): Response
    {
        return $this->documento($establecimiento, $anio, $informe)
            ->download('docentes_por_asignatura_prelacion_rbd_'.(int) $establecimiento->rbd.'_'.$anio.'.pdf')
            ->withHeaders(['Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function documento(Establecimiento $establecimiento, int $anio, array $informe): PDF
    {
        $asignaturas = collect($informe['asignaturas'] ?? []);
        $bloques = collect(DotacionNominaAsignaturas::BLOQUES)->map(function (string $titulo, string $key) use ($asignaturas): array {
            $items = $asignaturas->filter(fn (array $item) => ($item['bloque'] ?? 'plan_estudio') === $key)->values();

            return ['titulo' => $titulo, 'asignaturas' => $items, 'horas_asignadas' => round((float) $items->sum('horas_asignadas'), 2)];
        })->filter(fn (array $bloque) => $bloque['asignaturas']->isNotEmpty());
        $pdf = PdfFacade::loadView('admin.dotacion-establecimiento.nomina-asignaturas-pdf', compact('establecimiento', 'anio', 'informe', 'bloques'))
            ->setOptions(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isFontSubsettingEnabled' => true])
            ->setPaper(DotacionNominaDocentesPdfExport::PAPEL, 'portrait');
        $pdf->render();
        $canvas = $pdf->getDomPDF()->getCanvas();
        $font = $pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text(28, $canvas->get_height() - 20, 'RBD '.(int) $establecimiento->rbd.' - Docentes por asignatura - Dotación '.$anio, $font, 7, [0.2, 0.25, 0.33]);
        $canvas->page_text($canvas->get_width() - 125, $canvas->get_height() - 20, 'Página {PAGE_NUM} de {PAGE_COUNT}', $font, 7, [0.2, 0.25, 0.33]);

        return $pdf;
    }
}
