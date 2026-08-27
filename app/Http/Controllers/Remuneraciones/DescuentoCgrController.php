<?php

namespace App\Http\Controllers\Remuneraciones;

use App\Exports\DescuentosCgrMensualExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Remuneraciones\GuardarDescuentoCgrRequest;
use App\Models\DescuentoCgr;
use App\Services\Remuneraciones\CronogramaDescuentoCgrService;
use App\Services\Remuneraciones\DescuentoCgrPdfService;
use App\Services\Remuneraciones\ReemplazoPersonalRutService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DescuentoCgrController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'ensure.role:admin|funcionario_slep']);
    }

    public function index(Request $request): View|StreamedResponse
    {
        if ($request->boolean('exportar')) {
            $data = $request->validate([
                'mes_exportacion' => ['required', 'date_format:Y-m'],
            ], [
                'mes_exportacion.required' => 'Selecciona el mes que deseas exportar.',
                'mes_exportacion.date_format' => 'El mes de exportación no tiene un formato válido.',
            ]);
            $periodo = CarbonImmutable::createFromFormat('!Y-m', $data['mes_exportacion']);

            return app(DescuentosCgrMensualExport::class)->download($periodo, $request->user());
        }

        $buscar = trim((string) $request->get('buscar', ''));
        $buscarRut = preg_match('/\d/', $buscar)
            ? strtoupper((string) preg_replace('/[^0-9K]/i', '', $buscar))
            : '';
        $anio = (int) $request->integer('anio');
        $origenes = ReemplazoPersonalRutService::opcionesOrigen() + ['sin_clasificar' => 'Sin clasificar'];
        $origen = trim((string) $request->get('origen', ''));
        if (! array_key_exists($origen, $origenes)) {
            $origen = '';
        }

        $descuentos = DescuentoCgr::query()
            ->when($buscar !== '', function ($query) use ($buscar, $buscarRut) {
                $termino = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $buscar).'%';
                $query->where(function ($subquery) use ($termino, $buscarRut) {
                    $subquery->where('nombre', 'like', $termino)
                        ->orWhere('rut', 'like', $termino);

                    if ($buscarRut !== '') {
                        $rut = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $buscarRut).'%';
                        $subquery->orWhereRaw("REPLACE(REPLACE(REPLACE(UPPER(rut), '.', ''), '-', ''), ' ', '') LIKE ?", [$rut]);
                    }
                });
            })
            ->when($origen === 'sin_clasificar', fn ($query) => $query->whereNull('origen_funcionario'))
            ->when($origen !== '' && $origen !== 'sin_clasificar', fn ($query) => $query->where('origen_funcionario', $origen))
            ->when($anio > 0, fn ($query) => $query->whereYear('fecha_primer_descuento', $anio))
            ->orderByDesc('fecha_primer_descuento')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $anios = DescuentoCgr::query()
            ->whereNotNull('fecha_primer_descuento')
            ->select('fecha_primer_descuento')
            ->distinct()
            ->pluck('fecha_primer_descuento')
            ->map(fn ($fecha) => (int) substr((string) $fecha, 0, 4))
            ->filter()
            ->unique()
            ->sortDesc()
            ->values();

        return view('remuneraciones.descuentos-cgr.index', compact('descuentos', 'buscar', 'anio', 'anios', 'origen', 'origenes'));
    }

    public function create(): View
    {
        return view('remuneraciones.descuentos-cgr.form', ['descuentoCgr' => new DescuentoCgr]);
    }

    public function store(GuardarDescuentoCgrRequest $request, ReemplazoPersonalRutService $funcionarios): RedirectResponse
    {
        $data = $this->datosPersistencia($request, $funcionarios);
        $data += $this->guardarPdf($request);
        $data += ['creado_por_id' => $request->user()->id, 'actualizado_por_id' => $request->user()->id];

        $descuento = DescuentoCgr::create($data);

        return redirect()->route('descuentos-cgr.show', $descuento)
            ->with('status', 'Descuento CGR registrado. El cronograma fue calculado con los valores UTM disponibles.');
    }

    public function show(DescuentoCgr $descuentoCgr, CronogramaDescuentoCgrService $cronograma): View
    {
        $descuentoCgr->load('creadoPor', 'actualizadoPor');
        $calculo = $cronograma->calcular($descuentoCgr);

        return view('remuneraciones.descuentos-cgr.show', compact('descuentoCgr', 'calculo'));
    }

    public function edit(DescuentoCgr $descuentoCgr): View
    {
        return view('remuneraciones.descuentos-cgr.form', compact('descuentoCgr'));
    }

    public function buscarFuncionario(Request $request, ReemplazoPersonalRutService $funcionarios): JsonResponse
    {
        $data = $request->validate(['rut' => ['required', 'string', 'max:30']]);
        $rutNormalizado = $funcionarios->normalizar($data['rut']);

        if (! $rutNormalizado) {
            return response()->json(['message' => 'El RUT ingresado no es válido.'], 422);
        }

        $funcionario = $funcionarios->buscar($rutNormalizado);
        if (! $funcionario) {
            return response()->json([
                'message' => 'No se encontró el RUT en funcionarios autorizados de Administración Central ni en el padrón de reemplazos personal.',
                'rut' => $rutNormalizado,
            ], 404);
        }

        return response()->json($funcionario);
    }

    public function update(
        GuardarDescuentoCgrRequest $request,
        DescuentoCgr $descuentoCgr,
        ReemplazoPersonalRutService $funcionarios
    ): RedirectResponse {
        $data = $this->datosPersistencia($request, $funcionarios, $descuentoCgr);
        $data['actualizado_por_id'] = $request->user()->id;

        if ($request->hasFile('resolucion_pdf')) {
            $data += $this->guardarPdf($request);
        }

        $descuentoCgr->update($data);

        return redirect()->route('descuentos-cgr.show', $descuentoCgr)
            ->with('status', 'Descuento CGR actualizado y cronograma recalculado.');
    }

    public function destroy(DescuentoCgr $descuentoCgr): RedirectResponse
    {
        $resolucionPdfPath = $descuentoCgr->resolucion_pdf_path;

        DB::transaction(function () use ($descuentoCgr): void {
            // La eliminación explícita conserva el comportamiento en instalaciones
            // históricas donde la llave foránea pudiera no tener ON DELETE CASCADE.
            $descuentoCgr->documentosMensuales()->delete();
            $descuentoCgr->delete();
        });

        if ($resolucionPdfPath) {
            Storage::disk('local')->delete($resolucionPdfPath);
        }

        return redirect()->route('descuentos-cgr.index')
            ->with('status', 'Descuento CGR eliminado junto con su cronograma asociado.');
    }

    public function pdf(DescuentoCgr $descuentoCgr): mixed
    {
        abort_unless(Storage::disk('local')->exists($descuentoCgr->resolucion_pdf_path), 404);

        return Storage::disk('local')->response(
            $descuentoCgr->resolucion_pdf_path,
            $descuentoCgr->resolucion_pdf_nombre,
            ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline']
        );
    }

    public function informePdf(DescuentoCgr $descuentoCgr, DescuentoCgrPdfService $documentos): mixed
    {
        $nombre = 'informe-descuento-cgr-'.Str::slug($descuentoCgr->numero_resolucion)
            .'-'.Str::slug($descuentoCgr->rut).'.pdf';

        return response($documentos->generar($descuentoCgr), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
        ]);
    }

    public function cronogramaPdf(
        DescuentoCgr $descuentoCgr,
        int $cuota,
        DescuentoCgrPdfService $documentos
    ): mixed {
        $resultado = $documentos->generarMensual($descuentoCgr, $cuota);
        $periodo = $resultado['fila']['periodo']->format('Y-m');
        $nombre = 'descuento-cgr-'.Str::slug($descuentoCgr->rut).'-'.$periodo.'.pdf';

        return response($resultado['contenido'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nombre.'"',
        ]);
    }

    private function datosPersistencia(
        GuardarDescuentoCgrRequest $request,
        ReemplazoPersonalRutService $funcionarios,
        ?DescuentoCgr $existente = null
    ): array {
        $data = Arr::except($request->validated(), ['resolucion_pdf']);
        $funcionario = $funcionarios->buscar($data['rut']);

        if ($funcionario) {
            $mismoRut = $existente
                && $funcionarios->normalizar($data['rut']) === $funcionarios->normalizar($existente->rut);
            $data['rut'] = $funcionario['rut'];
            $data['nombre'] = $funcionario['nombre'];
            $data['origen_funcionario'] = $mismoRut && $existente->origen_funcionario
                ? $existente->origen_funcionario
                : $funcionario['origen'];

            return $data;
        }

        $rutNormalizado = $funcionarios->normalizar($data['rut']);
        if ($existente && $rutNormalizado === $funcionarios->normalizar($existente->rut)) {
            $data['rut'] = $rutNormalizado;
            $data['nombre'] = $existente->nombre;

            return $data;
        }

        throw ValidationException::withMessages([
            'rut' => 'No se encontró el RUT en funcionarios autorizados de Administración Central ni en el padrón de reemplazos personal.',
        ]);
    }

    private function guardarPdf(GuardarDescuentoCgrRequest $request): array
    {
        $archivo = $request->file('resolucion_pdf');
        $anio = substr((string) $request->validated('fecha_primer_descuento'), 0, 4);
        $nombre = Str::uuid().'.pdf';
        $path = $archivo->storeAs("descuentos-cgr/resoluciones/{$anio}", $nombre, 'local');

        return [
            'resolucion_pdf_path' => $path,
            'resolucion_pdf_nombre' => $archivo->getClientOriginalName(),
            'resolucion_pdf_tamano' => $archivo->getSize(),
        ];
    }
}
