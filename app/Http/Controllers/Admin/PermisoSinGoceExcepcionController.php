<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PermisoSinGoceExcepcion;
use App\Models\ReemplazoPersonal;
use App\Support\RutChile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PermisoSinGoceExcepcionController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'ensure.role:admin|coordinador_gdp']);
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $estado = trim((string) $request->query('estado', 'activos'));

        $items = PermisoSinGoceExcepcion::query()
            ->with(['creador', 'actualizador'])
            ->when($search !== '', function ($query) use ($search) {
                $rut = $this->normalizarRutPlano($search);
                $query->where(function ($inner) use ($search, $rut) {
                    if ($rut !== '') {
                        $inner->where('rut_normalizado', 'like', "%{$rut}%");
                    }
                    $inner->orWhere('nombre_titular', 'like', "%{$search}%")
                        ->orWhere('observacion', 'like', "%{$search}%");
                });
            })
            ->when($estado === 'activos', fn($q) => $q->where('activo', true))
            ->when($estado === 'inactivos', fn($q) => $q->where('activo', false))
            ->orderByDesc('activo')
            ->orderBy('nombre_titular')
            ->orderBy('rut_normalizado')
            ->paginate(20)
            ->withQueryString();

        return view('admin.permiso-sin-goce-excepciones.index', compact('items', 'search', 'estado'));
    }

    public function create(): View
    {
        return view('admin.permiso-sin-goce-excepciones.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rut' => ['required', 'string', 'max:30'],
            'nombre_titular' => ['nullable', 'string', 'max:255'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'rut' => 'RUT del titular',
            'nombre_titular' => 'nombre del titular',
            'observacion' => 'observación',
        ]);

        $rut = $this->normalizarRutPlano($data['rut']);
        if ($rut === '') {
            return back()->withErrors(['rut' => 'Debes ingresar un RUT válido.'])->withInput();
        }

        $titular = $this->buscarTitularPorRut($rut);
        if (!$titular) {
            return back()->withErrors(['rut' => 'No se encontró un titular en el padrón de reemplazos con ese RUT.'])->withInput();
        }

        if (!$this->esDocente($titular->estatuto)) {
            return back()->withErrors(['rut' => 'El titular encontrado no corresponde a estatuto docente.'])->withInput();
        }

        $nombre = trim((string) ($data['nombre_titular'] ?? '')) ?: (string) $titular->nombre;

        $item = PermisoSinGoceExcepcion::firstOrNew(['rut_normalizado' => $rut]);
        if (!$item->exists) {
            $item->created_by = $request->user()->id;
        }
        $item->fill([
            'rut_original' => (string) ($data['rut'] ?? $titular->rut),
            'nombre_titular' => $nombre,
            'observacion' => $data['observacion'] ?? null,
            'activo' => true,
            'updated_by' => $request->user()->id,
        ])->save();

        return redirect()
            ->route('admin.permiso-sin-goce-excepciones.index')
            ->with('status', 'Excepción agregada correctamente. El titular podrá ser usado en solicitudes con Permiso sin goce de sueldo.');
    }

    public function edit(PermisoSinGoceExcepcion $permisoSinGoceExcepcion): View
    {
        return view('admin.permiso-sin-goce-excepciones.edit', ['item' => $permisoSinGoceExcepcion]);
    }

    public function update(Request $request, PermisoSinGoceExcepcion $permisoSinGoceExcepcion): RedirectResponse
    {
        $data = $request->validate([
            'nombre_titular' => ['required', 'string', 'max:255'],
            'observacion' => ['nullable', 'string', 'max:1000'],
            'activo' => ['nullable', 'boolean'],
        ], [], [
            'nombre_titular' => 'nombre del titular',
            'observacion' => 'observación',
            'activo' => 'estado',
        ]);

        $permisoSinGoceExcepcion->fill([
            'nombre_titular' => $data['nombre_titular'],
            'observacion' => $data['observacion'] ?? null,
            'activo' => $request->boolean('activo'),
            'updated_by' => $request->user()->id,
        ])->save();

        return redirect()
            ->route('admin.permiso-sin-goce-excepciones.index')
            ->with('status', 'Excepción actualizada correctamente.');
    }

    public function toggle(Request $request, PermisoSinGoceExcepcion $permisoSinGoceExcepcion): RedirectResponse
    {
        $permisoSinGoceExcepcion->forceFill([
            'activo' => !$permisoSinGoceExcepcion->activo,
            'updated_by' => $request->user()->id,
        ])->save();

        return back()->with('status', $permisoSinGoceExcepcion->activo ? 'Excepción activada.' : 'Excepción desactivada.');
    }

    private function normalizarRutPlano(?string $value): string
    {
        $normalized = RutChile::normalize($value);
        if ($normalized && !empty($normalized['rut_body']) && !empty($normalized['rut_dv'])) {
            return strtoupper($normalized['rut_body'] . $normalized['rut_dv']);
        }

        return strtoupper(preg_replace('/[^0-9Kk]/', '', (string) $value));
    }

    private function buscarTitularPorRut(string $rutNormalizado): ?ReemplazoPersonal
    {
        return ReemplazoPersonal::query()
            ->whereRaw("REPLACE(REPLACE(REPLACE(UPPER(rut), '.', ''), '-', ''), ' ', '') = ?", [$rutNormalizado])
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->orderByDesc('id')
            ->first();
    }

    private function esDocente(?string $estatuto): bool
    {
        $value = strtoupper(trim((string) $estatuto));
        return $value === 'DOCENTE' || $value === 'PROFESOR' || $value === 'PROFESORA' || str_contains($value, 'DOC');
    }
}
