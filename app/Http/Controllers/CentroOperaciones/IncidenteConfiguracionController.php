<?php

namespace App\Http\Controllers\CentroOperaciones;

use App\Http\Controllers\Controller;
use App\Models\CentroOperacionesIncidenteConfiguracion;
use App\Models\FuncionarioAcAutorizado;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IncidenteConfiguracionController extends Controller
{
    public function index(): View
    {
        $configuraciones = CentroOperacionesIncidenteConfiguracion::with('responsable')->orderBy('tipo')->get();
        $funcionarios = FuncionarioAcAutorizado::query()->where('estado_autorizacion', 'activo')
            ->whereNotNull('unidad_departamento')->whereNotNull('subdireccion_dependencia')
            ->orderBy('unidad_departamento')->orderBy('apellido_paterno')->get();

        return view('centro-operaciones.configuraciones.index', compact('configuraciones', 'funcionarios'));
    }

    public function update(Request $request, CentroOperacionesIncidenteConfiguracion $configuracion): RedirectResponse
    {
        $datos = $request->validate([
            'responsable_funcionario_ac_id' => ['required', Rule::exists('funcionarios_ac_autorizados', 'id')->where('estado_autorizacion', 'activo')],
            'plazo_dias' => ['required', 'integer', 'between:1,365'],
            'activo' => ['nullable', 'boolean'],
        ]);
        $responsable = FuncionarioAcAutorizado::findOrFail($datos['responsable_funcionario_ac_id']);
        abort_unless($responsable->unidad_departamento && $responsable->subdireccion_dependencia, 422, 'El responsable debe tener unidad y subdirección configuradas.');
        $configuracion->update($datos + ['unidad_departamento' => $responsable->unidad_departamento, 'subdireccion_dependencia' => $responsable->subdireccion_dependencia, 'activo' => $request->boolean('activo')]);

        return back()->with('success', 'Configuración actualizada.');
    }
}
