<?php

namespace App\Http\Controllers\Tramites;

use App\Http\Controllers\Controller;
use App\Models\Establecimiento;
use App\Models\MaternidadCaso;
use App\Models\MaternidadDocumento;
use App\Models\MaternidadImportacion;
use App\Services\Maternidad\MaternidadImportador;
use App\Services\Maternidad\MaternidadAntecedentesService;
use App\Services\Maternidad\MaternidadPersonalResolver;
use App\Services\Maternidad\MaternidadRegistroService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class MaternidadController extends Controller
{
    public function index(Request $request)
    {
        $filtros = $request->validate(['estamento' => ['nullable', Rule::in(['asistente', 'docente'])],
            'q' => ['nullable', 'string', 'max:150'], 'establecimiento_id' => ['nullable', 'integer'],
            'estado' => ['nullable', Rule::in(['vigente', 'vencido', 'sin_fecha', 'pendiente'])]]);
        $estamento = $filtros['estamento'] ?? 'asistente';
        $base = MaternidadCaso::query()
            ->when(filled($filtros['q'] ?? null), function ($q) use ($filtros) {
                $texto = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filtros['q']).'%';
                $rut = preg_replace('/[^0-9Kk]/', '', $filtros['q']);
                $q->where(function ($q) use ($texto, $rut) {
                    foreach (['nombres', 'apellido_paterno', 'apellido_materno', 'referencia_caso'] as $campo) $q->orWhere($campo, 'like', $texto);
                    if ($rut !== '') $q->orWhereRaw("REPLACE(rut, '-', '') LIKE ?", ['%'.strtoupper($rut).'%']);
                });
            })
            ->when($filtros['establecimiento_id'] ?? null, fn ($q, $id) => $q->where('establecimiento_id', $id));
        $conteos = (clone $base)->selectRaw('estamento, COUNT(*) AS cantidad')->groupBy('estamento')->pluck('cantidad', 'estamento');
        $query = (clone $base)->where('estamento', $estamento);
        $estado = $filtros['estado'] ?? '';
        if ($estado === 'pendiente') $query->whereJsonLength('documentos_pendientes', '>', 0);
        if ($estado === 'vigente') $query->whereDate('fecha_termino_fuero', '>=', today());
        if ($estado === 'vencido') $query->whereDate('fecha_termino_fuero', '<', today());
        if ($estado === 'sin_fecha') $query->whereNull('fecha_termino_fuero');
        $casos = $query->orderByDesc('fecha_registro')->orderByDesc('id')->paginate(20)->withQueryString();
        $establecimientos = Establecimiento::orderBy('nombre_establecimiento')->get(['id', 'rbd', 'nombre_establecimiento']);
        return response()->view('tramites.maternidad.index', compact('casos', 'estamento', 'conteos', 'filtros', 'establecimientos'))->header('Cache-Control', 'private, no-store');
    }

    public function buscar(Request $request)
    {
        $datos = $request->validate(['rut' => ['required', 'string', 'max:32']]);
        return response()->json(['candidatos' => app(MaternidadPersonalResolver::class)->buscar($datos['rut'])])->header('Cache-Control', 'private, no-store');
    }

    public function create(Request $request)
    {
        $candidatos = []; $seleccionado = null;
        if (filled($request->query('rut'))) {
            $request->validate(['rut' => ['string', 'max:32'], 'fuente' => ['nullable', 'string', 'max:50']]);
            $candidatos = app(MaternidadPersonalResolver::class)->buscar($request->query('rut'));
            $seleccionado = collect($candidatos)->firstWhere('fuente', $request->query('fuente'))
                ?? (count($candidatos) === 1 ? $candidatos[0] : null);
        } elseif (is_string($request->old('rut')) && is_string($request->old('fuente')) && filled($request->old('fuente'))) {
            try {
                $candidatos = app(MaternidadPersonalResolver::class)->buscar($request->old('rut'));
                $seleccionado = collect($candidatos)->firstWhere('fuente', $request->old('fuente'));
            } catch (ValidationException) { /* Conservar el resumen de errores del envío. */ }
        }
        return response()->view('tramites.maternidad.form', ['caso' => null, 'candidatos' => $candidatos, 'seleccionado' => $seleccionado])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request)
    {
        $request->validate(['documentos' => ['nullable', 'array']]);
        $service = app(MaternidadRegistroService::class);
        $archivos = $request->file('documentos', []);
        $preparado = $service->preparar($request->input(), $archivos);
        $caso = $service->guardar($preparado, $archivos, (int) $request->user()->id);
        return redirect()->route('tramites.maternidad.show', $caso)->with('success', 'Caso de maternidad registrado.');
    }

    public function show(Request $request, MaternidadCaso $caso)
    {
        $request->validate(['licencias_page' => ['nullable', 'integer', 'min:1', 'max:100000'], 'reemplazos_page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        $caso->load(['hijos', 'documentos', 'historial.autor']);
        $antecedentes = app(MaternidadAntecedentesService::class)->obtener($caso);
        return response()->view('tramites.maternidad.show', compact('caso', 'antecedentes'))->header('Cache-Control', 'private, no-store');
    }

    public function edit(MaternidadCaso $caso)
    {
        $caso->load(['hijos', 'documentos']);
        return response()->view('tramites.maternidad.form', ['caso' => $caso, 'candidatos' => [], 'seleccionado' => null])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, MaternidadCaso $caso)
    {
        $request->validate(['version' => ['required', 'integer', 'min:1'], 'documentos' => ['nullable', 'array']]);
        $service = app(MaternidadRegistroService::class);
        $archivos = $request->file('documentos', []);
        $preparado = $service->preparar($request->input(), $archivos, $caso);
        $service->guardar($preparado, $archivos, (int) $request->user()->id, $caso, (int) $request->input('version'));
        return redirect()->route('tramites.maternidad.show', $caso)->with('success', 'Caso actualizado. Se conserva el historial y las versiones anteriores de los documentos.');
    }

    public function observar(Request $request, MaternidadCaso $caso)
    {
        $datos = $request->validate(['observacion' => ['required', 'string', 'max:5000']]);
        $texto = trim($datos['observacion']);
        if ($texto === '') throw ValidationException::withMessages(['observacion' => 'Escriba una observación.']);
        DB::transaction(function () use ($caso, $texto, $request) {
            $actual = MaternidadCaso::whereKey($caso->id)->lockForUpdate()->firstOrFail();
            $actual->historial()->create(['evento' => 'observacion', 'observacion' => $texto,
                'created_by' => $request->user()->id, 'created_at' => now()]);
            $actual->update(['version' => $actual->version + 1, 'updated_by' => $request->user()->id]);
        });
        return redirect()->route('tramites.maternidad.show', $caso)->with('success', 'Observación incorporada al historial.');
    }

    public function documento(MaternidadCaso $caso, MaternidadDocumento $documento)
    {
        abort_unless((int) $documento->maternidad_caso_id === (int) $caso->id, 404);
        abort_unless(str_starts_with($documento->path, 'maternidad/casos/'.$caso->uuid.'/') && ! str_contains($documento->path, '..'), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($documento->path), 404, 'El archivo no está disponible.');
        $real = realpath($disk->path($documento->path));
        $raiz = realpath($disk->path('maternidad/casos/'.$caso->uuid));
        abort_unless($real && $raiz && str_starts_with(str_replace('\\', '/', $real), str_replace('\\', '/', $raiz).'/'), 404);
        return $disk->download($documento->path, basename($documento->nombre_original), [
            'Content-Type' => $documento->mime, 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "sandbox; default-src 'none'",
        ]);
    }

    public function importar()
    {
        return response()->view('tramites.maternidad.importar')->header('Cache-Control', 'private, no-store');
    }

    public function plantilla()
    {
        $libro = app(MaternidadImportador::class)->plantilla();
        return response()->streamDownload(function () use ($libro) {
            try { (new Xlsx($libro))->save('php://output'); } finally { $libro->disconnectWorksheets(); }
        }, 'plantilla_maternidad.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function previsualizar(Request $request)
    {
        $request->validate(['archivo' => ['required', 'file', 'mimes:xlsx', 'max:10240']]);
        try {
            $importacion = app(MaternidadImportador::class)->previsualizar($request->file('archivo'), (int) $request->user()->id);
        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception|\PhpOffice\PhpSpreadsheet\Exception $error) {
            throw ValidationException::withMessages(['archivo' => 'No se pudo leer el libro. Descargue la plantilla y revise el archivo XLSX.']);
        }
        return redirect()->route('tramites.maternidad.importacion', $importacion);
    }

    public function revisar(Request $request, MaternidadImportacion $importacion)
    {
        abort_unless((int) $importacion->user_id === (int) $request->user()->id, 403);
        if (! $importacion->procesada_at && $importacion->expira_at->isPast()) throw ValidationException::withMessages(['importacion' => 'La previsualización expiró. Cargue nuevamente el archivo.']);
        return response()->view('tramites.maternidad.previsualizacion', compact('importacion'))->header('Cache-Control', 'private, no-store');
    }

    public function confirmar(Request $request, MaternidadImportacion $importacion)
    {
        $cantidad = app(MaternidadImportador::class)->confirmar($importacion, (int) $request->user()->id);
        return redirect()->route('tramites.maternidad.index')->with('success', $cantidad.' caso(s) registrados. Complete los documentos pendientes desde cada expediente.');
    }
}
