<?php

namespace App\Http\Controllers\Endeudamiento;

use App\Http\Controllers\Controller;
use App\Models\MaeCarga;
use App\Models\MaeCuotasImportacion;
use App\Services\Endeudamiento\MaeCuotasImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class MaeCuotasController extends Controller
{
    public function __construct(private readonly MaeCuotasImportService $service)
    {
        $this->middleware(['auth', 'ensure.role:admin']);
    }

    public function index(Request $request): View
    {
        $anio = (int) $request->integer('anio', 0);
        $mes = (int) $request->integer('mes', 0);
        $dominio = trim((string) $request->get('dominio', ''));
        $estado = trim((string) $request->get('estado', ''));

        $items = MaeCuotasImportacion::query()
            ->with(['carga', 'creadoPor'])
            ->when($anio > 0, fn ($query) => $query->whereHas('carga', fn ($q) => $q->where('anio', $anio)))
            ->when($mes > 0, fn ($query) => $query->whereHas('carga', fn ($q) => $q->where('mes', $mes)))
            ->when($dominio !== '', fn ($query) => $query->whereHas('carga', fn ($q) => $q->where('dominio', $dominio)))
            ->when($estado !== '', fn ($query) => $query->where('estado', $estado))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $anios = MaeCarga::query()->distinct()->orderByDesc('anio')->pluck('anio');
        $dominios = MaeCarga::query()->distinct()->orderBy('dominio')->pluck('dominio');
        $estados = collect(['procesando', 'procesado', 'procesado_con_errores', 'fallido']);

        return view('endeudamiento.cuotas.index', compact(
            'items', 'anios', 'dominios', 'estados', 'anio', 'mes', 'dominio', 'estado'
        ));
    }

    public function create(Request $request): View
    {
        $cargas = MaeCarga::query()
            ->whereIn('estado', ['procesado', 'procesado_con_observaciones'])
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->orderBy('dominio')
            ->orderByDesc('version')
            ->get(['id', 'anio', 'mes', 'dominio', 'version', 'es_vigente', 'estado']);

        $cargaId = (int) $request->integer('carga_id', 0);
        $cargaSeleccionada = $cargaId > 0 ? $cargas->firstWhere('id', $cargaId) : null;
        $descuentos = $cargaSeleccionada
            ? $this->service->availableDiscounts($cargaSeleccionada)
            : collect();

        return view('endeudamiento.cuotas.create', compact('cargas', 'cargaSeleccionada', 'descuentos'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mae_carga_id' => ['required', 'integer', 'exists:mae_cargas,id'],
            'columna_normalizada' => ['required', 'string', 'max:191'],
            'excel' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:20480'],
        ], [
            'mae_carga_id.required' => 'Debes seleccionar una carga MAE procesada.',
            'columna_normalizada.required' => 'Debes seleccionar el descuento que se complementará.',
            'excel.required' => 'Debes adjuntar la nómina de cuotas.',
            'excel.mimes' => 'La nómina debe ser un archivo Excel o CSV.',
            'excel.max' => 'La nómina no puede superar 20 MB.',
        ]);

        $carga = MaeCarga::query()->findOrFail((int) $data['mae_carga_id']);

        try {
            $importacion = $this->service->import(
                $carga,
                (string) $data['columna_normalizada'],
                $request->file('excel'),
                (int) $request->user()->id
            );

            return redirect()
                ->route('endeudamiento.cuotas.show', $importacion)
                ->with('status', 'La nómina fue revisada y las cuotas válidas quedaron asociadas al descuento seleccionado.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Error complementando cuotas de descuentos MAE.', [
                'mae_carga_id' => $carga->id,
                'columna_normalizada' => $data['columna_normalizada'],
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return back()
                ->withInput()
                ->withErrors(['excel' => 'No fue posible procesar la nómina de cuotas. Revisa el archivo y el log del sistema.']);
        }
    }

    public function show(MaeCuotasImportacion $maeCuotasImportacion): View
    {
        $maeCuotasImportacion->load(['carga', 'creadoPor']);
        $detalles = $maeCuotasImportacion->detalles()
            ->orderBy('numero_fila')
            ->paginate(100);

        return view('endeudamiento.cuotas.show', [
            'importacion' => $maeCuotasImportacion,
            'detalles' => $detalles,
        ]);
    }

    public function plantilla()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Cuotas descuentos');
        $sheet->fromArray([
            ['RUT', 'CUOTA_ACTUAL', 'TOTAL_CUOTAS', 'OBSERVACION'],
            ['12.345.678-5', 3, 12, 'Cuota normal: 3 de 12'],
            ['9.876.543-3', 135, 0, 'Indefinida: lleva 135 cuotas y no tiene término'],
            ['16.111.222-3', 0, 0, 'Indefinida: sin inicio ni término informado; el descuento se aplica'],
        ]);

        $sheet->getStyle('A1:D1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:D1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0D6EFD');
        $sheet->getStyle('A1:D4')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(48);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:D1');

        $directory = storage_path('app/tmp');
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $path = $directory . DIRECTORY_SEPARATOR . 'plantilla_cuotas_descuentos_' . uniqid('', true) . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return response()
            ->download($path, 'plantilla_cuotas_descuentos.xlsx')
            ->deleteFileAfterSend(true);
    }
}
