<?php

namespace App\Http\Controllers;

use App\Models\CargaFamiliar;
use App\Models\CargaFamiliarCausante;
use App\Models\CargaFamiliarDocumento;
use App\Models\CargaFamiliarSolicitud;
use App\Models\User;
use App\Services\CargaFamiliarImportService;
use App\Services\CargaFamiliarRutService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class CargaFamiliarController extends Controller
{
    private const APPLICANT_ROLES = ['postulante', 'funcionario', 'funcionario_ac'];
    private const REVIEWER_ROLES = ['admin', 'coordinador_gdp', 'funcionario_slep'];

    public function __construct(
        private readonly CargaFamiliarImportService $importService,
        private readonly CargaFamiliarRutService $rutService
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($this->isApplicant($user), 403, (string) config('cargas_familiares.acceso_solicitantes.mensaje_bloqueo', 'Acceso temporalmente bloqueado.'));

        $this->importService->associateForUser($user);

        $cargasVigentes = CargaFamiliar::query()
            ->where('user_id', $user->id)
            ->where('estado_carga', 'vigente')
            ->orderBy('causante_apellido_paterno')
            ->orderBy('causante_apellido_materno')
            ->orderBy('causante_nombres')
            ->get();

        $solicitudes = CargaFamiliarSolicitud::query()
            ->withCount(['causantes', 'documentos'])
            ->where('user_id', $user->id)
            ->latest('fecha_envio')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('tramites.cargas-familiares.index', [
            'user' => $user,
            'cargasVigentes' => $cargasVigentes,
            'solicitudes' => $solicitudes,
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        abort_unless($this->isApplicant($user), 403, (string) config('cargas_familiares.acceso_solicitantes.mensaje_bloqueo', 'Acceso temporalmente bloqueado.'));

        $this->importService->associateForUser($user);

        return view('tramites.cargas-familiares.create', [
            'user' => $user->loadMissing('postulantProfile.comuna'),
            'beneficiario' => $this->beneficiarioSnapshot($user),
            'cargasVigentes' => CargaFamiliar::query()
                ->where('user_id', $user->id)
                ->where('estado_carga', 'vigente')
                ->orderBy('causante_apellido_paterno')
                ->get(),
            'documentLabels' => CargaFamiliarDocumento::labels(),
            'parentescoOptions' => (array) config('cargas_familiares.parentescos', []),
            'beneficioOptions' => (array) config('cargas_familiares.beneficios', []),
            'causanteOptions' => $this->causanteOptions(),
            'documentacionCausantes' => $this->documentacionCausantesForJs(),
            'mesesPrimerSemestre' => $this->mesesPrimerSemestre(),
            'mesesSegundoSemestre' => $this->mesesSegundoSemestre(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->isApplicant($user), 403, (string) config('cargas_familiares.acceso_solicitantes.mensaje_bloqueo', 'Acceso temporalmente bloqueado.'));

        $validated = $this->validateSolicitud($request);

        $solicitud = DB::transaction(function () use ($request, $user, $validated) {
            $solicitud = CargaFamiliarSolicitud::query()->create([
                'user_id' => $user->id,
                'tipo_solicitud' => (string) $validated['tipo_solicitud'],
                'estado' => 'enviado',
                'beneficiario_snapshot' => $this->beneficiarioSnapshot($user, (array) $validated['beneficiario']),
                'solicitante_distinto' => $request->boolean('solicitante_distinto'),
                'solicitante_snapshot' => $request->boolean('solicitante_distinto') ? (array) ($validated['solicitante'] ?? []) : null,
                'solicita_pago_directo' => $request->boolean('solicita_pago_directo'),
                'declaracion_aceptada' => $request->boolean('declaracion_aceptada'),
                'declaracion_ingresos' => $this->normalizeDeclaracionIngresos((array) $validated['declaracion_ingresos']),
                'fecha_envio' => now(),
            ]);

            $this->storeSolicitudDocuments($request, $solicitud, $user->id);
            $this->storeCausantes($request, $solicitud, $validated, $user->id);

            return $solicitud;
        });

        return redirect()
            ->route('tramites.cargas-familiares.show', $solicitud)
            ->with('status', 'Solicitud de cargas familiares enviada correctamente.');
    }

    public function show(Request $request, CargaFamiliarSolicitud $solicitud): View
    {
        abort_unless($this->canAccessSolicitud($request->user(), $solicitud), 403);

        $solicitud->load([
            'user.roles',
            'causantes.documentos.uploader',
            'causantes.documentos.reviewedBy',
            'causantes.cargaVigente',
            'documentosSolicitud.uploader',
            'documentosSolicitud.reviewedBy',
            'revisadoPor',
        ]);

        $view = $this->isReviewer($request->user())
            ? 'tramites.cargas-familiares.review-show'
            : 'tramites.cargas-familiares.show';

        return view($view, [
            'solicitud' => $solicitud,
            'documentLabels' => CargaFamiliarDocumento::labels(),
            'canReview' => $this->isReviewer($request->user()),
        ]);
    }

    public function downloadDocumento(Request $request, CargaFamiliarSolicitud $solicitud, CargaFamiliarDocumento $documento)
    {
        abort_unless($this->canAccessSolicitud($request->user(), $solicitud), 403);
        abort_unless((int) $documento->solicitud_id === (int) $solicitud->id, 404);
        abort_unless($documento->path && Storage::disk('local')->exists($documento->path), 404);

        return Storage::disk('local')->download($documento->path, $documento->original_name ?: basename($documento->path), [
            'Content-Type' => $documento->mime ?: 'application/pdf',
        ]);
    }

    public function viewDocumento(Request $request, CargaFamiliarSolicitud $solicitud, CargaFamiliarDocumento $documento)
    {
        abort_unless($this->canAccessSolicitud($request->user(), $solicitud), 403);
        abort_unless((int) $documento->solicitud_id === (int) $solicitud->id, 404);
        abort_unless($documento->path && Storage::disk('local')->exists($documento->path), 404);

        return response()->file(Storage::disk('local')->path($documento->path), [
            'Content-Type' => $documento->mime ?: 'application/pdf',
            'Content-Disposition' => ResponseHeaderBag::DISPOSITION_INLINE . '; filename="' . ($documento->original_name ?: basename($documento->path)) . '"',
        ]);
    }

    public function reviewIndex(Request $request): View
    {
        abort_unless($this->isReviewer($request->user()), 403);

        $filters = [
            'estado' => trim((string) $request->input('estado', '')),
            'rut' => strtoupper(preg_replace('/[^0-9Kk]/', '', (string) $request->input('rut', '')) ?? ''),
            'fecha_desde' => trim((string) $request->input('fecha_desde', '')),
            'fecha_hasta' => trim((string) $request->input('fecha_hasta', '')),
        ];

        $solicitudes = CargaFamiliarSolicitud::query()
            ->with(['user.roles'])
            ->withCount(['causantes', 'documentos'])
            ->when($filters['estado'] !== '', fn (Builder $q) => $q->where('estado', $filters['estado']))
            ->when($filters['rut'] !== '', fn (Builder $q) => $q->whereHas('user', function (Builder $userQuery) use ($filters) {
                $userQuery->whereRaw("REPLACE(REPLACE(UPPER(rut), '.', ''), '-', '') = ?", [$filters['rut']]);
            }))
            ->when($filters['fecha_desde'] !== '', fn (Builder $q) => $q->whereDate('fecha_envio', '>=', $filters['fecha_desde']))
            ->when($filters['fecha_hasta'] !== '', fn (Builder $q) => $q->whereDate('fecha_envio', '<=', $filters['fecha_hasta']))
            ->latest('fecha_envio')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('tramites.cargas-familiares.review-index', [
            'solicitudes' => $solicitudes,
            'filters' => $filters,
            'estados' => ['enviado', 'en_revision', 'observado', 'aprobado', 'rechazado', 'anulado'],
        ]);
    }


    public function adminIndex(Request $request): View
    {
        abort_unless($this->isReviewer($request->user()), 403);

        $cargaFilters = [
            'q' => trim((string) $request->input('carga_q', $request->input('q', ''))),
            'comuna' => trim((string) $request->input('carga_comuna', $request->input('comuna', ''))),
            'periodo' => trim((string) $request->input('carga_periodo', $request->input('periodo', ''))),
            'estado' => trim((string) $request->input('carga_estado', $request->input('estado_carga', ''))),
            'vinculacion' => trim((string) $request->input('carga_vinculacion', $request->input('vinculacion', ''))),
            'codigo' => trim((string) $request->input('carga_codigo', '')),
        ];

        $solicitudFilters = [
            'q' => trim((string) $request->input('solicitud_q', $request->input('q', ''))),
            'estado' => trim((string) $request->input('solicitud_estado', $request->input('estado_solicitud', ''))),
            'tipo' => trim((string) $request->input('solicitud_tipo', '')),
            'fecha_desde' => trim((string) $request->input('solicitud_fecha_desde', '')),
            'fecha_hasta' => trim((string) $request->input('solicitud_fecha_hasta', '')),
        ];

        $cargasQuery = CargaFamiliar::query()
            ->with('user')
            ->when($cargaFilters['periodo'] !== '', fn (Builder $q) => $q->where('periodo_carga', $cargaFilters['periodo']))
            ->when($cargaFilters['comuna'] !== '', fn (Builder $q) => $q->where('comuna_origen', $cargaFilters['comuna']))
            ->when($cargaFilters['estado'] !== '', fn (Builder $q) => $q->where('estado_carga', $cargaFilters['estado']))
            ->when($cargaFilters['vinculacion'] === 'asociadas', fn (Builder $q) => $q->whereNotNull('user_id'))
            ->when($cargaFilters['vinculacion'] === 'sin_asociar', fn (Builder $q) => $q->whereNull('user_id'))
            ->when($cargaFilters['codigo'] !== '', function (Builder $q) use ($cargaFilters) {
                $codigo = str_pad($cargaFilters['codigo'], 2, '0', STR_PAD_LEFT);
                $q->where(function (Builder $inner) use ($codigo) {
                    $inner->where('codigo_tipo_causante', $codigo)
                        ->orWhere('codigo_siagf', $codigo);
                });
            });

        $this->applyCargaAdminSearch($cargasQuery, $cargaFilters['q']);

        $solicitudesQuery = CargaFamiliarSolicitud::query()
            ->with(['user', 'causantes'])
            ->withCount(['causantes', 'documentos'])
            ->when($solicitudFilters['estado'] !== '', fn (Builder $q) => $q->where('estado', $solicitudFilters['estado']))
            ->when($solicitudFilters['tipo'] !== '', fn (Builder $q) => $q->where('tipo_solicitud', $solicitudFilters['tipo']))
            ->when($solicitudFilters['fecha_desde'] !== '', fn (Builder $q) => $q->whereDate('fecha_envio', '>=', $solicitudFilters['fecha_desde']))
            ->when($solicitudFilters['fecha_hasta'] !== '', fn (Builder $q) => $q->whereDate('fecha_envio', '<=', $solicitudFilters['fecha_hasta']));

        $this->applySolicitudAdminSearch($solicitudesQuery, $solicitudFilters['q']);

        $stats = [
            'cargas_total' => CargaFamiliar::query()->count(),
            'cargas_asociadas' => CargaFamiliar::query()->whereNotNull('user_id')->count(),
            'cargas_sin_asociar' => CargaFamiliar::query()->whereNull('user_id')->count(),
            'cargas_filtradas' => (clone $cargasQuery)->count(),
            'solicitudes_total' => CargaFamiliarSolicitud::query()->count(),
            'solicitudes_pendientes' => CargaFamiliarSolicitud::query()->whereIn('estado', ['enviado', 'en_revision', 'observado'])->count(),
            'solicitudes_filtradas' => (clone $solicitudesQuery)->count(),
        ];

        $cargas = (clone $cargasQuery)
            ->orderBy('comuna_origen')
            ->orderBy('beneficiario_apellido_paterno')
            ->orderBy('beneficiario_apellido_materno')
            ->orderBy('causante_apellido_paterno')
            ->paginate(20, ['*'], 'cargas_page')
            ->withQueryString();

        $solicitudes = (clone $solicitudesQuery)
            ->latest('fecha_envio')
            ->latest('id')
            ->paginate(15, ['*'], 'solicitudes_page')
            ->withQueryString();

        $comunas = CargaFamiliar::query()
            ->whereNotNull('comuna_origen')
            ->distinct()
            ->orderBy('comuna_origen')
            ->pluck('comuna_origen')
            ->filter()
            ->values();

        $periodos = CargaFamiliar::query()
            ->whereNotNull('periodo_carga')
            ->distinct()
            ->orderByDesc('periodo_carga')
            ->pluck('periodo_carga')
            ->filter()
            ->values();

        return view('tramites.cargas-familiares.admin-index', [
            'cargaFilters' => $cargaFilters,
            'solicitudFilters' => $solicitudFilters,
            'filters' => array_merge($cargaFilters, $solicitudFilters),
            'cargas' => $cargas,
            'solicitudes' => $solicitudes,
            'stats' => $stats,
            'comunas' => $comunas,
            'periodos' => $periodos,
            'estadosCarga' => ['vigente', 'suspendida', 'extinguida'],
            'estadosSolicitud' => ['enviado', 'en_revision', 'observado', 'aprobado', 'rechazado', 'anulado'],
            'tiposSolicitud' => ['nueva_carga' => 'Nueva carga', 'actualizacion' => 'Actualizacion'],
            'codigosCausante' => $this->causanteOptions(),
        ]);
    }


    public function adminCargaShow(Request $request, CargaFamiliar $cargaFamiliar): View
    {
        abort_unless($this->isReviewer($request->user()), 403);

        $cargaFamiliar->load(['user.roles', 'importedBy', 'causantesSolicitados.solicitud.user']);

        $codigo = trim((string) ($cargaFamiliar->codigo_tipo_causante ?: $cargaFamiliar->codigo_siagf));
        $codigo = $codigo !== '' ? str_pad($codigo, 2, '0', STR_PAD_LEFT) : '';
        $configCodigo = $codigo !== '' ? (array) config('cargas_familiares.codigos_causante.' . $codigo, []) : [];
        $documentLabels = CargaFamiliarDocumento::labels();

        $documentosObligatorios = collect((array) ($configCodigo['documentos_obligatorios'] ?? []))
            ->map(fn (string $docType) => $documentLabels[$docType] ?? $docType)
            ->values();

        $documentosCondicionales = collect((array) ($configCodigo['documentos_condicionales'] ?? []))
            ->map(function (array $condition, string $key) use ($documentLabels) {
                $docType = (string) ($condition['documento'] ?? '');

                return [
                    'key' => $key,
                    'pregunta' => (string) ($condition['pregunta'] ?? $key),
                    'documento' => $docType,
                    'label' => $docType !== '' ? ($documentLabels[$docType] ?? $docType) : '',
                    'ayuda' => (string) ($condition['ayuda'] ?? ''),
                ];
            })
            ->values();

        return view('tramites.cargas-familiares.admin-carga-show', [
            'carga' => $cargaFamiliar,
            'codigo' => $codigo,
            'configCodigo' => $configCodigo,
            'documentosObligatorios' => $documentosObligatorios,
            'documentosCondicionales' => $documentosCondicionales,
        ]);
    }

    private function applyCargaAdminSearch(Builder $query, string $search): void
    {
        $search = trim($search);
        if ($search === '') {
            return;
        }

        $rut = $this->normalizeRutSearch($search);
        $tokens = $this->searchTokens($search);

        $query->where(function (Builder $outer) use ($search, $rut, $tokens) {
            if ($rut !== '') {
                $outer->orWhere('beneficiario_run_normalizado', 'like', '%' . $rut . '%')
                    ->orWhere('causante_run_normalizado', 'like', '%' . $rut . '%')
                    ->orWhere('beneficiario_rut_completo', 'like', '%' . $search . '%')
                    ->orWhere('causante_rut_completo', 'like', '%' . $search . '%');
            }

            if ($tokens !== []) {
                $outer->orWhere(function (Builder $nameQuery) use ($tokens) {
                    $this->applyAllTermsSearch($nameQuery, [
                        'beneficiario_nombres',
                        'beneficiario_apellido_paterno',
                        'beneficiario_apellido_materno',
                        'causante_nombres',
                        'causante_apellido_paterno',
                        'causante_apellido_materno',
                        'parentesco',
                        'observaciones',
                    ], $tokens);
                });
            }
        });
    }

    private function applySolicitudAdminSearch(Builder $query, string $search): void
    {
        $search = trim($search);
        if ($search === '') {
            return;
        }

        $rut = $this->normalizeRutSearch($search);
        $tokens = $this->searchTokens($search);

        $query->where(function (Builder $outer) use ($search, $rut, $tokens) {
            $outer->whereHas('user', function (Builder $userQuery) use ($search, $rut, $tokens) {
                $userQuery->where(function (Builder $inner) use ($search, $rut, $tokens) {
                    if ($rut !== '') {
                        $inner->orWhereRaw("REPLACE(REPLACE(UPPER(rut), '.', ''), '-', '') LIKE ?", ['%' . $rut . '%'])
                            ->orWhere('rut', 'like', '%' . $search . '%');
                    }

                    if ($tokens !== []) {
                        $inner->orWhere(function (Builder $nameQuery) use ($tokens) {
                            $this->applyAllTermsSearch($nameQuery, [
                                'nombres',
                                'apellido_paterno',
                                'apellido_materno',
                                'email',
                            ], $tokens);
                        });
                    }
                });
            })->orWhereHas('causantes', function (Builder $causanteQuery) use ($search, $rut, $tokens) {
                $causanteQuery->where(function (Builder $inner) use ($search, $rut, $tokens) {
                    if ($rut !== '') {
                        $inner->orWhere('run_normalizado', 'like', '%' . $rut . '%')
                            ->orWhere('rut_completo', 'like', '%' . $search . '%');
                    }

                    if ($tokens !== []) {
                        $inner->orWhere(function (Builder $nameQuery) use ($tokens) {
                            $this->applyAllTermsSearch($nameQuery, [
                                'nombres',
                                'apellido_paterno',
                                'apellido_materno',
                                'parentesco',
                                'codigo_tipo_causante',
                            ], $tokens);
                        });
                    }
                });
            });
        });
    }

    private function applyAllTermsSearch(Builder $query, array $columns, array $tokens): void
    {
        foreach ($tokens as $token) {
            $query->where(function (Builder $termQuery) use ($columns, $token) {
                foreach ($columns as $column) {
                    $termQuery->orWhere($column, 'like', '%' . $token . '%');
                }
            });
        }
    }

    private function normalizeRutSearch(string $value): string
    {
        return strtoupper(preg_replace('/[^0-9Kk]/', '', $value) ?? '');
    }

    private function searchTokens(string $value): array
    {
        $clean = strtr($value, [
            '.' => ' ', '-' => ' ', '_' => ' ', '/' => ' ', ',' => ' ', ';' => ' ', ':' => ' ',
        ]);
        $parts = preg_split('/\s+/', trim($clean)) ?: [];

        $tokens = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || mb_strlen($part) < 2 || ctype_digit($part)) {
                continue;
            }
            $tokens[] = $part;
        }

        return array_values(array_unique($tokens));
    }
    public function reviewDocumento(Request $request, CargaFamiliarSolicitud $solicitud, CargaFamiliarDocumento $documento): RedirectResponse
    {
        abort_unless($this->isReviewer($request->user()), 403);
        abort_unless((int) $documento->solicitud_id === (int) $solicitud->id, 404);

        $validated = $request->validate([
            'estado_revision' => ['required', Rule::in(['pendiente', 'aprobado', 'observado', 'rechazado'])],
            'revision_observacion' => ['nullable', 'string', 'max:1500'],
        ], [], [
            'estado_revision' => 'estado de revisión',
            'revision_observacion' => 'observación',
        ]);

        $documento->update([
            'estado_revision' => $validated['estado_revision'],
            'revision_observacion' => $validated['revision_observacion'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        if (in_array($validated['estado_revision'], ['observado', 'rechazado'], true)) {
            $solicitud->update(['estado' => 'observado']);
        } elseif ((string) $solicitud->estado === 'enviado') {
            $solicitud->update(['estado' => 'en_revision']);
        }

        return redirect()
            ->route('tramites.cargas-familiares.review.show', $solicitud)
            ->with('status', 'Documento revisado correctamente.');
    }

    public function resolve(Request $request, CargaFamiliarSolicitud $solicitud): RedirectResponse
    {
        abort_unless($this->isReviewer($request->user()), 403);

        $validated = $request->validate([
            'estado' => ['required', Rule::in(['en_revision', 'observado', 'aprobado', 'rechazado'])],
            'observacion_revision' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'estado' => 'estado final',
            'observacion_revision' => 'observación de revisión',
        ]);

        if ($validated['estado'] === 'aprobado') {
            $pendientes = $solicitud->documentos()->whereIn('estado_revision', ['pendiente', 'observado', 'rechazado'])->count();
            if ($pendientes > 0) {
                return back()->with('warning', 'No se puede aprobar mientras existan documentos pendientes, observados o rechazados.');
            }
        }

        $solicitud->update([
            'estado' => $validated['estado'],
            'observacion_revision' => $validated['observacion_revision'] ?? null,
            'revisado_por' => $request->user()->id,
            'fecha_revision' => now(),
        ]);

        return redirect()
            ->route('tramites.cargas-familiares.review.show', $solicitud)
            ->with('status', 'Estado de la solicitud actualizado correctamente.');
    }

    public function importForm(Request $request): View
    {
        abort_unless($this->canUseCargaMasiva($request->user()), 403);

        $stats = [
            'total' => CargaFamiliar::query()->count(),
            'asociadas' => CargaFamiliar::query()->whereNotNull('user_id')->count(),
            'sin_asociar' => CargaFamiliar::query()->whereNull('user_id')->count(),
            'beneficiarios' => CargaFamiliar::query()->distinct('beneficiario_run_normalizado')->count('beneficiario_run_normalizado'),
        ];

        return view('tramites.cargas-familiares.import', compact('stats'));
    }

    public function importStore(Request $request): RedirectResponse
    {
        abort_unless($this->canUseCargaMasiva($request->user()), 403);

        $request->validate([
            'excel' => ['required', 'file', 'mimes:xlsx,xls', 'max:51200'],
            'periodo_carga' => ['nullable', 'date_format:Y-m'],
        ], [
            'excel.required' => 'Debes seleccionar un archivo Excel.',
            'excel.mimes' => 'El archivo debe ser Excel (.xlsx o .xls).',
            'periodo_carga.date_format' => 'El periodo debe venir en formato año-mes, por ejemplo 2026-04.',
        ]);

        try {
            $summary = $this->importService->import(
                $request->file('excel'),
                (int) $request->user()->id,
                $request->input('periodo_carga') ?: null
            );

            return redirect()
                ->route('tramites.cargas-familiares.import')
                ->with('status', 'Carga masiva procesada correctamente.')
                ->with('import_summary', $summary);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Error importando cargas familiares', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()
                ->withInput()
                ->withErrors(['excel' => 'No fue posible importar el archivo. Revisa la estructura y vuelve a intentar. Detalle: ' . $e->getMessage()]);
        }
    }

    public function downloadTemplate(Request $request)
    {
        abort_unless($this->canUseCargaMasiva($request->user()), 403);

        $path = resource_path('templates/plantilla-carga-masiva-cargas-familiares.xlsx');
        abort_unless(is_file($path), 404);

        return response()->download($path, 'plantilla-carga-masiva-cargas-familiares.xlsx');
    }

    public function downloadDocumentTemplate(Request $request, string $template)
    {
        abort_unless($this->isApplicant($request->user()) || $this->isReviewer($request->user()), 403);

        $templates = [
            'formulario_solicitud_asignacion' => [
                'path' => resource_path('templates/formulario-solicitud-asignacion-familiar-y-maternal.pdf'),
                'download' => 'FORMULARIO DE SOLICITUD ASIGNACION FAMILIAR Y MATERNAL.pdf',
            ],
            'declaracion_jurada_ingresos' => [
                'path' => resource_path('templates/declaracion-jurada-ingresos-asignacion-familiar.pdf'),
                'download' => 'DECLARACION JURADA DE INGRESOS.pdf',
            ],
        ];

        abort_unless(array_key_exists($template, $templates), 404);
        abort_unless(is_file($templates[$template]['path']), 404);

        return response()->download($templates[$template]['path'], $templates[$template]['download'], [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function causanteOptions(): array
    {
        $options = [];

        foreach ((array) config('cargas_familiares.codigos_causante', []) as $codigo => $item) {
            $options[(string) $codigo] = str_pad((string) $codigo, 2, '0', STR_PAD_LEFT) . ' - ' . (string) ($item['nombre'] ?? $codigo);
        }

        return $options;
    }

    private function documentacionCausantesForJs(): array
    {
        $labels = CargaFamiliarDocumento::labels();
        $result = [];

        foreach ((array) config('cargas_familiares.codigos_causante', []) as $codigo => $item) {
            $obligatorios = [];
            foreach ((array) ($item['documentos_obligatorios'] ?? []) as $documento) {
                $obligatorios[] = [
                    'key' => (string) $documento,
                    'label' => $labels[(string) $documento] ?? (string) $documento,
                ];
            }

            $condicionales = [];
            foreach ((array) ($item['documentos_condicionales'] ?? []) as $key => $condicional) {
                $documento = (string) ($condicional['documento'] ?? '');
                if ($documento === '') {
                    continue;
                }

                $condicionales[] = [
                    'key' => (string) $key,
                    'question' => (string) ($condicional['pregunta'] ?? $key),
                    'document' => $documento,
                    'label' => $labels[$documento] ?? $documento,
                    'help' => (string) ($condicional['ayuda'] ?? ''),
                ];
            }

            $result[(string) $codigo] = [
                'name' => (string) ($item['nombre'] ?? $codigo),
                'parentesco' => (string) ($item['parentesco'] ?? ''),
                'required' => $obligatorios,
                'conditional' => $condicionales,
            ];
        }

        return $result;
    }

    private function validateSolicitud(Request $request): array
    {
        $causanteConfig = (array) config('cargas_familiares.codigos_causante', []);
        $causanteCodes = array_keys($causanteConfig);
        $causanteDocumentTypes = CargaFamiliarDocumento::causanteDocumentTypes();

        $rules = [
            'tipo_solicitud' => ['required', Rule::in(['nueva_carga', 'actualizacion'])],
            'beneficiario' => ['required', 'array'],
            'beneficiario.domicilio' => ['nullable', 'string', 'max:190'],
            'beneficiario.comuna' => ['nullable', 'string', 'max:120'],
            'beneficiario.ciudad' => ['nullable', 'string', 'max:120'],
            'beneficiario.region' => ['nullable', 'string', 'max:40'],
            'beneficiario.correo' => ['nullable', 'email', 'max:190'],
            'solicitante_distinto' => ['nullable', 'boolean'],
            'solicitante' => ['nullable', 'array'],
            'solicitante.nombre' => ['nullable', 'string', 'max:190'],
            'solicitante.rut' => ['nullable', 'string', 'max:20'],
            'solicitante.domicilio' => ['nullable', 'string', 'max:190'],
            'solicitante.comuna' => ['nullable', 'string', 'max:120'],
            'solicitante.ciudad' => ['nullable', 'string', 'max:120'],
            'solicitante.region' => ['nullable', 'string', 'max:40'],
            'solicitante.correo' => ['nullable', 'email', 'max:190'],
            'solicita_pago_directo' => ['required', 'boolean'],
            'declaracion_aceptada' => ['accepted'],
            'declaracion_ingresos' => ['required', 'array'],
            'declaracion_ingresos.condicion' => ['required', Rule::in(['trabajador', 'pensionado'])],
            'declaracion_ingresos.alternativa' => ['required', Rule::in(['sin_otros_ingresos', 'mas_de_un_ingreso'])],
            'declaracion_ingresos.anio_primer_semestre' => ['required', 'integer', 'min:2024', 'max:2100'],
            'declaracion_ingresos.declara_segundo_semestre' => ['nullable', 'boolean'],
            'declaracion_ingresos.anio_segundo_semestre' => ['nullable', 'integer', 'min:2023', 'max:2100'],
            'declaracion_ingresos.ingresos_primer_semestre' => ['nullable', 'array'],
            'declaracion_ingresos.ingresos_segundo_semestre' => ['nullable', 'array'],
            'causantes' => ['required', 'array', 'min:1'],
            'causantes.*.carga_familiar_id' => ['nullable', 'integer', 'exists:cargas_familiares,id'],
            'causantes.*.run' => ['required', 'string', 'max:20'],
            'causantes.*.dv' => ['required', 'string', 'max:2'],
            'causantes.*.apellido_paterno' => ['required', 'string', 'max:120'],
            'causantes.*.apellido_materno' => ['nullable', 'string', 'max:120'],
            'causantes.*.nombres' => ['required', 'string', 'max:180'],
            'causantes.*.sexo' => ['required', Rule::in(['01', '02', 'masculino', 'femenino'])],
            'causantes.*.parentesco' => ['required', 'string', 'max:120'],
            'causantes.*.codigo_tipo_beneficio' => ['required', Rule::in(['01', '02'])],
            'causantes.*.codigo_tipo_causante' => ['required', Rule::in($causanteCodes)],
            'causantes.*.fecha_nacimiento' => ['required', 'date'],
            'causantes.*.fecha_inicio_beneficio' => ['required', 'date'],
            'causantes.*.observaciones' => ['nullable', 'string', 'max:1500'],
            'documentos_solicitud.formulario_solicitud_asignacion' => ['required', 'file', 'mimetypes:application/pdf', 'mimes:pdf', 'max:102400'],
            'documentos_solicitud.declaracion_jurada_ingresos_pdf' => ['required', 'file', 'mimetypes:application/pdf', 'mimes:pdf', 'max:102400'],
            'documentos_causantes' => ['required', 'array'],
            'documentos_causantes_condiciones' => ['nullable', 'array'],
        ];

        foreach ($causanteDocumentTypes as $docType) {
            $rules['documentos_causantes.*.' . $docType] = ['nullable', 'file', 'mimetypes:application/pdf', 'mimes:pdf', 'max:102400'];
        }

        $attributes = [
            'documentos_solicitud.formulario_solicitud_asignacion' => 'Formulario de Solicitud de Asignacion Familiar y Maternal firmado',
            'documentos_solicitud.declaracion_jurada_ingresos_pdf' => 'Declaracion Jurada de Ingresos firmada',
        ];

        foreach (CargaFamiliarDocumento::labels() as $docType => $label) {
            $attributes['documentos_causantes.*.' . $docType] = $label;
        }

        $data = $request->validate($rules, [], $attributes);
        $labels = CargaFamiliarDocumento::labels();

        foreach ((array) $data['causantes'] as $index => $causante) {
            $codigoCausante = str_pad((string) ($causante['codigo_tipo_causante'] ?? ''), 2, '0', STR_PAD_LEFT);
            $config = (array) ($causanteConfig[$codigoCausante] ?? []);

            foreach ((array) ($config['documentos_obligatorios'] ?? []) as $docType) {
                if (!$request->hasFile("documentos_causantes.{$index}.{$docType}")) {
                    throw ValidationException::withMessages([
                        "documentos_causantes.{$index}.{$docType}" => 'Documento obligatorio para el codigo de causante ' . $codigoCausante . ': ' . ($labels[$docType] ?? $docType) . '.',
                    ]);
                }
            }

            foreach ((array) ($config['documentos_condicionales'] ?? []) as $conditionKey => $condition) {
                $docType = (string) ($condition['documento'] ?? '');
                if ($docType === '') {
                    continue;
                }

                if ($request->boolean("documentos_causantes_condiciones.{$index}.{$conditionKey}") && !$request->hasFile("documentos_causantes.{$index}.{$docType}")) {
                    throw ValidationException::withMessages([
                        "documentos_causantes.{$index}.{$docType}" => 'Documento obligatorio por condicion marcada: ' . ($labels[$docType] ?? $docType) . '.',
                    ]);
                }
            }
        }

        return $data;
    }

    private function storeSolicitudDocuments(Request $request, CargaFamiliarSolicitud $solicitud, int $userId): void
    {
        foreach (['formulario_solicitud_asignacion', 'declaracion_jurada_ingresos_pdf'] as $docType) {
            $file = $request->file("documentos_solicitud.{$docType}");
            if ($file) {
                $this->storeDocumento($solicitud, null, $docType, $file, $userId);
            }
        }
    }

    private function storeCausantes(Request $request, CargaFamiliarSolicitud $solicitud, array $validated, int $userId): void
    {
        foreach ((array) $validated['causantes'] as $index => $payload) {
            [$run, $dv, $rutCompleto, $runNormalizado] = $this->rutService->fromParts($payload['run'], $payload['dv']);
            $edad = Carbon::parse((string) $payload['fecha_nacimiento'])->age;

            $causante = $solicitud->causantes()->create([
                'carga_familiar_id' => $payload['carga_familiar_id'] ?? null,
                'accion' => $solicitud->tipo_solicitud === 'actualizacion' ? 'modificar' : 'nuevo',
                'run' => $run,
                'dv' => $dv,
                'rut_completo' => $rutCompleto,
                'run_normalizado' => $runNormalizado,
                'apellido_paterno' => $payload['apellido_paterno'],
                'apellido_materno' => $payload['apellido_materno'] ?? null,
                'nombres' => $payload['nombres'],
                'sexo' => $payload['sexo'],
                'parentesco' => $payload['parentesco'],
                'codigo_tipo_beneficio' => $payload['codigo_tipo_beneficio'],
                'codigo_tipo_causante' => $payload['codigo_tipo_causante'],
                'fecha_nacimiento' => $payload['fecha_nacimiento'],
                'edad_al_enviar' => $edad,
                'fecha_inicio_beneficio' => $payload['fecha_inicio_beneficio'],
                'observaciones' => $payload['observaciones'] ?? null,
            ]);

            foreach (CargaFamiliarDocumento::causanteDocumentTypes() as $docType) {
                $file = $request->file("documentos_causantes.{$index}.{$docType}");
                if ($file) {
                    $this->storeDocumento($solicitud, $causante, $docType, $file, $userId);
                }
            }
        }
    }

    private function storeDocumento(CargaFamiliarSolicitud $solicitud, ?CargaFamiliarCausante $causante, string $docType, mixed $file, int $userId): CargaFamiliarDocumento
    {
        $rut = strtoupper(preg_replace('/[^0-9Kk]/', '', (string) data_get($solicitud->beneficiario_snapshot, 'rut', $solicitud->user?->rut)) ?? '');
        $dir = 'cargas-familiares/' . $solicitud->id . '/' . ($causante ? 'causante-' . $causante->id : 'solicitud');
        Storage::disk('local')->makeDirectory($dir);

        $filename = trim($rut . '_' . Str::slug($docType, '-') . '_' . now()->format('Ymd_His') . '.pdf', '_');
        $path = $file->storeAs($dir, $filename, 'local');

        return CargaFamiliarDocumento::query()->create([
            'solicitud_id' => $solicitud->id,
            'causante_id' => $causante?->id,
            'nivel' => $causante ? 'causante' : 'solicitud',
            'tipo_documento' => $docType,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType() ?: 'application/pdf',
            'size' => $file->getSize(),
            'uploaded_by' => $userId,
        ]);
    }

    private function beneficiarioSnapshot(User $user, array $overrides = []): array
    {
        $user->loadMissing('postulantProfile.comuna');
        $profile = $user->postulantProfile;

        return [
            'rut' => $user->rut,
            'apellido_paterno' => $user->apellido_paterno,
            'apellido_materno' => $user->apellido_materno,
            'nombres' => $user->nombres,
            'nombre_completo' => $user->nombre_completo,
            'correo' => $overrides['correo'] ?? $profile?->email_contacto ?? $user->email,
            'domicilio' => $overrides['domicilio'] ?? $profile?->direccion,
            'comuna' => $overrides['comuna'] ?? $profile?->comuna?->name,
            'ciudad' => $overrides['ciudad'] ?? $profile?->comuna?->name,
            'region' => $overrides['region'] ?? $profile?->region_code,
        ];
    }

    private function normalizeDeclaracionIngresos(array $data): array
    {
        $data['ingresos_primer_semestre'] = $this->normalizeIngresoRows((array) ($data['ingresos_primer_semestre'] ?? []));
        $data['ingresos_segundo_semestre'] = $this->normalizeIngresoRows((array) ($data['ingresos_segundo_semestre'] ?? []));
        return $data;
    }

    private function normalizeIngresoRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $mes => $row) {
            $out[$mes] = [
                'mismo_empleador' => $this->numeric($row['mismo_empleador'] ?? 0),
                'otros_empleadores' => $this->numeric($row['otros_empleadores'] ?? 0),
                'trabajador_independiente' => $this->numeric($row['trabajador_independiente'] ?? 0),
                'subsidios' => $this->numeric($row['subsidios'] ?? 0),
                'pensiones_misma_entidad' => $this->numeric($row['pensiones_misma_entidad'] ?? 0),
                'otras_pensiones' => $this->numeric($row['otras_pensiones'] ?? 0),
            ];
            $out[$mes]['total'] = array_sum($out[$mes]);
        }
        return $out;
    }

    private function numeric(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        $clean = preg_replace('/[^0-9,.-]/', '', (string) $value) ?? '';
        $clean = str_replace('.', '', $clean);
        $clean = str_replace(',', '.', $clean);
        return is_numeric($clean) ? (float) $clean : 0.0;
    }

    private function mesesPrimerSemestre(): array
    {
        return ['enero' => 'Enero', 'febrero' => 'Febrero', 'marzo' => 'Marzo', 'abril' => 'Abril', 'mayo' => 'Mayo', 'junio' => 'Junio'];
    }

    private function mesesSegundoSemestre(): array
    {
        return ['julio' => 'Julio', 'agosto' => 'Agosto', 'septiembre' => 'Septiembre', 'octubre' => 'Octubre', 'noviembre' => 'Noviembre', 'diciembre' => 'Diciembre'];
    }


    private function canUseCargaMasiva(?User $user): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        $activeRole = (string) $user->activeRoleName();

        return in_array($activeRole, ['admin', 'funcionario_slep'], true) && $user->hasRole($activeRole);
    }

    private function isApplicant(?User $user): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        $roles = (array) config('cargas_familiares.acceso_solicitantes.roles_habilitados', self::APPLICANT_ROLES);
        $roles = array_values(array_filter(array_map('strval', $roles)));
        $activeRole = (string) $user->activeRoleName();

        return $roles !== [] && in_array($activeRole, $roles, true) && $user->hasRole($activeRole);
    }

    private function isReviewer(?User $user): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        $activeRole = (string) $user->activeRoleName();

        return in_array($activeRole, self::REVIEWER_ROLES, true) && $user->hasRole($activeRole);
    }

    private function canAccessSolicitud(?User $user, CargaFamiliarSolicitud $solicitud): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        if ($this->isReviewer($user)) {
            return true;
        }

        return $this->isApplicant($user) && (int) $solicitud->user_id === (int) $user->id;
    }
}
