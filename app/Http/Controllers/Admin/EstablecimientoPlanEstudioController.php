<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asignatura;
use App\Models\Curso;
use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use App\Models\EstablecimientoPlanEstudio;
use App\Models\EstablecimientoPlanEstudioAsignatura;
use App\Models\PlanEstudioBloque;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EstablecimientoPlanEstudioController extends Controller
{
    public function index(Request $request)
    {
        $anio = trim((string) $request->query('anio', (string) now()->year));
        $establecimientoId = trim((string) $request->query('establecimiento_id', ''));
        $cursoId = trim((string) $request->query('curso_id', ''));
        $estado = trim((string) $request->query('estado', ''));
        $q = trim((string) $request->query('q', ''));

        $items = DB::table('establecimiento_cursos as ec')
            ->join('establecimientos as e', 'e.id', '=', 'ec.establecimiento_id')
            ->join('cursos as c', 'c.id', '=', 'ec.curso_id')
            ->join('planes_estudio as pe', 'pe.id', '=', 'ec.plan_estudio_id')
            ->leftJoin('establecimiento_planes_estudio as epe', 'epe.establecimiento_curso_id', '=', 'ec.id')
            ->where('ec.activo', true)
            ->whereNotNull('ec.plan_estudio_id')
            ->when($anio !== '', fn ($query) => $query->where('ec.anio', (int) $anio))
            ->when($establecimientoId !== '', fn ($query) => $query->where('ec.establecimiento_id', (int) $establecimientoId))
            ->when($cursoId !== '', fn ($query) => $query->where('ec.curso_id', (int) $cursoId))
            ->when($estado !== '', function ($query) use ($estado) {
                if ($estado === 'sin_configurar') {
                    $query->whereNull('epe.id');
                } else {
                    $query->where('epe.estado', $estado);
                }
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('ec.nombre_seccion', 'like', "%{$q}%")
                        ->orWhere('ec.rbd', 'like', "%{$q}%")
                        ->orWhere('e.rbd', 'like', "%{$q}%")
                        ->orWhere('e.nombre_establecimiento', 'like', "%{$q}%")
                        ->orWhere('e.comuna', 'like', "%{$q}%")
                        ->orWhere('c.nombre', 'like', "%{$q}%")
                        ->orWhere('pe.nombre_plan', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('ec.anio')
            ->orderBy('e.comuna')
            ->orderBy('e.nombre_establecimiento')
            ->orderBy('c.orden')
            ->orderBy('ec.letra')
            ->select([
                'ec.id as establecimiento_curso_id',
                'ec.rbd',
                'ec.anio',
                'ec.letra',
                'ec.nombre_seccion',
                'ec.matricula',
                'ec.regimen_jec',
                'e.rbd as establecimiento_rbd',
                'e.nombre_establecimiento as establecimiento_nombre',
                'e.comuna as establecimiento_comuna',
                'c.nombre as curso_nombre',
                'pe.nombre_plan as plan_nombre',
                'pe.horas_semanales_total as plan_horas_semanales_total',
                'epe.id as establecimiento_plan_id',
                'epe.estado as configuracion_estado',
                'epe.updated_at as configuracion_actualizada_at',
            ])
            ->paginate(25)
            ->withQueryString();

        return view('admin.establecimiento-planes.index', [
            'items' => $items,
            'establecimientos' => $this->establecimientosAgrupados(),
            'cursos' => Curso::query()->where('activo', true)->orderBy('orden')->orderBy('nombre')->get(['id', 'nombre']),
            'estados' => EstablecimientoPlanEstudio::ESTADOS,
            'anio' => $anio,
            'establecimientoId' => $establecimientoId,
            'cursoId' => $cursoId,
            'estado' => $estado,
            'q' => $q,
        ]);
    }

    public function configure(EstablecimientoCurso $establecimiento_curso)
    {
        $establecimiento_curso->load(['establecimiento', 'curso', 'planEstudio.bloques', 'planEstudio.asignaturas']);

        if (! $establecimiento_curso->plan_estudio_id || ! $establecimiento_curso->planEstudio) {
            return redirect()
                ->route('admin.establecimiento-planes.index')
                ->withErrors(['plan' => 'El curso/sección seleccionado no tiene un plan de estudio asociado.']);
        }

        $configuracion = EstablecimientoPlanEstudio::firstOrCreate(
            ['establecimiento_curso_id' => $establecimiento_curso->id],
            [
                'establecimiento_id' => $establecimiento_curso->establecimiento_id,
                'plan_estudio_id' => $establecimiento_curso->plan_estudio_id,
                'curso_id' => $establecimiento_curso->curso_id,
                'anio' => $establecimiento_curso->anio,
                'estado' => 'borrador',
                'created_by' => auth()->id(),
            ]
        );

        return redirect()->route('admin.establecimiento-planes.edit', $configuracion);
    }

    public function show(EstablecimientoPlanEstudio $establecimiento_plan)
    {
        $establecimiento_plan->load([
            'establecimiento',
            'establecimientoCurso',
            'curso',
            'planEstudio.bloques',
            'detalles.bloque',
            'detalles.asignatura',
            'detalles.asignaturaPlanComun',
        ]);

        return view('admin.establecimiento-planes.show', [
            'configuracion' => $establecimiento_plan,
            'detallesPorBloque' => $establecimiento_plan->detalles->groupBy('plan_estudio_bloque_id'),
            'asignaturasOficialesPorBloque' => $this->asignaturasOficialesPorBloque($establecimiento_plan),
        ]);
    }

    public function edit(EstablecimientoPlanEstudio $establecimiento_plan)
    {
        $establecimiento_plan->load([
            'establecimiento',
            'establecimientoCurso',
            'curso',
            'planEstudio.bloques',
            'planEstudio.asignaturas',
            'detalles.asignatura',
            'detalles.asignaturaPlanComun',
        ]);

        return view('admin.establecimiento-planes.edit', [
            'configuracion' => $establecimiento_plan,
            'bloques' => $establecimiento_plan->planEstudio->bloques()->where('activo', true)->get(),
            'detallesPorBloque' => $establecimiento_plan->detalles->groupBy('plan_estudio_bloque_id'),
            'asignaturasPorTipo' => $this->asignaturasPorTipo(),
            'asignaturasOficialesPorBloque' => $this->asignaturasOficialesPorBloque($establecimiento_plan),
        ]);
    }

    public function update(Request $request, EstablecimientoPlanEstudio $establecimiento_plan)
    {
        $establecimiento_plan->load('planEstudio.bloques');

        $data = $request->validate([
            'observacion' => ['nullable', 'string', 'max:5000'],
            'action' => ['nullable', 'string'],
            'detalles' => ['nullable', 'array'],
            'detalles.*.plan_estudio_bloque_id' => ['nullable', 'integer', 'exists:planes_estudio_bloques,id'],
            'detalles.*.asignatura_id' => ['nullable', 'integer', 'exists:asignaturas,id'],
            'detalles.*.asignatura_plan_comun_id' => ['nullable', 'integer', 'exists:asignaturas,id'],
            'detalles.*.nombre_asignatura_personalizada' => ['nullable', 'string', 'max:190'],
            'detalles.*.horas_semanales' => ['nullable', 'numeric', 'min:0', 'max:99'],
            'detalles.*.horas_anuales' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'detalles.*.origen' => ['nullable', 'string', 'max:50'],
            'detalles.*.observacion' => ['nullable', 'string', 'max:1000'],
            'detalles.*.orden' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $bloques = $establecimiento_plan->planEstudio->bloques->keyBy('id');
        $payloads = [];
        $sumas = [];

        foreach (($data['detalles'] ?? []) as $row) {
            $bloqueId = (int) ($row['plan_estudio_bloque_id'] ?? 0);
            if (! $bloqueId || ! $bloques->has($bloqueId)) {
                continue;
            }

            /** @var PlanEstudioBloque $bloque */
            $bloque = $bloques[$bloqueId];
            if (! $bloque->permite_asignaturas_establecimiento && ! $bloque->permite_asignaturas_personalizadas) {
                continue;
            }

            $asignaturaId = isset($row['asignatura_id']) && $row['asignatura_id'] !== '' ? (int) $row['asignatura_id'] : null;
            $asignaturaPlanComunId = isset($row['asignatura_plan_comun_id']) && $row['asignatura_plan_comun_id'] !== '' ? (int) $row['asignatura_plan_comun_id'] : null;
            if ($bloque->tipo_bloque !== 'libre_disposicion') {
                $asignaturaPlanComunId = null;
            }
            $nombrePersonalizado = trim((string) ($row['nombre_asignatura_personalizada'] ?? ''));
            $horasSemanales = $this->toDecimal($row['horas_semanales'] ?? 0);
            $horasAnuales = isset($row['horas_anuales']) && $row['horas_anuales'] !== '' ? $this->toDecimal($row['horas_anuales']) : null;

            if (! $asignaturaId && $nombrePersonalizado === '' && $horasSemanales <= 0) {
                continue;
            }

            if ($nombrePersonalizado !== '' && ! $bloque->permite_asignaturas_personalizadas) {
                throw ValidationException::withMessages([
                    'detalles' => "El bloque {$bloque->nombre} no permite asignaturas personalizadas.",
                ]);
            }

            if (! $asignaturaId && $nombrePersonalizado === '') {
                throw ValidationException::withMessages([
                    'detalles' => "Cada fila del bloque {$bloque->nombre} debe seleccionar una asignatura o indicar una asignatura personalizada.",
                ]);
            }

            $sumas[$bloqueId] = ($sumas[$bloqueId] ?? 0) + $horasSemanales;
            $payloads[] = [
                'plan_estudio_bloque_id' => $bloqueId,
                'asignatura_id' => $asignaturaId,
                'asignatura_plan_comun_id' => $asignaturaPlanComunId,
                'nombre_asignatura_personalizada' => $nombrePersonalizado ?: null,
                'horas_semanales' => $horasSemanales,
                'horas_anuales' => $horasAnuales,
                'origen' => trim((string) ($row['origen'] ?? 'oficial')) ?: 'oficial',
                'observacion' => trim((string) ($row['observacion'] ?? '')) ?: null,
                'orden' => (int) ($row['orden'] ?? 1),
            ];
        }

        foreach ($sumas as $bloqueId => $totalHoras) {
            $bloque = $bloques[$bloqueId];
            $max = (float) $bloque->horas_semanales;
            if ($max > 0 && $totalHoras > $max + 0.001) {
                throw ValidationException::withMessages([
                    'detalles' => "Las horas del bloque {$bloque->nombre} suman {$totalHoras}, superando el máximo de {$max} horas.",
                ]);
            }
        }

        DB::transaction(function () use ($establecimiento_plan, $payloads, $data, $request) {
            $establecimiento_plan->detalles()->delete();

            foreach ($payloads as $payload) {
                $establecimiento_plan->detalles()->create($payload);
            }

            $estado = $establecimiento_plan->estado ?: 'borrador';
            $submittedAt = $establecimiento_plan->submitted_at;
            if ($request->input('action') === 'enviar') {
                $estado = 'enviado';
                $submittedAt = now();
            }

            $establecimiento_plan->update([
                'estado' => $estado,
                'submitted_at' => $submittedAt,
                'observacion' => trim((string) ($data['observacion'] ?? '')) ?: null,
            ]);
        });

        return redirect()
            ->route('admin.establecimiento-planes.show', $establecimiento_plan)
            ->with('status', 'Configuración del plan guardada correctamente.');
    }

    public function destroy(EstablecimientoPlanEstudio $establecimiento_plan)
    {
        $establecimiento_plan->delete();

        return redirect()->route('admin.establecimiento-planes.index')->with('status', 'Configuración eliminada correctamente.');
    }

    private function establecimientosAgrupados()
    {
        return Establecimiento::query()
            ->orderBy('comuna')
            ->orderBy('nombre_establecimiento')
            ->get(['id', 'rbd', 'nombre_establecimiento', 'comuna'])
            ->groupBy(fn ($establecimiento) => $establecimiento->comuna ?: 'Sin comuna');
    }

    private function asignaturasOficialesPorBloque(EstablecimientoPlanEstudio $establecimientoPlan): array
    {
        $plan = $establecimientoPlan->planEstudio;
        if (! $plan) {
            return [];
        }

        $bloques = $plan->bloques->keyBy('tipo_bloque');

        return $plan->asignaturas
            ->groupBy('tipo_bloque')
            ->map(function ($items, $tipoBloque) use ($bloques) {
                $bloque = $bloques[$tipoBloque] ?? null;

                return $items
                    ->sortBy('orden')
                    ->values()
                    ->map(function ($item) use ($bloque) {
                        return [
                            'asignatura' => $item->asignatura,
                            'horas_semanales' => (float) ($item->horas_semanales ?? 0),
                            'horas_anuales' => $item->horas_anuales !== null ? (float) $item->horas_anuales : null,
                            'tipo_bloque' => $item->tipo_bloque,
                            'bloque_nombre' => $bloque?->nombre,
                            'orden' => (int) ($item->orden ?? 1),
                        ];
                    })
                    ->all();
            })
            ->toArray();
    }

    private function asignaturasPorTipo(): array
    {
        $nivelLabels = [
            'Educación Parvularia' => 'Educación Parvularia / Prebásica',
            'Educacion Parvularia' => 'Educación Parvularia / Prebásica',
            'Educación Básica' => 'Educación Básica',
            'Educacion Basica' => 'Educación Básica',
            'Educación Media' => 'Educación Media',
            'Educacion Media' => 'Educación Media',
            'EPJA Básica' => 'EPJA Básica',
            'EPJA Basica' => 'EPJA Básica',
            'EPJA Media' => 'EPJA Media',
            'Educación Especial' => 'Educación Especial / Laboral',
            'Educacion Especial' => 'Educación Especial / Laboral',
        ];

        return Asignatura::query()
            ->where('activo', true)
            ->orderBy('nivel_educativo')
            ->orderBy('area')
            ->orderBy('tipo_asignatura')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo', 'tipo_asignatura', 'nivel_educativo', 'area'])
            ->map(function (Asignatura $asignatura) use ($nivelLabels) {
                $nivel = trim((string) ($asignatura->nivel_educativo ?: 'Sin nivel informado'));
                $area = trim((string) ($asignatura->area ?: 'Sin área / ámbito informado'));

                return [
                    'id' => $asignatura->id,
                    'nombre' => $asignatura->nombre,
                    'codigo' => $asignatura->codigo,
                    'tipo_asignatura' => $asignatura->tipo_asignatura,
                    'nivel_educativo' => $nivel,
                    'nivel_label' => $nivelLabels[$nivel] ?? $nivel,
                    'area' => $area,
                ];
            })
            ->groupBy('tipo_asignatura')
            ->map(function ($porTipo) {
                return $porTipo
                    ->groupBy('nivel_label')
                    ->map(function ($porNivel) {
                        return $porNivel
                            ->groupBy('area')
                            ->map(fn ($items) => $items->values()->toArray())
                            ->toArray();
                    })
                    ->toArray();
            })
            ->toArray();
    }

    private function toDecimal($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        return (float) str_replace(',', '.', (string) $value);
    }
}
