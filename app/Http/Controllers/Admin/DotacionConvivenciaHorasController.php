<?php

namespace App\Http\Controllers\Admin;

use App\Exports\DotacionConvivenciaHorasExport;
use App\Http\Controllers\Controller;
use App\Imports\DotacionConvivenciaHorasImport;
use App\Support\DotacionConvivenciaAnual;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DotacionConvivenciaHorasController extends Controller
{
    public function index(Request $request): View
    {
        $anio = $this->anioAutorizado($request);

        return view('admin.dotacion-funciones.convivencia-carga', [
            'anio' => $anio, 'filas' => DotacionConvivenciaAnual::filas($anio),
        ]);
    }

    public function plantilla(Request $request): StreamedResponse
    {
        $anio = $this->anioAutorizado($request);
        $book = (new DotacionConvivenciaHorasExport)->workbook(DotacionConvivenciaAnual::filas($anio), $anio);

        return response()->streamDownload(function () use ($book): void {
            try {
                (new Xlsx($book))->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, 'plantilla-convivencia-educativa-'.$anio.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $anio = $this->anioAutorizado($request);
        $request->validate(['anio' => ['required', 'integer', 'between:2020,2100'], 'archivo' => ['required', 'file', 'mimes:xlsx', 'max:10240']]);
        $resultado = (new DotacionConvivenciaHorasImport)->import($request->file('archivo')->getRealPath(), $anio, $request->user()?->id);

        return redirect()->route('admin.dotacion-funciones.convivencia.index', ['anio' => $anio])
            ->with('success', 'Carga completada para '.$anio.': '.$resultado['cargadas'].' establecimiento(s) configurado(s) y '
                .$resultado['omitidas'].' fila(s) con horas vacías conservadas.');
    }

    private function anioAutorizado(Request $request): int
    {
        $role = $request->user()?->activeRoleName();
        abort_unless(DotacionConvivenciaAnual::puedeConfigurar($role), 403);
        abort_unless(Schema::hasTable(DotacionConvivenciaAnual::TABLE), 503, 'Debe ejecutar la migración de horas anuales de Convivencia Educativa.');
        $data = $request->validate(['anio' => ['sometimes', 'required', 'integer', 'between:2020,2100']]);

        return (int) ($data['anio'] ?? now()->year);
    }
}
