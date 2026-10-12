<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Establecimiento;
use App\Services\Pie\PieComparisonService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PieComparisonController extends Controller
{
    public function index(Request $request, PieComparisonService $service)
    {
        $this->authorizeAccess($request);
        $filters = $this->filters($request, false);
        $establishment = !empty($filters['establecimiento_id']) ? Establecimiento::findOrFail($filters['establecimiento_id']) : null;
        $years = [(int) $filters['anio_a'], (int) $filters['anio_b']];
        $data = $establishment ? $service->screen($establishment->id, $years) : ['groups' => [], 'rows' => [], 'matrix' => [], 'rules_token' => ''];
        return response()->view('admin.establecimiento-curso-pie.comparison', $data + [
            'filters' => $filters, 'years' => $years, 'establishment' => $establishment,
            'establishments' => Establecimiento::whereIn('id', fn ($q) => $q->select('establecimiento_id')->from('establecimiento_cursos')->where('activo', true))
                ->orderBy('comuna')->orderBy('nombre_establecimiento')->get(['id', 'rbd', 'nombre_establecimiento', 'comuna'])->groupBy(fn ($e) => $e->comuna ?: 'Sin comuna'),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function update(Request $request, PieComparisonService $service)
    {
        $this->authorizeAccess($request);
        $filters = $this->filters($request, true);
        $data = $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:400'], 'rows.*' => ['required', 'array:course_id,neet,neep,version'],
            'rows.*.course_id' => ['required', 'integer', 'min:1', 'distinct'],
            'rows.*.neet' => ['required', 'integer', 'min:0', 'max:9999'], 'rows.*.neep' => ['required', 'integer', 'min:0', 'max:9999'],
            'rows.*.version' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'rules_token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'], 'expected_rows' => ['required', 'integer', 'min:1', 'max:400'],
        ]);
        if ((int) $data['expected_rows'] !== count($data['rows'])) throw ValidationException::withMessages(['rows' => 'El formulario llegó incompleto. Recarga la comparación antes de guardar.']);
        $saved = $service->save((int) $filters['establecimiento_id'], [(int) $filters['anio_a'], (int) $filters['anio_b']],
            $data['rows'], $data['rules_token'], $request->user()->id);
        $message = $saved ? "Se guardaron los cambios de $saved cursos/secciones en una sola operación." : 'No había cambios para guardar.';
        $url = route('admin.establecimiento-curso-pie.comparison', $filters);
        if ($request->expectsJson()) {
            $request->session()->flash('status', $message);
            return response()->json(['saved' => $saved, 'redirect' => $url]);
        }
        return redirect($url)->with('status', $message);
    }

    private function authorizeAccess(Request $request): void
    {
        $user = $request->user(); $role = $user?->activeRoleName();
        abort_unless($user && in_array($role, ['admin', 'coordinador_uatp'], true)
            && $user->hasRole($role) && $user->canModule('admin.establecimiento-curso-pie', $role), 403);
    }

    private function filters(Request $request, bool $saving): array
    {
        $data = $request->validate([
            'establecimiento_id' => [$saving ? 'required' : 'nullable', 'integer', 'exists:establecimientos,id'],
            'anio_a' => [$saving ? 'required' : 'sometimes', 'integer', 'min:2020', 'max:2100'],
            'anio_b' => [$saving ? 'required' : 'sometimes', 'integer', 'min:2020', 'max:2100'],
        ]);
        $data += ['anio_a' => now()->year, 'anio_b' => now()->year + 1];
        if ((int) $data['anio_a'] === (int) $data['anio_b']) throw ValidationException::withMessages(['anio_b' => 'Selecciona dos años diferentes.']);
        return $data;
    }
}
