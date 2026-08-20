<?php

namespace App\Http\Controllers\Remuneraciones;

use App\Http\Controllers\Controller;
use App\Models\UtmValor;
use App\Services\Remuneraciones\UtmImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UtmValorController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'ensure.role:admin|funcionario_slep']);
    }

    public function index(Request $request): View
    {
        $anio = (int) $request->integer('anio');
        $valores = UtmValor::query()
            ->with('actualizadoPor')
            ->when($anio > 0, fn ($query) => $query->where('anio', $anio))
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->paginate(24)
            ->withQueryString();
        $anios = UtmValor::query()->distinct()->orderByDesc('anio')->pluck('anio');

        return view('remuneraciones.descuentos-cgr.utm.index', compact('valores', 'anios', 'anio'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validar($request);
        UtmValor::create($data + [
            'creado_por_id' => $request->user()->id,
            'actualizado_por_id' => $request->user()->id,
        ]);

        return back()->with('status', 'Valor UTM registrado correctamente.');
    }

    public function update(Request $request, UtmValor $utmValor): RedirectResponse
    {
        $data = $this->validar($request, $utmValor);
        $utmValor->update($data + ['actualizado_por_id' => $request->user()->id]);

        return back()->with('status', 'Valor UTM actualizado. Los cronogramas usarán el nuevo valor.');
    }

    public function importar(Request $request, UtmImportService $importador): RedirectResponse
    {
        $data = $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $cantidad = $importador->importar($data['archivo'], $request->user()->id);

        return back()->with('status', "Se importaron {$cantidad} valores UTM correctamente.");
    }

    public function plantilla(): BinaryFileResponse
    {
        $libro = new Spreadsheet;
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Valores UTM');
        $hoja->fromArray([
            ['ANIO', 'MES', 'VALOR_UTM'],
            [(int) now()->year, (int) now()->month, ''],
        ]);
        $hoja->getStyle('A1:C1')->getFont()->setBold(true);
        $hoja->getColumnDimension('A')->setWidth(12);
        $hoja->getColumnDimension('B')->setWidth(12);
        $hoja->getColumnDimension('C')->setWidth(18);
        $hoja->getStyle('C2:C500')->getNumberFormat()->setFormatCode('#,##0.00');

        $temporal = tempnam(sys_get_temp_dir(), 'utm_plantilla_');
        (new Xlsx($libro))->save($temporal);
        $libro->disconnectWorksheets();

        return response()->download($temporal, 'plantilla_valores_utm.xlsx')->deleteFileAfterSend(true);
    }

    private function validar(Request $request, ?UtmValor $utmValor = null): array
    {
        return $request->validate([
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
            'mes' => [
                'required', 'integer', 'min:1', 'max:12',
                Rule::unique('utm_valores', 'mes')
                    ->where(fn ($query) => $query->where('anio', $request->integer('anio')))
                    ->ignore($utmValor?->id),
            ],
            'valor' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
        ], [
            'mes.unique' => 'Ya existe un valor UTM para el mes y año indicados.',
        ]);
    }
}
