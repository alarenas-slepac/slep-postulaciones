<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FuncionarioViaticoAnexo;
use App\Models\ReemplazoPersonal;
use App\Support\RutChile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class FuncionarioViaticoAnexoController extends Controller
{
    public function index(Request $request)
    {
        $query = FuncionarioViaticoAnexo::query()
            ->with('establecimiento')
            ->orderByDesc('activo')
            ->orderBy('nombre_completo')
            ->orderBy('rut_body');

        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('rut', 'like', "%{$q}%")
                    ->orWhere('rut_body', 'like', "%{$q}%")
                    ->orWhere('nombre_completo', 'like', "%{$q}%")
                    ->orWhere('establecimiento_nombre', 'like', "%{$q}%")
                    ->orWhere('cargo_funcion', 'like', "%{$q}%");
            });
        }

        if ($request->filled('estado')) {
            $query->where('activo', $request->input('estado') === 'activos');
        }

        $registros = $query->paginate(15)->withQueryString();

        return view('admin.funcionarios-viatico-anexo.index', compact('registros'));
    }

    public function create()
    {
        return view('admin.funcionarios-viatico-anexo.create', [
            'registro' => new FuncionarioViaticoAnexo(['activo' => true]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $funcionario = $this->funcionarioActivoPorRut($data['rut_body']);

        if (!$funcionario) {
            throw ValidationException::withMessages([
                'rut' => 'El RUT indicado no aparece activo en el último padrón vigente/cargado de personal. No se puede habilitar viático por anexo.',
            ]);
        }

        FuncionarioViaticoAnexo::create(array_merge($data, $this->snapshotFuncionario($funcionario), [
            'registrado_por' => $request->user()?->id,
            'validado_at' => now(),
        ]));

        return redirect()
            ->route('admin.funcionarios-viatico-anexo.index')
            ->with('success', 'Funcionario habilitado para viático por anexo registrado correctamente.');
    }

    public function edit(FuncionarioViaticoAnexo $funcionarios_viatico_anexo)
    {
        return view('admin.funcionarios-viatico-anexo.edit', [
            'registro' => $funcionarios_viatico_anexo,
        ]);
    }

    public function update(Request $request, FuncionarioViaticoAnexo $funcionarios_viatico_anexo)
    {
        $data = $this->validatedData($request, $funcionarios_viatico_anexo->id);
        $funcionario = $this->funcionarioActivoPorRut($data['rut_body']);

        if (!$funcionario) {
            throw ValidationException::withMessages([
                'rut' => 'El RUT indicado no aparece activo en el último padrón vigente/cargado de personal. No se puede mantener habilitado para viático por anexo.',
            ]);
        }

        $funcionarios_viatico_anexo->update(array_merge($data, $this->snapshotFuncionario($funcionario), [
            'validado_at' => now(),
        ]));

        return redirect()
            ->route('admin.funcionarios-viatico-anexo.index')
            ->with('success', 'Registro actualizado y validado contra padrón activo.');
    }

    public function toggle(FuncionarioViaticoAnexo $funcionarios_viatico_anexo)
    {
        $funcionarios_viatico_anexo->update([
            'activo' => !$funcionarios_viatico_anexo->activo,
        ]);

        return back()->with('success', $funcionarios_viatico_anexo->activo ? 'Registro activado.' : 'Registro desactivado.');
    }

    public function destroy(FuncionarioViaticoAnexo $funcionarios_viatico_anexo)
    {
        $funcionarios_viatico_anexo->delete();

        return redirect()
            ->route('admin.funcionarios-viatico-anexo.index')
            ->with('success', 'Registro eliminado.');
    }

    private function validatedData(Request $request, ?int $ignoreId = null): array
    {
        $payload = $request->validate([
            'rut' => ['required', 'string', 'max:32'],
            'activo' => ['nullable', 'boolean'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ]);

        $rut = RutChile::normalize($payload['rut']);
        if (!$rut || ($rut['status'] ?? '') === 'invalid_dv') {
            throw ValidationException::withMessages(['rut' => 'Debe ingresar un RUT válido.']);
        }

        $exists = FuncionarioViaticoAnexo::query()
            ->where('rut_body', $rut['rut_body'])
            ->when($ignoreId, fn($q) => $q->where('id', '<>', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['rut' => 'El RUT ya tiene un registro de viático por anexo.']);
        }

        return [
            'rut' => $rut['rut'],
            'rut_body' => $rut['rut_body'],
            'rut_dv' => $rut['rut_dv'],
            'activo' => $request->boolean('activo'),
            'observacion' => $payload['observacion'] ?? null,
        ];
    }

    private function funcionarioActivoPorRut(string $rutBody): ?ReemplazoPersonal
    {
        $base = ReemplazoPersonal::query();

        if (Schema::hasColumn('reemplazos_personal', 'vigente')) {
            $base->where('vigente', true);
        }

        $periodo = (clone $base)
            ->whereNotNull('anio')
            ->whereNotNull('mes')
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->first(['anio', 'mes']);

        $query = ReemplazoPersonal::query();
        if ($periodo) {
            $query->where('anio', $periodo->anio)->where('mes', $periodo->mes);
        }
        if (Schema::hasColumn('reemplazos_personal', 'vigente')) {
            $query->where('vigente', true);
        }

        return $query
            ->with('establecimiento')
            ->where(function ($q) use ($rutBody) {
                $q->where('rut', $rutBody)
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(UPPER(rut), '.', ''), '-', ''), ' ', '') LIKE ?", [$rutBody . '%']);
            })
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->orderByDesc('id')
            ->first();
    }

    private function snapshotFuncionario(ReemplazoPersonal $funcionario): array
    {
        return [
            'nombre_completo' => $funcionario->nombre,
            'establecimiento_id' => $funcionario->establecimiento_id,
            'establecimiento_nombre' => $funcionario->establecimiento?->nombre ?? $funcionario->establecimiento?->name ?? null,
            'estamento' => $funcionario->estatuto,
            'cargo_funcion' => $funcionario->escalafon,
        ];
    }
}
