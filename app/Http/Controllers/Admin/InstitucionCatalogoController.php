<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\InstitucionesCatalogoImport;
use App\Models\DeclaracionSostenedor;
use App\Models\InstitucionCatalogo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InstitucionCatalogoController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAdminDeclaracion();

        $q = trim((string) $request->query('q', ''));
        $query = InstitucionCatalogo::query();
        if ($q !== '') {
            $query->where('nombre', 'like', "%{$q}%");
        }

        $items = $query->orderBy('nombre')->paginate(20)->withQueryString();
        return view('admin.instituciones-catalogo.index', compact('items', 'q'));
    }

    public function create()
    {
        $this->authorizeAdminDeclaracion();
        $item = new InstitucionCatalogo();
        return view('admin.instituciones-catalogo.create', compact('item'));
    }

    public function store(Request $request)
    {
        $this->authorizeAdminDeclaracion();
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255', 'unique:instituciones_catalogo,nombre'],
        ]);
        $data['nombre'] = $this->normalizeName($data['nombre']);
        InstitucionCatalogo::create($data);
        return redirect()->route('admin.instituciones-catalogo.index')->with('status', 'Institución creada correctamente.');
    }

    public function show(InstitucionCatalogo $instituciones_catalogo)
    {
        $this->authorizeAdminDeclaracion();
        $item = $instituciones_catalogo;
        return view('admin.instituciones-catalogo.show', compact('item'));
    }

    public function edit(InstitucionCatalogo $instituciones_catalogo)
    {
        $this->authorizeAdminDeclaracion();
        $item = $instituciones_catalogo;
        return view('admin.instituciones-catalogo.edit', compact('item'));
    }

    public function update(Request $request, InstitucionCatalogo $instituciones_catalogo)
    {
        $this->authorizeAdminDeclaracion();
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255', 'unique:instituciones_catalogo,nombre,' . $instituciones_catalogo->id],
        ]);
        $data['nombre'] = $this->normalizeName($data['nombre']);
        $instituciones_catalogo->update($data);
        DeclaracionSostenedor::where('institucion_catalogo_id', $instituciones_catalogo->id)
            ->update(['institucion_educacional' => $data['nombre']]);
        return redirect()->route('admin.instituciones-catalogo.index')->with('status', 'Institución actualizada correctamente.');
    }

    public function destroy(InstitucionCatalogo $instituciones_catalogo)
    {
        $this->authorizeAdminDeclaracion();
        DeclaracionSostenedor::where('institucion_catalogo_id', $instituciones_catalogo->id)
            ->update(['institucion_catalogo_id' => null]);
        $instituciones_catalogo->delete();
        return redirect()->route('admin.instituciones-catalogo.index')->with('status', 'Institución eliminada.');
    }

    public function importForm()
    {
        $this->authorizeAdminDeclaracion();
        return redirect()->route('admin.instituciones-catalogo.index');
    }

    public function importStore(Request $request)
    {
        $this->authorizeAdminDeclaracion();
        $request->validate(['archivo' => 'required|file|mimes:xlsx,xls,csv']);
        try {
            $result = (new InstitucionesCatalogoImport())->import($request->file('archivo'));
            return redirect()->route('admin.instituciones-catalogo.index')->with('status', 'Importación completada. Insertados: ' . $result['inserted'] . ', actualizados: ' . $result['updated'] . ', omitidos: ' . $result['skipped'] . '.');
        } catch (\Throwable $e) {
            return redirect()->route('admin.instituciones-catalogo.index')->with('error', 'No fue posible importar instituciones: ' . $e->getMessage());
        }
    }

    public function downloadTemplate()
    {
        $this->authorizeAdminDeclaracion();
        $path = resource_path('templates/plantilla-carga-instituciones-catalogo.xlsx');

        abort_unless(is_file($path), 404);

        return response()->download($path, 'plantilla-carga-instituciones-catalogo.xlsx');
    }

    protected function authorizeAdminDeclaracion(): void
    {
        $user = Auth::user();
        abort_unless($user, 403);
        $activeRole = method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null;
        abort_unless($activeRole === 'admin' && $user->canModule('declaracion', $activeRole), 403);
    }

    protected function normalizeName(string $value): string
    {
        return preg_replace('/\s+/', ' ', trim($value)) ?: trim($value);
    }
}
