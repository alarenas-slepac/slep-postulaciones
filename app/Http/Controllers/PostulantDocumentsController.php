<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use App\Models\BolsaTrabajoOferta;
use App\Models\UserDocument;
use App\Services\BolsaTrabajoPostulacionService;
use App\Services\UserDocumentReplacement;
use App\Support\DocumentRules;
use App\Support\ProfileChecklist;
use App\Support\PostulantJobProfiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PostulantDocumentsController extends Controller
{
    public function index(Request $request, BolsaTrabajoPostulacionService $offers)
    {
        $user = $request->user();
        $user->loadMissing('postulantProfile');

        // Bloqueo por perfil incompleto
        $check = ProfileChecklist::compute($user);
        if (empty($check['ok']) || $check['ok'] !== true) {
            return redirect()->route('postulant.profile.edit')
                ->with('warning', 'Debes completar tu perfil antes de subir documentos.')
                ->with('checklist', $check);
        }

        $catalog = DocumentType::query()
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        $context = $this->offerContext($request, $offers);
        $visibility = $this->availableTypes($request, $catalog, $context);
        $byTypeId = $user->documents()->latest('updated_at')->get()->groupBy('document_type_id');
        $requestedType = $request->integer('tipo');
        $documentTypes = $catalog->filter(fn ($type) => ($visibility['visible']->has($type->id) || $byTypeId->has($type->id))
            && (! $requestedType || (int) $type->id === $requestedType));
        $requiredTypeIds = $visibility['required'];
        $visibleTypeIds = $visibility['visible'];

        return view('postulant.documents.index', compact('documentTypes', 'requiredTypeIds', 'visibleTypeIds', 'byTypeId', 'context', 'requestedType'));
    }

    public function store(Request $request, DocumentType $type, BolsaTrabajoPostulacionService $offers)
    {
        $user = $request->user();
        $user->loadMissing(['postulantProfile']);

        // Bloqueo por perfil incompleto
        $check = \App\Support\ProfileChecklist::compute($user);
        if (empty($check['ok']) || $check['ok'] !== true) {
            return back()->with('warning', 'Completa tu perfil antes de subir documentos.');
        }

        $context = $this->offerContext($request, $offers);
        $types = $this->availableTypes($request, collect([$type]), $context);
        if (! $types['visible']->has($type->id)) {
            return back()->withErrors(['file' => 'Este documento no está disponible para tu perfil.']);
        }

        // Asegura coherencia con la ruta
        $request->merge(['type_id' => $type->id]);

        // Validación: SOLO PDF (10 MB máx)
        $data = $request->validate([
            'type_id' => ['required', 'integer', 'exists:document_types,id'],
            'file'    => [
                'required',
                'file',
                'mimetypes:application/pdf',
                'mimes:pdf',
                'max:10240',
            ],
            'library_document_id' => ['nullable', 'integer', 'min:1'],
            'additional' => ['nullable', 'boolean'],
            'credential_label' => ['nullable', 'string', 'max:150', 'required_if:additional,1'],
            'issued_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.BolsaTrabajoOferta::portalNow()->toDateString()],
        ], [
            'file.mimetypes' => 'Solo se permiten archivos PDF.',
            'file.mimes'     => 'Solo se permiten archivos PDF.',
            'file.max'       => 'El PDF no puede superar 10 MB.',
        ]);
        $additional = $request->boolean('additional');
        $documentId = isset($data['library_document_id']) ? (int) $data['library_document_id'] : null;
        if ($additional && (! $type->supportsMultipleCredentials() || $documentId !== null)) {
            return back()->withErrors(['file' => 'Este tipo de documento admite un solo archivo vigente.']);
        }

        $f = $request->file('file');

        // Guardia extra (MIME real + extensión)
        $ext  = strtolower($f->getClientOriginalExtension() ?: '');
        $mime = strtolower($f->getMimeType() ?: '');
        if ($ext !== 'pdf' || strpos($mime, 'pdf') === false) {
            return back()->withErrors(['file' => 'El archivo debe ser un PDF válido.'])->withInput();
        }

        // ==== Construcción del nombre de archivo ====
        // 1) RUT
        $rutRaw = (string)($user->rut ?? optional($user->postulantProfile)->rut ?? $user->id);
        $rutRaw = str_replace(['.', '-', ' '], '', $rutRaw);
        $rut    = strtoupper(preg_replace('/[^0-9K]/', '', $rutRaw));
        if ($rut === '') {
            $rut = (string)$user->id;
        }

        // 2) Nombre completo
        $nameRaw = (string)(
            $user->display_name
            ?? ($user->name ?? null)
            ?? trim(implode(' ', array_filter([
                $user->first_name ?? $user->nombres ?? null,
                $user->last_name
                    ?? $user->apellidos
                    ?? trim(($user->apellido_paterno ?? '') . ' ' . ($user->apellido_materno ?? '')),
            ])))
            ?: 'postulante'
        );
        $nameSlug = Str::slug($nameRaw, '-');

        // 3) Nombre del tipo
        $labelRaw  = (string)($type->label ?? $type->slug ?? 'documento');
        $labelSlug = Str::slug($labelRaw, '-');

        // 4) Control de longitud
        $rutPart   = mb_substr($rut, 0, 20);
        $namePart  = mb_substr($nameSlug, 0, 80);
        $labelPart = mb_substr($labelSlug, 0, 50);

        // 5) Nombre final
        $filename = "{$rutPart}_{$namePart}_{$labelPart}.pdf";
        app(UserDocumentReplacement::class)->replace(
            $user, $type, $f, $filename, $documentId, $data['credential_label'] ?? null, $data['issued_on'] ?? null, $additional,
        );

        if ($context) {
            return redirect()->route('postulant.ofertas-laborales.show', ['oferta' => $context['offer'], 'cargo' => $context['key']])
                ->with('status', 'Documento guardado y pendiente de revisión.');
        }

        return back()->with('status', 'Documento subido correctamente. Queda pendiente de revisión.');
    }

    public function downloadTemplate(DocumentType $type)
    {
        $path = ltrim($type->template_path ?? '', '/'); // templates/xxx.pdf
        abort_if($path === '', 404);

        $headers = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];

        // Las plantillas incluidas con el sistema tienen prioridad sobre las
        // copias históricas que puedan permanecer en el almacenamiento público.
        $bundledPath = resource_path($path);
        if (is_file($bundledPath)) {
            return response()->download($bundledPath, basename($path), $headers);
        }

        $disk = Storage::disk('public'); // storage/app/public

        if (! $disk->exists($path)) {
            // útil para depurar en logs
            \Log::error('Template no existe', ['fullpath' => $disk->path($path), 'path' => $path]);
            abort(404);
        }

        return $disk->download($path, basename($path), $headers);
    }

    public function download(UserDocument $document)
    {
        $this->authorize('view', $document); // o la policy que uses

        $disk = $document->disk ?? 'public';
        $path = $document->path;

        abort_unless($path && Storage::disk($disk)->exists($path), 404);

        $downloadAs = basename($path); // <- usa el nombre físico renombrado
        return Storage::disk($disk)->download($path, $downloadAs);
    }

    public function storeIssueDate(Request $request, UserDocument $document, BolsaTrabajoPostulacionService $offers)
    {
        $this->authorize('update', $document);
        $data = $request->validate([
            'issued_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.BolsaTrabajoOferta::portalNow()->toDateString()],
        ]);
        $user = $request->user();
        $connection = $user->getConnection();
        $connection->transaction(function () use ($user, $document, $data, $connection) {
            $user->newQuery()->lockForUpdate()->findOrFail($user->id);
            $current = $user->documents()->lockForUpdate()->findOrFail($document->id);
            if ($current->issued_on !== null || $current->status === 'rejected'
                || ! $current->path || ! Storage::disk($current->disk ?? 'public')->exists($current->path)) {
                throw ValidationException::withMessages(['issued_on' => 'No se puede registrar la fecha de emisión de este archivo.']);
            }
            $connection->table('user_documents')->where('id', $current->id)->update(['issued_on' => $data['issued_on']]);
        });
        $context = $this->offerContext($request, $offers);

        return $context
            ? redirect()->route('postulant.ofertas-laborales.show', ['oferta' => $context['offer'], 'cargo' => $context['key']])->with('status', 'Fecha de emisión registrada.')
            : back()->with('status', 'Fecha de emisión registrada.');
    }

    private function offerContext(Request $request, BolsaTrabajoPostulacionService $offers): ?array
    {
        $user = $request->user();
        $offerId = $request->integer('oferta');
        $key = $request->input('cargo');
        $role = $user->activeRoleName();
        if (! $offerId || ! is_string($key) || ! in_array($role, ['postulante', 'funcionario'], true)
            || ! $user->canModule('postulant.ofertas-laborales', $role)) {
            return null;
        }
        $offer = BolsaTrabajoOferta::find($offerId);
        $now = BolsaTrabajoOferta::portalNow();
        if (! $offer || ! $offer->isVisibleEnPortal($now) || ! $offer->isPostulacionAbierta($now)) {
            return null;
        }
        $profile = $offers->compatibleProfiles($user, $offer)->get($key);

        return $profile ? compact('offer', 'key') : null;
    }

    private function availableTypes(Request $request, $catalog, ?array $context): array
    {
        $user = $request->user();
        $profiles = PostulantJobProfiles::forUser($user);
        $required = $profiles->flatMap(fn ($profile) => DocumentRules::requiredTypesFromCatalog($user, $catalog, $profile)->pluck('id'));
        $visible = $profiles->flatMap(fn ($profile) => DocumentRules::visibleTypesFromCatalog($user, $catalog, $profile)->pluck('id'));
        $specific = collect(($context['offer'] ?? null)?->requisitos_documentales ?? [])->pluck('document_type_id');

        return ['required' => $required->merge($specific)->flip(), 'visible' => $visible->merge($specific)->flip()];
    }
}
