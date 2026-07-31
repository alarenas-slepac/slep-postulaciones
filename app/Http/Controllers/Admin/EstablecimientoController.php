<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Establecimiento;
use App\Models\ReemplazoPersonal;
use App\Services\EstablecimientoImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EstablecimientoController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'ensure.role:admin|coordinador_gdp']);
        $this->middleware(['ensure.role:admin'])->only([
            'importForm',
            'importStore',
            'downloadTemplate',
        ]);
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->get('q', ''));
        $comuna = trim((string) $request->get('comuna', ''));
        $salaCuna = $request->get('sala_cuna', '');

        $items = Establecimiento::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('nombre_establecimiento', 'like', "%{$q}%")
                        ->orWhere('comuna', 'like', "%{$q}%")
                        ->orWhere('tipo_estab', 'like', "%{$q}%")
                        ->orWhere('rbd', 'like', "%{$q}%")
                        ->orWhere('cod_estab', 'like', "%{$q}%");
                });
            })
            ->when($comuna !== '', function ($query) use ($comuna) {
                $query->where('comuna', $comuna);
            })
            ->when($salaCuna !== '' && in_array((int) $salaCuna, [1, 2], true), function ($query) use ($salaCuna) {
                $salaCuna = (int) $salaCuna;
                if ($salaCuna === 1) {
                    $query->where('sala_cuna', 1);
                } else {
                    $query->whereIn('sala_cuna', [0, 2]);
                }
            })
            ->orderBy('comuna')
            ->orderBy('nombre_establecimiento')
            ->paginate(20)
            ->withQueryString();

        $comunas = Establecimiento::query()
            ->select('comuna')
            ->whereNotNull('comuna')
            ->where('comuna', '<>', '')
            ->distinct()
            ->orderBy('comuna')
            ->pluck('comuna');

        return view('admin.establecimientos.index', [
            'title' => 'Establecimientos',
            'items' => $items,
            'q' => $q,
            'comunas' => $comunas,
            'comuna' => $comuna,
            'sala_cuna' => $salaCuna,
        ]);
    }

    public function importForm(EstablecimientoImportService $service): View
    {
        return view('admin.establecimientos.import', [
            'title' => 'Carga masiva de establecimientos',
            'expectedHeaders' => $service->expectedHeaders(),
        ]);
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        $path = resource_path('templates/plantilla-carga-establecimientos.xlsx');

        abort_unless(is_file($path), 404);

        return response()->download($path, 'plantilla-carga-establecimientos.xlsx');
    }

    public function importStore(Request $request, EstablecimientoImportService $service): RedirectResponse
    {
        $request->validate([
            'excel' => ['required', 'file', 'mimes:xlsx,xls'],
            'truncate' => ['nullable', 'boolean'],
        ], [
            'excel.required' => 'Debes seleccionar un archivo Excel.',
            'excel.mimes' => 'El archivo debe ser .xlsx o .xls.',
        ]);

        $file = $request->file('excel');
        $disk = 'local';
        $dir = 'imports/establecimientos';
        Storage::disk($disk)->makeDirectory($dir);
        $storedPath = $file->store($dir, $disk);
        $fullPath = Storage::disk($disk)->path($storedPath);

        try {
            $summary = $service->importFromPath($fullPath, null, (bool) $request->boolean('truncate'));
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors([
                'excel' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('admin.establecimientos.import')
            ->with('status', sprintf(
                'Importación completada. Procesadas: %d. Creadas: %d. Actualizadas: %d. Omitidas: %d. Hoja: %s.',
                $summary['processed'],
                $summary['created'],
                $summary['updated'],
                $summary['skipped'],
                $summary['sheet_name']
            ))
            ->with('import_errors', $summary['errors']);
    }

    public function create(): View
    {
        return view('admin.establecimientos.create', [
            'title' => 'Nuevo establecimiento',
            'item' => new Establecimiento(),
        ]);
    }

    public function show(Establecimiento $establecimiento): View
    {
        $registros = ReemplazoPersonal::delEstablecimiento($establecimiento->id)
            ->orderBy('nombre')
            ->get();

        $totalJornada = $registros->sum('jornada');

        $funcionariosResumen = $registros
            ->filter(fn($r) => filled($r->rut))
            ->groupBy('rut')
            ->map(function (Collection $rows) {
                $first = $rows->first();
                $first->jornada_total = $rows->sum('jornada');
                $first->jornada_basica_total = $rows->sum('jornada_basica');
                $first->jornada_media_total = $rows->sum('jornada_media');
                return $first;
            })
            ->values();

        return view('admin.establecimientos.show', compact(
            'establecimiento',
            'registros',
            'funcionariosResumen',
            'totalJornada'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        Establecimiento::create($data);

        return redirect()
            ->route('admin.establecimientos.index')
            ->with('status', 'Establecimiento creado correctamente.');
    }

    public function edit(Establecimiento $establecimiento): View
    {
        return view('admin.establecimientos.edit', [
            'title' => 'Editar establecimiento',
            'item' => $establecimiento,
        ]);
    }

    public function update(Request $request, Establecimiento $establecimiento): RedirectResponse
    {
        $data = $this->validated($request, $establecimiento->id);
        $establecimiento->update($data);

        return redirect()
            ->route('admin.establecimientos.index')
            ->with('status', 'Establecimiento actualizado correctamente.');
    }

    public function destroy(Establecimiento $establecimiento): RedirectResponse
    {
        $establecimiento->delete();

        return redirect()
            ->route('admin.establecimientos.index')
            ->with('status', 'Establecimiento eliminado correctamente.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'cod_estab' => ['required', 'integer', 'min:0', 'unique:establecimientos,cod_estab' . ($ignoreId ? ",{$ignoreId}" : '')],
            'rbd' => ['required', 'integer', 'min:0', 'unique:establecimientos,rbd' . ($ignoreId ? ",{$ignoreId}" : '')],
            'dv' => ['nullable', 'string', 'max:2'],
            'nombre_establecimiento' => ['required', 'string', 'max:255'],
            'clasificacion' => ['nullable', 'string', 'max:255'],
            'tipo_estab' => ['nullable', 'string', 'max:80'],
            'comuna' => ['nullable', 'string', 'max:120'],
            'asignacion_zona' => ['nullable', 'integer', 'min:0', 'max:100'],
            'matricula_total' => ['nullable', 'integer', 'min:0'],
            'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'],
            'sala_cuna' => ['sometimes', 'boolean'],
            'unidocencia' => ['sometimes', 'boolean'],
            'pre_escolar' => ['sometimes', 'boolean'],
            'basica' => ['sometimes', 'boolean'],
            'media' => ['sometimes', 'boolean'],
            'tecnico_profesional' => ['sometimes', 'boolean'],
            'adultos' => ['sometimes', 'boolean'],
            'especial' => ['sometimes', 'boolean'],
        ]) + [
            'sala_cuna' => (bool) $request->boolean('sala_cuna'),
            'unidocencia' => (bool) $request->boolean('unidocencia'),
            'pre_escolar' => (bool) $request->boolean('pre_escolar'),
            'basica' => (bool) $request->boolean('basica'),
            'media' => (bool) $request->boolean('media'),
            'tecnico_profesional' => (bool) $request->boolean('tecnico_profesional'),
            'adultos' => (bool) $request->boolean('adultos'),
            'especial' => (bool) $request->boolean('especial'),
            'asignacion_zona' => (int) $request->input('asignacion_zona', 0),
            'matricula_total' => $request->filled('matricula_total') ? (int) $request->input('matricula_total') : null,
            'latitud' => $request->filled('latitud') ? (float) $request->input('latitud') : null,
            'longitud' => $request->filled('longitud') ? (float) $request->input('longitud') : null,
        ];
    }
}
