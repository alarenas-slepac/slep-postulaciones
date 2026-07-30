<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Establecimiento;
use App\Models\EstablecimientoValorHora;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EstablecimientoValorHoraController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'ensure.role:admin|coordinador_gdp']);
    }

    public function index(Request $request)
    {
        $establecimientoId = (string) $request->query('establecimiento', '');
        $rol = (string) $request->query('rol', '');

        $items = EstablecimientoValorHora::query()
            ->with('establecimiento')
            ->when($establecimientoId !== '', fn($q) => $q->where('establecimiento_id', (int) $establecimientoId))
            ->when($rol !== '', fn($q) => $q->where('rol', $rol))
            ->orderBy('establecimiento_id')
            ->orderBy('rol')
            ->paginate(20)
            ->withQueryString();

        $establecimientos = Establecimiento::query()
            ->orderBy('rbd')
            ->orderBy('nombre_establecimiento')
            ->get(['id', 'rbd', 'nombre_establecimiento']);

        $roles = EstablecimientoValorHora::roles();

        return view('admin.establecimiento-valores-hora.index', compact('items', 'establecimientos', 'roles', 'establecimientoId', 'rol'));
    }

    public function create()
    {
        return view('admin.establecimiento-valores-hora.create', [
            'item' => new EstablecimientoValorHora(['activo' => true]),
            'establecimientos' => Establecimiento::query()->orderBy('rbd')->orderBy('nombre_establecimiento')->get(['id', 'rbd', 'nombre_establecimiento']),
            'roles' => EstablecimientoValorHora::roles(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'establecimiento_id' => ['required', 'integer', 'exists:establecimientos,id'],
            'rol' => ['required', Rule::in(array_keys(EstablecimientoValorHora::roles()))],
            'valor_hora' => ['required', 'numeric', 'min:0'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $data['activo'] = (bool) ($data['activo'] ?? false);

        EstablecimientoValorHora::create($data);

        return redirect()->route('admin.establecimiento-valores-hora.index')->with('status', 'Valor hora por establecimiento creado.');
    }

    public function edit(EstablecimientoValorHora $establecimiento_valor_hora)
    {
        return view('admin.establecimiento-valores-hora.edit', [
            'item' => $establecimiento_valor_hora,
            'establecimientos' => Establecimiento::query()->orderBy('rbd')->orderBy('nombre_establecimiento')->get(['id', 'rbd', 'nombre_establecimiento']),
            'roles' => EstablecimientoValorHora::roles(),
        ]);
    }

    public function update(Request $request, EstablecimientoValorHora $establecimiento_valor_hora)
    {
        $data = $request->validate([
            'establecimiento_id' => ['required', 'integer', 'exists:establecimientos,id'],
            'rol' => ['required', Rule::in(array_keys(EstablecimientoValorHora::roles()))],
            'valor_hora' => ['required', 'numeric', 'min:0'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $exists = EstablecimientoValorHora::query()
            ->where('id', '<>', $establecimiento_valor_hora->id)
            ->where('establecimiento_id', $data['establecimiento_id'])
            ->where('rol', $data['rol'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['rol' => 'Ya existe un valor hora para ese Establecimiento y Rol.'])->withInput();
        }

        $data['activo'] = (bool) ($data['activo'] ?? false);

        $establecimiento_valor_hora->update($data);

        return redirect()->route('admin.establecimiento-valores-hora.index')->with('status', 'Valor hora por establecimiento actualizado.');
    }

    public function destroy(EstablecimientoValorHora $establecimiento_valor_hora)
    {
        $establecimiento_valor_hora->delete();
        return redirect()->route('admin.establecimiento-valores-hora.index')->with('status', 'Valor hora por establecimiento eliminado.');
    }
}
