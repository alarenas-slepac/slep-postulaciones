<?php

namespace App\Http\Controllers;

use App\Models\Establecimiento;
use App\Models\ReemplazoPersonal;
use App\Models\ReemplazoPersonalBloqueo;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReemplazosController extends Controller
{
    public function __construct()
    {
        $this->middleware([
            'auth',
            'ensure.role:admin|funcionario_slep|coordinador_gdp|coordinador_uatp|funcionario_estab|supervisor_plani|coordinador_plani',
        ]);

        $this->middleware([
            'auth',
            'ensure.role:admin',
        ])->only(['editPersonal', 'updatePersonal']);

        $this->middleware([
            'auth',
            'ensure.role:admin|funcionario_slep|coordinador_uatp|supervisor_plani|coordinador_plani',
        ])->only(['bloquearPersonal', 'desbloquearPersonal', 'traspasarBloqueosPersonal']);
    }

    public function index(Request $request): View
    {
        [$filters, $context] = $this->resolvePadronContext($request);

        $padronQuery = $this->buildPadronQuery($filters);

        $summary = [
            'total' => (clone $padronQuery)->count(),
            'establecimientos' => (clone $padronQuery)->distinct()->count('reemplazos_personal.establecimiento_id'),
            'ultima_actualizacion' => (clone $padronQuery)->max('reemplazos_personal.updated_at'),
            'bloqueados' => (clone $padronQuery)->whereHas('bloqueoActivo')->count(),
        ];

        $resumenEstablecimientos = (clone $padronQuery)
            ->selectRaw('reemplazos_personal.establecimiento_id, MAX(reemplazos_personal.rbd) as rbd, MAX(establecimientos.nombre_establecimiento) as establecimiento_nombre, COUNT(*) as total_registros')
            ->groupBy('reemplazos_personal.establecimiento_id')
            ->orderBy('establecimiento_nombre')
            ->get();

        $personal = (clone $padronQuery)
            ->select('reemplazos_personal.*')
            ->orderBy('establecimientos.nombre_establecimiento')
            ->orderBy('reemplazos_personal.nombre')
            ->paginate($filters['per_page'])
            ->withQueryString();

        return view('reemplazos.index', [
            'title' => 'Reemplazos',
            'filters' => $filters,
            'periodOptions' => $context['periodOptions'],
            'establecimientos' => $context['establecimientos'],
            'resumenEstablecimientos' => $resumenEstablecimientos,
            'summary' => $summary,
            'personal' => $personal,
            'selectedPeriodLabel' => $context['selectedPeriodLabel'],
            'latestFile' => $context['latestFile'],
            'isFuncionarioEstab' => $context['isFuncionarioEstab'],
            'forcedEstablecimiento' => $context['forcedEstablecimiento'],
            'hasPadron' => $context['hasPadron'],
            'lockedWithoutEstablecimiento' => $context['lockedWithoutEstablecimiento'],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$filters, $context] = $this->resolvePadronContext($request);

        $rows = $this->buildPadronQuery($filters)
            ->select('reemplazos_personal.*')
            ->orderBy('establecimientos.nombre_establecimiento')
            ->orderBy('reemplazos_personal.nombre')
            ->get();

        $suffix = $filters['period_key'];
        if (!empty($filters['establecimiento_id'])) {
            $est = $context['establecimientos']->firstWhere('id', (int) $filters['establecimiento_id']);
            if ($est) {
                $suffix .= '-' . Str::slug($est->nombre_establecimiento ?: ('rbd-' . $est->rbd));
            }
        }

        $filename = 'padron-personal-' . $suffix . '.csv';

        return response()->streamDownload(function () use ($rows, $context) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Periodo',
                'RBD',
                'Establecimiento',
                'RUT',
                'Nombre',
                'Fecha nacimiento',
                'Fecha ingreso',
                'Fecha termino',
                'Tipo contrato',
                'Financiamiento',
                'Estatuto',
                'Escalafon',
                'Jornada',
                'Jornada basica',
                'Jornada media',
                'Ultima actualizacion',
            ], ';');

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $context['selectedPeriodLabel'],
                    $row->rbd,
                    $row->establecimiento?->nombre_establecimiento,
                    $row->rut,
                    $row->nombre,
                    $this->formatDateOnly($row->fecha_nacimiento),
                    $this->formatDateOnly($row->fecha_ingreso),
                    $this->formatDateOnly($row->fecha_termino),
                    $row->tipocontrato,
                    $row->financiamiento,
                    $row->estatuto,
                    $row->escalafon,
                    $this->formatHours($row->jornada),
                    $this->formatHours($row->jornada_basica),
                    $this->formatHours($row->jornada_media),
                    cl_datetime($row->updated_at, 'd/m/Y H:i', ''),
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function editPersonal(Request $request, ReemplazoPersonal $reemplazoPersonal): View
    {
        $reemplazoPersonal->load('establecimiento:id,rbd,nombre_establecimiento');

        $establecimientos = Establecimiento::query()
            ->select('id', 'rbd', 'nombre_establecimiento')
            ->orderBy('nombre_establecimiento')
            ->get();

        return view('reemplazos.personal.edit', [
            'item' => $reemplazoPersonal,
            'establecimientos' => $establecimientos,
            'returnFilters' => $this->extractReturnFilters($request),
        ]);
    }

    public function updatePersonal(Request $request, ReemplazoPersonal $reemplazoPersonal): RedirectResponse
    {
        $validated = $request->validate([
            'establecimiento_id' => ['required', 'integer', 'exists:establecimientos,id'],
            'return.periodo' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'return.establecimiento_id' => ['nullable', 'integer', 'exists:establecimientos,id'],
            'return.q' => ['nullable', 'string', 'max:120'],
            'return.per_page' => ['nullable', 'integer', 'in:15,25,50,100'],
            'return.page' => ['nullable', 'integer', 'min:1'],
        ]);

        $establecimiento = Establecimiento::query()
            ->select('id', 'rbd', 'nombre_establecimiento')
            ->findOrFail((int) $validated['establecimiento_id']);

        $newRowHash = $this->buildRowHash([
            'rut' => $reemplazoPersonal->rut,
            'rbd' => $establecimiento->rbd,
            'anio' => $reemplazoPersonal->anio,
            'mes' => $reemplazoPersonal->mes,
            'fecha_ingreso' => $reemplazoPersonal->fecha_ingreso,
            'tipocontrato' => $reemplazoPersonal->tipocontrato,
            'financiamiento' => $reemplazoPersonal->financiamiento,
            'estatuto' => $reemplazoPersonal->estatuto,
            'escalafon' => $reemplazoPersonal->escalafon,
            'jornada' => $reemplazoPersonal->jornada,
            'jornada_basica' => $reemplazoPersonal->jornada_basica,
            'jornada_media' => $reemplazoPersonal->jornada_media,
        ]);

        $duplicateExists = ReemplazoPersonal::query()
            ->where('row_hash', $newRowHash)
            ->whereKeyNot($reemplazoPersonal->getKey())
            ->exists();

        if ($duplicateExists) {
            return back()
                ->withInput()
                ->withErrors([
                    'establecimiento_id' => 'No se puede mover el registro porque ya existe otro funcionario con la misma clave de padrón en el establecimiento destino para este período.',
                ]);
        }

        $oldEstablecimiento = $reemplazoPersonal->establecimiento?->nombre_establecimiento ?: ('RBD ' . $reemplazoPersonal->rbd);

        $reemplazoPersonal->forceFill([
            'establecimiento_id' => $establecimiento->id,
            'rbd' => (int) $establecimiento->rbd,
            'row_hash' => $newRowHash,
        ])->save();

        $returnFilters = $validated['return'] ?? [];

        return redirect()
            ->route('reemplazos.index', array_filter([
                'periodo' => $returnFilters['periodo'] ?? null,
                'establecimiento_id' => $returnFilters['establecimiento_id'] ?? null,
                'q' => $returnFilters['q'] ?? null,
                'per_page' => $returnFilters['per_page'] ?? null,
                'page' => $returnFilters['page'] ?? null,
            ], fn($value) => $value !== null && $value !== ''))
            ->with('status', 'Registro actualizado: ' . $reemplazoPersonal->nombre . ' fue movido desde ' . $oldEstablecimiento . ' a ' . ($establecimiento->nombre_establecimiento ?: ('RBD ' . $establecimiento->rbd)) . '.');
    }

    public function bloquearPersonal(Request $request, ReemplazoPersonal $reemplazoPersonal): RedirectResponse
    {
        $validated = $request->validate([
            'motivo' => ['required', 'string', 'max:255'],
            'observacion' => ['nullable', 'string', 'max:2000'],
            'return.periodo' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'return.establecimiento_id' => ['nullable', 'integer', 'exists:establecimientos,id'],
            'return.q' => ['nullable', 'string', 'max:120'],
            'return.per_page' => ['nullable', 'integer', 'in:15,25,50,100'],
            'return.page' => ['nullable', 'integer', 'min:1'],
        ]);

        if (!$this->esPersonalBloqueablePadron($reemplazoPersonal)) {
            return $this->redirectToPadronWithReturn($validated['return'] ?? [])
                ->with('error', 'Sólo se pueden bloquear docentes o AAEE titulares del padrón.');
        }

        $tipoPersonal = $this->tipoPersonalBloqueablePadron($reemplazoPersonal);

        DB::transaction(function () use ($reemplazoPersonal, $validated, $request) {
            $existing = ReemplazoPersonalBloqueo::query()
                ->where('reemplazo_personal_id', $reemplazoPersonal->id)
                ->where('activo', true)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return;
            }

            ReemplazoPersonalBloqueo::create([
                'reemplazo_personal_id' => $reemplazoPersonal->id,
                'establecimiento_id' => $reemplazoPersonal->establecimiento_id,
                'rbd' => $reemplazoPersonal->rbd,
                'rut' => $reemplazoPersonal->rut,
                'nombre' => $reemplazoPersonal->nombre,
                'motivo' => $validated['motivo'],
                'observacion' => $validated['observacion'] ?? null,
                'activo' => true,
                'bloqueado_por' => $request->user()?->id,
            ]);
        });

        return $this->redirectToPadronWithReturn($validated['return'] ?? [])
            ->with('status', $tipoPersonal . ' bloqueado correctamente: ' . $reemplazoPersonal->nombre . '.');
    }

    public function desbloquearPersonal(Request $request, ReemplazoPersonal $reemplazoPersonal): RedirectResponse
    {
        $validated = $request->validate([
            'return.periodo' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'return.establecimiento_id' => ['nullable', 'integer', 'exists:establecimientos,id'],
            'return.q' => ['nullable', 'string', 'max:120'],
            'return.per_page' => ['nullable', 'integer', 'in:15,25,50,100'],
            'return.page' => ['nullable', 'integer', 'min:1'],
        ]);

        $tipoPersonal = $this->tipoPersonalBloqueablePadron($reemplazoPersonal);

        $updated = ReemplazoPersonalBloqueo::query()
            ->where('reemplazo_personal_id', $reemplazoPersonal->id)
            ->where('activo', true)
            ->update([
                'activo' => false,
                'desbloqueado_por' => $request->user()?->id,
                'desbloqueado_at' => now(),
                'updated_at' => now(),
            ]);

        $message = $updated > 0
            ? $tipoPersonal . ' desbloqueado correctamente: ' . $reemplazoPersonal->nombre . '.'
            : 'El registro no tenía un bloqueo activo.';

        return $this->redirectToPadronWithReturn($validated['return'] ?? [])
            ->with('status', $message);
    }

    public function traspasarBloqueosPersonal(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'periodo_origen' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'periodo_destino' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ], [
            'periodo_origen.required' => 'Debes seleccionar el padrón origen.',
            'periodo_destino.required' => 'Debes seleccionar el padrón destino.',
        ]);

        if ($validated['periodo_origen'] === $validated['periodo_destino']) {
            return redirect()->route('reemplazos.index', ['periodo' => $validated['periodo_destino']])
                ->with('error', 'El padrón origen y el padrón destino deben ser distintos.');
        }

        [$origenAnio, $origenMes] = $this->parsePeriodKey($validated['periodo_origen']);
        [$destinoAnio, $destinoMes] = $this->parsePeriodKey($validated['periodo_destino']);

        if (!$origenAnio || !$origenMes || !$destinoAnio || !$destinoMes) {
            return redirect()->route('reemplazos.index')
                ->with('error', 'Los períodos seleccionados no son válidos.');
        }

        $resumen = DB::transaction(function () use ($origenAnio, $origenMes, $destinoAnio, $destinoMes, $validated, $request) {
            $bloqueosOrigen = ReemplazoPersonalBloqueo::query()
                ->with(['personal.establecimiento:id,rbd,nombre_establecimiento'])
                ->where('activo', true)
                ->whereHas('personal', function ($query) use ($origenAnio, $origenMes) {
                    $query->where('anio', $origenAnio)
                        ->where('mes', $origenMes);
                })
                ->orderBy('rbd')
                ->orderBy('rut')
                ->get();

            if ($bloqueosOrigen->isEmpty()) {
                return [
                    'periodo_origen' => $this->formatPeriodKeyLabel($validated['periodo_origen']),
                    'periodo_destino' => $this->formatPeriodKeyLabel($validated['periodo_destino']),
                    'bloqueos_encontrados' => 0,
                    'traspasados' => 0,
                    'ya_existian' => 0,
                    'no_encontrados' => 0,
                    'omitidos_no_bloqueables' => 0,
                    'detalle' => [],
                ];
            }

            $ruts = $bloqueosOrigen
                ->map(fn($bloqueo) => $this->normalizarRutPadron($bloqueo->rut ?: $bloqueo->personal?->rut))
                ->filter()
                ->unique()
                ->values();

            $rbds = $bloqueosOrigen
                ->map(fn($bloqueo) => (int) ($bloqueo->rbd ?: $bloqueo->personal?->rbd))
                ->filter()
                ->unique()
                ->values();

            $destinos = ReemplazoPersonal::query()
                ->where('anio', $destinoAnio)
                ->where('mes', $destinoMes)
                ->when($rbds->isNotEmpty(), fn($query) => $query->whereIn('rbd', $rbds->all()))
                ->get()
                ->filter(fn($row) => $ruts->contains($this->normalizarRutPadron($row->rut)) && $this->esPersonalBloqueablePadron($row))
                ->groupBy(fn($row) => $this->claveTraspasoBloqueo($row->rut, $row->rbd));

            $detalle = [];
            $resumen = [
                'periodo_origen' => $this->formatPeriodKeyLabel($validated['periodo_origen']),
                'periodo_destino' => $this->formatPeriodKeyLabel($validated['periodo_destino']),
                'bloqueos_encontrados' => $bloqueosOrigen->count(),
                'traspasados' => 0,
                'ya_existian' => 0,
                'no_encontrados' => 0,
                'omitidos_no_bloqueables' => 0,
                'detalle' => [],
            ];

            foreach ($bloqueosOrigen as $bloqueo) {
                $personalOrigen = $bloqueo->personal;

                if (!$personalOrigen || !$this->esPersonalBloqueablePadron($personalOrigen)) {
                    $resumen['omitidos_no_bloqueables']++;
                    $this->pushDetalleTraspaso($detalle, $bloqueo, 'Omitido: el registro origen no corresponde a Docente o AAEE bloqueable.');
                    continue;
                }

                $rbd = (int) ($bloqueo->rbd ?: $personalOrigen->rbd);
                $rut = $bloqueo->rut ?: $personalOrigen->rut;
                $key = $this->claveTraspasoBloqueo($rut, $rbd);
                $destinosCoincidentes = $destinos->get($key, collect());

                if ($destinosCoincidentes->isEmpty()) {
                    $resumen['no_encontrados']++;
                    $this->pushDetalleTraspaso($detalle, $bloqueo, 'No encontrado en el padrón destino con el mismo RUT y RBD.');
                    continue;
                }

                foreach ($destinosCoincidentes as $personalDestino) {
                    $yaExiste = ReemplazoPersonalBloqueo::query()
                        ->where('reemplazo_personal_id', $personalDestino->id)
                        ->where('activo', true)
                        ->lockForUpdate()
                        ->exists();

                    if ($yaExiste) {
                        $resumen['ya_existian']++;
                        $this->pushDetalleTraspaso($detalle, $bloqueo, 'Ya existía bloqueo activo en destino para ' . $personalDestino->nombre . '.');
                        continue;
                    }

                    ReemplazoPersonalBloqueo::create([
                        'reemplazo_personal_id' => $personalDestino->id,
                        'establecimiento_id' => $personalDestino->establecimiento_id,
                        'rbd' => $personalDestino->rbd,
                        'rut' => $personalDestino->rut,
                        'nombre' => $personalDestino->nombre,
                        'motivo' => $bloqueo->motivo,
                        'observacion' => $this->observacionTraspasoBloqueo($bloqueo->observacion, $validated['periodo_origen']),
                        'activo' => true,
                        'bloqueado_por' => $request->user()?->id,
                    ]);

                    $resumen['traspasados']++;
                    $this->pushDetalleTraspaso($detalle, $bloqueo, 'Traspasado a ' . $personalDestino->nombre . '.');
                }
            }

            $resumen['detalle'] = array_slice($detalle, 0, 80);

            return $resumen;
        });

        return redirect()->route('reemplazos.index', ['periodo' => $validated['periodo_destino']])
            ->with('status', 'Traspaso de bloqueos finalizado: ' . $resumen['traspasados'] . ' bloqueo(s) traspasado(s) al padrón destino.')
            ->with('traspaso_bloqueos_resumen', $resumen);
    }


    private function normalizarRutPadron(?string $rut): string
    {
        return preg_replace('/[^0-9Kk]/', '', mb_strtoupper((string) $rut)) ?: '';
    }

    private function claveTraspasoBloqueo(?string $rut, $rbd): string
    {
        return $this->normalizarRutPadron($rut) . '|' . (int) $rbd;
    }

    private function formatPeriodKeyLabel(string $periodKey): string
    {
        [$anio, $mes] = $this->parsePeriodKey($periodKey);

        if (!$anio || !$mes) {
            return $periodKey;
        }

        return str_pad((string) $mes, 2, '0', STR_PAD_LEFT) . '/' . $anio;
    }

    private function observacionTraspasoBloqueo(?string $observacionOriginal, string $periodoOrigen): string
    {
        $nota = 'Bloqueo traspasado automáticamente desde el padrón ' . $this->formatPeriodKeyLabel($periodoOrigen) . '.';
        $observacionOriginal = trim((string) $observacionOriginal);

        return $observacionOriginal !== ''
            ? $observacionOriginal . "\n\n" . $nota
            : $nota;
    }

    private function pushDetalleTraspaso(array &$detalle, ReemplazoPersonalBloqueo $bloqueo, string $resultado): void
    {
        if (count($detalle) >= 80) {
            return;
        }

        $detalle[] = [
            'rut' => $bloqueo->rut ?: $bloqueo->personal?->rut,
            'nombre' => $bloqueo->nombre ?: $bloqueo->personal?->nombre,
            'rbd' => $bloqueo->rbd ?: $bloqueo->personal?->rbd,
            'motivo' => $bloqueo->motivo,
            'resultado' => $resultado,
        ];
    }

    private function redirectToPadronWithReturn(array $returnFilters): RedirectResponse
    {
        return redirect()->route('reemplazos.index', array_filter([
            'periodo' => $returnFilters['periodo'] ?? null,
            'establecimiento_id' => $returnFilters['establecimiento_id'] ?? null,
            'q' => $returnFilters['q'] ?? null,
            'per_page' => $returnFilters['per_page'] ?? null,
            'page' => $returnFilters['page'] ?? null,
        ], fn($value) => $value !== null && $value !== ''));
    }

    private function esPersonalBloqueablePadron(ReemplazoPersonal $reemplazoPersonal): bool
    {
        return $this->esDocentePadron($reemplazoPersonal)
            || $this->esAaeePadron($reemplazoPersonal);
    }

    private function tipoPersonalBloqueablePadron(ReemplazoPersonal $reemplazoPersonal): string
    {
        return $this->esAaeePadron($reemplazoPersonal) ? 'AAEE' : 'Docente';
    }

    private function esDocentePadron(ReemplazoPersonal $reemplazoPersonal): bool
    {
        $estatuto = mb_strtoupper(trim((string) $reemplazoPersonal->estatuto));

        return in_array($estatuto, ['DOCENTE', 'PROFESOR', 'PROFESORA'], true)
            || str_contains($estatuto, 'DOC');
    }

    private function esAaeePadron(ReemplazoPersonal $reemplazoPersonal): bool
    {
        $estatuto = mb_strtoupper(trim((string) $reemplazoPersonal->estatuto));
        $escalafon = mb_strtoupper(trim((string) $reemplazoPersonal->escalafon));
        $texto = $estatuto . ' ' . $escalafon;

        return in_array($estatuto, ['AAEE', 'ASISTENTE DE LA EDUCACION', 'ASISTENTE DE LA EDUCACIÓN'], true)
            || str_contains($texto, 'AAEE')
            || str_contains($texto, 'ASISTENTE DE LA EDUCACION')
            || str_contains($texto, 'ASISTENTE DE LA EDUCACIÓN')
            || str_contains($texto, 'ASISTENTES DE LA EDUCACION')
            || str_contains($texto, 'ASISTENTES DE LA EDUCACIÓN');
    }

    private function resolvePadronContext(Request $request): array
    {
        $user = $request->user();
        $isFuncionarioEstab = $user && method_exists($user, 'hasRole') && $user->hasRole('funcionario_estab');
        $forcedEstablecimiento = $isFuncionarioEstab ? $user->establecimiento()->first() : null;

        $periodOptions = ReemplazoPersonal::query()
            ->select('anio', 'mes')
            ->distinct()
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->get()
            ->map(function ($row) {
                $month = str_pad((string) $row->mes, 2, '0', STR_PAD_LEFT);

                return (object) [
                    'key' => sprintf('%04d-%02d', (int) $row->anio, (int) $row->mes),
                    'label' => $month . '/' . $row->anio,
                    'anio' => (int) $row->anio,
                    'mes' => (int) $row->mes,
                ];
            });

        $latestPeriod = $periodOptions->first();
        $validated = $request->validate([
            'periodo' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'establecimiento_id' => ['nullable', 'integer', 'exists:establecimientos,id'],
            'q' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'in:15,25,50,100'],
        ]);

        $selectedPeriodKey = (string) ($validated['periodo'] ?? ($latestPeriod->key ?? ''));
        if ($selectedPeriodKey !== '' && !$periodOptions->contains(fn($period) => $period->key === $selectedPeriodKey)) {
            $selectedPeriodKey = $latestPeriod->key ?? '';
        }

        [$selectedYear, $selectedMonth] = $this->parsePeriodKey($selectedPeriodKey);

        $establecimientosQuery = Establecimiento::query()
            ->select('establecimientos.id', 'establecimientos.rbd', 'establecimientos.nombre_establecimiento')
            ->whereIn('establecimientos.id', function ($sub) use ($selectedYear, $selectedMonth) {
                $sub->from('reemplazos_personal')
                    ->select('establecimiento_id')
                    ->where('anio', $selectedYear)
                    ->where('mes', $selectedMonth)
                    ->distinct();
            })
            ->orderBy('establecimientos.nombre_establecimiento');

        if ($forcedEstablecimiento) {
            $establecimientosQuery->where('establecimientos.id', $forcedEstablecimiento->id);
        }

        $establecimientos = $establecimientosQuery->get();

        $lockedWithoutEstablecimiento = $isFuncionarioEstab && !$forcedEstablecimiento;

        $selectedEstablecimientoId = $forcedEstablecimiento?->id ?: ($validated['establecimiento_id'] ?? null);
        if ($selectedEstablecimientoId && !$establecimientos->contains('id', (int) $selectedEstablecimientoId)) {
            $selectedEstablecimientoId = $forcedEstablecimiento?->id;
        }

        $latestFile = null;
        if ($selectedYear && $selectedMonth) {
            $latestFile = ReemplazoPersonal::query()
                ->where('anio', $selectedYear)
                ->where('mes', $selectedMonth)
                ->whereNotNull('source_filename')
                ->orderByDesc('updated_at')
                ->value('source_filename');
        }

        $selectedPeriodLabel = $selectedPeriodKey !== ''
            ? str_pad((string) $selectedMonth, 2, '0', STR_PAD_LEFT) . '/' . $selectedYear
            : 'Sin padrón cargado';

        $filters = [
            'period_key' => $selectedPeriodKey,
            'anio' => $selectedYear,
            'mes' => $selectedMonth,
            'establecimiento_id' => $selectedEstablecimientoId ? (int) $selectedEstablecimientoId : null,
            'q' => trim((string) ($validated['q'] ?? '')),
            'per_page' => (int) ($validated['per_page'] ?? 25),
            'locked_without_establecimiento' => $lockedWithoutEstablecimiento,
        ];

        return [$filters, [
            'periodOptions' => $periodOptions,
            'establecimientos' => $establecimientos,
            'selectedPeriodLabel' => $selectedPeriodLabel,
            'latestFile' => $latestFile,
            'isFuncionarioEstab' => $isFuncionarioEstab,
            'forcedEstablecimiento' => $forcedEstablecimiento,
            'hasPadron' => $latestPeriod !== null,
            'lockedWithoutEstablecimiento' => $lockedWithoutEstablecimiento,
        ]];
    }

    private function buildPadronQuery(array $filters)
    {
        $query = ReemplazoPersonal::query()
            ->leftJoin('establecimientos', 'establecimientos.id', '=', 'reemplazos_personal.establecimiento_id')
            ->with([
                'establecimiento:id,rbd,nombre_establecimiento',
                'bloqueoActivo',
            ]);

        if (!empty($filters['locked_without_establecimiento'])) {
            $query->whereRaw('1 = 0');
        } elseif (!empty($filters['anio']) && !empty($filters['mes'])) {
            $query->where('reemplazos_personal.anio', $filters['anio'])
                ->where('reemplazos_personal.mes', $filters['mes']);
        } else {
            $query->whereRaw('1 = 0');
        }

        if (!empty($filters['establecimiento_id'])) {
            $query->where('reemplazos_personal.establecimiento_id', (int) $filters['establecimiento_id']);
        }

        if ($filters['q'] !== '') {
            $tokens = array_values(array_filter(preg_split('/\s+/', $filters['q']) ?: []));
            foreach ($tokens as $token) {
                $query->where(function ($inner) use ($token) {
                    $inner->where('reemplazos_personal.rut', 'like', '%' . $token . '%')
                        ->orWhere('reemplazos_personal.nombre', 'like', '%' . $token . '%')
                        ->orWhere('reemplazos_personal.rbd', 'like', '%' . $token . '%')
                        ->orWhere('establecimientos.nombre_establecimiento', 'like', '%' . $token . '%');
                });
            }
        }

        return $query;
    }

    private function extractReturnFilters(Request $request): array
    {
        return [
            'periodo' => $request->query('periodo'),
            'establecimiento_id' => $request->query('establecimiento_id'),
            'q' => $request->query('q'),
            'per_page' => $request->query('per_page'),
            'page' => $request->query('page'),
        ];
    }

    private function buildRowHash(array $data): string
    {
        $fechaIngreso = $data['fecha_ingreso'] ?? null;
        if ($fechaIngreso instanceof \DateTimeInterface) {
            $fechaIngreso = $fechaIngreso->format('Y-m-d');
        } elseif (!empty($fechaIngreso)) {
            try {
                $fechaIngreso = \Illuminate\Support\Carbon::parse($fechaIngreso)->format('Y-m-d');
            } catch (\Throwable $e) {
                $fechaIngreso = (string) $fechaIngreso;
            }
        } else {
            $fechaIngreso = '';
        }

        $hashInput = implode('|', [
            mb_strtolower(trim((string) ($data['rut'] ?? ''))),
            (int) ($data['rbd'] ?? 0),
            (int) ($data['anio'] ?? 0),
            (int) ($data['mes'] ?? 0),
            $fechaIngreso,
            mb_strtolower(trim((string) ($data['tipocontrato'] ?? ''))),
            mb_strtolower(trim((string) ($data['financiamiento'] ?? ''))),
            mb_strtolower(trim((string) ($data['estatuto'] ?? ''))),
            mb_strtolower(trim((string) ($data['escalafon'] ?? ''))),
            (string) ($data['jornada'] ?? ''),
            (string) ($data['jornada_basica'] ?? ''),
            (string) ($data['jornada_media'] ?? ''),
        ]);

        return hash('sha256', $hashInput);
    }

    private function parsePeriodKey(string $periodKey): array
    {
        if (!preg_match('/^(\d{4})-(\d{2})$/', $periodKey, $matches)) {
            return [null, null];
        }

        return [(int) $matches[1], (int) $matches[2]];
    }

    private function formatDateOnly($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y');
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    private function formatHours($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_numeric($value) && (float) $value == (int) $value) {
            return (string) ((int) $value);
        }

        return (string) $value;
    }
}
