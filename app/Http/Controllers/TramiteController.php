<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTramiteRequest;
use App\Http\Requests\UpdateTramiteRequest;
use App\Models\Tramite;
use App\Models\TramiteDocumento;
use App\Mail\TramiteDocumentoStatusChangedMail;
use App\Mail\TramiteResultadoBieniosMail;
use App\Mail\TramiteCierreBieniosInformadoMail;
use App\Mail\TramiteBieniosSolicitudRecibidaMail;
use App\Mail\TramiteAnuladoMail;
use App\Mail\TramiteManualApplicantNotificationMail;
use App\Models\NotificationDispatchLog;
use App\Models\User;
use App\Services\TramiteAutofillService;
use App\Services\TramiteDocumentoDataCaptureService;
use App\Services\ResolucionReconocimientoBieniosService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Support\NotificationAudit;
use App\Support\StreamingXlsxWriter;

class TramiteController extends Controller
{
    private const APPLICANT_ROLES = ['postulante', 'funcionario'];
    private const REVIEWER_ROLES = ['admin', 'coordinador_gdp', 'funcionario_slep'];

    public function index(Request $request)
    {
        $user = $request->user();

        if ($this->isReviewer($user)) {
            $filters = $this->reviewFilters($request);

            $tramites = $this->buildReviewIndexQuery($filters)
                ->latest('enviado_at')
                ->latest('id')
                ->paginate(15)
                ->withQueryString();

            $tramites->getCollection()->each->syncEstadoRevisionFromDocumentos();

            $tabCounts = $this->reviewEstamentoCounts($filters);

            return view('tramites.review.index', [
                'tramites' => $tramites,
                'user' => $user,
                'filters' => $filters,
                'tabCounts' => $tabCounts,
                'estamentosDisponibles' => $this->reviewEstamentosDisponibles(),
                'tiposDisponibles' => $this->reviewTiposDisponibles(),
                'estadosDisponibles' => $this->reviewEstadosDisponibles(),
                'canExportReviewExcel' => $user->hasAnyRole(['admin', 'funcionario_slep']),
            ]);
        }

        $tramites = Tramite::query()
            ->withCount('documentos')
            ->where('user_id', $user->id)
            ->latest('enviado_at')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        $tramites->getCollection()->each->syncEstadoRevisionFromDocumentos();

        return view('tramites.index', [
            'tramites' => $tramites,
            'user' => $user,
        ]);
    }

