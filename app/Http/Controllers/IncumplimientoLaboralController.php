<?php

namespace App\Http\Controllers;

use App\Models\Establecimiento;
use App\Models\IncumplimientoLaboral;
use App\Models\IncumplimientoLaboralHistorial;
use App\Models\ReemplazoPersonal;
use App\Support\Rut;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Validation\ValidationException;

class IncumplimientoLaboralController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'ensure.role:admin|funcionario_estab|funcionario_slep'])->only(['index', 'show', 'constancia']);
        $this->middleware(['auth', 'ensure.role:admin|funcionario_estab'])->only(['create', 'store', 'ajaxFuncionarios']);
        $this->middleware(['auth', 'ensure.role:admin'])->only(['edit', 'update', 'destroy', 'export']);
    }

    public function index(Request $request): View
    {
        [$filters, $context] = $this->resolveContext($request);

        $query = $this->buildIndexQuery($filters);

        $items = (clone $query)
            ->orderByDesc('incumplimientos_laborales.fecha_desde')
            ->orderByDesc('incumplimientos_laborales.id')
            ->paginate($filters['per_page'])
            ->withQueryString();

        $summary = [
            'total' => (clone $query)->count(),
            'establecimientos' => (clone $query)->distinct()->count('incumplimientos_laborales.establecimiento_id'),
            'ultimo' => (clone $query)->max('incumplimientos_laborales.created_at'),
        ];

        return view('incumplimientos.index', [
            'title' => 'Incumplimiento Laboral',
            'items' => $items,
            'filters' => $filters,
            'summary' => $summary,
            'establecimientos' => $context['establecimientos'],
            'isAdmin' => $context['isAdmin'],
            'isFuncionarioSlep' => $context['isFuncionarioSlep'],
            'isFuncionarioEstab' => $context['isFuncionarioEstab'],
            'forcedEstablecimiento' => $context['forcedEstablecimiento'],
            'lockedWithoutEstablecimiento' => $context['lockedWithoutEstablecimiento'],
        ]);
    }

    public function create(Request $request): View
    {
        [$filters, $context] = $this->resolveContext($request, false);

        $selectedEstablecimientoId = old('establecimiento_id', $context['forcedEstablecimiento']?->id);
        $selectedFuncionarioOption = $this->buildFuncionarioOption(
            old('reemplazo_personal_id') ? (int) old('reemplazo_personal_id') : null,
            $selectedEstablecimientoId ? (int) $selectedEstablecimientoId : ($context['forcedEstablecimiento']?->id)
        );

        return view('incumplimientos.create', [
            'title' => 'Nuevo Incumplimiento Laboral',
            'item' => new IncumplimientoLaboral(),
            'establecimientos' => $context['establecimientos'],
            'isAdmin' => $context['isAdmin'],
            'isFuncionarioSlep' => $context['isFuncionarioSlep'],
            'isFuncionarioEstab' => $context['isFuncionarioEstab'],
            'forcedEstablecimiento' => $context['forcedEstablecimiento'],
            'selectedEstablecimientoId' => $selectedEstablecimientoId,
            'selectedFuncionarioOption' => $selectedFuncionarioOption,
            'action' => route('incumplimientos.store'),
            'method' => 'POST',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$validated, $payload] = $this->validateAndResolvePayload($request, null);

        $item = new IncumplimientoLaboral();
        $item->fill($payload);
        $item->informado_por_user_id = $request->user()->id;
        $item->updated_by_user_id = $request->user()->id;
        $item->save();

        $item->loadMissing([
            'establecimiento:id,rbd,nombre_establecimiento,comuna',
            'reemplazoPersonal:id,establecimiento_id,rut,nombre,anio,mes,rbd',
        ]);

        $this->logHistory($item, 'created', $request->user()->id, null, $this->snapshotItem($item));

        return redirect()
            ->route('incumplimientos.show', $item)
            ->with('status', 'Incumplimiento laboral informado correctamente.');
    }

    public function show(Request $request, IncumplimientoLaboral $incumplimientoLaboral): View
    {
        $item = $this->resolveVisibleItem($request, $incumplimientoLaboral);
        $item->loadMissing([
            'establecimiento:id,rbd,nombre_establecimiento,comuna',
            'reemplazoPersonal:id,establecimiento_id,rut,nombre,anio,mes,rbd',
            'informadoPor:id,nombres,apellido_paterno,apellido_materno,email',
            'actualizadoPor:id,nombres,apellido_paterno,apellido_materno,email',
            'historial.user:id,nombres,apellido_paterno,apellido_materno,email',
        ]);

        return view('incumplimientos.show', [
            'title' => 'Detalle Incumplimiento Laboral',
            'item' => $item,
            'canEdit' => $request->user()->hasRole('admin'),
            'canDelete' => $request->user()->hasRole('admin'),
        ]);
    }

    public function constancia(Request $request, IncumplimientoLaboral $incumplimientoLaboral)
    {
        $item = $this->resolveVisibleItem($request, $incumplimientoLaboral);
        $item->loadMissing([
            'establecimiento:id,rbd,nombre_establecimiento,comuna',
            'reemplazoPersonal:id,establecimiento_id,rut,nombre,anio,mes,rbd',
            'informadoPor:id,nombres,apellido_paterno,apellido_materno,email',
            'actualizadoPor:id,nombres,apellido_paterno,apellido_materno,email',
        ]);

        $pdf = Pdf::loadView('pdf.incumplimiento-laboral-constancia', [
            'item' => $item,
            'issuedAt' => now(),
        ])->setPaper('letter', 'portrait');

        $filename = 'CONSTANCIA_INCUMPLIMIENTO_' . str_pad((string) $item->id, 6, '0', STR_PAD_LEFT) . '.pdf';

        return $pdf->download($filename);
    }

    public function edit(Request $request, IncumplimientoLaboral $incumplimientoLaboral): View
    {
        $item = $this->resolveVisibleItem($request, $incumplimientoLaboral);
        abort_unless($request->user()->hasRole('admin'), 403);

        [$filters, $context] = $this->resolveContext($request, false);
        $selectedEstablecimientoId = old('establecimiento_id', $item->establecimiento_id);
        $selectedFuncionarioOption = $this->buildFuncionarioOption(
            old('reemplazo_personal_id') ? (int) old('reemplazo_personal_id') : $item->reemplazo_personal_id,
            $selectedEstablecimientoId
        );

        return view('incumplimientos.edit', [
            'title' => 'Editar Incumplimiento Laboral',
            'item' => $item,
            'establecimientos' => $context['establecimientos'],
            'isAdmin' => true,
            'isFuncionarioEstab' => false,
            'forcedEstablecimiento' => null,
            'selectedEstablecimientoId' => $selectedEstablecimientoId,
            'selectedFuncionarioOption' => $selectedFuncionarioOption,
            'action' => route('incumplimientos.update', $item),
            'method' => 'PUT',
        ]);
    }

    public function update(Request $request, IncumplimientoLaboral $incumplimientoLaboral): RedirectResponse
    {
        $item = $this->resolveVisibleItem($request, $incumplimientoLaboral);
        abort_unless($request->user()->hasRole('admin'), 403);

        [$validated, $payload] = $this->validateAndResolvePayload($request, $item);

        $item->loadMissing([
            'establecimiento:id,rbd,nombre_establecimiento,comuna',
            'reemplazoPersonal:id,establecimiento_id,rut,nombre,anio,mes,rbd',
        ]);

        $before = $this->snapshotItem($item);

        $item->fill($payload);
        $item->updated_by_user_id = $request->user()->id;
        $item->save();

        $item->unsetRelation('establecimiento');
        $item->unsetRelation('reemplazoPersonal');
        $item->loadMissing([
            'establecimiento:id,rbd,nombre_establecimiento,comuna',
            'reemplazoPersonal:id,establecimiento_id,rut,nombre,anio,mes,rbd',
        ]);

        $after = $this->snapshotItem($item);
        $changedFields = $this->buildChangedFields($before, $after);
        if (!empty($changedFields)) {
            $this->logHistory($item, 'updated', $request->user()->id, $before, $after, $changedFields);
        }

        return redirect()
            ->route('incumplimientos.show', $item)
            ->with('status', 'Incumplimiento laboral actualizado correctamente.');
    }

    public function destroy(Request $request, IncumplimientoLaboral $incumplimientoLaboral): RedirectResponse
    {
        $item = $this->resolveVisibleItem($request, $incumplimientoLaboral);
        abort_unless($request->user()->hasRole('admin'), 403);

        $item->loadMissing([
            'establecimiento:id,rbd,nombre_establecimiento,comuna',
            'reemplazoPersonal:id,establecimiento_id,rut,nombre,anio,mes,rbd',
        ]);

        $this->logHistory($item, 'deleted', $request->user()->id, $this->snapshotItem($item), null);
        $item->delete();

        return redirect()
            ->route('incumplimientos.index')
            ->with('status', 'Incumplimiento laboral eliminado correctamente.');
    }

    public function export(Request $request): StreamedResponse
    {
        [$filters, $context] = $this->resolveContext($request);
        $rows = $this->buildIndexQuery($filters)
            ->orderBy('establecimientos.nombre_establecimiento')
            ->orderBy('incumplimientos_laborales.funcionario_nombre')
            ->get();

        $filenameParts = ['incumplimientos-laborales'];
        if (!empty($filters['establecimiento_id'])) {
            $est = $context['establecimientos']->firstWhere('id', (int) $filters['establecimiento_id']);
            if ($est) {
                $filenameParts[] = Str::slug($est->nombre_establecimiento ?: ('rbd-' . $est->rbd));
            }
        }
        if (!empty($filters['mes'])) {
            $filenameParts[] = $filters['mes'];
        } elseif (!empty($filters['fecha_desde']) || !empty($filters['fecha_hasta'])) {
            $filenameParts[] = ($filters['fecha_desde'] ?: 'inicio') . '-a-' . ($filters['fecha_hasta'] ?: 'fin');
        } else {
            $filenameParts[] = 'todos';
        }

        $filename = implode('-', array_filter($filenameParts)) . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'ID',
                'RUT funcionario',
                'Funcionario',
                'RBD',
                'Establecimiento',
                'Comuna',
                'Fecha desde',
                'Fecha hasta',
                'Días',
                'Horas',
                'Minutos',
                'Informado por',
                'Creado',
                'Actualizado',
            ], ';');

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->id,
                    Rut::format($row->funcionario_rut) ?? $row->funcionario_rut,
                    $row->funcionario_nombre,
                    $row->funcionario_rbd,
                    $row->establecimiento?->nombre_establecimiento,
                    $row->establecimiento?->comuna,
                    optional($row->fecha_desde)->format('d/m/Y'),
                    optional($row->fecha_hasta)->format('d/m/Y'),
                    $row->dias,
                    $row->horas,
                    $row->minutos,
                    $row->informadoPor?->nombre_completo ?: $row->informadoPor?->email,
                    cl_datetime($row->created_at),
                    cl_datetime($row->updated_at),
                ], ';');
            }

            fclose($handle);
        }, $filename);
    }

    public function ajaxFuncionarios(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'funcionario_estab']), 403);

        $establecimientoId = (int) $request->query('establecimiento_id');
        if ($request->user()->hasRole('funcionario_estab')) {
            $establecimientoId = (int) ($request->user()->establecimiento_id ?? 0);
        }

        if ($establecimientoId <= 0) {
            return response()->json(['results' => []]);
        }

        $term = trim((string) $request->query('term', ''));

        $rows = ReemplazoPersonal::query()
            ->where('establecimiento_id', $establecimientoId)
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($inner) use ($term) {
                    $inner->where('rut', 'like', '%' . $term . '%')
                        ->orWhere('nombre', 'like', '%' . $term . '%');
                });
            })
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->orderBy('nombre')
            ->get(['id', 'rut', 'nombre', 'anio', 'mes', 'establecimiento_id']);

        $results = $rows
            ->groupBy(fn($row) => strtoupper((string) $row->rut))
            ->map(function (Collection $group) {
                $row = $group->first();
                return [
                    'id' => (string) $row->id,
                    'text' => trim((Rut::format($row->rut) ?? $row->rut) . ' · ' . $row->nombre),
                ];
            })
            ->values()
            ->take(80);

        return response()->json(['results' => $results]);
    }

    private function resolveContext(Request $request, bool $forIndex = true): array
    {
        $user = $request->user();
        $isAdmin = $user && $user->hasRole('admin');
        $isFuncionarioSlep = $user && $user->hasRole('funcionario_slep');
        $isFuncionarioEstab = $user && $user->hasRole('funcionario_estab');
        $forcedEstablecimiento = $isFuncionarioEstab ? $user->establecimiento()->first() : null;
        $lockedWithoutEstablecimiento = $isFuncionarioEstab && !$forcedEstablecimiento;

        $validated = $request->validate([
            'rut' => ['nullable', 'string', 'max:30'],
            'nombre' => ['nullable', 'string', 'max:120'],
            'establecimiento_id' => ['nullable', 'integer', 'exists:establecimientos,id'],
            'mes' => ['nullable', 'date_format:Y-m'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date'],
            'fecha_registro_desde' => ['nullable', 'date'],
            'fecha_registro_hasta' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'in:15,25,50,100'],
        ]);

        $establecimientosQuery = Establecimiento::query()
            ->select('id', 'rbd', 'nombre_establecimiento', 'comuna')
            ->orderBy('nombre_establecimiento');

        if ($forcedEstablecimiento) {
            $establecimientosQuery->where('id', $forcedEstablecimiento->id);
        }

        $establecimientos = $establecimientosQuery->get();

        $selectedEstablecimientoId = $forcedEstablecimiento?->id ?: ($validated['establecimiento_id'] ?? null);
        if ($selectedEstablecimientoId && !$establecimientos->contains('id', (int) $selectedEstablecimientoId)) {
            $selectedEstablecimientoId = $forcedEstablecimiento?->id;
        }

        $filters = [
            'rut' => trim((string) ($validated['rut'] ?? '')),
            'nombre' => trim((string) ($validated['nombre'] ?? '')),
            'establecimiento_id' => $selectedEstablecimientoId ? (int) $selectedEstablecimientoId : null,
            'mes' => trim((string) ($validated['mes'] ?? '')),
            'fecha_desde' => !empty($validated['fecha_desde']) ? Carbon::parse($validated['fecha_desde'])->format('Y-m-d') : null,
            'fecha_hasta' => !empty($validated['fecha_hasta']) ? Carbon::parse($validated['fecha_hasta'])->format('Y-m-d') : null,
            'fecha_registro_desde' => !empty($validated['fecha_registro_desde']) ? Carbon::parse($validated['fecha_registro_desde'])->format('Y-m-d') : null,
            'fecha_registro_hasta' => !empty($validated['fecha_registro_hasta']) ? Carbon::parse($validated['fecha_registro_hasta'])->format('Y-m-d') : null,
            'per_page' => (int) ($validated['per_page'] ?? 25),
            'locked_without_establecimiento' => $lockedWithoutEstablecimiento,
        ];

        return [$filters, [
            'isAdmin' => $isAdmin,
            'isFuncionarioSlep' => $isFuncionarioSlep,
            'isFuncionarioEstab' => $isFuncionarioEstab,
            'forcedEstablecimiento' => $forcedEstablecimiento,
            'lockedWithoutEstablecimiento' => $lockedWithoutEstablecimiento,
            'establecimientos' => $establecimientos,
        ]];
    }

    private function buildIndexQuery(array $filters)
    {
        $query = IncumplimientoLaboral::query()
            ->leftJoin('establecimientos', 'establecimientos.id', '=', 'incumplimientos_laborales.establecimiento_id')
            ->with([
                'establecimiento:id,rbd,nombre_establecimiento,comuna',
                'informadoPor:id,nombres,apellido_paterno,apellido_materno,email',
            ])
            ->select('incumplimientos_laborales.*');

        if (!empty($filters['locked_without_establecimiento'])) {
            $query->whereRaw('1 = 0');
            return $query;
        }

        if (!empty($filters['establecimiento_id'])) {
            $query->where('incumplimientos_laborales.establecimiento_id', (int) $filters['establecimiento_id']);
        }

        if ($filters['rut'] !== '') {
            $rutTerm = preg_replace('/[^0-9Kk]/', '', strtoupper($filters['rut']));
            $query->where('incumplimientos_laborales.funcionario_rut', 'like', '%' . $rutTerm . '%');
        }

        if ($filters['nombre'] !== '') {
            $tokens = array_values(array_filter(preg_split('/\s+/', $filters['nombre']) ?: []));
            foreach ($tokens as $token) {
                $query->where('incumplimientos_laborales.funcionario_nombre', 'like', '%' . $token . '%');
            }
        }

        [$rangeStart, $rangeEnd] = $this->resolveDateRangeFilter($filters);
        if ($rangeStart || $rangeEnd) {
            $rangeStart = $rangeStart ?: '0001-01-01';
            $rangeEnd = $rangeEnd ?: '9999-12-31';
            $query->whereDate('incumplimientos_laborales.fecha_desde', '<=', $rangeEnd)
                ->whereDate('incumplimientos_laborales.fecha_hasta', '>=', $rangeStart);
        }

        $registroDesde = $filters['fecha_registro_desde'] ?? null;
        $registroHasta = $filters['fecha_registro_hasta'] ?? null;
        if ($registroDesde) {
            $query->whereDate('incumplimientos_laborales.created_at', '>=', $registroDesde);
        }
        if ($registroHasta) {
            $query->whereDate('incumplimientos_laborales.created_at', '<=', $registroHasta);
        }

        return $query;
    }

    private function resolveDateRangeFilter(array $filters): array
    {
        if (!empty($filters['mes'])) {
            $start = Carbon::createFromFormat('Y-m', $filters['mes'])->startOfMonth();
            $end = (clone $start)->endOfMonth();
            return [$start->format('Y-m-d'), $end->format('Y-m-d')];
        }

        return [$filters['fecha_desde'] ?? null, $filters['fecha_hasta'] ?? null];
    }

    private function resolveVisibleItem(Request $request, IncumplimientoLaboral $item): IncumplimientoLaboral
    {
        $user = $request->user();
        if ($user->hasAnyRole(['admin', 'funcionario_slep'])) {
            return $item;
        }

        abort_unless(
            $user->hasRole('funcionario_estab') && $user->establecimiento_id && (int) $item->establecimiento_id === (int) $user->establecimiento_id,
            403
        );

        return $item;
    }

    private function validateAndResolvePayload(Request $request, ?IncumplimientoLaboral $existing): array
    {
        $user = $request->user();
        $isAdmin = $user->hasRole('admin');
        $forcedEstablecimientoId = $user->hasRole('funcionario_estab') ? (int) ($user->establecimiento_id ?? 0) : 0;

        $validated = $request->validate([
            'establecimiento_id' => [$isAdmin ? 'required' : 'nullable', 'integer', 'exists:establecimientos,id'],
            'reemplazo_personal_id' => ['required', 'integer', 'exists:reemplazos_personal,id'],
            'fecha_desde' => ['required', 'date'],
            'fecha_hasta' => ['required', 'date', 'after_or_equal:fecha_desde'],
            'dias' => [
                'required',
                'integer',
                'min:0',
                function ($attribute, $value, $fail) use ($request) {
                    $fechaDesde = $request->input('fecha_desde');
                    $fechaHasta = $request->input('fecha_hasta');
                    if (!$fechaDesde || !$fechaHasta) {
                        return;
                    }

                    try {
                        $desde = Carbon::parse($fechaDesde)->startOfDay();
                        $hasta = Carbon::parse($fechaHasta)->startOfDay();
                    } catch (\Throwable $e) {
                        return;
                    }

                    $diff = $desde->diffInDays($hasta);
                    if ($diff > 0 && (int) $value < $diff) {
                        $fail('El campo días no puede ser menor a la diferencia entre la fecha desde y la fecha hasta.');
                    }
                },
            ],
            'horas' => ['required', 'integer', 'min:0', 'max:12'],
            'minutos' => ['required', 'integer', 'min:0', 'max:60'],
        ]);

        $establecimientoId = $isAdmin ? (int) $validated['establecimiento_id'] : $forcedEstablecimientoId;
        abort_if(!$isAdmin && $establecimientoId <= 0, 403, 'Usuario sin establecimiento asociado.');

        /** @var ReemplazoPersonal|null $funcionario */
        $funcionario = ReemplazoPersonal::query()->find($validated['reemplazo_personal_id']);
        if (!$funcionario || (int) $funcionario->establecimiento_id !== (int) $establecimientoId) {
            throw ValidationException::withMessages([
                'reemplazo_personal_id' => 'El funcionario seleccionado no pertenece al establecimiento indicado.',
            ]);
        }

        $establecimiento = Establecimiento::query()->findOrFail($establecimientoId);

        $payload = [
            'establecimiento_id' => $establecimiento->id,
            'reemplazo_personal_id' => $funcionario->id,
            'funcionario_rut' => strtoupper(preg_replace('/[^0-9Kk]/', '', (string) $funcionario->rut)),
            'funcionario_nombre' => trim((string) $funcionario->nombre),
            'funcionario_rbd' => (int) ($establecimiento->rbd ?? $funcionario->rbd ?? 0),
            'fecha_desde' => Carbon::parse($validated['fecha_desde'])->format('Y-m-d'),
            'fecha_hasta' => Carbon::parse($validated['fecha_hasta'])->format('Y-m-d'),
            'dias' => (int) $validated['dias'],
            'horas' => (int) $validated['horas'],
            'minutos' => (int) $validated['minutos'],
        ];

        return [$validated, $payload];
    }


    private function snapshotItem(IncumplimientoLaboral $item): array
    {
        return [
            'establecimiento_id' => $item->establecimiento_id ? (int) $item->establecimiento_id : null,
            'establecimiento' => $item->establecimiento?->nombre_establecimiento,
            'establecimiento_rbd' => $item->establecimiento?->rbd ? (string) $item->establecimiento?->rbd : ($item->funcionario_rbd ? (string) $item->funcionario_rbd : null),
            'funcionario_rut' => (string) $item->funcionario_rut,
            'funcionario_rut_formatted' => Rut::format($item->funcionario_rut) ?? $item->funcionario_rut,
            'funcionario_nombre' => (string) $item->funcionario_nombre,
            'reemplazo_personal_id' => $item->reemplazo_personal_id ? (int) $item->reemplazo_personal_id : null,
            'fecha_desde' => optional($item->fecha_desde)->format('Y-m-d'),
            'fecha_hasta' => optional($item->fecha_hasta)->format('Y-m-d'),
            'dias' => (int) $item->dias,
            'horas' => (int) $item->horas,
            'minutos' => (int) $item->minutos,
        ];
    }

    private function buildChangedFields(?array $before, ?array $after): array
    {
        $before = $before ?: [];
        $after = $after ?: [];

        $labels = [
            'establecimiento' => 'Establecimiento',
            'establecimiento_rbd' => 'RBD',
            'funcionario_rut_formatted' => 'RUT funcionario',
            'funcionario_nombre' => 'Funcionario',
            'fecha_desde' => 'Fecha desde',
            'fecha_hasta' => 'Fecha hasta',
            'dias' => 'Días',
            'horas' => 'Horas',
            'minutos' => 'Minutos',
        ];

        $changed = [];
        foreach ($labels as $key => $label) {
            $oldValue = $before[$key] ?? null;
            $newValue = $after[$key] ?? null;
            if ((string) $oldValue === (string) $newValue) {
                continue;
            }

            $changed[] = [
                'key' => $key,
                'label' => $label,
                'from' => $oldValue,
                'to' => $newValue,
            ];
        }

        return $changed;
    }

    private function logHistory(IncumplimientoLaboral $item, string $action, ?int $userId, ?array $before = null, ?array $after = null, array $changedFields = []): void
    {
        IncumplimientoLaboralHistorial::query()->create([
            'incumplimiento_laboral_id' => $item->id,
            'action' => $action,
            'user_id' => $userId,
            'old_values' => $before,
            'new_values' => $after,
            'changed_fields' => $changedFields,
        ]);
    }

    private function buildFuncionarioOption(?int $reemplazoPersonalId, ?int $establecimientoId): ?array
    {
        if (!$reemplazoPersonalId || !$establecimientoId) {
            return null;
        }

        $row = ReemplazoPersonal::query()
            ->where('establecimiento_id', $establecimientoId)
            ->find($reemplazoPersonalId);

        if (!$row) {
            return null;
        }

        return [
            'id' => (string) $row->id,
            'text' => trim((Rut::format($row->rut) ?? $row->rut) . ' · ' . $row->nombre),
        ];
    }
}
