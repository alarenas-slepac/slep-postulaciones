<?php

namespace App\Http\Controllers\Reemplazos;

use App\Http\Controllers\Controller;
use App\Services\Padron\PadronDatosActualizacionService;
use App\Services\Padron\PadronDatosExcel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PersonalDatosController extends Controller
{
    private function campos(Request $request): array
    {
        return $request->validate([
            'campos' => ['required', 'array', 'min:1', 'max:4'],
            'campos.*' => ['required', 'string', 'distinct', Rule::in(array_keys(PadronDatosExcel::CAMPOS))],
        ])['campos'];
    }

    public function plantilla(Request $request, PadronDatosExcel $excel)
    {
        return $this->descargar($excel->plantilla($this->campos($request)), 'plantilla_actualizacion_personal.xlsx');
    }

    public function actualizar(Request $request, PadronDatosExcel $excel, PadronDatosActualizacionService $service)
    {
        $campos = $this->campos($request);
        $data = $request->validate([
            'periodo' => ['required', 'integer', 'min:200001', 'max:210012'],
            'excel_actualizacion' => ['required', 'file', 'mimes:xlsx,xls', 'extensions:xlsx,xls', 'max:10240'],
        ]);
        try {
            $filas = $excel->leer($request->file('excel_actualizacion')->getRealPath(), $campos);
        } catch (ValidationException $error) {
            throw $error;
        } catch (\Throwable) {
            throw ValidationException::withMessages(['excel_actualizacion' => 'No se pudo leer el Excel. Verifique que no esté dañado ni protegido con contraseña.']);
        }
        $resumen = $service->actualizar($filas, $campos, (int) $data['periodo'], $request->user());
        return redirect()->route('reemplazos.personal.import')->with('actualizacion_datos', $resumen);
    }

    public function omitidos(Request $request, string $reporte, PadronDatosActualizacionService $service, PadronDatosExcel $excel)
    {
        $path = $service->reportePath((int) $request->user()->id, $reporte);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);
        $datos = json_decode($disk->get($path), true, 512, JSON_THROW_ON_ERROR);
        abort_unless((int) $datos['usuario_id'] === (int) $request->user()->id, 404);
        return $this->descargar($excel->documento(['Fila Excel', 'RUT', 'Motivo de omisión'], $datos['omitidos'], 'Registros omitidos'), 'registros_omitidos_'.$datos['periodo'].'.xlsx');
    }

    private function descargar(Spreadsheet $book, string $nombre)
    {
        return response()->streamDownload(function () use ($book) {
            try { (new Xlsx($book))->save('php://output'); }
            finally { $book->disconnectWorksheets(); }
        }, $nombre, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'private, no-store']);
    }
}
