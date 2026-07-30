<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curso;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CursoController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $nivel = trim((string) $request->query('nivel_educativo', ''));
        $modalidad = trim((string) $request->query('modalidad', ''));
        $activo = trim((string) $request->query('activo', ''));

        $niveles = Curso::query()
            ->select('nivel_educativo')
            ->whereNotNull('nivel_educativo')
            ->distinct()
            ->orderBy('nivel_educativo')
            ->pluck('nivel_educativo')
            ->filter()
            ->values();

        $modalidades = Curso::query()
            ->select('modalidad')
            ->whereNotNull('modalidad')
            ->distinct()
            ->orderBy('modalidad')
            ->pluck('modalidad')
            ->filter()
            ->values();

        $cursos = Curso::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('nombre', 'like', "%{$q}%")
                        ->orWhere('codigo', 'like', "%{$q}%");
                });
            })
            ->when($nivel !== '', fn ($query) => $query->where('nivel_educativo', $nivel))
            ->when($modalidad !== '', fn ($query) => $query->where('modalidad', $modalidad))
            ->when($activo !== '', fn ($query) => $query->where('activo', $activo === '1'))
            ->orderBy('orden')
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        return view('admin.cursos.index', compact('cursos', 'q', 'nivel', 'modalidad', 'activo', 'niveles', 'modalidades'));
    }

    public function create()
    {
        return view('admin.cursos.create', [
            'curso' => new Curso([
                'activo' => true,
                'orden' => ((int) Curso::max('orden')) + 1,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        Curso::create($data);

        return redirect()
            ->route('admin.cursos.index')
            ->with('status', 'Curso creado correctamente.');
    }

    public function show(Curso $curso)
    {
        return view('admin.cursos.show', compact('curso'));
    }

    public function edit(Curso $curso)
    {
        return view('admin.cursos.edit', compact('curso'));
    }

    public function update(Request $request, Curso $curso)
    {
        $data = $this->validatedData($request, $curso);
        $curso->update($data);

        return redirect()
            ->route('admin.cursos.index')
            ->with('status', 'Curso actualizado correctamente.');
    }

    public function destroy(Curso $curso)
    {
        $curso->delete();

        return redirect()
            ->route('admin.cursos.index')
            ->with('status', 'Curso eliminado correctamente.');
    }

    private function validatedData(Request $request, ?Curso $curso = null): array
    {
        $cursoId = $curso?->id;

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:120', Rule::unique('cursos', 'nombre')->ignore($cursoId)],
            'codigo' => ['required', 'string', 'max:50', Rule::unique('cursos', 'codigo')->ignore($cursoId)],
            'nivel_educativo' => ['required', 'string', 'max:80'],
            'modalidad' => ['nullable', 'string', 'max:80'],
            'orden' => ['required', 'integer', 'min:1', 'max:999'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $data['activo'] = (bool) ($data['activo'] ?? false);
        $data['modalidad'] = trim((string) ($data['modalidad'] ?? '')) ?: null;

        return $data;
    }
}
