<?php

namespace App\Http\Controllers\Endeudamiento;

use App\Http\Controllers\Controller;
use App\Models\MaeHomologacionColumna;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaeNormativaController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'ensure.role:admin']);
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->get('q', ''));
        $grupo = trim((string) $request->get('grupo', ''));
        $subgrupo = trim((string) $request->get('subgrupo', ''));
        $bucket = trim((string) $request->get('bucket', ''));

        $items = MaeHomologacionColumna::query()
            ->where('activo', true)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('columna_origen', 'like', '%' . $q . '%')
                        ->orWhere('campo_canonico', 'like', '%' . $q . '%')
                        ->orWhere('observaciones', 'like', '%' . $q . '%');
                });
            })
            ->when($grupo !== '', fn ($query) => $query->where('grupo', $grupo))
            ->when($subgrupo !== '', fn ($query) => $query->where('subgrupo', $subgrupo))
            ->when($bucket !== '', function ($query) use ($bucket) {
                if ($bucket === '__sin_regla__') {
                    $query->where(function ($qq) {
                        $qq->whereNull('normativa_bucket')->orWhere('normativa_bucket', '');
                    });
                } else {
                    $query->where('normativa_bucket', $bucket);
                }
            })
            ->orderBy('grupo')
            ->orderBy('subgrupo')
            ->orderBy('columna_origen')
            ->paginate(20)
            ->withQueryString();

        $grupos = MaeHomologacionColumna::query()->where('activo', true)->distinct()->orderBy('grupo')->pluck('grupo')->filter()->values();
        $subgrupos = MaeHomologacionColumna::query()->where('activo', true)
            ->when($grupo !== '', fn ($query) => $query->where('grupo', $grupo))
            ->distinct()->orderBy('subgrupo')->pluck('subgrupo')->filter()->values();

        $bucketOptions = [
            'obligatorio' => 'Obligatorio',
            'facultativo_15' => 'Facultativo 15%',
            'facultativo_30' => 'Facultativo 30%',
            'judicial' => 'Judicial',
            'patronal_no_aplica' => 'Patronal no aplica',
            'revision_manual' => 'Revisión manual',
            'informativo' => 'Informativo',
            'ignorado' => 'Ignorado',
        ];

        return view('endeudamiento.normativa.index', compact('items', 'grupos', 'subgrupos', 'bucketOptions', 'q', 'grupo', 'subgrupo', 'bucket'));
    }

    public function update(Request $request, MaeHomologacionColumna $homologacion): RedirectResponse
    {
        $data = $request->validate([
            'normativa_bucket' => ['nullable', 'string', 'max:50'],
            'normativa_label' => ['nullable', 'string', 'max:120'],
            'normativa_regla' => ['nullable', 'string'],
            'normativa_prioridad' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'normativa_activa' => ['nullable', 'boolean'],
            'guardar_en_detalle' => ['nullable', 'boolean'],
            'guardar_en_resumen' => ['nullable', 'boolean'],
        ]);

        $homologacion->update([
            'normativa_bucket' => $data['normativa_bucket'] ?? null,
            'normativa_label' => $data['normativa_label'] ?? null,
            'normativa_regla' => $data['normativa_regla'] ?? null,
            'normativa_prioridad' => $data['normativa_prioridad'] ?? null,
            'normativa_activa' => $request->boolean('normativa_activa') && !empty($data['normativa_bucket']),
            'guardar_en_detalle' => $request->boolean('guardar_en_detalle'),
            'guardar_en_resumen' => $request->boolean('guardar_en_resumen'),
        ]);

        return redirect()->route('endeudamiento.normativa.index', $request->only(['q', 'grupo', 'subgrupo', 'bucket', 'page']))
            ->with('success', 'Regla normativa actualizada para ' . $homologacion->columna_origen . '.');
    }
}