    public function exportReviewExcel(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'funcionario_slep']), 403);

        $filters = $this->reviewFilters($request);
        $rows = $this->buildReviewIndexQuery($filters)
            ->latest('enviado_at')
            ->latest('id')
            ->get();

        $tmp = storage_path('app/tmp');
        if (!is_dir($tmp) && !mkdir($tmp, 0775, true) && !is_dir($tmp)) {
            return redirect()
                ->route('tramites.index', $request->query())
                ->withErrors(['general' => 'No se pudo preparar la carpeta temporal para exportar el archivo.']);
        }

        $filename = 'tramites_revision_' . ($filters['estamento'] ?: 'todos') . '_' . now()->format('Ymd_His') . '.xlsx';
        $path = $tmp . DIRECTORY_SEPARATOR . $filename;

        try {
            $writer = new StreamingXlsxWriter($path);
            $sheet = $writer->addSheet('Tramites', [
                'ID',
                'Solicitante',
                'RUT',
                'Rol solicitante',
                'Tipo de trámite',
                'Estado trámite',
                'Establecimiento',
                'Email',
                'Enviado',
                'Creado',
                'Documentos',
            ], [10, 35, 18, 18, 28, 18, 35, 32, 20, 20, 12]);

            foreach ($rows as $tramite) {
                $owner = $tramite->user;
                $ownerRole = $owner?->roles?->pluck('name')->intersect(self::APPLICANT_ROLES)->first();

                $writer->appendRow($sheet, [
                    $tramite->id,
                    $tramite->nombre_completo_snapshot ?: ($owner?->nombre_completo ?: '—'),
                    $tramite->rut_snapshot ?: '—',
                    $ownerRole === 'funcionario' ? 'Funcionario' : ($ownerRole === 'postulante' ? 'Postulante' : '—'),
                    $tramite->tipo_label,
                    $tramite->estado_label,
                    $tramite->establecimiento_nombre_snapshot ?: '—',
                    $tramite->email_snapshot ?: ($owner?->email ?: '—'),
                    optional($tramite->enviado_at)->timezone(config('app.display_timezone', 'America/Santiago'))->format('d-m-Y H:i') ?: '—',
                    optional($tramite->created_at)->timezone(config('app.display_timezone', 'America/Santiago'))->format('d-m-Y H:i') ?: '—',
                    $tramite->documentos_count,
                ]);
            }

            $writer->close();
        } catch (\Throwable $e) {
            if (is_file($path)) {
                @unlink($path);
            }

            report($e);

            return redirect()
                ->route('tramites.index', $request->query())
                ->withErrors(['general' => 'No se pudo generar la exportación Excel de trámites.']);
        }

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    private function reviewFilters(Request $request): array
    {
        $estados = collect($request->input('estados', []))
            ->flatten()
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();

        $allowedEstados = array_keys($this->reviewEstadosDisponibles());
        $estados = array_values(array_intersect($estados, $allowedEstados));

        $tipo = trim((string) $request->input('tipo', ''));
        $allowedTipos = array_keys($this->reviewTiposDisponibles());
        if (!in_array($tipo, $allowedTipos, true)) {
            $tipo = '';
        }

        $estamento = trim((string) $request->input('estamento', 'docente'));
        if (!array_key_exists($estamento, $this->reviewEstamentosDisponibles())) {
            $estamento = 'docente';
        }

        return [
            'tipo' => $tipo,
            'estados' => $estados,
            'fecha_desde' => trim((string) $request->input('fecha_desde', '')),
            'fecha_hasta' => trim((string) $request->input('fecha_hasta', '')),
            'estamento' => $estamento,
        ];
    }

    private function buildReviewIndexQuery(array $filters): Builder
    {
        $query = Tramite::query()
            ->withCount('documentos')
            ->with(['user.roles'])
            ->whereHas('user.roles', fn ($q) => $q->whereIn('name', self::APPLICANT_ROLES))
            ->when($filters['tipo'] !== '', fn (Builder $query) => $query->where('tipo', $filters['tipo']))
            ->when(!empty($filters['estados']), fn (Builder $query) => $query->whereIn('estado', $filters['estados']))
            ->when($filters['fecha_desde'] !== '', fn (Builder $query) => $query->whereDate('enviado_at', '>=', $filters['fecha_desde']))
            ->when($filters['fecha_hasta'] !== '', fn (Builder $query) => $query->whereDate('enviado_at', '<=', $filters['fecha_hasta']));

        return $this->applyReviewEstamentoFilter($query, (string) ($filters['estamento'] ?? 'docente'));
    }

    private function reviewEstamentoCounts(array $filters): array
    {
        return collect($this->reviewEstamentosDisponibles())
            ->mapWithKeys(function (string $label, string $estamento) use ($filters) {
                return [$estamento => $this->buildReviewIndexQuery(array_merge($filters, ['estamento' => $estamento]))->count()];
            })
            ->all();
    }

    private function applyReviewEstamentoFilter(Builder $query, string $estamento): Builder
    {
        return $query->where(function (Builder $q) use ($estamento) {
            $estatuto = "UPPER(COALESCE(estatuto_snapshot, ''))";
            $escalafon = "UPPER(COALESCE(escalafon_snapshot, ''))";

            if ($estamento === 'asistente') {
                $q->whereRaw("{$estatuto} LIKE ?", ['%ASIST%'])
                    ->orWhereRaw("{$estatuto} LIKE ?", ['%AAEE%'])
                    ->orWhereRaw("{$estatuto} LIKE ?", ['%A.A.E.E%'])
                    ->orWhereRaw("{$escalafon} LIKE ?", ['%ASIST%'])
                    ->orWhereRaw("{$escalafon} LIKE ?", ['%AAEE%'])
                    ->orWhereRaw("{$escalafon} LIKE ?", ['%A.A.E.E%']);

                return;
            }

            $q->whereRaw("{$estatuto} LIKE ?", ['%DOC%'])
                ->orWhereRaw("{$estatuto} LIKE ?", ['%PROFES%'])
                ->orWhereRaw("{$escalafon} LIKE ?", ['%DOC%'])
                ->orWhereRaw("{$escalafon} LIKE ?", ['%PROFES%']);
        });
    }

    private function reviewEstamentosDisponibles(): array
    {
        return [
            'docente' => 'Docentes',
            'asistente' => 'Asistentes de la Educación',
        ];
    }

    private function reviewTiposDisponibles(): array
    {
        return collect((array) config('tramites.tipos', []))
            ->mapWithKeys(fn (array $config, string $key) => [$key => (string) ($config['label'] ?? $key)])
            ->all();
    }

    private function reviewEstadosDisponibles(): array
    {
        return [
            'enviado' => 'Enviado',
            'en_revision' => 'En revisión',
            'resuelto' => 'Resuelto',
            'anulado' => 'Anulado',
        ];
    }

    public function create(Request $request, TramiteAutofillService $autofillService)
    {
        abort_unless($this->isApplicant($request->user()), 403);

        $tipoSeleccionado = (string) old('tipo', 'reconocimiento_bienios');
        $autofill = $autofillService->forUser($request->user());
        $tipos = config('tramites.tipos', []);

        return view('tramites.create', [
            'user' => $request->user(),
            'autofill' => $autofill,
            'tipos' => $tipos,
            'tipoSeleccionado' => $tipoSeleccionado,
            'tramite' => null,
        ]);
    }

    public function store(StoreTramiteRequest $request, TramiteAutofillService $autofillService)
    {
        abort_unless($this->isApplicant($request->user()), 403);

        $user = $request->user();
        $tipo = (string) $request->input('tipo');
        $tipoConfig = (array) config('tramites.tipos.' . $tipo, []);
        $autofill = $autofillService->forUser($user);

        if (!($autofill['ok'] ?? false)) {
            return back()->withErrors([
                'tipo' => (string) ($autofill['message'] ?? 'No fue posible autocompletar los datos base del trámite.'),
            ])->withInput();
        }

        $tramite = DB::transaction(function () use ($request, $user, $tipo, $tipoConfig, $autofill) {
            $tramite = Tramite::create([
                'user_id' => $user->id,
                'tipo' => $tipo,
                'bienios_flujo_externo' => $tipo === 'reconocimiento_bienios'
                    && (bool) data_get($tipoConfig, 'external_calculation', false),
                'estado' => 'enviado',
                'rut_snapshot' => (string) ($autofill['rut'] ?? ''),
                'nombre_completo_snapshot' => (string) ($autofill['nombre_completo'] ?? ''),
                'email_snapshot' => (string) ($autofill['email'] ?? ''),
                'estatuto_snapshot' => (string) ($autofill['estatuto'] ?? ''),
                'escalafon_snapshot' => (string) ($autofill['escalafon'] ?? ''),
                'establecimiento_id_snapshot' => (int) ($autofill['establecimiento_id'] ?? 0) ?: null,
                'establecimiento_nombre_snapshot' => (string) ($autofill['establecimiento_label'] ?? $autofill['establecimiento_nombre'] ?? ''),
                'enviado_at' => now(),
            ]);

            $this->storeUploadedDocuments($request, $tramite, $user->id, $tipoConfig);

            return $tramite;
        });

        if ($tipo === 'reconocimiento_bienios') {
            $this->notifyBieniosSolicitudRecibida($tramite->fresh(['user']));
        }

        $statusMessage = $tipo === 'reconocimiento_bienios'
            ? 'Solicitud enviada correctamente. El trámite de Reconocimiento de Bienios podrá demorar hasta un máximo de 30 días corridos desde su recepción. Recibirás notificaciones al correo registrado en tu cuenta.'
            : 'Trámite enviado correctamente.';

        return redirect()
            ->route('tramites.show', $tramite)
            ->with('status', $statusMessage);
    }

    private function notifyBieniosSolicitudRecibida(Tramite $tramite): void
    {
        $recipientEmail = trim((string) optional($tramite->user)->email);

        if ($recipientEmail === '') {
            Log::warning('No se pudo notificar recepción de Reconocimiento de Bienios: usuario sin correo.', [
                'tramite_id' => $tramite->id,
                'user_id' => $tramite->user_id,
            ]);

            return;
        }

        try {
            NotificationAudit::sendMail($recipientEmail, new TramiteBieniosSolicitudRecibidaMail($tramite), [
                'event_key' => 'tramite.bienios.solicitud_recibida',
                'description' => 'Correo de recepción de solicitud de Reconocimiento de Bienios con plazo máximo informado.',
                'subject' => 'Solicitud de Reconocimiento de Bienios recibida',
                'recipient_name' => $tramite->nombre_completo_snapshot ?: optional($tramite->user)->name,
                'notifiable' => $tramite->user,
                'related' => $tramite,
                'context' => [
                    'tramite_id' => $tramite->id,
                    'tipo' => $tramite->tipo,
                    'enviado_at' => optional($tramite->enviado_at)->toDateTimeString(),
                    'plazo_maximo_dias' => 30,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al enviar correo de recepción de Reconocimiento de Bienios.', [
                'tramite_id' => $tramite->id,
                'recipient' => $recipientEmail,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function edit(Request $request, Tramite $tramite, TramiteAutofillService $autofillService)
    {
        abort_unless($this->isApplicant($request->user()) && $tramite->canBeEditedBy($request->user()), 403);

        $tramite->load('documentos');
        $tramite->syncEstadoRevisionFromDocumentos();
        $tramite->load('documentos');
        $tipos = config('tramites.tipos', []);

        return view('tramites.edit', [
            'user' => $request->user(),
            'autofill' => $autofillService->forUser($request->user()),
            'tipos' => $tipos,
            'tipoSeleccionado' => $tramite->tipo,
            'tramite' => $tramite,
        ]);
    }

    public function update(UpdateTramiteRequest $request, Tramite $tramite)
    {
        abort_unless($this->isApplicant($request->user()) && $tramite->canBeEditedBy($request->user()), 403);

        $tipoConfig = (array) config('tramites.tipos.' . $tramite->tipo, []);
        $removedIds = collect($request->input('existing_documentos_remove', []))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->values();

        DB::transaction(function () use ($request, $tramite, $tipoConfig, $removedIds) {
            if ($removedIds->isNotEmpty()) {
                $docsToRemove = $tramite->documentos()->whereIn('id', $removedIds->all())->get();
                foreach ($docsToRemove as $documento) {
                    if ($documento->path && Storage::disk('local')->exists($documento->path)) {
                        Storage::disk('local')->delete($documento->path);
                    }
                    $documento->delete();
                }
            }

            $uploadedDocTypes = $this->storeUploadedDocuments($request, $tramite, $request->user()->id, $tipoConfig);

            if (!empty($uploadedDocTypes)) {
                $rejectedDocsToRemove = $tramite->documentos()
                    ->where('estado_revision', 'rechazado')
                    ->whereIn('tipo_documento', $uploadedDocTypes)
                    ->get();

                foreach ($rejectedDocsToRemove as $documento) {
                    if ($documento->path && Storage::disk('local')->exists($documento->path)) {
                        Storage::disk('local')->delete($documento->path);
                    }
                    $documento->delete();
                }
            }

            $tramite->touch();
        });

        return redirect()
            ->route('tramites.show', $tramite)
            ->with('status', 'Trámite actualizado correctamente.');
    }

    public function anular(Request $request, Tramite $tramite)
    {
        abort_unless($this->isApplicant($request->user()) && $tramite->canBeEditedBy($request->user()), 403);

        $tramite->update([
            'estado' => 'anulado',
            'anulado_at' => now(),
            'anulado_por_user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('tramites.show', $tramite)
            ->with('status', 'Trámite anulado correctamente por usuario.');
    }

    public function show(Request $request, Tramite $tramite)
    {
        $user = $request->user();
        abort_unless($this->canAccessTramite($user, $tramite), 403);

        $tramite->load([
            'documentos.uploader',
            'documentos.reviewedBy',
            'establecimientoSnapshot',
            'anuladoPor',
            'user.roles',
        ]);
        $tramite->syncEstadoRevisionFromDocumentos();
        $tramite->load([
            'documentos.uploader',
            'documentos.reviewedBy',
            'establecimientoSnapshot',
            'anuladoPor',
            'user.roles',
        ]);

        $tipoConfig = (array) config('tramites.tipos.' . $tramite->tipo, []);
        $bieniosDocumentationStatus = (string) $tramite->tipo === 'reconocimiento_bienios'
            ? $this->bieniosDocumentationStatus($tramite)
            : [];
        $manualApplicantNotifications = NotificationDispatchLog::query()
            ->with('triggeredBy')
            ->where('event_key', 'tramite.manual_applicant_notification')
            ->where('related_type', $tramite->getMorphClass())
            ->where('related_id', $tramite->getKey())
            ->latest('created_at')
            ->get();

        if ($this->isReviewer($user)) {
            return view('tramites.review.show', [
                'tramite' => $tramite,
                'tipoConfig' => $tipoConfig,
                'canReviewDocuments' => $this->canReviewDocuments($user),
                'manualApplicantNotifications' => $manualApplicantNotifications,
                'bieniosDocumentationStatus' => $bieniosDocumentationStatus,
            ]);
        }

        return view('tramites.show', [
            'tramite' => $tramite,
            'tipoConfig' => $tipoConfig,
            'manualApplicantNotifications' => $manualApplicantNotifications,
            'bieniosDocumentationStatus' => $bieniosDocumentationStatus,
        ]);
    }

    public function downloadDocumento(Request $request, Tramite $tramite, TramiteDocumento $documento)
    {
        abort_unless($this->canAccessTramite($request->user(), $tramite), 403);
        abort_unless((int) $documento->tramite_id === (int) $tramite->id, 404);
        abort_unless($documento->path && Storage::disk('local')->exists($documento->path), 404);

        return Storage::disk('local')->download($documento->path, $documento->original_name ?: basename($documento->path), [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function downloadApprovedZip(Request $request, Tramite $tramite)
    {
        $user = $request->user();
        abort_unless($this->canReviewDocuments($user), 403);
        abort_unless($this->canAccessTramite($user, $tramite), 403);

        $docs = $tramite->documentos()
            ->where('estado_revision', 'aprobado')
            ->orderBy('tipo_documento')
            ->orderBy('id')
            ->get();

        if ($docs->isEmpty()) {
            return back()->with('warning', 'El trámite no tiene documentos aprobados para descargar.');
        }

        $rutRaw = (string) ($tramite->rut_snapshot ?: optional($tramite->user)->rut ?: $tramite->id);
        $rutRaw = str_replace(['.', '-', ' '], '', $rutRaw);
        $rut = strtoupper(preg_replace('/[^0-9K]/', '', $rutRaw));
        if ($rut === '') {
            $rut = (string) $tramite->id;
        }

        $zipName = sprintf('%s_TRAMITE_%s_APROBADOS.zip', $rut, $tramite->id);

        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0775, true);
        }

        $zipPath = $tmpDir . DIRECTORY_SEPARATOR . uniqid('tramite_docs_' . $tramite->id . '_', true) . '.zip';
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return back()->withErrors(['zip' => 'No se pudo crear el archivo ZIP de documentos aprobados.']);
        }

        foreach ($docs as $doc) {
            if (!$doc->path || !Storage::disk('local')->exists($doc->path)) {
                continue;
            }

            $nameInside = $doc->original_name ?: basename($doc->path);
            if ($zip->locateName($nameInside) !== false) {
                $base = pathinfo($nameInside, PATHINFO_FILENAME);
                $ext = pathinfo($nameInside, PATHINFO_EXTENSION);
                $suffix = $doc->id;
                $nameInside = $base . '_' . $suffix . ($ext ? '.' . $ext : '');
            }

            $zip->addFromString($nameInside, Storage::disk('local')->get($doc->path));
        }

        $zip->close();

        return response()->download($zipPath, $zipName)->deleteFileAfterSend(true);
    }

    public function viewDocumento(Request $request, Tramite $tramite, TramiteDocumento $documento)
    {
        abort_unless($this->canAccessTramite($request->user(), $tramite), 403);
        abort_unless((int) $documento->tramite_id === (int) $tramite->id, 404);
        abort_unless($documento->path && Storage::disk('local')->exists($documento->path), 404);

        return response()->file(Storage::disk('local')->path($documento->path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ResponseHeaderBag::DISPOSITION_INLINE . '; filename="' . ($documento->original_name ?: basename($documento->path)) . '"',
        ]);
    }

    public function reviewDocumento(Request $request, Tramite $tramite, TramiteDocumento $documento)
    {
        $user = $request->user();
        abort_unless($this->canReviewDocuments($user), 403);
        abort_unless((int) $documento->tramite_id === (int) $tramite->id, 404);

        $validated = $request->validate([
            'estado_revision' => ['required', 'in:aprobado,rechazado'],
            'revision_observacion' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'estado_revision' => 'estado de revisión',
            'revision_observacion' => 'observación',
        ]);

        $previousEstado = (string) $documento->estado_revision;
        $previousObservacion = $documento->revision_observacion;

        $documento->update([
            'estado_revision' => $validated['estado_revision'],
            'revision_observacion' => $validated['revision_observacion'] ?? null,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        $tramite->syncEstadoRevisionFromDocumentos();

        if ($validated['estado_revision'] === 'aprobado' && !$this->usesExternalBieniosFlow($tramite)) {
            $this->prepareCalculationBlockFromApprovedDocument($tramite->fresh(['documentos']), $documento->fresh(['tramite']), $user);
            $tramite = $tramite->fresh(['documentos']);
            $documento = $documento->fresh(['tramite']);
        }

        $shouldNotify = in_array($documento->estado_revision, ['aprobado', 'rechazado'], true)
            && ($previousEstado !== $documento->estado_revision || ($documento->estado_revision === 'rechazado' && $previousObservacion !== $documento->revision_observacion));

        if ($shouldNotify && $tramite->user?->email) {
            try {
                NotificationAudit::sendMail($tramite->user->email, new TramiteDocumentoStatusChangedMail($documento), [
                    'event_key' => 'tramite.documento.review.status_changed',
                    'description' => 'Notificación de cambio de estado de documento de trámite',
                    'subject' => 'Estado de documento de trámite: ' . $documento->tipo_documento_label . ' — ' . ($documento->estado_revision === 'aprobado' ? 'Aprobado' : 'Rechazado'),
                    'related' => $documento,
                    'notifiable' => $tramite->user,
                    'context' => [
                        'tramite_id' => $tramite->id,
                        'tipo_tramite' => $tramite->tipo,
                        'documento_id' => $documento->id,
                        'estado_revision' => $documento->estado_revision,
                        'calculo_periodos_habilitado_at' => optional($tramite->calculo_periodos_habilitado_at)?->toDateTimeString(),
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::warning('[MAIL] Falló envío estado documento de trámite', [
                    'tramite_id' => $tramite->id,
                    'documento_id' => $documento->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $redirectTab = 'documentos';
        if ($validated['estado_revision'] === 'aprobado') {
            if ($this->usesExternalBieniosFlow($tramite)) {
                $status = $this->bieniosDocumentationStatus($tramite->fresh(['documentos']));
                $redirectTab = ($status['ready'] ?? false) ? 'resolucion' : 'documentos';
            } else {
                $redirectTab = 'calculo';
            }
        }

        $redirect = redirect()->route('tramites.show', [
            'tramite' => $tramite,
            'tab' => $redirectTab,
        ]);

        $message = 'Documento ' . ($validated['estado_revision'] === 'aprobado' ? 'aprobado' : 'rechazado') . ' correctamente.';
        if ($validated['estado_revision'] === 'aprobado') {
            $message .= $this->usesExternalBieniosFlow($tramite)
                ? ' El antecedente quedó disponible para el cómputo administrativo externo.'
                : ' Ahora puedes confirmar, modificar o agregar períodos desde la pestaña Cálculo de períodos.';
        }

        return $redirect->with('status', $message);
    }

    public function captureDocumento(Request $request, Tramite $tramite, TramiteDocumento $documento, TramiteDocumentoDataCaptureService $dataCaptureService)
    {
        abort_unless($this->canReviewDocuments($request->user()), 403);
        abort_unless((int) $documento->tramite_id === (int) $tramite->id, 404);

        if ($this->usesExternalBieniosFlow($tramite)) {
            return $this->externalBieniosFlowRedirect($tramite);
        }

        return redirect()
            ->route('tramites.show', ['tramite' => $tramite, 'tab' => 'calculo'])
            ->with('warning', 'La captura automática/OCR fue desactivada. Confirma, modifica o agrega las fechas manualmente desde Cálculo de períodos.');
    }

    public function updateCaptureManualDates(Request $request, Tramite $tramite, TramiteDocumento $documento, TramiteDocumentoDataCaptureService $dataCaptureService)
    {
        abort_unless($this->canReviewDocuments($request->user()), 403);
        abort_unless((int) $documento->tramite_id === (int) $tramite->id, 404);

        if ($this->usesExternalBieniosFlow($tramite)) {
            return $this->externalBieniosFlowRedirect($tramite);
        }

        $validated = $request->validate([
            'labor_start_text' => ['nullable', 'string', 'max:80'],
            'labor_end_text' => ['nullable', 'string', 'max:80'],
        ], [], [
            'labor_start_text' => 'fecha de inicio manual',
            'labor_end_text' => 'fecha de término manual',
        ]);

        return redirect()
            ->route('tramites.show', ['tramite' => $tramite, 'tab' => 'calculo'])
            ->with('warning', 'La captura automática/OCR fue desactivada. Guarda las fechas directamente en Cálculo de períodos.');
    }

    public function confirmCapturePeriods(Request $request, Tramite $tramite, TramiteDocumento $documento)
    {
        abort_unless($this->canReviewDocuments($request->user()), 403);
        abort_unless((int) $documento->tramite_id === (int) $tramite->id, 404);

        if ($this->usesExternalBieniosFlow($tramite)) {
            return $this->externalBieniosFlowRedirect($tramite);
        }

        $validated = $request->validate([
            'periodos' => ['required', 'array', 'min:1'],
            'periodos.*' => ['integer', 'min:0'],
        ], [], [
            'periodos' => 'períodos seleccionados',
            'periodos.*' => 'índice de período',
        ]);

        $availablePeriods = collect($documento->captura_periodos ?? [])->values();
        $selectedIndexes = collect($validated['periodos'] ?? [])->map(fn ($value) => (int) $value)->unique()->sort()->values();

        $selectedPeriods = $selectedIndexes
            ->map(function (int $index) use ($availablePeriods) {
                $periodo = $availablePeriods->get($index);
                if (!is_array($periodo) || empty($periodo['inicio']) || empty($periodo['termino'])) {
                    return null;
                }

                return [
                    'selected_index' => $index,
                    'inicio' => (string) $periodo['inicio'],
                    'termino' => (string) $periodo['termino'],
                ];
            })
            ->filter()
            ->values();

        if ($selectedPeriods->isEmpty()) {
            return redirect()
                ->route('tramites.show', ['tramite' => $tramite, 'tab' => 'captura'])
                ->with('warning', 'Selecciona al menos un período detectado válido antes de confirmar.');
        }

        $tipoConfig = (array) data_get(config('tramites.tipos'), $tramite->tipo . '.documentos.' . $documento->tipo_documento, []);
        $documentLabel = (string) data_get($tipoConfig, 'label', $documento->tipo_documento_label);
        $confirmingUser = $request->user();
        $block = [
            'documento_id' => (int) $documento->id,
            'documento_tipo' => (string) $documento->tipo_documento,
            'documento_label' => $documentLabel,
            'documento_nombre' => (string) ($documento->original_name ?: basename((string) $documento->path)),
            'captura_metodo' => (string) $documento->captura_metodo_label,
            'captura_estado' => (string) $documento->captura_estado_label,
            'captura_ejecutada_at' => optional($documento->captura_ejecutada_at)?->toDateTimeString(),
            'confirmed_at' => now()->toDateTimeString(),
            'confirmed_by_user_id' => (int) $confirmingUser->id,
            'confirmed_by_name' => trim(collect([$confirmingUser->nombres, $confirmingUser->apellido_paterno, $confirmingUser->apellido_materno])->filter()->implode(' ')) ?: $confirmingUser->email,
            'periodos' => $selectedPeriods->all(),
        ];

        $blocks = collect($tramite->calculo_periodos_data ?? [])
            ->filter(fn ($item) => is_array($item))
            ->reject(fn ($item) => (int) data_get($item, 'documento_id') === (int) $documento->id)
            ->push($block)
            ->sortBy(fn ($item) => [
                (string) data_get($item, 'documento_label', ''),
                (int) data_get($item, 'documento_id', 0),
            ])
            ->values()
            ->all();

        $tramite->forceFill([
            'calculo_periodos_habilitado_at' => $tramite->calculo_periodos_habilitado_at ?: now(),
            'calculo_periodos_data' => $blocks,
        ])->save();

        return redirect()
            ->route('tramites.show', ['tramite' => $tramite, 'tab' => 'calculo'])
            ->with('status', 'Período(s) confirmados y trasladados a la pestaña Cálculo de períodos.');
    }

    public function storeManualCapturePeriod(Request $request, Tramite $tramite, TramiteDocumento $documento)
    {
        abort_unless($this->canReviewDocuments($request->user()), 403);
        abort_unless($this->canAccessTramite($request->user(), $tramite), 403);
        abort_unless((int) $documento->tramite_id === (int) $tramite->id, 404);
        abort_unless($documento->can_run_data_capture, 403);

        if ($this->usesExternalBieniosFlow($tramite)) {
            return $this->externalBieniosFlowRedirect($tramite);
        }

        $validator = Validator::make($request->all(), [
            'manual_fecha_inicio' => ['required', 'date'],
            'manual_fecha_termino' => ['required', 'date', 'after_or_equal:manual_fecha_inicio'],
            'manual_referencia' => ['nullable', 'string', 'max:160'],
        ], [], [
            'manual_fecha_inicio' => 'fecha de inicio manual',
            'manual_fecha_termino' => 'fecha de término manual',
            'manual_referencia' => 'referencia manual',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('tramites.show', ['tramite' => $tramite, 'tab' => 'captura'])
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();
        $currentUser = $request->user();
        $userDisplayName = trim(collect([
            $currentUser->nombres,
            $currentUser->apellido_paterno,
            $currentUser->apellido_materno,
        ])->filter()->implode(' ')) ?: $currentUser->email;

        $tipoConfig = (array) data_get(config('tramites.tipos'), $tramite->tipo . '.documentos.' . $documento->tipo_documento, []);
        $documentLabel = (string) data_get($tipoConfig, 'label', $documento->tipo_documento_label);

        $manualPeriod = [
            'selected_index' => 'manual-doc-' . $documento->id . '-' . (string) Str::uuid(),
            'inicio' => (string) $validated['manual_fecha_inicio'],
            'termino' => (string) $validated['manual_fecha_termino'],
            'referencia' => trim((string) ($validated['manual_referencia'] ?? '')),
            'origen' => 'manual_captura',
            'created_at' => now()->toDateTimeString(),
            'created_by_user_id' => (int) $currentUser->id,
            'created_by_name' => $userDisplayName,
        ];

        $blocks = collect($tramite->calculo_periodos_data ?? [])
            ->filter(fn ($item) => is_array($item))
            ->values();

        $documentBlockIndex = $blocks->search(fn ($item) => (int) data_get($item, 'documento_id') === (int) $documento->id);

        if ($documentBlockIndex === false) {
            $blocks->push([
                'documento_id' => (int) $documento->id,
                'documento_tipo' => (string) $documento->tipo_documento,
                'documento_label' => $documentLabel,
                'documento_nombre' => (string) ($documento->original_name ?: basename((string) $documento->path)),
                'captura_metodo' => (string) $documento->captura_metodo_label,
                'captura_estado' => (string) $documento->captura_estado_label,
                'captura_ejecutada_at' => optional($documento->captura_ejecutada_at)?->toDateTimeString(),
                'confirmed_at' => now()->toDateTimeString(),
                'confirmed_by_user_id' => (int) $currentUser->id,
                'confirmed_by_name' => $userDisplayName,
                'periodos' => [$manualPeriod],
            ]);
        } else {
            $block = (array) $blocks->get($documentBlockIndex, []);
            $periodos = collect((array) data_get($block, 'periodos', []))
                ->filter(fn ($item) => is_array($item))
                ->push($manualPeriod)
                ->values()
                ->all();

            $block['documento_id'] = (int) $documento->id;
            $block['documento_tipo'] = (string) $documento->tipo_documento;
            $block['documento_label'] = $documentLabel;
            $block['documento_nombre'] = (string) ($documento->original_name ?: basename((string) $documento->path));
            $block['captura_metodo'] = (string) $documento->captura_metodo_label;
            $block['captura_estado'] = (string) $documento->captura_estado_label;
            $block['captura_ejecutada_at'] = optional($documento->captura_ejecutada_at)?->toDateTimeString();
            $block['confirmed_at'] = now()->toDateTimeString();
            $block['confirmed_by_user_id'] = (int) $currentUser->id;
            $block['confirmed_by_name'] = $userDisplayName;
            $block['periodos'] = $periodos;

            $blocks->put($documentBlockIndex, $block);
        }

        $tramite->forceFill([
            'calculo_periodos_habilitado_at' => $tramite->calculo_periodos_habilitado_at ?: now(),
            'calculo_periodos_data' => $blocks->values()->all(),
        ])->save();

        return redirect()
            ->route('tramites.show', ['tramite' => $tramite, 'tab' => 'calculo'])
            ->with('status', 'Período manual agregado correctamente al documento aprobado.');
    }

    public function storeManualCalculationPeriod(Request $request, Tramite $tramite)
    {
        abort_unless($this->canReviewDocuments($request->user()), 403);
        abort_unless($this->canAccessTramite($request->user(), $tramite), 403);

        if ($this->usesExternalBieniosFlow($tramite)) {
            return $this->externalBieniosFlowRedirect($tramite);
        }

        $validator = Validator::make($request->all(), [
            'manual_fecha_inicio' => ['required', 'date'],
            'manual_fecha_termino' => ['required', 'date', 'after_or_equal:manual_fecha_inicio'],
            'manual_referencia' => ['nullable', 'string', 'max:160'],
        ], [], [
            'manual_fecha_inicio' => 'fecha de inicio manual',
            'manual_fecha_termino' => 'fecha de término manual',
            'manual_referencia' => 'referencia manual',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('tramites.show', ['tramite' => $tramite, 'tab' => 'calculo'])
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $currentUser = $request->user();
        $userDisplayName = trim(collect([$currentUser->nombres, $currentUser->apellido_paterno, $currentUser->apellido_materno])->filter()->implode(' ')) ?: $currentUser->email;

        $blocks = collect($tramite->calculo_periodos_data ?? [])
            ->filter(fn ($item) => is_array($item))
            ->values();

        $manualBlockIndex = $blocks->search(fn ($item) => (string) data_get($item, 'documento_tipo') === 'manual');
        $manualPeriod = [
            'selected_index' => 'manual-' . (string) Str::uuid(),
            'inicio' => (string) $validated['manual_fecha_inicio'],
            'termino' => (string) $validated['manual_fecha_termino'],
            'referencia' => trim((string) ($validated['manual_referencia'] ?? '')),
            'origen' => 'manual',
            'created_at' => now()->toDateTimeString(),
            'created_by_user_id' => (int) $currentUser->id,
            'created_by_name' => $userDisplayName,
        ];

        if ($manualBlockIndex === false) {
            $blocks->push([
                'documento_id' => null,
                'documento_tipo' => 'manual',
                'documento_label' => 'Períodos manuales',
                'documento_nombre' => 'Ingreso manual en cálculo de períodos',
                'captura_metodo' => 'Manual',
                'captura_estado' => 'Agregado manualmente',
                'captura_ejecutada_at' => now()->toDateTimeString(),
                'confirmed_at' => now()->toDateTimeString(),
                'confirmed_by_user_id' => (int) $currentUser->id,
                'confirmed_by_name' => $userDisplayName,
                'periodos' => [$manualPeriod],
            ]);
        } else {
            $block = (array) $blocks->get($manualBlockIndex, []);
            $periodos = collect((array) data_get($block, 'periodos', []))
                ->filter(fn ($item) => is_array($item))
                ->push($manualPeriod)
                ->values()
                ->all();

            $block['documento_label'] = 'Períodos manuales';
            $block['documento_nombre'] = 'Ingreso manual en cálculo de períodos';
            $block['captura_metodo'] = 'Manual';
            $block['captura_estado'] = 'Agregado manualmente';
            $block['captura_ejecutada_at'] = now()->toDateTimeString();
            $block['confirmed_at'] = now()->toDateTimeString();
            $block['confirmed_by_user_id'] = (int) $currentUser->id;
            $block['confirmed_by_name'] = $userDisplayName;
            $block['periodos'] = $periodos;
            $blocks->put($manualBlockIndex, $block);
        }

        $tramite->forceFill([
            'calculo_periodos_habilitado_at' => $tramite->calculo_periodos_habilitado_at ?: now(),
            'calculo_periodos_data' => $blocks->values()->all(),
        ])->save();

        return redirect()
            ->route('tramites.show', ['tramite' => $tramite, 'tab' => 'calculo'])
            ->with('status', 'Período manual agregado correctamente al cálculo.');
    }


    public function deleteCalculationPeriod(Request $request, Tramite $tramite, int $blockIndex, int $periodIndex)
    {
        abort_unless($this->canReviewDocuments($request->user()), 403);
        abort_unless($this->canAccessTramite($request->user(), $tramite), 403);

        if ($this->usesExternalBieniosFlow($tramite)) {
            return $this->externalBieniosFlowRedirect($tramite);
        }

        $blocks = collect($tramite->calculo_periodos_data ?? [])
            ->filter(fn ($item) => is_array($item))
            ->values();

        $block = (array) $blocks->get($blockIndex, []);
        if (empty($block)) {
            return redirect()
                ->route('tramites.show', ['tramite' => $tramite, 'tab' => 'calculo'])
                ->with('warning', 'No fue posible encontrar el bloque de períodos indicado. Recarga la página e intenta nuevamente.');
        }

        $periodos = collect((array) data_get($block, 'periodos', []))
            ->filter(fn ($item) => is_array($item))
            ->values();

        if (!$periodos->has($periodIndex)) {
            return redirect()
                ->route('tramites.show', ['tramite' => $tramite, 'tab' => 'calculo'])
                ->with('warning', 'No fue posible encontrar el período indicado. Recarga la página e intenta nuevamente.');
        }

        $periodos->forget($periodIndex);
        $periodos = $periodos->values()->all();

        $block['periodos'] = $periodos;
        $block['confirmed_at'] = now()->toDateTimeString();
        $block['confirmed_by_user_id'] = (int) $request->user()->id;
        $block['confirmed_by_name'] = $this->displayUserName($request->user());
        $block['captura_ejecutada_at'] = now()->toDateTimeString();

        if (count($periodos) === 0) {
            $block['captura_estado'] = ((string) data_get($block, 'documento_tipo') === 'manual')
                ? 'Sin períodos manuales vigentes'
                : 'Pendiente de completar fechas';
        }

        if ((string) data_get($block, 'documento_tipo') === 'manual' && count($periodos) === 0) {
            $blocks->forget($blockIndex);
        } else {
            $blocks->put($blockIndex, $block);
        }

        $tramite->forceFill([
            'calculo_periodos_data' => $blocks->values()->all(),
        ])->save();

        return redirect()
            ->route('tramites.show', ['tramite' => $tramite, 'tab' => 'calculo'])
            ->with('status', 'Período eliminado correctamente del cálculo. Los totales y bienios fueron recalculados.');
    }

    public function storeDocumentoCalculationPeriods(Request $request, Tramite $tramite, TramiteDocumento $documento)
    {
        abort_unless($this->canReviewDocuments($request->user()), 403);
        abort_unless($this->canAccessTramite($request->user(), $tramite), 403);
        abort_unless((int) $documento->tramite_id === (int) $tramite->id, 404);
        abort_unless((string) $documento->estado_revision === 'aprobado', 403);

        if ($this->usesExternalBieniosFlow($tramite)) {
            return $this->externalBieniosFlowRedirect($tramite);
        }

        $inputPeriods = collect($request->input('periodos', []))
            ->filter(fn ($item) => is_array($item))
            ->values();

        $validPeriods = [];
        $errors = [];

        foreach ($inputPeriods as $index => $periodo) {
            $inicio = trim((string) data_get($periodo, 'inicio', ''));
            $termino = trim((string) data_get($periodo, 'termino', ''));
            $referencia = trim((string) data_get($periodo, 'referencia', ''));

            if ($inicio === '' && $termino === '' && $referencia === '') {
                continue;
            }

            $row = $index + 1;
            if ($inicio === '' || $termino === '') {
                $errors[] = "El período {$row} debe tener fecha inicio y fecha término.";
                continue;
            }

            try {
                $inicioDate = \Illuminate\Support\Carbon::parse($inicio)->toDateString();
                $terminoDate = \Illuminate\Support\Carbon::parse($termino)->toDateString();
            } catch (\Throwable $e) {
                $errors[] = "El período {$row} tiene fechas inválidas.";
                continue;
            }

            if (\Illuminate\Support\Carbon::parse($terminoDate)->lt(\Illuminate\Support\Carbon::parse($inicioDate))) {
                $errors[] = "El período {$row} tiene fecha término anterior a la fecha inicio.";
                continue;
            }

            $validPeriods[] = [
                'selected_index' => 'documento-' . $documento->id . '-' . (string) Str::uuid(),
                'inicio' => $inicioDate,
                'termino' => $terminoDate,
                'referencia' => $referencia,
                'origen' => 'manual_documento_aprobado',
                'created_at' => now()->toDateTimeString(),
                'created_by_user_id' => (int) $request->user()->id,
                'created_by_name' => $this->displayUserName($request->user()),
            ];
        }

        if (!empty($errors)) {
            return redirect()
                ->route('tramites.show', ['tramite' => $tramite, 'tab' => 'calculo'])
                ->withErrors(['periodos_documento_' . $documento->id => implode(' ', $errors)])
                ->withInput();
        }

        if (empty($validPeriods)) {
            return redirect()
                ->route('tramites.show', ['tramite' => $tramite, 'tab' => 'calculo'])
                ->with('warning', 'Debes ingresar al menos un período válido para guardar fechas del documento aprobado.');
        }

        $this->replaceCalculationBlockForDocument($tramite, $documento, $validPeriods, $request->user(), 'Manual por documento aprobado');

        return redirect()
            ->route('tramites.show', ['tramite' => $tramite, 'tab' => 'calculo'])
            ->with('status', 'Período(s) del documento aprobado guardados correctamente.');
    }

    protected function prepareCalculationBlockFromApprovedDocument(Tramite $tramite, TramiteDocumento $documento, User $user): void
    {
        if ((string) $documento->estado_revision !== 'aprobado') {
            return;
        }

        $existingBlocks = collect($tramite->calculo_periodos_data ?? [])->filter(fn ($item) => is_array($item));
        $alreadyExists = $existingBlocks->contains(fn ($item) => (int) data_get($item, 'documento_id') === (int) $documento->id);
        if ($alreadyExists) {
            return;
        }

        $periods = [];
        if ($documento->fecha_inicio && $documento->fecha_termino) {
            $periods[] = [
                'selected_index' => 'documento-' . $documento->id . '-declarado',
                'inicio' => $documento->fecha_inicio->toDateString(),
                'termino' => $documento->fecha_termino->toDateString(),
                'referencia' => 'Período declarado por el solicitante al cargar el documento.',
                'origen' => 'declarado_solicitante',
                'created_at' => now()->toDateTimeString(),
                'created_by_user_id' => (int) $user->id,
                'created_by_name' => $this->displayUserName($user),
            ];
        }

        $this->replaceCalculationBlockForDocument($tramite, $documento, $periods, $user, 'Pendiente de revisión manual');
    }

    protected function replaceCalculationBlockForDocument(Tramite $tramite, TramiteDocumento $documento, array $periods, User $user, string $methodLabel): void
    {
        $tipoConfig = (array) data_get(config('tramites.tipos'), $tramite->tipo . '.documentos.' . $documento->tipo_documento, []);
        $documentLabel = (string) data_get($tipoConfig, 'label', $documento->tipo_documento_label);

        $block = [
            'documento_id' => (int) $documento->id,
            'documento_tipo' => (string) $documento->tipo_documento,
            'documento_label' => $documentLabel,
            'documento_nombre' => (string) ($documento->original_name ?: basename((string) $documento->path)),
            'captura_metodo' => $methodLabel,
            'captura_estado' => count($periods) > 0 ? 'Fechas disponibles para cálculo' : 'Pendiente de completar fechas',
            'captura_ejecutada_at' => now()->toDateTimeString(),
            'confirmed_at' => now()->toDateTimeString(),
            'confirmed_by_user_id' => (int) $user->id,
            'confirmed_by_name' => $this->displayUserName($user),
            'periodos' => array_values($periods),
        ];

        $blocks = collect($tramite->calculo_periodos_data ?? [])
            ->filter(fn ($item) => is_array($item))
            ->reject(fn ($item) => (int) data_get($item, 'documento_id') === (int) $documento->id)
            ->push($block)
            ->sortBy(fn ($item) => [
                (string) data_get($item, 'documento_label', ''),
                (int) data_get($item, 'documento_id', 0),
            ])
            ->values()
            ->all();

        $tramite->forceFill([
            'calculo_periodos_habilitado_at' => $tramite->calculo_periodos_habilitado_at ?: now(),
            'calculo_periodos_data' => $blocks,
        ])->save();
    }

    protected function displayUserName(User $user): string
    {
        return trim(collect([$user->nombres, $user->apellido_paterno, $user->apellido_materno])->filter()->implode(' ')) ?: (string) $user->email;
    }

    public function generateRex(Request $request, Tramite $tramite, ResolucionReconocimientoBieniosService $resolutionService)
    {
        abort_unless($this->canReviewDocuments($request->user()), 403);
        abort_unless($this->canAccessTramite($request->user(), $tramite), 403);

        if ($this->usesExternalBieniosFlow($tramite)) {
            return $this->externalBieniosFlowRedirect($tramite);
        }

        if ($tramite->calculo_periodos_flattened_collection->isEmpty()) {
            return redirect()
                ->route('tramites.show', ['tramite' => $tramite, 'tab' => 'calculo'])
                ->with('warning', 'Primero debes guardar al menos un período con fecha inicio y término para generar la resolución.');
        }

        $validated = $request->validate([
            'fecha_reconocimiento' => ['required', 'date'],
        ], [], [
            'fecha_reconocimiento' => 'fecha de reconocimiento',
        ]);

        $generated = $resolutionService->generateAndStore(
            $tramite->fresh(['documentos', 'user']),
            (string) $validated['fecha_reconocimiento']
        );

        $tramite->forceFill([
            'rex_generado_at' => now(),
            'rex_fecha_reconocimiento' => $validated['fecha_reconocimiento'],
            'rex_docx_path' => $generated['path'],
        ])->save();

        return redirect()
            ->route('tramites.show', ['tramite' => $tramite, 'tab' => 'resolucion'])
            ->with('status', 'Resolución Word generada correctamente.');
    }

    public function downloadRexDocx(Request $request, Tramite $tramite)
    {
        abort_unless($this->canAccessTramite($request->user(), $tramite), 403);
        abort_unless($tramite->rex_docx_path && Storage::disk('local')->exists($tramite->rex_docx_path), 404);

        return Storage::disk('local')->download($tramite->rex_docx_path, basename($tramite->rex_docx_path));
    }

    public function uploadResolucionPdf(Request $request, Tramite $tramite)
    {
        abort_unless($this->canReviewDocuments($request->user()), 403);
        abort_unless($this->canAccessTramite($request->user(), $tramite), 403);
        abort_if((string) $tramite->estado === 'anulado', 409, 'No se puede cargar una resolución en un trámite anulado.');

        if ($this->usesExternalBieniosFlow($tramite)) {
            $tramite->loadMissing('documentos');
            $documentationStatus = $this->bieniosDocumentationStatus($tramite);

            if (!($documentationStatus['ready'] ?? false)) {
                return redirect()
                    ->route('tramites.show', ['tramite' => $tramite, 'tab' => 'documentos'])
                    ->with('warning', 'No se puede cargar el resultado mientras la revisión documental esté incompleta: ' . implode(' ', (array) ($documentationStatus['messages'] ?? [])));
            }

            $validated = $request->validate([
                'resolucion_pdf' => ['required', 'file', 'mimetypes:application/pdf', 'mimes:pdf', 'max:20480'],
                'detalle_calculo_pdf' => ['required', 'file', 'mimetypes:application/pdf', 'mimes:pdf', 'max:20480'],
            ], [], [
                'resolucion_pdf' => 'resolución firmada en PDF',
                'detalle_calculo_pdf' => 'detalle del cómputo en PDF',
            ]);

            $dir = 'tramites/' . $tramite->id . '/resolucion-bienios';
            Storage::disk('local')->makeDirectory($dir);

            $resolutionPath = $validated['resolucion_pdf']->storeAs(
                $dir,
                'RESOLUCION_RECONOCIMIENTO_BIENIOS_' . $tramite->id . '.pdf',
                'local'
            );
            $detailPath = $validated['detalle_calculo_pdf']->storeAs(
                $dir,
                'DETALLE_COMPUTO_BIENIOS_' . $tramite->id . '.pdf',
                'local'
            );

            $tramite->forceFill([
                'resolucion_pdf_path' => $resolutionPath,
                'resolucion_pdf_uploaded_at' => now(),
                'detalle_calculo_pdf_path' => $detailPath,
                'detalle_calculo_pdf_uploaded_at' => now(),
                'detalle_calculo_pdf_uploaded_by_user_id' => $request->user()->id,
                'estado' => 'en_revision',
                'resultado_enviado_at' => null,
                'resuelto_at' => null,
            ])->save();

            return redirect()
                ->route('tramites.show', ['tramite' => $tramite, 'tab' => 'resolucion'])
                ->with('status', 'Resolución firmada y detalle del cómputo cargados correctamente. El resultado quedó disponible para notificación.');
        }

        $validated = $request->validate([
            'resolucion_pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ], [], [
            'resolucion_pdf' => 'REX firmada en PDF',
        ]);

        $dir = 'tramites/' . $tramite->id . '/resolucion-bienios';
        Storage::disk('local')->makeDirectory($dir);
        $filename = 'RESOLUCION_RECONOCIMIENTO_BIENIOS_' . $tramite->id . '.pdf';
        $path = $validated['resolucion_pdf']->storeAs($dir, $filename, 'local');

        $tramite->forceFill([
            'resolucion_pdf_path' => $path,
            'resolucion_pdf_uploaded_at' => now(),
            'estado' => 'resuelto',
            'resuelto_at' => now(),
        ])->save();

        return redirect()
            ->route('tramites.show', ['tramite' => $tramite, 'tab' => 'resolucion'])
            ->with('status', 'REX firmada en PDF cargada correctamente. El trámite quedó en estado Resuelto.');
    }

    public function downloadResolucionPdf(Request $request, Tramite $tramite)
    {
        abort_unless($this->canAccessTramite($request->user(), $tramite), 403);

        $type = (string) $request->query('tipo', 'resolucion');
        if ($type === 'detalle') {
            $path = (string) $tramite->detalle_calculo_pdf_path;
            $downloadName = 'DETALLE_COMPUTO_BIENIOS_' . $tramite->id . '.pdf';
        } else {
            $path = (string) $tramite->resolucion_pdf_path;
            $downloadName = 'RESOLUCION_RECONOCIMIENTO_BIENIOS_' . $tramite->id . '.pdf';
        }

        abort_unless($path !== '' && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $downloadName, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function sendResultado(Request $request, Tramite $tramite, ResolucionReconocimientoBieniosService $resolutionService)
    {
        abort_unless($this->canReviewDocuments($request->user()), 403);
        abort_unless($this->canAccessTramite($request->user(), $tramite), 403);
        abort_if((string) $tramite->estado === 'anulado', 409, 'No se puede enviar el resultado de un trámite anulado.');

        if (!$tramite->resolucion_pdf_path || !Storage::disk('local')->exists($tramite->resolucion_pdf_path)) {
            return redirect()
                ->route('tramites.show', ['tramite' => $tramite, 'tab' => 'resolucion'])
                ->with('warning', 'Primero debes cargar la resolución en PDF para poder enviar el resultado.');
        }

        if ($this->usesExternalBieniosFlow($tramite)
            && (!$tramite->detalle_calculo_pdf_path || !Storage::disk('local')->exists($tramite->detalle_calculo_pdf_path))) {
            return redirect()
                ->route('tramites.show', ['tramite' => $tramite, 'tab' => 'resolucion'])
                ->with('warning', 'Primero debes cargar el detalle del cómputo en PDF para poder enviar el resultado.');
        }

        if ($this->usesExternalBieniosFlow($tramite)) {
            $documentationStatus = $this->bieniosDocumentationStatus($tramite->fresh(['documentos']));
            if (!($documentationStatus['ready'] ?? false)) {
                return redirect()
                    ->route('tramites.show', ['tramite' => $tramite, 'tab' => 'documentos'])
                    ->with('warning', 'No se puede enviar el resultado mientras la revisión documental esté incompleta: ' . implode(' ', (array) ($documentationStatus['messages'] ?? [])));
            }
        }

        $resolutionData = $this->usesExternalBieniosFlow($tramite)
            ? []
            : $resolutionService->buildData($tramite->fresh(['documentos', 'user']));

        $tramite->loadMissing('user');
        $mailRecipients = collect([
            [
                'email' => (string) $tramite->user->email,
                'name' => $tramite->user->nombre_completo ?? $tramite->user->email,
                'notifiable' => $tramite->user,
                'recipient_role' => 'solicitante',
            ],
        ])
            ->filter(fn (array $item) => trim((string) ($item['email'] ?? '')) !== '')
            ->unique(fn (array $item) => mb_strtolower(trim((string) ($item['email'] ?? ''))))
            ->values();

        if ($mailRecipients->isEmpty()) {
            return redirect()
                ->route('tramites.show', ['tramite' => $tramite, 'tab' => 'resolucion'])
                ->with('warning', 'No fue posible enviar el resultado: el usuario solicitante no tiene correo electrónico configurado.');
        }

        try {
            foreach ($mailRecipients as $recipient) {
                NotificationAudit::sendMail((string) $recipient['email'], new TramiteResultadoBieniosMail($tramite->fresh(['user']), $resolutionData), [
                    'event_key' => 'tramite.bienios.resultado_enviado',
                    'description' => 'Envío de resultado del trámite de reconocimiento de bienios',
                    'subject' => 'Resultado trámite reconocimiento de bienios #' . $tramite->id,
                    'related' => $tramite,
                    'notifiable' => $recipient['notifiable'],
                    'recipient_name' => $recipient['name'],
                    'context' => [
                        'tramite_id' => $tramite->id,
                        'tipo_tramite' => $tramite->tipo,
                        'estado' => $tramite->estado,
                        'recipient_role' => $recipient['recipient_role'],
                        'calculo_externo' => $this->usesExternalBieniosFlow($tramite),
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[MAIL] Falló envío de resultado de trámite bienios', [
                'tramite_id' => $tramite->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('tramites.show', ['tramite' => $tramite, 'tab' => 'resolucion'])
                ->with('warning', 'No fue posible enviar el correo de resultado: ' . $e->getMessage());
        }

        $updates = ['resultado_enviado_at' => now()];
        if ($this->usesExternalBieniosFlow($tramite)) {
            $updates['estado'] = 'resuelto';
            $updates['resuelto_at'] = now();
        }
        $tramite->forceFill($updates)->save();

        return redirect()
            ->route('tramites.show', ['tramite' => $tramite, 'tab' => 'resolucion'])
            ->with('status', 'Resultado enviado correctamente al correo del usuario solicitante.');
    }

    public function informarCierreBienios(Request $request, Tramite $tramite)
    {
        abort_unless($this->canReviewDocuments($request->user()), 403);
        abort_unless($this->canAccessTramite($request->user(), $tramite), 403);
        abort_unless((string) $tramite->tipo === 'reconocimiento_bienios', 404);

        $tramite->loadMissing('user');

        $recipientEmail = trim((string) ($tramite->user?->email ?? ''));
        if ($recipientEmail === '') {
            return redirect()
                ->route('tramites.show', ['tramite' => $tramite, 'tab' => 'calculo'])
                ->with('warning', 'No fue posible informar el cierre: el usuario solicitante no tiene correo electrónico configurado.');
        }

        $recipientName = $tramite->nombre_completo_snapshot ?: ($tramite->user?->nombre_completo ?? $recipientEmail);
        $subject = 'Trámite de Reconocimiento de Bienios finalizado';

        try {
            NotificationAudit::sendMail($recipientEmail, new TramiteCierreBieniosInformadoMail($tramite->fresh(['user'])), [
                'event_key' => 'tramite.bienios.cierre_informado',
                'description' => 'Información de cierre del trámite de reconocimiento de bienios al solicitante',
                'subject' => $subject,
                'related' => $tramite,
                'notifiable' => $tramite->user,
                'recipient_name' => $recipientName,
                'context' => [
                    'tramite_id' => $tramite->id,
                    'tipo_tramite' => $tramite->tipo,
                    'estado' => $tramite->estado,
                    'fecha_reconocimiento' => optional($tramite->enviado_at)->toDateString(),
                    'origin' => 'bienios_cierre_manual',
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('[MAIL] Falló informe de cierre de trámite bienios', [
                'tramite_id' => $tramite->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('tramites.show', ['tramite' => $tramite, 'tab' => 'calculo'])
                ->with('warning', 'No fue posible enviar el informe de cierre: ' . $e->getMessage());
        }

        return redirect()
            ->route('tramites.show', ['tramite' => $tramite, 'tab' => 'calculo'])
            ->with('status', 'Cierre del trámite informado correctamente al correo del solicitante.');
    }

    public function notifyApplicant(Request $request, Tramite $tramite)
    {
        abort_unless($this->canReviewDocuments($request->user()), 403);
        abort_unless($this->canAccessTramite($request->user(), $tramite), 403);

        $validator = Validator::make($request->all(), [
            'mensaje_notificacion' => ['required', 'string', 'max:5000'],
        ], [], [
            'mensaje_notificacion' => 'mensaje',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('tramites.show', ['tramite' => $tramite, 'tab' => 'documentos'])
                ->withErrors($validator, 'notifyApplicant')
                ->withInput()
                ->with('open_notify_applicant_modal', true);
        }

        $tramite->loadMissing('user');

        $recipientEmail = trim((string) ($tramite->user?->email ?: $tramite->email_snapshot));
        if ($recipientEmail === '') {
            return redirect()
                ->route('tramites.show', ['tramite' => $tramite, 'tab' => 'documentos'])
                ->with('warning', 'El trámite no tiene correo del solicitante configurado para enviar la notificación.');
        }

        $message = trim((string) $validator->validated()['mensaje_notificacion']);
        $recipientName = $tramite->nombre_completo_snapshot ?: ($tramite->user?->nombre_completo ?? $recipientEmail);
        $subject = 'Notificación sobre tu trámite #' . $tramite->id . ' (' . $tramite->tipo_label . ')';

        try {
            NotificationAudit::sendMail($recipientEmail, new TramiteManualApplicantNotificationMail($tramite->fresh(['user']), $message), [
                'event_key' => 'tramite.manual_applicant_notification',
                'description' => 'Notificación manual enviada al solicitante desde revisión del trámite',
                'subject' => $subject,
                'related' => $tramite,
                'notifiable' => $tramite->user,
                'recipient_name' => $recipientName,
                'context' => [
                    'tramite_id' => $tramite->id,
                    'tipo_tramite' => $tramite->tipo,
                    'estado' => $tramite->estado,
                    'mensaje' => $message,
                    'origin' => 'review_modal',
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('[MAIL] Falló notificación manual al solicitante', [
                'tramite_id' => $tramite->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('tramites.show', ['tramite' => $tramite, 'tab' => 'documentos'])
                ->with('warning', 'No fue posible enviar la notificación al solicitante: ' . $e->getMessage())
                ->with('open_notify_applicant_modal', true)
                ->withInput();
        }

        return redirect()
            ->route('tramites.show', ['tramite' => $tramite, 'tab' => 'documentos'])
            ->with('status', 'Notificación enviada correctamente al solicitante.');
    }


    public function anularGestion(Request $request, Tramite $tramite)
    {
        abort_unless($this->canReviewDocuments($request->user()), 403);
        abort_unless($this->canAccessTramite($request->user(), $tramite), 403);

        $validated = $request->validate([
            'motivo_anulacion' => ['required', 'string', 'max:1500'],
        ], [], [
            'motivo_anulacion' => 'motivo de anulación',
        ]);

        $tramite->forceFill([
            'estado' => 'anulado',
            'anulado_at' => now(),
            'anulado_por_user_id' => $request->user()->id,
            'anulado_motivo' => trim((string) $validated['motivo_anulacion']),
        ])->save();

        if ($tramite->user?->email) {
            try {
                NotificationAudit::sendMail($tramite->user->email, new TramiteAnuladoMail($tramite->fresh(['user', 'anuladoPor'])), [
                    'event_key' => 'tramite.anulado',
                    'description' => 'Notificación de anulación del trámite',
                    'subject' => 'Anulación de trámite #' . $tramite->id,
                    'related' => $tramite,
                    'notifiable' => $tramite->user,
                    'context' => [
                        'tramite_id' => $tramite->id,
                        'tipo_tramite' => $tramite->tipo,
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::warning('[MAIL] Falló envío de anulación de trámite', [
                    'tramite_id' => $tramite->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return redirect()
            ->route('tramites.show', ['tramite' => $tramite])
            ->with('status', 'Trámite anulado correctamente y notificación enviada al usuario.');
    }

    public function downloadTemplate(Request $request, string $tipo)
    {
        abort_unless($this->isApplicant($request->user()), 403);

        $relativePath = (string) data_get(config('tramites.tipos'), $tipo . '.template_relative_path', '');
        abort_if($relativePath === '', 404);

        $fullPath = resource_path($relativePath);
        abort_unless(is_file($fullPath), 404);

        return response()->download($fullPath, basename($fullPath));
    }

    private function storeUploadedDocuments(Request $request, Tramite $tramite, int $userId, array $tipoConfig): array
    {
        $uploadedDocTypes = [];

        foreach ($request->file('documentos', []) as $index => $fileRow) {
            $file = $fileRow['archivo'] ?? null;
            if (!$file) {
                continue;
            }

            $row = $request->input('documentos.' . $index, []);
            $docType = (string) ($row['tipo_documento'] ?? '');
            if ($docType === '') {
                continue;
            }

            $docConfig = (array) data_get($tipoConfig, 'documentos.' . $docType, []);
            $filename = $this->buildFilename($tramite, $docType, $index);
            $dir = 'tramites/' . $tramite->id . '/' . $docType;
            Storage::disk('local')->makeDirectory($dir);
            $path = $file->storeAs($dir, $filename, 'local');

            TramiteDocumento::create([
                'tramite_id' => $tramite->id,
                'uploaded_by' => $userId,
                'tipo_documento' => $docType,
                'formato' => 'pdf',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => 'application/pdf',
                'size' => $file->getSize(),
                'fecha_inicio' => (bool) ($docConfig['requires_period'] ?? false) ? ($row['fecha_inicio'] ?? null) : null,
                'fecha_termino' => (bool) ($docConfig['requires_period'] ?? false) ? ($row['fecha_termino'] ?? null) : null,
            ]);

            $uploadedDocTypes[] = $docType;
        }

        return array_values(array_unique($uploadedDocTypes));
    }

    private function buildFilename(Tramite $tramite, string $docType, int $index): string
    {
        $rut = preg_replace('/[^0-9a-z]/i', '', (string) $tramite->rut_snapshot) ?: (string) $tramite->user_id;
        $doc = Str::slug((string) data_get(config('tramites.tipos'), $tramite->tipo . '.documentos.' . $docType . '.label', $docType), '-');
        $stamp = now()->format('Ymd_His');

        return $rut . '_' . $doc . '_' . $stamp . '_' . $index . '.pdf';
    }

    private function usesExternalBieniosFlow(Tramite $tramite): bool
    {
        return (string) $tramite->tipo === 'reconocimiento_bienios'
            && (bool) $tramite->bienios_flujo_externo;
    }

    private function externalBieniosFlowRedirect(Tramite $tramite)
    {
        return redirect()
            ->route('tramites.show', ['tramite' => $tramite, 'tab' => 'resolucion'])
            ->with('warning', 'Este trámite utiliza cómputo administrativo externo. La plataforma no permite ingresar períodos ni calcular bienios para este expediente.');
    }

    private function bieniosDocumentationStatus(Tramite $tramite): array
    {
        if ((string) $tramite->tipo !== 'reconocimiento_bienios') {
            return [
                'ready' => false,
                'messages' => ['El trámite no corresponde a Reconocimiento de Bienios.'],
                'missing_required' => [],
                'approved_optional_count' => 0,
            ];
        }

        $config = collect((array) config('tramites.tipos.reconocimiento_bienios.documentos', []));
        $documents = $tramite->relationLoaded('documentos')
            ? $tramite->documentos
            : $tramite->documentos()->get();

        $approvedTypes = $documents
            ->where('estado_revision', 'aprobado')
            ->pluck('tipo_documento')
            ->map(fn ($value) => (string) $value)
            ->values();

        $required = $config->filter(fn ($doc) => (bool) data_get($doc, 'required', false));
        $optional = $config->reject(fn ($doc) => (bool) data_get($doc, 'required', false));

        $missingRequired = $required
            ->reject(fn ($doc, $key) => $approvedTypes->contains((string) $key))
            ->map(fn ($doc) => (string) data_get($doc, 'label', 'Documento obligatorio'))
            ->values();

        $approvedOptionalCount = $optional
            ->keys()
            ->filter(fn ($key) => $approvedTypes->contains((string) $key))
            ->count();

        $messages = collect();
        if ($missingRequired->isNotEmpty()) {
            $messages->push('Falta aprobar: ' . $missingRequired->implode(', ') . '.');
        }
        if ($approvedOptionalCount < 1) {
            $messages->push('Debe existir al menos un antecedente complementario aprobado.');
        }

        return [
            'ready' => $missingRequired->isEmpty() && $approvedOptionalCount >= 1,
            'messages' => $messages->values()->all(),
            'missing_required' => $missingRequired->all(),
            'approved_optional_count' => $approvedOptionalCount,
        ];
    }

    private function isApplicant(?User $user): bool
    {
        return $user instanceof User && $user->hasAnyRole(self::APPLICANT_ROLES);
    }

    private function isReviewer(?User $user): bool
    {
        return $user instanceof User && $user->hasAnyRole(self::REVIEWER_ROLES);
    }

    private function canAccessTramite(?User $user, Tramite $tramite): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        if ($this->isReviewer($user)) {
            return $tramite->user()
                ->whereHas('roles', fn (Builder $q) => $q->whereIn('name', self::APPLICANT_ROLES))
                ->exists();
        }

        return (int) $tramite->user_id === (int) $user->id;
    }

    private function canReviewDocuments(?User $user): bool
    {
        return $this->isReviewer($user);
    }
}
