<?php

namespace App\Exports;

use App\Models\Establecimiento;
use App\Support\DotacionNominaDocentes;
use Barryvdh\DomPDF\Facade\Pdf as PdfFacade;
use Barryvdh\DomPDF\PDF;
use Symfony\Component\HttpFoundation\Response;

class DotacionNominaDocentesPdfExport
{
    // México Oficio: 216 x 340 mm, en puntos PDF; Dompdf aplica la orientación.
    public const PAPEL = [0, 0, 612.2834645669, 963.7795275591];
    public const HORAS = ['contrato_original', 'contrato_considerado', 'aula_asignada', 'contrato_asignado',
        'reservadas', 'pendientes', 'con_justificacion', 'sin_justificacion'];

    public function download(Establecimiento $establecimiento, int $anio, array $informe): Response
    {
        return $this->documento($establecimiento, $anio, $informe)
            ->download('nomina_docentes_prelacion_rbd_'.(int) $establecimiento->rbd.'_'.$anio.'.pdf')
            ->withHeaders(['Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function documento(Establecimiento $establecimiento, int $anio, array $informe): PDF
    {
        $bloques = [];
        foreach (DotacionNominaDocentes::BLOQUES as $key => $titulo) {
            $filas = collect($informe['bloques'][$key] ?? ($key === 'plan_estudio' ? ($informe['filas'] ?? []) : []))->values();
            $totales = [];
            foreach (self::HORAS as $campo) {
                $totales[$campo] = $filas->contains(fn ($fila) => !array_key_exists($campo, $fila) || $fila[$campo] === null)
                    ? null : round((float) $filas->sum($campo), 2);
            }
            $bloques[$key] = compact('titulo', 'filas', 'totales');
        }
        $definiciones = (new DotacionNominaDocentesExport)->definiciones($anio, $informe);
        $pdf = PdfFacade::loadView('admin.dotacion-establecimiento.nomina-docentes-pdf', compact('establecimiento', 'anio', 'informe', 'bloques', 'definiciones'))
            ->setOptions(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isFontSubsettingEnabled' => true])
            ->setPaper(self::PAPEL, 'landscape');
        $pdf->render();
        $canvas = $pdf->getDomPDF()->getCanvas();
        $font = $pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text(28, $canvas->get_height() - 20, 'RBD '.(int) $establecimiento->rbd.' - Dotación '.$anio.' - México Oficio horizontal', $font, 7, [0.2, 0.25, 0.33]);
        $canvas->page_text($canvas->get_width() - 125, $canvas->get_height() - 20, 'Página {PAGE_NUM} de {PAGE_COUNT}', $font, 7, [0.2, 0.25, 0.33]);

        return $pdf;
    }
}
