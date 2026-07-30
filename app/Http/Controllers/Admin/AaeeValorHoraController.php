<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AaeeValorHora;
use App\Models\AreaDesempeno;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AaeeValorHoraController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'ensure.role:admin|coordinador_gdp']);
    }

    public function index(Request $request)
    {
        $areaId = (string) $request->query('area', '');
        $categoria = (string) $request->query('categoria', '');

        $items = AaeeValorHora::query()
            ->with('areaDesempeno')
            ->when($areaId !== '', fn($q) => $q->where('area_desempeno_id', (int) $areaId))
            ->when($categoria !== '', fn($q) => $q->where('categoria', $categoria))
            ->orderBy('area_desempeno_id')
            ->orderBy('categoria')
            ->paginate(20)
            ->withQueryString();

        $areas = AreaDesempeno::query()
            ->where('estamento', 'asistente')
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $categorias = AaeeValorHora::categorias();

        return view('admin.aaee-valores-hora.index', compact('items', 'areas', 'categorias', 'areaId', 'categoria'));
    }

    public function create()
    {
        return view('admin.aaee-valores-hora.create', [
            'item' => new AaeeValorHora(['activo' => true]),
            'areas' => AreaDesempeno::query()->where('estamento', 'asistente')->orderBy('nombre')->get(['id', 'nombre']),
            'categorias' => AaeeValorHora::categorias(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'area_desempeno_id' => ['required', 'integer', 'exists:areas_desempeno,id'],
            'categoria' => ['required', Rule::in(AaeeValorHora::categorias())],
            'valor_hora' => ['required', 'numeric', 'min:0'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $data['activo'] = (bool) ($data['activo'] ?? false);

        AaeeValorHora::create($data);

        return redirect()->route('admin.aaee-valores-hora.index')->with('status', 'Valor hora AAEE creado.');
    }

    public function edit(AaeeValorHora $aaee_valor_hora)
    {
        return view('admin.aaee-valores-hora.edit', [
            'item' => $aaee_valor_hora,
            'areas' => AreaDesempeno::query()->where('estamento', 'asistente')->orderBy('nombre')->get(['id', 'nombre']),
            'categorias' => AaeeValorHora::categorias(),
        ]);
    }

    public function update(Request $request, AaeeValorHora $aaee_valor_hora)
    {
        $data = $request->validate([
            'area_desempeno_id' => ['required', 'integer', 'exists:areas_desempeno,id'],
            'categoria' => ['required', Rule::in(AaeeValorHora::categorias())],
            'valor_hora' => ['required', 'numeric', 'min:0'],
            'activo' => ['nullable', 'boolean'],
        ]);

        // unique compuesto (area + categoria)
        $exists = AaeeValorHora::query()
            ->where('id', '<>', $aaee_valor_hora->id)
            ->where('area_desempeno_id', $data['area_desempeno_id'])
            ->where('categoria', $data['categoria'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['categoria' => 'Ya existe un valor hora para esa Área y Categoría.'])->withInput();
        }

        $data['activo'] = (bool) ($data['activo'] ?? false);

        $aaee_valor_hora->update($data);

        return redirect()->route('admin.aaee-valores-hora.index')->with('status', 'Valor hora AAEE actualizado.');
    }

    public function destroy(AaeeValorHora $aaee_valor_hora)
    {
        $aaee_valor_hora->delete();
        return redirect()->route('admin.aaee-valores-hora.index')->with('status', 'Valor hora AAEE eliminado.');
    }
}
