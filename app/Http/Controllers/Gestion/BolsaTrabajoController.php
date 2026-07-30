<?php

namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use App\Mail\BolsaTrabajoDifusionRequestMail;
use App\Mail\BolsaTrabajoEtapaPostulacionMail;
use App\Models\AreaDesempeno;
use App\Models\BolsaTrabajoOferta;
use App\Models\BolsaTrabajoPostulacion;
use App\Models\DocumentType;
use App\Models\Establecimiento;
use App\Support\NotificationAudit;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BolsaTrabajoController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeGestionBolsa();

        $q = trim((string) $request->query('q', ''));
        $estamento = trim((string) $request->query('estamento', ''));
        $comuna = trim((string) $request->query('comuna', ''));

        $query = BolsaTrabajoOferta::query()
            ->with(['establecimiento', 'areaDesempeno', 'creador', 'selectedPostulacion.user'])
            ->withCount('postulaciones');

        if ($q !== '') {
            $query->where(function ($inner) use ($q) {
                $inner->whereHas('establecimiento', function ($qEst) use ($q) {
                    $qEst->where('nombre_establecimiento', 'like', '%' . $q . '%')
                        ->orWhere('rbd', 'like', '%' . $q . '%');
                })->orWhereHas('areaDesempeno', function ($qArea) use ($q) {
                    $qArea->where('nombre', 'like', '%' . $q . '%');
                })->orWhere('correo_contacto', 'like', '%' . $q . '%');
            });
        }

        if (in_array($estamento, ['docente', 'asistente'], true)) {
            $query->where('estamento', $estamento);
        }

        if ($comuna !== '') {
            $query->where('comuna', $comuna);
        }

        $items = $query
            ->orderByDesc('fecha_inicio_postulaciones')
            ->orderByDesc('hora_inicio_postulaciones')
            ->paginate(15)
            ->withQueryString();

        $comunas = $this->availableComunas();

        return view('gestion.bolsa-trabajo.index', compact('items', 'q', 'estamento', 'comuna', 'comunas'));
    }

    public function create(): View
    {
        $this->authorizeGestionBolsa();

        $item = new BolsaTrabajoOferta();
        return view('gestion.bolsa-trabajo.create', $this->formViewData($item));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->authorizeGestionBolsa();
        $data = $this->validatedData($request);
        $basesPdf = $request->file('bases_pdf');
        unset($data['bases_pdf']);
        $data['created_by_user_id'] = $user->id;
        $data['updated_by_user_id'] = $user->id;
        $data['etapa_estado'] = BolsaTrabajoOferta::ETAPA_RECEPCION_ANTECEDENTES;

        $item = BolsaTrabajoOferta::create($data);

        if ($basesPdf) {
            $path = $this->storeBasesPdf($item, $basesPdf);
            $item->forceFill([
                'bases_pdf_path' => $path,
                'bases_pdf_original_name' => $basesPdf->getClientOriginalName(),
            ])->save();
        }

        $warning = $this->notifyComunicaciones($item->fresh(['establecimiento', 'areaDesempeno']), 'creada');

        return redirect()
            ->route('gestion.bolsa-trabajo.index')
            ->with('status', 'Oferta laboral creada correctamente.' . ($warning ? ' ' . $warning : ''));
    }

    public function show(BolsaTrabajoOferta $bolsa_trabajo): View
    {
        $this->authorizeGestionBolsa();
        $item = $bolsa_trabajo->load([
            'establecimiento',
            'areaDesempeno',
            'creador',
            'actualizador',
            'selectedPostulacion.user',
            'postulaciones.user.postulantProfile',
            'postulaciones.user.documents.type',
            'postulaciones.postulantProfile',
        ]);

        return view('gestion.bolsa-trabajo.show', [
            'item' => $item,
            'etapaOptions' => BolsaTrabajoOferta::etapaOptions(),
            'exportStageCount' => $this->resolvePostulacionesForExport($item, 'stage')->count(),
            'exportAllCount' => $this->resolvePostulacionesForExport($item, 'all')->count(),
        ]);
    }

    public function edit(BolsaTrabajoOferta $bolsa_trabajo): View
    {
        $this->authorizeGestionBolsa();
        $item = $bolsa_trabajo;
        return view('gestion.bolsa-trabajo.edit', $this->formViewData($item));
    }

    public function update(Request $request, BolsaTrabajoOferta $bolsa_trabajo): RedirectResponse
    {
        $user = $this->authorizeGestionBolsa();
        $data = $this->validatedData($request);
        $basesPdf = $request->file('bases_pdf');
        unset($data['bases_pdf']);
        $data['updated_by_user_id'] = $user->id;

        $bolsa_trabajo->update($data);

        if ($basesPdf) {
            $this->deleteBasesPdf($bolsa_trabajo);
            $path = $this->storeBasesPdf($bolsa_trabajo, $basesPdf);
            $bolsa_trabajo->forceFill([
                'bases_pdf_path' => $path,
                'bases_pdf_original_name' => $basesPdf->getClientOriginalName(),
            ])->save();
        }

        $warning = $this->notifyComunicaciones($bolsa_trabajo->fresh(['establecimiento', 'areaDesempeno']), 'actualizada');

        return redirect()
            ->route('gestion.bolsa-trabajo.index')
            ->with('status', 'Oferta laboral actualizada correctamente.' . ($warning ? ' ' . $warning : ''));
    }

    public function updateEtapa(Request $request, BolsaTrabajoOferta $bolsa_trabajo): RedirectResponse
    {
        $this->authorizeGestionBolsa();

        $validated = $request->validate([
            'etapa_estado' => ['required', 'in:' . implode(',', array_keys(BolsaTrabajoOferta::etapaOptions()))],
            'selected_postulacion_id' => ['nullable', 'integer'],
            'avanza_postulaciones' => ['nullable', 'array'],
            'avanza_postulaciones.*' => ['integer'],
        ], [
            'etapa_estado.required' => 'Debe seleccionar la etapa/estado de la oferta.',
        ]);

        $targetStage = (string) $validated['etapa_estado'];
        $currentStage = $bolsa_trabajo->currentEtapaKey();

        if ($targetStage === $currentStage) {
            return back()->with('warning', 'Debe seleccionar una etapa distinta para aplicar el cambio.');
        }

        $postulaciones = $bolsa_trabajo->postulaciones()
            ->with(['user.postulantProfile'])
            ->orderBy('created_at')
            ->get();

        $selectedIds = collect($validated['avanza_postulaciones'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $warnings = [];

        if (in_array($targetStage, BolsaTrabajoOferta::etapasEvaluables(), true)) {
            $eligibleIds = $postulaciones
                ->filter(fn (BolsaTrabajoPostulacion $postulacion) => $postulacion->canParticipateInStageSelection())
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values();

            $selectedIds = $selectedIds->filter(fn ($id) => $eligibleIds->contains($id))->values();

            if ($selectedIds->isEmpty()) {
                throw ValidationException::withMessages([
                    'avanza_postulaciones' => 'Debe marcar al menos un postulante que avance a la nueva etapa.',
                ]);
            }

            foreach ($postulaciones as $postulacion) {
                if (!$postulacion->canParticipateInStageSelection()) {
                    continue;
                }

                $avanza = $selectedIds->contains((int) $postulacion->id);
                $postulacion->forceFill([
                    'avanza_etapa' => $avanza,
                    'estado' => $avanza ? 'en_proceso' : 'no_avanza',
                ])->save();

                $warnings = array_merge($warnings, $this->notifyPostulacionByStageResult(
                    $postulacion,
                    $bolsa_trabajo,
                    $avanza ? 'avance' : 'no_avanza',
                    ['target_stage_label' => BolsaTrabajoOferta::etapaOptions()[$targetStage] ?? $targetStage]
                ));
            }

            $bolsa_trabajo->forceFill([
                'etapa_estado' => $targetStage,
                'selected_postulacion_id' => null,
                'etapa_changed_at' => now(),
            ])->save();

            return back()->with('status', 'Etapa actualizada correctamente.' . $this->warningsToString($warnings));
        }

        if ($targetStage === BolsaTrabajoOferta::ETAPA_DESIERTO) {
            foreach ($postulaciones as $postulacion) {
                $postulacion->forceFill([
                    'avanza_etapa' => false,
                    'estado' => 'proceso_desierto',
                ])->save();

                $warnings = array_merge($warnings, $this->notifyPostulacionByStageResult($postulacion, $bolsa_trabajo, 'desierto'));
            }

            $bolsa_trabajo->forceFill([
                'etapa_estado' => $targetStage,
                'selected_postulacion_id' => null,
                'etapa_changed_at' => now(),
            ])->save();

            return back()->with('status', 'La oferta quedó marcada como Desierta.' . $this->warningsToString($warnings));
        }

        if ($targetStage === BolsaTrabajoOferta::ETAPA_CERRADO) {
            $selectedPostulacionId = isset($validated['selected_postulacion_id']) && $validated['selected_postulacion_id'] !== ''
                ? (int) $validated['selected_postulacion_id']
                : null;

            $selectedPostulacion = $postulaciones->firstWhere('id', $selectedPostulacionId);
            if (!$selectedPostulacion) {
                throw ValidationException::withMessages([
                    'selected_postulacion_id' => 'Debe seleccionar la persona finalmente elegida para cerrar la oferta.',
                ]);
            }

            foreach ($postulaciones as $postulacion) {
                $isSelected = (int) $postulacion->id === (int) $selectedPostulacion->id;
                $postulacion->forceFill([
                    'avanza_etapa' => false,
                    'estado' => $isSelected ? 'seleccionado' : 'cerrado_no_seleccionado',
                ])->save();

                $warnings = array_merge($warnings, $this->notifyPostulacionByStageResult(
                    $postulacion,
                    $bolsa_trabajo,
                    'cerrado',
                    ['selected_name' => $selectedPostulacion->user?->display_name ?? $selectedPostulacion->user?->full_name ?? '']
                ));
            }

            $bolsa_trabajo->forceFill([
                'etapa_estado' => $targetStage,
                'selected_postulacion_id' => $selectedPostulacion->id,
                'etapa_changed_at' => now(),
            ])->save();

            return back()->with('status', 'La oferta quedó cerrada correctamente.' . $this->warningsToString($warnings));
        }

        // Recepción de antecedentes o cualquier ajuste de reapertura manual.
        $bolsa_trabajo->forceFill([
            'etapa_estado' => $targetStage,
            'selected_postulacion_id' => null,
            'etapa_changed_at' => now(),
        ])->save();

        return back()->with('status', 'Etapa actualizada correctamente.');
    }

    public function destroy(BolsaTrabajoOferta $bolsa_trabajo): RedirectResponse
    {
        $this->authorizeGestionBolsa();
        $this->deleteBasesPdf($bolsa_trabajo);
        $bolsa_trabajo->delete();

        return redirect()
            ->route('gestion.bolsa-trabajo.index')
            ->with('status', 'Oferta laboral eliminada correctamente.');
    }

    public function downloadBasesPdf(BolsaTrabajoOferta $bolsa_trabajo)
    {
        $this->authorizeGestionBolsa();
        abort_unless($bolsa_trabajo->bases_pdf_path && Storage::disk('local')->exists($bolsa_trabajo->bases_pdf_path), 404);

        return Storage::disk('local')->download(
            $bolsa_trabajo->bases_pdf_path,
            $bolsa_trabajo->bases_pdf_original_name ?: ('bases-oferta-' . $bolsa_trabajo->id . '.pdf'),
            ['Content-Type' => 'application/pdf']
        );
    }

    public function downloadApprovedDocumentsZip(Request $request, BolsaTrabajoOferta $bolsa_trabajo)
    {
        $this->authorizeGestionBolsa();

        return $this->downloadPostulacionesZip($request, $bolsa_trabajo, 'approved_documents');
    }

    public function downloadCurriculumZip(Request $request, BolsaTrabajoOferta $bolsa_trabajo)
    {
        $this->authorizeGestionBolsa();

        return $this->downloadPostulacionesZip($request, $bolsa_trabajo, 'curriculum_only');
    }

    protected function downloadPostulacionesZip(Request $request, BolsaTrabajoOferta $oferta, string $mode)
    {
        $scope = (string) $request->query('scope', 'stage');
        if (!in_array($scope, ['stage', 'all'], true)) {
            $scope = 'stage';
        }

        $oferta->loadMissing([
            'establecimiento',
            'areaDesempeno',
            'selectedPostulacion.user',
            'postulaciones.user.documents.type',
            'postulaciones.user.postulantProfile',
            'postulaciones.postulantProfile',
        ]);

        $postulaciones = $this->resolvePostulacionesForExport($oferta, $scope);
        if ($postulaciones->isEmpty()) {
            return back()->with('warning', 'No hay postulantes disponibles para generar el ZIP según el alcance seleccionado.');
        }

        $curriculumTypeId = $mode === 'curriculum_only'
            ? (int) DocumentType::query()->where('slug', 'curriculum')->value('id')
            : 0;

        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0775, true);
        }

        $zipPath = $tmpDir . DIRECTORY_SEPARATOR . uniqid('bolsa_trabajo_' . $oferta->id . '_', true) . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return back()->withErrors(['zip' => 'No se pudo crear el archivo ZIP solicitado.']);
        }

        $addedFiles = 0;

        foreach ($postulaciones as $postulacion) {
            $user = $postulacion->user;
            if (!$user) {
                continue;
            }

            $folder = $this->candidateFolderName($user);
            $zip->addEmptyDir($folder);

            $documents = $mode === 'curriculum_only'
                ? $this->curriculumDocumentsForZip($user, $curriculumTypeId)
                : $this->approvedDocumentsForZip($user);

            foreach ($documents as $document) {
                $disk = (string) ($document->disk ?? 'public');
                $path = (string) $document->path;

                if ($path === '' || !Storage::disk($disk)->exists($path)) {
                    continue;
                }

                $nameInside = $folder . '/' . basename($path);
                if ($zip->locateName($nameInside) !== false) {
                    $base = pathinfo($nameInside, PATHINFO_FILENAME);
                    $ext = pathinfo($nameInside, PATHINFO_EXTENSION);
                    $nameInside = $base . '_' . $document->id . ($ext ? '.' . $ext : '');
                }

                $zip->addFromString($nameInside, Storage::disk($disk)->get($path));
                $addedFiles++;
            }
        }

        $zip->close();

        if ($addedFiles === 0) {
            @unlink($zipPath);
            return back()->with('warning', $mode === 'curriculum_only'
                ? 'No se encontraron CV cargados para los postulantes incluidos en el alcance seleccionado.'
                : 'No se encontraron documentos aprobados para los postulantes incluidos en el alcance seleccionado.');
        }

        $downloadName = $this->offerZipBaseName($oferta)
            . ($mode === 'curriculum_only' ? '-cvs' : '')
            . '.zip';

        return response()->download($zipPath, $downloadName)->deleteFileAfterSend(true);
    }

    protected function resolvePostulacionesForExport(BolsaTrabajoOferta $oferta, string $scope = 'stage')
    {
        $postulaciones = $oferta->postulaciones
            ->filter(fn (BolsaTrabajoPostulacion $postulacion) => (bool) $postulacion->user)
            ->values();

        if ($scope === 'all') {
            return $postulaciones;
        }

        return match ($oferta->currentEtapaKey()) {
            BolsaTrabajoOferta::ETAPA_RECEPCION_ANTECEDENTES => $postulaciones,
            BolsaTrabajoOferta::ETAPA_EVALUACION_ANTECEDENTES,
            BolsaTrabajoOferta::ETAPA_ENTREVISTA_PSICOLABORAL,
            BolsaTrabajoOferta::ETAPA_ENTREVISTA_FINAL => $postulaciones
                ->filter(fn (BolsaTrabajoPostulacion $postulacion) => $postulacion->canParticipateInStageSelection())
                ->values(),
            BolsaTrabajoOferta::ETAPA_CERRADO => $postulaciones
                ->filter(function (BolsaTrabajoPostulacion $postulacion) use ($oferta) {
                    if ($oferta->selected_postulacion_id) {
                        return (int) $postulacion->id === (int) $oferta->selected_postulacion_id;
                    }

                    return (string) $postulacion->estado === 'seleccionado';
                })
                ->values(),
            BolsaTrabajoOferta::ETAPA_DESIERTO => $postulaciones,
            default => $postulaciones,
        };
    }

    protected function approvedDocumentsForZip($user)
    {
        return $user->documents
            ->filter(fn ($document) => (string) $document->status === 'approved' && !empty($document->path))
            ->sortBy(fn ($document) => sprintf('%05d-%010d', (int) $document->document_type_id, (int) $document->id))
            ->values();
    }

    protected function curriculumDocumentsForZip($user, int $curriculumTypeId)
    {
        if ($curriculumTypeId <= 0) {
            return collect();
        }

        $documents = $user->documents
            ->filter(function ($document) use ($curriculumTypeId) {
                return (int) $document->document_type_id === $curriculumTypeId && !empty($document->path);
            })
            ->sortByDesc('updated_at')
            ->values();

        return $documents->take(1);
    }

    protected function offerZipBaseName(BolsaTrabajoOferta $oferta): string
    {
        $rbd = $oferta->rbds_display ?: 'sin-rbd';
        $estamento = Str::slug((string) $oferta->estamento_label, '-');
        $area = Str::slug((string) optional($oferta->areaDesempeno)->nombre ?: 'sin-area', '-');
        $calidad = Str::slug((string) $oferta->calidad_contractual_label, '-');
        $horas = (string) ((int) $oferta->cantidad_horas);

        return $rbd . '-' . $estamento . '-' . $area . '-' . $calidad . '-' . $horas;
    }

    protected function candidateFolderName($user): string
    {
        $rut = strtoupper(preg_replace('/[^0-9Kk]/', '', (string) ($user->rut ?? optional($user->postulantProfile)->rut ?? '')));
        if ($rut === '') {
            return 'USER_' . $user->id;
        }

        $dv = substr($rut, -1);
        $body = substr($rut, 0, -1);
        if ($body === '') {
            return 'USER_' . $user->id;
        }

        return $body . '_' . strtoupper($dv);
    }

    protected function formViewData(BolsaTrabajoOferta $item): array
    {
        return [
            'item' => $item,
            'establecimientos' => Establecimiento::query()
                ->orderBy('nombre_establecimiento')
                ->get(['id', 'rbd', 'nombre_establecimiento', 'comuna']),
            'comunas' => $this->availableComunas(),
            'areasGrouped' => AreaDesempeno::query()
                ->activos()
                ->orderBy('estamento')
                ->orderBy('nombre')
                ->get()
                ->groupBy(function ($area) {
                    return $area->estamento === 'asistente' ? 'Asistente' : 'Docente';
                }),
            'calidadesContractuales' => [
                'reemplazo' => 'Reemplazo',
                'contrata' => 'Contrata',
                'plazo_fijo' => 'Plazo Fijo',
            ],
        ];
    }

    protected function validatedData(Request $request): array
    {
        $data = $request->validate([
            'establecimientos_ids' => ['required', 'array', 'min:1'],
            'establecimientos_ids.*' => ['integer', 'exists:establecimientos,id'],
            'comuna' => ['required', 'string', 'max:120'],
            'estamento' => ['required', 'in:docente,asistente'],
            'area_desempeno_id' => ['required', 'integer', 'exists:areas_desempeno,id'],
            'calidad_contractual' => ['required', 'in:reemplazo,contrata,plazo_fijo'],
            'cantidad_horas' => ['required', 'integer', 'min:1', 'max:60'],
            'remuneracion_bruta' => ['required', 'integer', 'min:1', 'max:999999999'],
            'inicio_trabajo_aproximado' => ['required', 'date'],
            'fecha_inicio_postulaciones' => ['required', 'date'],
            'hora_inicio_postulaciones' => ['required', 'date_format:H:i'],
            'fecha_termino_postulaciones' => ['required', 'date'],
            'hora_termino_postulaciones' => ['required', 'date_format:H:i'],
            'correo_contacto' => ['required', 'email:rfc', 'max:190'],
            'bases_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:102400'],
        ], [
            'establecimientos_ids.required' => 'Debe seleccionar al menos un establecimiento.',
            'establecimientos_ids.min' => 'Debe seleccionar al menos un establecimiento.',
            'area_desempeno_id.required' => 'Debe seleccionar un área de desempeño.',
            'remuneracion_bruta.required' => 'Debe ingresar la remuneración bruta.',
            'remuneracion_bruta.integer' => 'La remuneración bruta debe ingresarse como número entero.',
            'remuneracion_bruta.min' => 'La remuneración bruta debe ser mayor a cero.',
            'correo_contacto.email' => 'Debe ingresar un correo de contacto válido.',
            'bases_pdf.mimes' => 'Las bases deben estar en formato PDF.',
            'bases_pdf.max' => 'Las bases no pueden superar los 100 MB.',
        ]);

        $establecimientosIds = collect($data['establecimientos_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($establecimientosIds->isEmpty()) {
            throw ValidationException::withMessages([
                'establecimientos_ids' => 'Debe seleccionar al menos un establecimiento.',
            ]);
        }

        $data['establecimientos_ids'] = $establecimientosIds->all();
        $data['establecimiento_id'] = (int) $establecimientosIds->first();

        $area = AreaDesempeno::query()->find($data['area_desempeno_id']);
        if (!$area || (string) $area->estamento !== (string) $data['estamento']) {
            throw ValidationException::withMessages([
                'area_desempeno_id' => 'El área de desempeño seleccionada no corresponde al estamento indicado.',
            ]);
        }

        $tz = BolsaTrabajoOferta::portalTimezone();
        $inicio = Carbon::createFromFormat('Y-m-d H:i', $data['fecha_inicio_postulaciones'] . ' ' . $data['hora_inicio_postulaciones'], $tz);
        $termino = Carbon::createFromFormat('Y-m-d H:i', $data['fecha_termino_postulaciones'] . ' ' . $data['hora_termino_postulaciones'], $tz);

        if (!$inicio || !$termino || $termino->lt($inicio)) {
            throw ValidationException::withMessages([
                'fecha_termino_postulaciones' => 'La fecha/hora de término de postulaciones debe ser igual o posterior al inicio.',
            ]);
        }

        return $data;
    }

    protected function storeBasesPdf(BolsaTrabajoOferta $item, $file): string
    {
        $dir = 'private/bolsa-trabajo/ofertas/' . $item->id;
        Storage::disk('local')->makeDirectory($dir);

        return $file->storeAs($dir, 'bases-oferta-' . $item->id . '.pdf', 'local');
    }

    protected function deleteBasesPdf(BolsaTrabajoOferta $item): void
    {
        if ($item->bases_pdf_path && Storage::disk('local')->exists($item->bases_pdf_path)) {
            Storage::disk('local')->delete($item->bases_pdf_path);
        }
    }

    protected function authorizeGestionBolsa()
    {
        $user = Auth::user();
        abort_unless($user, 403);
        $activeRole = method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null;
        abort_unless(in_array($activeRole, ['admin', 'funcionario_slep'], true), 403);
        abort_unless($user->canModule('gestion.bolsa-trabajo', $activeRole), 403);

        return $user;
    }

    protected function availableComunas(): array
    {
        $comunas = (array) config('chile.comunas_postulacion_permitidas', []);
        natcasesort($comunas);
        return array_values($comunas);
    }

    protected function notifyComunicaciones(BolsaTrabajoOferta $item, string $accion): ?string
    {
        try {
            NotificationAudit::sendMail('comunicaciones@slepandaliencosta.gob.cl', new BolsaTrabajoDifusionRequestMail($item, $accion), [
                'event_key' => 'gestion.bolsa-trabajo.difusion',
                'description' => 'Solicitud de difusión de oferta laboral',
                'subject' => 'Solicitud de difusión de oferta laboral — Bolsa de Trabajo #' . $item->id,
                'related' => $item,
            ]);
        } catch (\Throwable $e) {
            return 'La oferta se guardó, pero no fue posible enviar el correo a Comunicaciones: ' . $e->getMessage();
        }

        return null;
    }

    /**
     * @return array<int,string>
     */
    protected function notifyPostulacionByStageResult(BolsaTrabajoPostulacion $postulacion, BolsaTrabajoOferta $oferta, string $tipo, array $context = []): array
    {
        $user = $postulacion->user;
        if (!$user) {
            return [];
        }

        $emails = collect([
            $user->email,
            optional($user->postulantProfile)->email_contacto,
            optional($postulacion->postulantProfile)->email_contacto,
        ])->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter()
            ->unique()
            ->values();

        if ($emails->isEmpty()) {
            return [];
        }

        $warnings = [];
        $recipientName = $user->display_name ?? $user->full_name ?? 'postulante';

        foreach ($emails as $email) {
            try {
                $mail = new BolsaTrabajoEtapaPostulacionMail($oferta->fresh(['establecimiento', 'areaDesempeno', 'selectedPostulacion.user']), $tipo, $recipientName, $context);
                NotificationAudit::sendMail($email, $mail, [
                    'event_key' => 'gestion.bolsa-trabajo.etapas',
                    'description' => 'Notificación de avance/cierre de oferta laboral',
                    'subject' => $this->stageMailSubject($tipo, $context),
                    'related' => $oferta,
                    'recipient_name' => $recipientName,
                ]);
            } catch (\Throwable $e) {
                $warnings[] = 'No fue posible notificar a ' . $email . ': ' . $e->getMessage();
            }
        }

        return $warnings;
    }

    /**
     * @param array<int,string> $warnings
     */

    protected function stageMailSubject(string $tipo, array $context = []): string
    {
        return match ($tipo) {
            'avance' => 'Resultado de postulación — Avance a ' . (string) ($context['target_stage_label'] ?? 'la siguiente etapa'),
            'no_avanza' => 'Resultado de postulación — No continúa en el proceso',
            'desierto' => 'Cierre de proceso — Oferta laboral declarada desierta',
            'cerrado' => 'Cierre de proceso — Oferta laboral finalizada',
            default => 'Actualización de postulación',
        };
    }

    protected function warningsToString(array $warnings): string
    {
        $warnings = array_values(array_unique(array_filter($warnings)));
        if (empty($warnings)) {
            return '';
        }

        if (count($warnings) === 1) {
            return ' Advertencia: ' . $warnings[0];
        }

        return ' Advertencias: ' . implode(' | ', array_slice($warnings, 0, 3));
    }
}
