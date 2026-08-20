<?php

namespace App\Http\Controllers\CentroOperaciones;

use App\Http\Controllers\Controller;
use App\Models\CentroOperacionesRiesgoEvaluacion;
use App\Models\CentroOperacionesRiesgoModelo;
use App\Models\Establecimiento;
use App\Services\CentroOperaciones\DatosBaseService;
use App\Services\CentroOperaciones\RiesgoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RiesgoEvaluacionController extends Controller
{
    public function __construct(
        private readonly RiesgoService $riesgos,
        private readonly DatosBaseService $datosBase,
    ) {
    }

    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:120'],
            'comuna' => ['nullable', 'string', 'max:120'],
        ]);
        $establecimientos = Establecimiento::query()
            ->with('ultimaEvaluacionRiesgoCentroOperaciones.modelo')
            ->when($filtros['buscar'] ?? null, function ($query, string $buscar) {
                $query->where(function ($subquery) use ($buscar) {
                    $subquery->where('nombre_establecimiento', 'like', "%{$buscar}%")
                        ->orWhere('rbd', 'like', "%{$buscar}%");
                });
            })
            ->when($filtros['comuna'] ?? null, fn ($query, string $comuna) => $query->where('comuna', $comuna))
            ->orderBy('comuna')
            ->orderBy('nombre_establecimiento')
            ->paginate(25)
            ->withQueryString();
        $comunas = Establecimiento::query()->whereNotNull('comuna')->distinct()->orderBy('comuna')->pluck('comuna');
        $matriculas = $this->datosBase->matriculasPara(
            $establecimientos->getCollection(),
            now(config('centro_operaciones.timezone'))->year
        );
        $ultimas = CentroOperacionesRiesgoEvaluacion::query()
            ->where('estado', 'publicado')
            ->latest('fecha_evaluacion')
            ->latest('id')
            ->get()
            ->unique('establecimiento_id');
        $metricas = [
            'evaluados' => $ultimas->count(),
            'criticos' => $ultimas->where('categoria', 'critico')->count(),
            'atencion' => $ultimas->where('categoria', 'atencion_prioritaria')->count(),
            'vencidos' => $ultimas->filter->esta_vencida->count(),
            'sin_evaluacion' => max(0, Establecimiento::query()->count() - $ultimas->count()),
        ];

        return view('centro-operaciones.riesgos.index', compact(
            'establecimientos',
            'comunas',
            'matriculas',
            'metricas'
        ));
    }

    public function create(Establecimiento $establecimiento): View
    {
        $modelo = $this->riesgos->modeloPublicado();
        abort_unless($modelo, 422, 'No existe un modelo de riesgo publicado.');
        $historial = $establecimiento->evaluacionesRiesgoCentroOperaciones()
            ->with(['modelo', 'evaluadoPor'])
            ->latest('fecha_evaluacion')
            ->latest('id')
            ->limit(10)
            ->get();
        $borrador = $establecimiento->evaluacionesRiesgoCentroOperaciones()
            ->with('respuestas')
            ->where('modelo_id', $modelo->id)
            ->where('estado', 'borrador')
            ->latest('id')
            ->first();
        $matricula = $this->datosBase->matriculasPara(
            collect([$establecimiento]),
            now(config('centro_operaciones.timezone'))->year
        )[$establecimiento->id];

        return view('centro-operaciones.riesgos.form', compact(
            'establecimiento',
            'modelo',
            'historial',
            'borrador',
            'matricula'
        ));
    }

    public function store(Request $request, Establecimiento $establecimiento): RedirectResponse
    {
        $datos = $request->validate([
            'modelo_id' => ['required', 'integer', Rule::exists('centro_operaciones_riesgo_modelos', 'id')->where('estado', 'publicado')],
            'fecha_evaluacion' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'estado' => ['required', Rule::in(['borrador', 'publicado'])],
            'respuestas' => ['nullable', 'array'],
            'respuestas.*' => ['nullable', 'integer', Rule::exists('centro_operaciones_riesgo_opciones', 'id')->where('activo', true)],
            'observaciones_dimension' => ['nullable', 'array'],
            'observaciones_dimension.*' => ['nullable', 'string', 'max:1000'],
            'observaciones' => ['nullable', 'string', 'max:3000'],
        ]);
        $modelo = CentroOperacionesRiesgoModelo::query()->findOrFail($datos['modelo_id']);
        $evaluacion = $this->riesgos->guardar($establecimiento, $request->user(), $modelo, $datos);

        return redirect()
            ->route('centro-operaciones.riesgos.evaluar', $establecimiento)
            ->with(
                'success',
                $evaluacion->estado === 'publicado'
                    ? 'Evaluación publicada con IRTE '.$evaluacion->irte.'.'
                    : 'Borrador de evaluación guardado.'
            );
    }
}
