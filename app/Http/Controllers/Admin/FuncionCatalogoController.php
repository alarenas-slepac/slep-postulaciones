<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\FuncionesCatalogoImport;
use App\Models\DeclaracionSostenedor;
use App\Models\FuncionCatalogo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FuncionCatalogoController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAdminDeclaracion();

        $q = trim((string) $request->query('q', ''));
        $query = FuncionCatalogo::query();
        if ($q !== '') {
            $query->where('nombre', 'like', "%{$q}%");
        }

        $items = $query->orderBy('nombre')->paginate(20)->withQueryString();
        return view('admin.funciones-catalogo.index', compact('items', 'q'));
    }

    public function create()
    {
        $this->authorizeAdminDeclaracion();
        $item = new FuncionCatalogo();
        return view('admin.funciones-catalogo.create', compact('item'));
    }

    public function store(Request $request)
    {
        $this->authorizeAdminDeclaracion();
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255', 'unique:funciones_catalogo,nombre'],
        ]);
        $data['nombre'] = $this->normalizeName($data['nombre']);
        FuncionCatalogo::create($data);
        return redirect()->route('admin.funciones-catalogo.index')->with('status', 'Función creada correctamente.');
    }

    public function show(FuncionCatalogo $funciones_catalogo)
    {
        $this->authorizeAdminDeclaracion();
        $item = $funciones_catalogo;
        return view('admin.funciones-catalogo.show', compact('item'));
    }

    public function edit(FuncionCatalogo $funciones_catalogo)
    {
        $this->authorizeAdminDeclaracion();
        $item = $funciones_catalogo;
        return view('admin.funciones-catalogo.edit', compact('item'));
    }

    public function update(Request $request, FuncionCatalogo $funciones_catalogo)
    {
        $this->authorizeAdminDeclaracion();
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255', 'unique:funciones_catalogo,nombre,' . $funciones_catalogo->id],
        ]);
        $data['nombre'] = $this->normalizeName($data['nombre']);
        $funciones_catalogo->update($data);
        return redirect()->route('admin.funciones-catalogo.index')->with('status', 'Función actualizada correctamente.');
    }

    public function destroy(FuncionCatalogo $funciones_catalogo)
    {
        $this->authorizeAdminDeclaracion();
        DeclaracionSostenedor::where('funcion_catalogo_id', $funciones_catalogo->id)
            ->update(['funcion_catalogo_id' => null]);
        $funciones_catalogo->delete();
        return redirect()->route('admin.funciones-catalogo.index')->with('status', 'Función eliminada.');
    }

    public function importForm()
    {
        $this->authorizeAdminDeclaracion();
        return redirect()->route('admin.funciones-catalogo.index');
    }

    public function importStore(Request $request)
    {
        $this->authorizeAdminDeclaracion();
        $request->validate(['archivo' => 'required|file|mimes:xlsx,xls,csv']);
        try {
            $result = (new FuncionesCatalogoImport())->import($request->file('archivo'));
            return redirect()->route('admin.funciones-catalogo.index')->with('status', 'Importación completada. Insertados: ' . $result['inserted'] . ', actualizados: ' . $result['updated'] . ', omitidos: ' . $result['skipped'] . '.');
        } catch (\Throwable $e) {
            return redirect()->route('admin.funciones-catalogo.index')->with('error', 'No fue posible importar funciones: ' . $e->getMessage());
        }
    }

    public function downloadTemplate()
    {
        $this->authorizeAdminDeclaracion();
        $path = resource_path('templates/plantilla-carga-funciones-catalogo.xlsx');

        abort_unless(is_file($path), 404);

        return response()->download($path, 'plantilla-carga-funciones-catalogo.xlsx');
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
