<?php

namespace App\Http\Controllers\Admin;

use App\Exports\DotacionNominaAsignaturasPdfExport;
use App\Exports\DotacionNominaDocentesExport;
use App\Exports\DotacionNominaDocentesPdfExport;
use App\Exports\DotacionSobredotacionExport;
use App\Exports\DotacionSobredotacionPdfExport;
use App\Http\Controllers\Controller;
use App\Models\Establecimiento;
use App\Support\DotacionNominaAsignaturas;
use App\Support\DotacionNominaDocentes;
use App\Support\DotacionSobredotacionInforme;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DotacionNominaDocentesController extends Controller
{
    public function __invoke(Request $request, Establecimiento $establecimiento, DotacionNominaDocentes $nomina, DotacionNominaDocentesExport $export): StreamedResponse
    {
        $anio = $this->anioAutorizado($request, $establecimiento);

        return $export->download($establecimiento, $anio, $nomina->datos($establecimiento, $anio));
    }

    public function sobredotacion(Request $request, Establecimiento $establecimiento, DotacionNominaDocentes $nomina, DotacionSobredotacionExport $export): StreamedResponse
    {
        $anio = $this->anioAutorizado($request, $establecimiento);

        return $export->download($establecimiento, $anio, $nomina->datosSobredotacion($establecimiento, $anio));
    }

    public function pdf(Request $request, Establecimiento $establecimiento, DotacionNominaDocentes $nomina, DotacionNominaDocentesPdfExport $export): Response
    {
        $anio = $this->anioAutorizado($request, $establecimiento);

        return $export->download($establecimiento, $anio, $nomina->datos($establecimiento, $anio));
    }

    public function sobredotacionPdf(Request $request, Establecimiento $establecimiento, DotacionSobredotacionInforme $informe, DotacionSobredotacionPdfExport $export): Response
    {
        $anio = $this->anioAutorizado($request, $establecimiento);

        return $export->download($establecimiento, $anio, $informe->datos($establecimiento, $anio));
    }

    public function asignaturasPdf(Request $request, Establecimiento $establecimiento, DotacionNominaAsignaturas $nomina, DotacionNominaAsignaturasPdfExport $export): Response
    {
        $anio = $this->anioAutorizado($request, $establecimiento);

        return $export->download($establecimiento, $anio, $nomina->datos($establecimiento, $anio));
    }

    private function anioAutorizado(Request $request, Establecimiento $establecimiento): int
    {
        $rol = $request->user()?->activeRoleName();
        abort_unless(DotacionNominaDocentesExport::canExport($rol), 403);
        abort_if((bool) ($establecimiento->sala_cuna ?? false), 404);
        if ($rol === 'funcionario_directivo_estab') {
            abort_unless((int) ($request->user()->establecimiento_id ?? 0) > 0
                && (int) $request->user()->establecimiento_id === (int) $establecimiento->id, 403);
        }
        $data = $request->validate(['anio' => ['nullable', 'integer', 'between:1,9999']]);
        return (int) ($data['anio'] ?? now()->year);
    }
}
