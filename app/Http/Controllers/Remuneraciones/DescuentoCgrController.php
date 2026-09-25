<?php

namespace App\Http\Controllers\Remuneraciones;

use App\Exports\DescuentosCgrMensualExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Remuneraciones\GuardarDescuentoCgrRequest;
use App\Models\DescuentoCgr;
use App\Services\Remuneraciones\CronogramaDescuentoCgrService;
use App\Services\Remuneraciones\DescuentoCgrIndicadoresService;
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
        $this->middleware(['auth', 'ensure.role:admin|funcionario_slep|funcionario_daf|auditoria_slep']);
    }

    public function index(Request $request): View|StreamedResponse
    {
        if ($request->boolean('exportar')) {
            abort_unless($request->user()->hasAnyRole(['admin', 'funcionario_slep']), 403);
            $data = $request->validate([
                'mes_exportacion' => ['required', 'date_format:Y-m'],
            ], [
                'mes_exportacion.required' => 'Selecciona el mes que deseas exportar.',
                'mes_exportacion.date_format' => 'El mes de exportación no tiene un formato válido.',
            ]);
            $periodo = CarbonImmutable::createFromFormat('!Y-m', $data['mes_exportacion']);

            return app(DescuentosCgrMensualExport::class)->download($periodo, $request->user());
        }

        $estado = (string) $request->get('estado', 'ingresado');
        if (! in_array($estado, ['ingresado', 'descuentos_realizados', 'en_auditoria', 'cerrado'], true)) {
            $estado = 'ingresado';
        }

        $claveFiltros = 'descuentos_cgr.filtros.'.$estado;
        $camposFiltro = ['buscar', 'origen', 'anio', 'ultimo_mes', 'mes_descuento'];
        if ($request->boolean('limpiar')) {
            if ($request->hasSession()) {
                $request->session()->forget($claveFiltros);
            }
            $filtros = [];
        } elseif ($request->boolean('filtrar') || $request->hasAny($camposFiltro)) {
            $filtros = $request->validate([
                'buscar' => ['nullable', 'string', 'max:255'],
                'origen' => ['nullable', 'string', 'max:40'],
                'anio' => ['nullable', 'integer', 'min:1', 'max:9999'],
                'ultimo_mes' => ['nullable', 'date_format:Y-m'],
                'mes_descuento' => ['nullable', 'date_format:Y-m'],
            ]);
            if ($request->hasSession()) {
                $request->session()->put($claveFiltros, $filtros);
            }
        } else {
            $filtros = $request->hasSession() ? (array) $request->session()->get($claveFiltros, []) : [];
        }

        $buscar = trim((string) ($filtros['buscar'] ?? ''));
        $buscarRut = preg_match('/\d/', $buscar)
            ? strtoupper((string) preg_replace('/[^0-9K]/i', '', $buscar))
            : '';
        $anio = (int) ($filtros['anio'] ?? 0);
        $origenes = ReemplazoPersonalRutService::opcionesOrigen() + ['sin_clasificar' => 'Sin clasificar'];
        $origen = trim((string) ($filtros['origen'] ?? ''));
        $ultimoMes = (string) ($filtros['ultimo_mes'] ?? '');
        $mesDescuento = (string) ($filtros['mes_descuento'] ?? '');
        if (! array_key_exists($origen, $origenes)) {
            $origen = '';
        }
        $indicePrimerMes = DB::connection()->getDriverName() === 'sqlite'
            ? "(CAST(strftime('%Y', fecha_primer_descuento) AS INTEGER) * 12 + CAST(strftime('%m', fecha_primer_descuento) AS INTEGER))"
            : '(YEAR(fecha_primer_descuento) * 12 + MONTH(fecha_primer_descuento))';
        $mesIndice = static function (string $mes): int {
            return (int) substr($mes, 0, 4) * 12 + (int) substr($mes, 5, 2);
        };

        $consulta = DescuentoCgr::query()
            ->where('estado', $estado)
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
            ->when($ultimoMes !== '', fn ($query) => $query->whereRaw("{$indicePrimerMes} + numero_cuotas - 1 = ?", [$mesIndice($ultimoMes)]))
            ->when($mesDescuento !== '', fn ($query) => $query
                ->whereRaw("{$indicePrimerMes} <= ?", [$mesIndice($mesDescuento)])
                ->whereRaw("{$indicePrimerMes} + numero_cuotas - 1 >= ?", [$mesIndice($mesDescuento)]));

        $indicadores = app(DescuentoCgrIndicadoresService::class)->calcular($consulta, $estado);
        $descuentos = (clone $consulta)
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

        $conteos = DescuentoCgr::query()->selectRaw('estado, COUNT(*) as total')->groupBy('estado')->pluck('total', 'estado');

        $filtrosActivos = $buscar !== '' || $origen !== '' || $anio > 0 || $ultimoMes !== '' || $mesDescuento !== '';

        return view('remuneraciones.descuentos-cgr.index', compact('descuentos', 'buscar', 'anio', 'anios', 'origen', 'origenes', 'estado', 'conteos', 'ultimoMes', 'mesDescuento', 'filtrosActivos', 'indicadores'));
    }

    public function create(): View
    {
        abort_unless(request()->user()->hasAnyRole(['admin', 'funcionario_slep']), 403);
        return view('remuneraciones.descuentos-cgr.form', ['descuentoCgr' => new DescuentoCgr]);
    }

    public function store(GuardarDescuentoCgrRequest $request, ReemplazoPersonalRutService $funcionarios): RedirectResponse
    {
        $data = $this->datosPersistencia($request, $funcionarios);
        $data += $this->guardarPdf($request);
        $data += ['estado' => 'ingresado', 'creado_por_id' => $request->user()->id, 'actualizado_por_id' => $request->user()->id];

        $descuento = DescuentoCgr::create($data);

        return redirect()->route('descuentos-cgr.show', $descuento)
            ->with('status', 'Descuento CGR registrado. El cronograma fue calculado con los valores UTM disponibles.');
    }

    public function show(DescuentoCgr $descuentoCgr, CronogramaDescuentoCgrService $cronograma): View
    {
        $descuentoCgr->load('creadoPor', 'actualizadoPor', 'archivos.cargadoPor');
        $calculo = $cronograma->calcular($descuentoCgr);

        return view('remuneraciones.descuentos-cgr.show', compact('descuentoCgr', 'calculo'));
    }

    public function edit(DescuentoCgr $descuentoCgr): View
    {
        $this->autorizarEdicion($descuentoCgr);
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
        $this->autorizarEdicion($descuentoCgr);
        $data = $this->datosPersistencia($request, $funcionarios, $descuentoCgr);
        if ($descuentoCgr->archivos()->exists()) {
            foreach (['numero_cuotas', 'fecha_primer_descuento', 'deuda_definitiva_pesos', 'deuda_equivalente_utm', 'cuota_utm', 'tasa_interes_anual', 'tasa_interes_mensual'] as $campo) {
                $actual = $campo === 'fecha_primer_descuento' ? $descuentoCgr->fecha_primer_descuento?->toDateString() : (float) $descuentoCgr->{$campo};
                $nuevo = $campo === 'fecha_primer_descuento' ? (string) ($data[$campo] ?? '') : (float) ($data[$campo] ?? 0);
                if ($campo === 'fecha_primer_descuento' ? $actual !== $nuevo : abs($actual - $nuevo) > 0.00001) {
                    throw ValidationException::withMessages([$campo => 'No se pueden cambiar los parámetros del cronograma después de cargar liquidaciones.']);
                }
            }
        }
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
        $this->autorizarEdicion($descuentoCgr);
        $resolucionPdfPath = $descuentoCgr->resolucion_pdf_path;
        $archivosPaths = $descuentoCgr->archivos()->pluck('path')->unique()->all();

        DB::transaction(function () use ($descuentoCgr): void {
            // La eliminación explícita conserva el comportamiento en instalaciones
            // históricas donde la llave foránea pudiera no tener ON DELETE CASCADE.
            $descuentoCgr->documentosMensuales()->delete();
            $descuentoCgr->archivos()->delete();
            $descuentoCgr->delete();
        });

        if ($resolucionPdfPath) {
            Storage::disk('local')->delete($resolucionPdfPath);
        }
        Storage::disk('local')->delete($archivosPaths);

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

    private function autorizarEdicion(DescuentoCgr $descuento): void
    {
        abort_unless(request()->user()?->hasAnyRole(['admin', 'funcionario_slep']), 403);
        abort_unless($descuento->estadoActual() === 'ingresado', 403);
    }
}
