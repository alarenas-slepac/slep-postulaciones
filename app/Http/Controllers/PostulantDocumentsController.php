<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use App\Models\UserDocument;
use App\Support\DocumentRules;
use App\Support\ProfileChecklist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PostulantDocumentsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $user->loadMissing(['postulantProfile', 'documents' => function ($q) {
            $q->with('type')->latest('updated_at');
        }]);

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

        $documentTypes = DocumentRules::visibleTypesFromCatalog($user, $catalog);
        $requiredTypeIds = DocumentRules::requiredTypesFromCatalog($user, $catalog)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->flip();
        $byTypeId = $user->documents->keyBy('document_type_id');

        return view('postulant.documents.index', compact('documentTypes', 'requiredTypeIds', 'byTypeId', 'user'));
    }

    public function store(Request $request, DocumentType $type)
    {
        $user = $request->user();
        $user->loadMissing(['postulantProfile']);

        // Bloqueo por perfil incompleto
        $check = \App\Support\ProfileChecklist::compute($user);
        if (empty($check['ok']) || $check['ok'] !== true) {
            return back()->with('warning', 'Completa tu perfil antes de subir documentos.');
        }

        // Solo permitir subir si este tipo está disponible para el perfil actual.
        if (!(new DocumentType($type->toArray()))->isVisibleForUser($user)) {
            return back()->withErrors(['file' => 'Este documento no está disponible para tu perfil.']);
        }

        // Asegura coherencia con la ruta
        $request->merge(['type_id' => $type->id]);

        // Validación: SOLO PDF (10 MB máx)
        $request->validate([
            'type_id' => ['required', 'integer', 'exists:document_types,id'],
            'file'    => [
                'required',
                'file',
                'mimetypes:application/pdf',
                'mimes:pdf',
                'max:10240',
            ],
        ], [
            'file.mimetypes' => 'Solo se permiten archivos PDF.',
            'file.mimes'     => 'Solo se permiten archivos PDF.',
            'file.max'       => 'El PDF no puede superar 10 MB.',
        ]);

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
        $dir      = "documents/{$user->id}/{$type->slug}";

        // ====== Cambios clave: BORRAR ANTES y luego GUARDAR ======

        // upsert (único por user+type) ANTES de guardar para poder borrar el anterior
        $doc = UserDocument::firstOrNew([
            'user_id'          => $user->id,
            'document_type_id' => $type->id,
        ]);

        // Si existe archivo anterior, BÓRRALO ANTES (evita borrar el nuevo si el nombre coincide)
        if ($doc->exists && $doc->path && Storage::disk('public')->exists($doc->path)) {
            Storage::disk('public')->delete($doc->path);
        }

        // Ahora sí, guardar el nuevo archivo
        $path = $f->storeAs($dir, $filename, 'public');

        // Metadatos/estado
        $doc->fill([
            'path'             => $path,
            'original_name'    => $f->getClientOriginalName(),
            'mime'             => 'application/pdf',
            'size'             => $f->getSize(),
            'status'           => 'pending',
            'reviewer_comment' => null,
            'reviewed_by'      => null,
            'reviewed_at'      => null,
        ])->save();

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
}
