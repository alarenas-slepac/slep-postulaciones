<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\TitulosCatalogoImport;
use App\Models\TituloCatalogo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TituloCatalogoController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAdminDeclaracion();

        $q = trim((string) $request->query('q', ''));
        $query = TituloCatalogo::query();
        if ($q !== '') {
            $query->where('nombre', 'like', "%{$q}%");
        }

        $items = $query->orderBy('nombre')->paginate(20)->withQueryString();
        return view('admin.titulos-catalogo.index', compact('items', 'q'));
    }

    public function create()
    {
        $this->authorizeAdminDeclaracion();
        $item = new TituloCatalogo();
        return view('admin.titulos-catalogo.create', compact('item'));
    }

    public function store(Request $request)
    {
        $this->authorizeAdminDeclaracion();
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255', 'unique:titulos_catalogo,nombre'],
        ]);
        $data['nombre'] = $this->normalizeName($data['nombre']);
        TituloCatalogo::create($data);
        return redirect()->route('admin.titulos-catalogo.index')->with('status', 'Título creado correctamente.');
    }

    public function show(TituloCatalogo $titulos_catalogo)
    {
        $this->authorizeAdminDeclaracion();
        $item = $titulos_catalogo;
        return view('admin.titulos-catalogo.show', compact('item'));
    }

    public function edit(TituloCatalogo $titulos_catalogo)
    {
        $this->authorizeAdminDeclaracion();
        $item = $titulos_catalogo;
        return view('admin.titulos-catalogo.edit', compact('item'));
    }

    public function update(Request $request, TituloCatalogo $titulos_catalogo)
    {
        $this->authorizeAdminDeclaracion();
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255', 'unique:titulos_catalogo,nombre,' . $titulos_catalogo->id],
        ]);
        $data['nombre'] = $this->normalizeName($data['nombre']);
        $titulos_catalogo->update($data);
        return redirect()->route('admin.titulos-catalogo.index')->with('status', 'Título actualizado correctamente.');
    }

    public function destroy(TituloCatalogo $titulos_catalogo)
    {
        $this->authorizeAdminDeclaracion();
        $titulos_catalogo->delete();
        return redirect()->route('admin.titulos-catalogo.index')->with('status', 'Título eliminado.');
    }

    public function importForm()
    {
        $this->authorizeAdminDeclaracion();
        return redirect()->route('admin.titulos-catalogo.index');
    }

    public function importStore(Request $request)
    {
        $this->authorizeAdminDeclaracion();
        $request->validate(['archivo' => 'required|file|mimes:xlsx,xls,csv']);
        try {
            $result = (new TitulosCatalogoImport())->import($request->file('archivo'));
            return redirect()->route('admin.titulos-catalogo.index')->with('status', 'Importación completada. Insertados: ' . $result['inserted'] . ', actualizados: ' . $result['updated'] . ', omitidos: ' . $result['skipped'] . '.');
        } catch (\Throwable $e) {
            return redirect()->route('admin.titulos-catalogo.index')->with('error', 'No fue posible importar títulos: ' . $e->getMessage());
        }
    }

    public function downloadTemplate()
    {
        $this->authorizeAdminDeclaracion();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Titulos');
        $sheet->fromArray([
            ['nombre_titulo'],
            ['Profesor de Educación General Básica'],
            ['Educadora de Párvulos'],
        ], null, 'A1');
        $sheet->getColumnDimension('A')->setAutoSize(true);

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 'plantilla_titulos_catalogo.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
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
