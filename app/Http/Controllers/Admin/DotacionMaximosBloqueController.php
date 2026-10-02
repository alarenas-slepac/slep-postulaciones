<?php

namespace App\Http\Controllers\Admin;

use App\Exports\DotacionMaximosBloqueExport;
use App\Http\Controllers\Controller;
use App\Imports\DotacionMaximosBloqueImport;
use App\Models\DotacionProceso2027Configuracion;
use App\Models\Establecimiento;
use App\Support\DotacionConvivenciaAnual;
use App\Support\DotacionContratoVigentePorBloque;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DotacionMaximosBloqueController extends Controller
{
    public function index(Request $request): View
    {
        $anio = $this->anioAutorizado($request);

        return view('admin.dotacion-funciones.maximos-carga', ['anio' => $anio, 'filas' => $this->filas($anio)]);
    }

    public function plantilla(Request $request): StreamedResponse
    {
        $anio = $this->anioAutorizado($request);
        $book = (new DotacionMaximosBloqueExport)->workbook($this->filas($anio), $anio);

        return response()->streamDownload(function () use ($book): void {
            try {
                (new Xlsx($book))->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, 'plantilla-maximos-bloque-'.$anio.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'private, no-store',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $anio = $this->anioAutorizado($request);
        $request->validate(['anio' => ['required', 'integer', 'between:2020,2100'], 'archivo' => ['required', 'file', 'mimes:xlsx', 'max:10240']]);
        $resultado = (new DotacionMaximosBloqueImport)->import($request->file('archivo')->getRealPath(), $anio, $request->user()?->id);

        return redirect()->route('admin.dotacion-funciones.maximos.index', ['anio' => $anio])
            ->with('success', 'Máximos cargados para '.$anio.': '.$resultado['cargadas'].' establecimiento(s) configurado(s) y '
                .$resultado['omitidas'].' fila(s) sin cambios.');
    }

    public function filas(int $anio): Collection
    {
        $configs = DotacionProceso2027Configuracion::query()->where('anio', $anio)->get()->keyBy('establecimiento_id');
        $matriculas = DB::table('establecimiento_cursos')->where('anio', $anio)->where('activo', true)
            ->groupBy('establecimiento_id')->selectRaw('establecimiento_id, SUM(matricula) as total')->pluck('total', 'establecimiento_id');

        $contratos = app(DotacionContratoVigentePorBloque::class);
        return Establecimiento::query()
            ->where(fn ($query) => $query->whereNull('sala_cuna')->orWhere('sala_cuna', false))
            ->whereIn('id', $matriculas->keys())
            ->orderBy('rbd')->orderBy('id')->get()->map(function ($establecimiento) use ($configs, $matriculas, $contratos, $anio): array {
            $config = $configs->get($establecimiento->id);
            $fila = ['rbd' => $establecimiento->rbd, 'nombre' => $establecimiento->nombre_establecimiento, 'matricula' => (int) $matriculas->get($establecimiento->id, 0)];
            foreach (DotacionMaximosBloqueExport::COLUMNAS as $campo) {
                $fila[$campo] = $config?->{$campo} === null ? null : (float) $config->{$campo};
            }
            $fila += $contratos->paraEstablecimiento($establecimiento, $anio - 1);

            return $fila;
        });
    }

    private function anioAutorizado(Request $request): int
    {
        abort_unless(DotacionConvivenciaAnual::puedeConfigurar($request->user()?->activeRoleName()), 403);
        abort_unless(Schema::hasTable('dotacion_proceso_2027_configuraciones'), 503, 'Debe ejecutar la migración de configuración de dotación.');
        $data = $request->validate(['anio' => ['sometimes', 'required', 'integer', 'between:2020,2100']]);

        return (int) ($data['anio'] ?? 2027);
    }
}
