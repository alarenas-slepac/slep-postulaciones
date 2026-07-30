<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DotacionDocenteAsignacion;
use App\Models\DotacionEstablecimientoConfiguracion;
use App\Models\DotacionFuncionEstablecimiento;
use App\Models\DotacionFuncionRegla;
use App\Models\Establecimiento;
use App\Support\DotacionFuncionesCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class DotacionFuncionesController extends Controller
{
    private array $allowedRoles = ['admin', 'funcionario_directivo_estab', 'coordinador_uatp', 'coordinador_gdp'];
    private array $editableRoles = ['admin', 'funcionario_directivo_estab', 'coordinador_uatp'];
    private array $validatorRoles = ['admin', 'coordinador_uatp'];

    public function index(Request $request)
    {
        $activeRole = $this->authorizeDotacionAccess($request);
        $user = $request->user();
        $anio = (int) $request->query('anio', now()->year);
        $q = trim((string) $request->query('q', ''));
        $comuna = trim((string) $request->query('comuna', ''));

        $establecimientos = Establecimiento::query()
            ->where(function ($query) {
                $query->whereNull('sala_cuna')
                    ->orWhere('sala_cuna', false);
            })
            ->when($this->isEstablecimientoRole($activeRole), fn ($query) => $query->where('id', (int) ($user->establecimiento_id ?? 0)))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('nombre_establecimiento', 'like', "%{$q}%")
                        ->orWhere('rbd', 'like', "%{$q}%")
                        ->orWhere('comuna', 'like', "%{$q}%");
                });
            })
            ->when($comuna !== '', fn ($query) => $query->where('comuna', $comuna))
            ->orderBy('comuna')
            ->orderBy('nombre_establecimiento')
            ->paginate(25)
            ->withQueryString();

        $establecimientos->getCollection()->transform(function ($establecimiento) use ($anio) {
            $establecimiento->dotacion_resumen = $this->resumenEstablecimiento($establecimiento, $anio);
            return $establecimiento;
        });

        $comunas = Establecimiento::query()
            ->where(function ($query) {
                $query->whereNull('sala_cuna')
                    ->orWhere('sala_cuna', false);
            })
            ->whereNotNull('comuna')
            ->orderBy('comuna')
            ->distinct()
            ->pluck('comuna')
            ->filter()
            ->values();

        return view('admin.dotacion-funciones.index', [
            'establecimientos' => $establecimientos,
            'comunas' => $comunas,
            'anio' => $anio,
            'q' => $q,
            'comuna' => $comuna,
            'activeRole' => $activeRole,
            'bloquesConsolidados' => $this->bloquesConsolidados(),
        ]);
    }

    public function show(Request $request, Establecimiento $establecimiento)
    {
        $activeRole = $this->authorizeDotacionAccess($request);
        $this->authorizeEstablecimientoScope($request, $establecimiento);
        $anio = (int) $request->query('anio', now()->year);

        $contexto = DotacionFuncionesCalculator::contexto($establecimiento, $anio);
        $config = $contexto['config'] ?: new DotacionEstablecimientoConfiguracion([
            'establecimiento_id' => $establecimiento->id,
            'anio' => $anio,
            'director_adp' => false,
        ]);
        $sugerencias = DotacionFuncionesCalculator::sugerencias($establecimiento, $anio)->groupBy('categoria');
        $manuales = DotacionFuncionEstablecimiento::query()
            ->with(['regla', 'creador', 'actualizador', 'validador'])
            ->where('establecimiento_id', $establecimiento->id)
            ->where('anio', $anio)
            ->orderBy('categoria')
            ->orderBy('nombre_funcion')
            ->get()
            ->groupBy('categoria');

        $resumen = $this->resumenEstablecimiento($establecimiento, $anio);
        $rules = DotacionFuncionRegla::query()->where('vigente', true)->get()->keyBy('codigo');

        return view('admin.dotacion-funciones.show', [
            'establecimiento' => $establecimiento,
            'anio' => $anio,
            'contexto' => $contexto,
            'config' => $config,
            'sugerencias' => $sugerencias,
            'manuales' => $manuales,
            'resumen' => $resumen,
            'rules' => $rules,
            'categorias' => DotacionFuncionEstablecimiento::CATEGORIAS,
            'estados' => DotacionFuncionEstablecimiento::ESTADOS,
            'activeRole' => $activeRole,
            'canEdit' => in_array($activeRole, $this->editableRoles, true),
            'canValidate' => in_array($activeRole, $this->validatorRoles, true),
            'bloquesConsolidados' => $this->bloquesConsolidados(),
        ]);
    }

    public function updateConfig(Request $request, Establecimiento $establecimiento)
    {
        $this->authorizeDotacionAccess($request, true);
        $this->authorizeEstablecimientoScope($request, $establecimiento);

        $data = $request->validate([
            'anio' => ['required', 'integer', 'min:2020', 'max:2100'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ]);

        DotacionEstablecimientoConfiguracion::updateOrCreate(
            [
                'establecimiento_id' => $establecimiento->id,
                'anio' => (int) $data['anio'],
            ],
            [
                'director_adp' => false,
                'observacion' => trim((string) ($data['observacion'] ?? '')) ?: null,
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
            ]
        );

        return redirect()->route('admin.dotacion-funciones.show', [$establecimiento, 'anio' => (int) $data['anio']])
            ->with('status', 'Parámetros de dotación actualizados correctamente.');
    }

    public function storeManual(Request $request, Establecimiento $establecimiento)
    {
        $activeRole = $this->authorizeDotacionAccess($request, true);
        $this->authorizeEstablecimientoScope($request, $establecimiento);

        $data = $request->validate([
            'anio' => ['required', 'integer', 'min:2020', 'max:2100'],
            'tipo' => ['required', Rule::in(['coordinacion', 'orientador', 'otra'])],
            'tipo_coordinacion' => ['nullable', 'string', 'max:80'],
            'nombre_funcion' => ['required', 'string', 'max:180'],
            'descripcion_funcion' => ['nullable', 'string', 'max:3000'],
            'horas_declaradas' => ['required', 'integer', 'min:0', 'max:200'],
            'horas_aprobadas' => ['nullable', 'integer', 'min:0', 'max:200'],
            'fundamento' => ['nullable', 'string', 'max:3000'],
            'observacion' => ['nullable', 'string', 'max:3000'],
        ]);

        $rule = $this->ruleForType($data['tipo']);
        $contexto = DotacionFuncionesCalculator::contexto($establecimiento, (int) $data['anio']);
        $horasSugeridas = $rule ? DotacionFuncionesCalculator::calcularHorasRegla($rule, $contexto) : null;
        if ($data['tipo'] === 'orientador' || $data['tipo'] === 'otra') {
            $horasSugeridas = null;
        }

        DotacionFuncionEstablecimiento::create([
            'establecimiento_id' => $establecimiento->id,
            'regla_id' => $rule?->id,
            'anio' => (int) $data['anio'],
            'categoria' => $this->categoriaForType($data['tipo']),
            'nombre_funcion' => trim($data['nombre_funcion']),
            'tipo_coordinacion' => trim((string) ($data['tipo_coordinacion'] ?? '')) ?: null,
            'descripcion_funcion' => trim((string) ($data['descripcion_funcion'] ?? '')) ?: null,
            'origen' => 'manual_establecimiento',
            'tipo_regla' => $rule?->tipo_regla ?: 'manual',
            'horas_sugeridas' => $horasSugeridas,
            'horas_declaradas' => (int) $data['horas_declaradas'],
            'horas_aprobadas' => array_key_exists('horas_aprobadas', $data) && $data['horas_aprobadas'] !== null ? (int) $data['horas_aprobadas'] : null,
            'fundamento' => trim((string) ($data['fundamento'] ?? '')) ?: null,
            'observacion' => trim((string) ($data['observacion'] ?? '')) ?: null,
            'estado' => in_array($activeRole, $this->validatorRoles, true) ? 'validado_uatp' : 'en_revision',
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
            'validated_by' => in_array($activeRole, $this->validatorRoles, true) ? $request->user()?->id : null,
            'validated_at' => in_array($activeRole, $this->validatorRoles, true) ? now() : null,
        ]);

        return redirect()->route('admin.dotacion-funciones.show', [$establecimiento, 'anio' => (int) $data['anio']])
            ->with('status', 'Función declarada correctamente.');
    }

    public function updateManual(Request $request, Establecimiento $establecimiento, DotacionFuncionEstablecimiento $funcion)
    {
        $this->authorizeDotacionAccess($request, true);
        $this->authorizeEstablecimientoScope($request, $establecimiento);
        abort_unless((int) $funcion->establecimiento_id === (int) $establecimiento->id, 404);

        $data = $request->validate([
            'anio' => ['required', 'integer', 'min:2020', 'max:2100'],
            'tipo_coordinacion' => ['nullable', 'string', 'max:80'],
            'nombre_funcion' => ['required', 'string', 'max:180'],
            'descripcion_funcion' => ['nullable', 'string', 'max:3000'],
            'horas_declaradas' => ['required', 'integer', 'min:0', 'max:200'],
            'horas_aprobadas' => ['nullable', 'integer', 'min:0', 'max:200'],
            'fundamento' => ['nullable', 'string', 'max:3000'],
            'observacion' => ['nullable', 'string', 'max:3000'],
            'estado' => ['nullable', Rule::in(array_keys(DotacionFuncionEstablecimiento::ESTADOS))],
        ]);

        $funcion->update([
            'nombre_funcion' => trim($data['nombre_funcion']),
            'tipo_coordinacion' => trim((string) ($data['tipo_coordinacion'] ?? '')) ?: null,
            'descripcion_funcion' => trim((string) ($data['descripcion_funcion'] ?? '')) ?: null,
            'horas_declaradas' => (int) $data['horas_declaradas'],
            'horas_aprobadas' => array_key_exists('horas_aprobadas', $data) && $data['horas_aprobadas'] !== null ? (int) $data['horas_aprobadas'] : null,
            'fundamento' => trim((string) ($data['fundamento'] ?? '')) ?: null,
            'observacion' => trim((string) ($data['observacion'] ?? '')) ?: null,
            'estado' => $data['estado'] ?? $funcion->estado,
            'updated_by' => $request->user()?->id,
        ]);

        return redirect()->route('admin.dotacion-funciones.show', [$establecimiento, 'anio' => (int) $data['anio']])
            ->with('status', 'Función actualizada correctamente.');
    }

    public function destroyManual(Request $request, Establecimiento $establecimiento, DotacionFuncionEstablecimiento $funcion)
    {
        $this->authorizeDotacionAccess($request, true);
        $this->authorizeEstablecimientoScope($request, $establecimiento);
        abort_unless((int) $funcion->establecimiento_id === (int) $establecimiento->id, 404);
        $anio = $funcion->anio;
        $asignacionesVinculadas = Schema::hasTable('dotacion_docente_asignaciones')
            ? DotacionDocenteAsignacion::query()
                ->where('establecimiento_id', $establecimiento->id)
                ->where('anio', $anio)
                ->where('estado', 'activa')
                ->where('dotacion_funcion_id', $funcion->id)
                ->count()
            : 0;

        $funcion->delete();

        $mensaje = 'Función eliminada correctamente.';
        if ($asignacionesVinculadas > 0) {
            $mensaje .= ' Se conservaron '.$asignacionesVinculadas.' asignación(es) activa(s) para revisión manual en el bloque Horas fantasmas de Dotación Establecimiento.';
        }

        return redirect()->route('admin.dotacion-funciones.show', [$establecimiento, 'anio' => $anio])
            ->with('status', $mensaje);
    }

    public function validarManual(Request $request, Establecimiento $establecimiento, DotacionFuncionEstablecimiento $funcion)
    {
        $activeRole = $this->authorizeDotacionAccess($request);
        abort_unless(in_array($activeRole, $this->validatorRoles, true), 403);
        $this->authorizeEstablecimientoScope($request, $establecimiento);
        abort_unless((int) $funcion->establecimiento_id === (int) $establecimiento->id, 404);

        $data = $request->validate([
            'horas_aprobadas' => ['required', 'integer', 'min:0', 'max:200'],
            'observacion' => ['nullable', 'string', 'max:3000'],
        ]);

        $funcion->update([
            'horas_aprobadas' => (int) $data['horas_aprobadas'],
            'observacion' => trim((string) ($data['observacion'] ?? '')) ?: $funcion->observacion,
            'estado' => 'validado_uatp',
            'validated_by' => $request->user()?->id,
            'validated_at' => now(),
            'updated_by' => $request->user()?->id,
        ]);

        return redirect()->route('admin.dotacion-funciones.show', [$establecimiento, 'anio' => $funcion->anio])
            ->with('status', 'Función validada correctamente.');
    }

    public function observarManual(Request $request, Establecimiento $establecimiento, DotacionFuncionEstablecimiento $funcion)
    {
        $activeRole = $this->authorizeDotacionAccess($request);
        abort_unless(in_array($activeRole, $this->validatorRoles, true), 403);
        $this->authorizeEstablecimientoScope($request, $establecimiento);
        abort_unless((int) $funcion->establecimiento_id === (int) $establecimiento->id, 404);

        $data = $request->validate([
            'observacion' => ['required', 'string', 'max:3000'],
        ]);

        $funcion->update([
            'observacion' => trim($data['observacion']),
            'estado' => 'observado',
            'updated_by' => $request->user()?->id,
        ]);

        return redirect()->route('admin.dotacion-funciones.show', [$establecimiento, 'anio' => $funcion->anio])
            ->with('status', 'Función observada correctamente.');
    }

    private function authorizeDotacionAccess(Request $request, bool $requiresEdit = false): string
    {
        $activeRole = $this->activeRole($request);
        abort_unless(in_array($activeRole, $this->allowedRoles, true), 403);
        if ($requiresEdit) {
            abort_unless(in_array($activeRole, $this->editableRoles, true), 403);
        }
        if ($this->isEstablecimientoRole($activeRole)) {
            abort_unless((int) ($request->user()->establecimiento_id ?? 0) > 0, 403, 'Usuario sin establecimiento asociado.');
        }
        return $activeRole;
    }

    private function activeRole(Request $request): ?string
    {
        $user = $request->user();
        return $user && method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null;
    }

    private function isEstablecimientoRole(?string $activeRole): bool
    {
        return $activeRole === 'funcionario_directivo_estab';
    }

    private function authorizeEstablecimientoScope(Request $request, Establecimiento $establecimiento): void
    {
        abort_if((bool) ($establecimiento->sala_cuna ?? false), 404, 'El establecimiento no participa en el proceso de dotación funciones y planes.');

        if ($this->isEstablecimientoRole($this->activeRole($request))) {
            abort_unless((int) $establecimiento->id === (int) ($request->user()->establecimiento_id ?? 0), 403);
        }
    }

    private function ruleForType(string $type): ?DotacionFuncionRegla
    {
        $codigo = match ($type) {
            'coordinacion' => 'coordinador_ciclo_tp_especialidad',
            'orientador' => 'orientador',
            'otra' => 'otra_funcion_docente',
            default => null,
        };

        return $codigo ? DotacionFuncionRegla::query()->where('codigo', $codigo)->first() : null;
    }

    private function categoriaForType(string $type): string
    {
        return match ($type) {
            'coordinacion', 'orientador' => 'tecnico_pedagogica',
            'otra' => 'otras_funciones_docentes',
            default => 'otras_funciones_docentes',
        };
    }

    private function resumenEstablecimiento(Establecimiento $establecimiento, int $anio): array
    {
        $contexto = DotacionFuncionesCalculator::contexto($establecimiento, $anio);
        $sugerencias = DotacionFuncionesCalculator::sugerencias($establecimiento, $anio);
        $manuales = DotacionFuncionEstablecimiento::query()
            ->with('regla')
            ->where('establecimiento_id', $establecimiento->id)
            ->where('anio', $anio)
            ->get();

        $totalesPorCategoria = [];
        foreach (DotacionFuncionEstablecimiento::CATEGORIAS as $categoria => $label) {
            $totalesPorCategoria[$categoria] = 0;
        }

        $consolidado = $this->initConsolidado();

        foreach ($sugerencias as $item) {
            $horas = (int) $item['horas_sugeridas'];
            $totalesPorCategoria[$item['categoria']] = ($totalesPorCategoria[$item['categoria']] ?? 0) + $horas;

            $grupo = $this->grupoConsolidadoFor($item['categoria'] ?? null, $item['codigo'] ?? null);
            if (isset($consolidado[$grupo])) {
                $consolidado[$grupo]['automaticas'] += $horas;
                $consolidado[$grupo]['total'] += $horas;
            }
        }
        foreach ($manuales as $manual) {
            $horas = $manual->horasFinales();
            $totalesPorCategoria[$manual->categoria] = ($totalesPorCategoria[$manual->categoria] ?? 0) + $horas;

            $grupo = $this->grupoConsolidadoFor($manual->categoria, $manual->regla?->codigo);
            if (isset($consolidado[$grupo])) {
                $consolidado[$grupo]['declaradas'] += $horas;
                $consolidado[$grupo]['total'] += $horas;
            }
        }

        return [
            'matricula_total' => $contexto['matricula_total'],
            'cursos_nee' => $contexto['cursos_nee'],
            'matricula_nt1_nt2' => $contexto['matricula_nt1_nt2'],
            'director_adp' => $contexto['director_adp'],
            'horas_automaticas' => $sugerencias->sum('horas_sugeridas'),
            'horas_declaradas' => $manuales->sum(fn ($item) => $item->horasFinales()),
            'horas_totales' => array_sum(array_column($consolidado, 'total')),
            'pendientes_revision' => $manuales->whereIn('estado', ['borrador', 'en_revision', 'observado'])->count(),
            'validadas' => $manuales->where('estado', 'validado_uatp')->count(),
            'totales_por_categoria' => $totalesPorCategoria,
            'consolidado_por_bloque' => $consolidado,
        ];
    }

    private function bloquesConsolidados(): array
    {
        return [
            'directiva' => 'Directivos',
            'tecnico_pedagogica' => 'Técnico-pedagógicas',
            'pie' => 'PIE',
            'planes_programas' => 'Planes',
            'otras_funciones_docentes' => 'Otras funciones declaradas',
        ];
    }

    private function initConsolidado(): array
    {
        $items = [];
        foreach ($this->bloquesConsolidados() as $key => $label) {
            $items[$key] = [
                'label' => $label,
                'automaticas' => 0,
                'declaradas' => 0,
                'total' => 0,
            ];
        }
        return $items;
    }

    private function grupoConsolidadoFor(?string $categoria, ?string $codigo = null): string
    {
        if ($codigo === 'coordinador_pie') {
            return 'pie';
        }

        if ($categoria === 'directiva') {
            return 'directiva';
        }

        if ($categoria === 'planes_programas') {
            return 'planes_programas';
        }

        if ($categoria === 'otras_funciones_docentes') {
            return 'otras_funciones_docentes';
        }

        return 'tecnico_pedagogica';
    }
}
