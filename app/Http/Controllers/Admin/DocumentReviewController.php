<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\PdfBranding;
use ZipArchive;
use Illuminate\Support\Facades\Schema;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\UserDocument;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use App\Support\NotificationAudit;
use App\Mail\DocumentStatusChangedMail;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Support\DocumentRules;


class DocumentReviewController extends Controller
{
    /**
     * Vista de documentos (solo lectura) para roles de Gestión/Reemplazos.
     * Usa policies de "viewAny"/"view" (no "review").
     */
    public function forUserReadOnly(User $user)
    {
        $this->authorize('viewAny', UserDocument::class);

        $types = DocumentType::query()
            ->orderByRaw('sort_order IS NULL, sort_order ASC')
            ->orderBy('label')
            ->get();

        $docs = UserDocument::with(['type', 'reviewer'])
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('document_type_id');

        $freshSince = \Carbon\Carbon::now()->subHours(72);
        $visibleTypes = DocumentRules::visibleTypesFromCatalog($user, $types);
        $requiredIds = DocumentRules::requiredTypesFromCatalog($user, $types)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $items = [];
        foreach ($visibleTypes as $t) {
            $doc = $docs->get($t->id);

            $items[] = [
                'type'        => $t,
                'doc'         => $doc,
                'is_required' => in_array((int) $t->id, $requiredIds, true),
                'is_new'      => $doc
                    && $doc->status === 'pending'
                    && $doc->updated_at
                    && $doc->updated_at->gte($freshSince),
            ];
        }

        $total    = count($requiredIds);
        $uploaded = collect($items)->filter(fn($row) => $row['is_required'] && $row['doc'])->count();
        $approved = collect($items)->filter(fn($x) => $x['doc'] && $x['doc']->status === 'approved')->count();
        $newCount = collect($items)->filter(fn($x) => $x['is_new'])->count();
        $percent  = $total > 0 ? (int) round($uploaded * 100 / $total) : 0;

        return view('reemplazos.documents.user', compact(
            'user',
            'items',
            'total',
            'uploaded',
            'approved',
            'newCount',
            'percent'
        ));
    }

    public function downloadView(UserDocument $document)
    {
        $this->authorize('view', $document);

        $disk = $document->disk ?? 'public';
        $path = $document->path;
        abort_unless($path && Storage::disk($disk)->exists($path), 404);

        $downloadAs = basename($path);
        return Storage::disk($disk)->download($path, $downloadAs);
    }

    public function previewView(UserDocument $document)
    {
        $this->authorize('view', $document);

        $disk = $document->disk ?? 'public';
        $path = $document->path ?? $document->file_path;
        abort_unless($path && Storage::disk($disk)->exists($path), 404);

        $mime = $document->mime ?? $document->mime_type ?? Storage::disk($disk)->mimeType($path) ?? '';
        $isPdf = Str::of($mime)->lower()->contains('pdf') || Str::of($path)->lower()->endsWith('.pdf');
        abort_unless($isPdf, 415);

        return Storage::disk($disk)->response($path, $document->original_name ?? 'documento.pdf');
    }

    public function downloadApprovedZipView(User $user)
    {
        $this->authorize('viewAny', UserDocument::class);
        return $this->downloadApprovedZip($user);
    }

    public function exportProfileInlineView(User $user)
    {
        $this->authorize('viewAny', UserDocument::class);
        return $this->exportProfileInline($user);
    }

    public function exportProfilePdfView(User $user)
    {
        $this->authorize('viewAny', UserDocument::class);
        return $this->exportProfile($user);
    }

    public function index(Request $request)
    {
        $usersQuery = User::query();

        // Usuarios elegibles para revisión documental: postulante o funcionario
        $usersQuery->whereHas('roles', function ($rq) {
            $rq->whereIn('name', ['postulante', 'funcionario']);
        });

        // -------------------------
        // Filtro server-side (?q=)
        // -------------------------
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $rutQ = strtoupper(preg_replace('/[^0-9Kk]/', '', $q));
            $rutLike = $rutQ !== '' ? ('%' . $rutQ . '%') : null;

            $usersQuery->where(function ($w) use ($like, $rutLike) {
                // Email
                if (Schema::hasColumn('users', 'email')) {
                    $w->where('email', 'like', $like);
                }

                // RUT
                if ($rutLike && Schema::hasColumn('users', 'rut')) {
                    $w->orWhere('rut', 'like', $rutLike);
                }

                // Campos de nombre (esquemas soportados)
                foreach (['display_name', 'name', 'full_name', 'nombres', 'apellido_paterno', 'apellido_materno', 'first_name', 'last_name'] as $col) {
                    if (Schema::hasColumn('users', $col)) {
                        $w->orWhere($col, 'like', $like);
                    }
                }

                // Búsqueda por nombre completo concatenado (si aplica)
                $hasNombres = Schema::hasColumn('users', 'nombres');
                $hasApPat   = Schema::hasColumn('users', 'apellido_paterno');
                $hasApMat   = Schema::hasColumn('users', 'apellido_materno');

                if ($hasNombres || $hasApPat || $hasApMat) {
                    $w->orWhereRaw(
                        "CONCAT(COALESCE(nombres,''),' ',COALESCE(apellido_paterno,''),' ',COALESCE(apellido_materno,'')) LIKE ?",
                        [$like]
                    );
                }
            });
        }

        // -------------------------
        // Orden: pendientes primero (más antiguo)
        // -------------------------
        $usersQuery->addSelect([
            'oldest_pending_at' => UserDocument::query()
                ->selectRaw('MIN(updated_at)')
                ->whereColumn('user_id', 'users.id')
                ->where('status', 'pending'),
            'pending_count_all' => UserDocument::query()
                ->selectRaw('COUNT(*)')
                ->whereColumn('user_id', 'users.id')
                ->where('status', 'pending'),
        ]);

        $usersQuery->orderByRaw('oldest_pending_at IS NULL, oldest_pending_at ASC');

        // Fallback estable
        if (Schema::hasColumn('users', 'apellido_paterno') && Schema::hasColumn('users', 'nombres')) {
            $usersQuery->orderBy('apellido_paterno')->orderBy('apellido_materno')->orderBy('nombres');
        } elseif (Schema::hasColumn('users', 'name')) {
            $usersQuery->orderBy('name');
        } elseif (Schema::hasColumn('users', 'full_name')) {
            $usersQuery->orderBy('full_name');
        } elseif (Schema::hasColumn('users', 'last_name') && Schema::hasColumn('users', 'first_name')) {
            $usersQuery->orderBy('last_name')->orderBy('first_name');
        } else {
            $usersQuery->orderBy('email');
        }

        // Paginación
        $perPage = (int) $request->query('per_page', 25);
        if ($perPage < 10) $perPage = 10;
        if ($perPage > 100) $perPage = 100;

        $users = $usersQuery
            ->with(['postulantProfile', 'documents:id,user_id,document_type_id,status,path,original_name,updated_at'])
            ->paginate($perPage)
            ->withQueryString();

        $types = DocumentType::query()
            ->orderByRaw('sort_order IS NULL, sort_order ASC')
            ->orderBy('label')
            ->get();

        // Ventana para considerar "nuevo"
        $freshSince = Carbon::now()->subHours(72);

        // -------------------------
        // Contadores globales (pendientes)
        // -------------------------
        $postulantsIds = User::query()
            ->select('id');

        // Mismo criterio que el listado: postulante o funcionario
        $postulantsIds->whereHas('roles', function ($rq) {
            $rq->whereIn('name', ['postulante', 'funcionario']);
        });

        // ✅ Pendientes globales (todos los pendientes del sistema para postulantes)
        $globalPendingCount = UserDocument::query()
            ->where('status', 'pending')
            ->whereIn('user_id', $postulantsIds)
            ->count();

        // ✅ Pendientes nuevos globales (últimas 72 horas)
        $globalNew72hCount = UserDocument::query()
            ->where('status', 'pending')
            ->where('updated_at', '>=', $freshSince)
            ->whereIn('user_id', $postulantsIds)
            ->count();

        // -------------------------
        // Contadores globales por PERSONAS (postulantes)
        // -------------------------

        // Subquery de postulantes (IDs)
        $postulantsSub = User::query()->select('users.id');

        $postulantsSub->whereHas('roles', function ($rq) {
            $rq->whereIn('name', ['postulante', 'funcionario']);
        });

        // IMPORTANTÍSIMO: pasar a Query Builder base para usarlo en whereIn sin problemas
        $postulantsSub = $postulantsSub->toBase();

        // ✅ Personas con al menos 1 pendiente (global)
        $globalPendingPeopleCount = UserDocument::query()
            ->where('status', 'pending')
            ->whereIn('user_id', $postulantsSub)
            ->distinct()
            ->count('user_id');

        // ✅ Personas con al menos 1 pendiente nuevo (últimas 72h)
        $globalNew72hPeopleCount = UserDocument::query()
            ->where('status', 'pending')
            ->where('updated_at', '>=', $freshSince)
            ->whereIn('user_id', $postulantsSub)
            ->distinct()
            ->count('user_id');

        // ✅ Personas revisadas: tienen documentos pero ninguno pendiente
        // (equivale a: existe user_documents, y SUM(pending)=0)
        $reviewedPeopleSub = UserDocument::query()
            ->select('user_id')
            ->whereIn('user_id', $postulantsSub)
            ->groupBy('user_id')
            ->havingRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) = 0");

        $totalPostulants = User::query()
            ->whereHas('roles', function ($rq) {
                $rq->whereIn('name', ['postulante', 'funcionario']);
            })
            ->count();

        $globalReviewedPeopleCount = $totalPostulants - $globalPendingPeopleCount;
            
        // Mantener el paginator pero transformando cada item en "row"
        $rows = $users->through(function (User $u) use ($types, $freshSince) {
            $required    = $types->filter(fn($t) => (new DocumentType($t->toArray()))->isRequiredForUser($u))->values();
            $requiredIds = $required->pluck('id')->all();
            $visibleIds  = DocumentRules::visibleTypesFromCatalog($u, $types)->pluck('id')->all();

            $uploaded = $u->documents
                ? $u->documents->whereIn('document_type_id', $requiredIds)->count()
                : 0;

            $total   = max(0, count($requiredIds));
            $percent = $total > 0 ? (int) round($uploaded * 100 / $total) : 0;

            // Nuevos pendientes (últimas 72h)
            $newCount = $u->documents
                ? $u->documents
                    ->where('status', 'pending')
                    ->whereIn('document_type_id', $visibleIds)
                    ->filter(fn($d) => $d->updated_at && $d->updated_at->gte($freshSince))
                    ->count()
                : 0;

            // Pendientes totales (no revisados), sin ventana de tiempo
            $pendingCount = $u->documents
                ? $u->documents
                    ->where('status', 'pending')
                    ->whereIn('document_type_id', $visibleIds)
                    ->count()
                : 0;

            // Fecha del pendiente más antiguo (para mostrar)
            $oldestPendingAt = $u->documents
                ? $u->documents
                    ->where('status', 'pending')
                    ->whereIn('document_type_id', $visibleIds)
                    ->min('updated_at')
                : null;

            return [
                'user'              => $u,
                'uploaded'          => $uploaded,
                'total'             => $total,
                'percent'           => $percent,
                'new_count'         => $newCount,
                'pending_count'     => $pendingCount,
                'oldest_pending_at' => $oldestPendingAt,
            ];
        });

        return view('admin.documents.index', compact('rows', 'globalPendingCount', 'globalNew72hCount','globalPendingPeopleCount','globalNew72hPeopleCount','globalReviewedPeopleCount', 'q', 'perPage'));
	}


    public function show(UserDocument $document)
    {
        $this->authorize('review', $document);

        // Permite reutilizar esta vista desde distintas secciones (Admin vs Reemplazos)
        $routeNs = request()->routeIs('reemplazos.*') ? 'reemplazos' : 'admin';

        // -------------------------
        // Navegación: docs del mismo postulante
        // Orden por grilla/tipo de documento (DocumentType.sort_order) y luego por fecha.
        // -------------------------
        $prevDocument = null;
        $nextDocument = null;

        if ($document->user_id) {
            $ids = UserDocument::query()
                ->from('user_documents')
                ->leftJoin('document_types as dt', 'dt.id', '=', 'user_documents.document_type_id')
                ->where('user_documents.user_id', $document->user_id)
                // dt.sort_order nulls last
                ->orderByRaw('dt.sort_order IS NULL, dt.sort_order ASC')
                ->orderBy('dt.label', 'asc')
                ->orderBy('user_documents.updated_at', 'asc')
                ->orderBy('user_documents.id', 'asc')
                ->pluck('user_documents.id')
                ->all();

            $pos = array_search($document->id, $ids, true);
            if ($pos !== false) {
                if ($pos > 0) {
                    $prevId = $ids[$pos - 1] ?? null;
                    $prevDocument = $prevId ? UserDocument::find($prevId) : null;
                }
                if ($pos < (count($ids) - 1)) {
                    $nextId = $ids[$pos + 1] ?? null;
                    $nextDocument = $nextId ? UserDocument::find($nextId) : null;
                }
            }
        }

        // Tipos para el select (si la vista los usa)
        $types = DocumentType::query()
            ->orderByRaw('sort_order IS NULL, sort_order ASC')
            ->orderBy('label')
            ->get();

        // Listado de usuarios (tolerante al esquema)
        $usersQuery = User::query();
        if (Schema::hasColumn('users', 'name')) {
            $usersQuery->orderBy('name');
        } elseif (Schema::hasColumn('users', 'full_name')) {
            $usersQuery->orderBy('full_name');
        } elseif (Schema::hasColumn('users', 'last_name') && Schema::hasColumn('users', 'first_name')) {
            $usersQuery->orderBy('last_name')->orderBy('first_name');
        } else {
            $usersQuery->orderBy('email');
        }
        $users = $usersQuery->limit(300)->get();

        // Envolver el único documento en un paginador para que ->links() funcione
        $items = new LengthAwarePaginator(
            [$document],         // items
            1,                   // total
            15,                  // per page (cualquiera)
            1,                   // current page
            ['path' => request()->url(), 'query' => request()->query()]
        );

        $requestedBackUrl = (string) request('back_to', '');
        $safeBackUrl = $requestedBackUrl !== ''
            && (Str::startsWith($requestedBackUrl, url('/')) || Str::startsWith($requestedBackUrl, '/'));

        $backUrl = $safeBackUrl
            ? $requestedBackUrl
            : ($routeNs === 'reemplazos'
                ? route('reemplazos.documents.forUser', ['user' => $document->user_id, 'return_to' => request('return_to')])
                : route('admin.documents.forUser', ['user' => $document->user_id]));

        return view('admin.documents.review', compact(
            'document',
            'types',
            'users',
            'items',
            'prevDocument',
            'nextDocument',
            'routeNs',
            'backUrl'
        ));
    }


    public function download(UserDocument $document)
    {
        $this->authorize('review', $document);

        $disk = $document->disk ?? 'public';
        $path = $document->path;

        abort_unless($path && Storage::disk($disk)->exists($path), 404);

        $downloadAs = basename($path); // <- usa el nombre nuevo
        return Storage::disk($disk)->download($path, $downloadAs);
    }

    public function update(Request $request, UserDocument $document)
    {
        $this->authorize('review', $document);

        $prevStatus = $document->status;
        $prevReason = $document->reviewer_comment;

        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'approved', 'rejected'])],
            'reviewer_comment' => [
                'nullable',
                'string',
                'max:5000',
                Rule::requiredIf(fn() => $request->input('status') === 'rejected'),
            ],
        ], [
            'reviewer_comment.required' => 'Debes indicar el motivo cuando rechazas un documento.',
        ]);

        $document->fill([
            'status'           => $data['status'],
            'reviewer_comment' => $data['reviewer_comment'] ?? null,
            'reviewed_by'      => $request->user()->id,
            'reviewed_at'      => now(),
        ])->save();

        // Enviar correo SOLO si quedó aprobado o rechazado y/o cambió el motivo
        $shouldNotify = in_array($document->status, ['approved', 'rejected'], true)
            && ($prevStatus !== $document->status || ($document->status === 'rejected' && $prevReason !== $document->reviewer_comment));

        if ($shouldNotify && $document->user?->email) {
            try {
                NotificationAudit::sendMail($document->user->email, new DocumentStatusChangedMail($document), [
                    'event_key' => 'document.review.status_changed',
                    'description' => 'Notificación de cambio de estado de documento',
                    'subject' => 'Estado de documento: ' . ($document->type?->label ?? 'Documento') . ' — ' . ($document->status === 'approved' ? 'Aprobado' : ($document->status === 'rejected' ? 'Rechazado' : 'Pendiente')),
                    'related' => $document,
                    'notifiable' => $document->user,
                ]);
            } catch (\Throwable $e) {
                Log::warning('[MAIL] Falló envío estado documento', [
                    'document_id' => $document->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        return back()->with('status', 'Estado actualizado correctamente.');
    }

    public function preview(UserDocument $document)
    {
        // Requiere tu policy (admin/funcionario)
        $this->authorize('review', $document);

        // Ajusta si tu columna se llama distinto: e.g. file_path
        $disk = $document->disk ?? 'public';
        $path = $document->path ?? $document->file_path;

        abort_unless($path && Storage::disk($disk)->exists($path), 404);

        // Solo permitimos PDF aquí (seguridad/UX)
        $mime = $document->mime ?? $document->mime_type ?? Storage::disk($disk)->mimeType($path) ?? '';
        $isPdf = Str::of($mime)->lower()->contains('pdf') || Str::of($path)->lower()->endsWith('.pdf');
        abort_unless($isPdf, 415); // Unsupported Media Type

        // Mostramos inline (no descarga)
        return Storage::disk($disk)->response($path, $document->original_name ?? 'documento.pdf');
    }

    public function forUser(User $user)
    {
        // (Opcional) Restringe a admin/funcionario_slep
        if (!auth()->user()->hasAnyRole(['admin', 'funcionario_slep', 'coordinador_gdp', 'coordinador_uatp'])) {
            abort(403);
        }

        // Tipos requeridos para armar la grilla
        $types = DocumentType::query()
            ->orderByRaw('sort_order IS NULL, sort_order ASC')
            ->orderBy('label')
            ->get();

        // Trae documentos del usuario con relaciones necesarias:
        // - type (por si la vista lo usa)
        // - reviewer (quien aprobó/rechazó)  ✅
        $docs = UserDocument::with(['type', 'reviewer'])
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('document_type_id');

        // Ventana para marcar “Nuevo” (solo pendientes)
        $freshSince = \Carbon\Carbon::now()->subHours(72);
        $visibleTypes = DocumentRules::visibleTypesFromCatalog($user, $types);
        $requiredIds = DocumentRules::requiredTypesFromCatalog($user, $types)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $items = [];
        foreach ($visibleTypes as $t) {
            /** @var \App\Models\UserDocument|null $doc */
            $doc = $docs->get($t->id);

            $items[] = [
                'type'        => $t,
                'doc'         => $doc, // ->reviewer y ->reviewed_at vienen listos para la vista
                'is_required' => in_array((int) $t->id, $requiredIds, true),
                // “Nuevo” únicamente cuando está pendiente y reciente
                'is_new'      => $doc
                    && $doc->status === 'pending'
                    && $doc->updated_at
                    && $doc->updated_at->gte($freshSince),
            ];
        }

        // Métricas para cabecera / progreso
        $total    = count($requiredIds);
        $uploaded = collect($items)->filter(fn($row) => $row['is_required'] && $row['doc'])->count();
        $approved = collect($items)->filter(fn($x) => $x['doc'] && $x['doc']->status === 'approved')->count();
        $newCount = collect($items)->filter(fn($x) => $x['is_new'])->count();
        $percent  = $total > 0 ? (int) round($uploaded * 100 / $total) : 0;

        return view('admin.documents.user', compact(
            'user',
            'items',
            'total',
            'uploaded',
            'approved',
            'newCount',
            'percent'
        ));
    }


    public function downloadApprovedZip(User $user)
    {
        // (Opcional) si tienes policy específica:
        // $this->authorize('reviewAny', UserDocument::class);

        // Documentos aprobados del postulante
        $docs = UserDocument::where('user_id', $user->id)
            ->where('status', 'approved')
            ->get();

        if ($docs->isEmpty()) {
            return back()->with('warning', 'El postulante no tiene documentos aprobados para descargar.');
        }

        // Normaliza RUT para el nombre del ZIP
        $rutRaw = (string)($user->rut ?? optional($user->postulantProfile)->rut ?? $user->id);
        $rutRaw = str_replace(['.', '-', ' '], '', $rutRaw);
        $rut    = strtoupper(preg_replace('/[^0-9K]/', '', $rutRaw));
        if ($rut === '') $rut = (string) $user->id;

        $zipName = "{$rut}_DOCUMENTOS.zip";

        // Ruta temporal para crear el zip
        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0775, true);
        }
        $zipPath = $tmpDir . DIRECTORY_SEPARATOR . uniqid("docs_{$user->id}_", true) . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return back()->withErrors(['zip' => 'No se pudo crear el archivo ZIP.']);
        }

        foreach ($docs as $doc) {
            $disk = $doc->disk ?? 'public';
            $path = $doc->path;

            if (!$path || !Storage::disk($disk)->exists($path)) {
                continue;
            }

            // Nombre interno = el nombre que se estableció en el sistema (basename del path)
            $nameInside = basename($path);

            // Evitar duplicados dentro del zip
            if ($zip->locateName($nameInside) !== false) {
                $base = pathinfo($nameInside, PATHINFO_FILENAME);
                $ext  = pathinfo($nameInside, PATHINFO_EXTENSION);
                $nameInside = $base . '_' . $doc->id . ($ext ? ".{$ext}" : '');
            }

            $zip->addFromString($nameInside, Storage::disk($disk)->get($path));
        }

        $zip->close();

        return response()->download($zipPath, $zipName)->deleteFileAfterSend(true);
    }
    public function exportProfile(User $user)
    {
        // Autorización: admin o funcionario_slep
        if (!auth()->user()->hasAnyRole(['admin', 'funcionario_slep', 'coordinador_gdp', 'coordinador_uatp'])) {
            abort(403);
        }

        $user->load(['postulantProfile.comuna', 'communes']);
        $profile = $user->postulantProfile;

        if (!$profile) {
            return back()->withErrors(['general' => 'Este postulante aún no tiene perfil.']);
        }

        // --- Región legible ---
        $regiones   = config('chile.regiones', []);
        $regionName = $profile->region_code ? ($regiones[$profile->region_code] ?? $profile->region_code) : '';

        // --- RUT formateado (sin puntos, con guion) ---
        $rutRaw = (string)($user->rut ?? '');
        $rutSan = strtoupper(preg_replace('/[^0-9Kk]/', '', $rutRaw)); // deja solo dígitos y K
        if ($rutSan !== '') {
            $dv     = substr($rutSan, -1);
            $cuerpo = substr($rutSan, 0, -1);
            $rutFmt = $cuerpo . '-' . $dv; // ej: 12345678-K
        } else {
            $rutFmt = 'ID' . $user->id;
        }
        // --- Nacionalidad + bandera (data URI) ---
        $nationalities = collect(config('nacionalidades', []));
        $val   = (string) ($profile->nacionalidad ?? '');
        $match = $nationalities->first(function ($n) use ($val) {
            return strcasecmp($n['value'] ?? '', $val) === 0
                || strcasecmp($n['iso'] ?? '', $val) === 0
                || strcasecmp($n['abbr'] ?? '', $val) === 0
                || strcasecmp($n['name'] ?? '', $val) === 0;
        });
        $nacName = $match['name'] ?? ($val ?: null);
        $iso2    = strtolower($match['iso'] ?? $match['value'] ?? '');

        $flagDataUrl = null;
        if ($iso2) {
            $candidates = [
                public_path("flags-svg/{$iso2}.svg"),
                public_path("flags/{$iso2}.png"),
            ];
            foreach ($candidates as $p) {
                if (is_file($p)) {
                    $mime = str_ends_with(strtolower($p), '.svg') ? 'image/svg+xml' : 'image/png';
                    $flagDataUrl = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($p));
                    break;
                }
            }
        }
        // --- Foto miniatura absoluta (si existe) ---
        $fotoThumbAbs = null;
        if (!empty($profile->foto_thumb_path) && Storage::disk('public')->exists($profile->foto_thumb_path)) {
            $fotoThumbAbs = Storage::disk('public')->path($profile->foto_thumb_path);
        }

        // --- Marca/colores ---
        $brand = PdfBranding::profileBrand();

        $data = [
            'user'         => $user,
            'profile'      => $profile,
            'rutFmt'       => $rutFmt,
            'regionName'   => $regionName,
            'communes'     => $user->communes,
            'brand'        => $brand,
            'fotoThumbAbs' => $fotoThumbAbs,
            'generatedAt'  => now(),
            'nacName'     => $nacName,
            'flagDataUrl' => $flagDataUrl,
        ];

        $pdf = Pdf::loadView('pdf.profile', $data)
            ->setPaper('letter', 'portrait');

        // Nombre de archivo seguro: PERFIL_{RUT}.pdf (mantiene dígitos/K/guion)
        $fileRut  = preg_replace('/[^0-9Kk-]/', '', $rutFmt);
        $filename = "PERFIL_{$fileRut}.pdf";

        return $pdf->download($filename);
    }

    public function exportProfileInline(User $user)
    {
        if (!auth()->user()->hasAnyRole(['admin', 'funcionario_slep', 'coordinador_gdp', 'coordinador_uatp'])) {
            abort(403);
        }

        $user->load(['postulantProfile.comuna', 'communes']);
        $profile = $user->postulantProfile;

        if (!$profile) {
            return back()->withErrors(['general' => 'Este postulante aún no tiene perfil.']);
        }

        // --- Región legible ---
        $regiones   = config('chile.regiones', []);
        $regionName = $profile->region_code ? ($regiones[$profile->region_code] ?? $profile->region_code) : '';

        // --- RUT formateado (sin puntos, con guion) ---
        $rutRaw = (string)($user->rut ?? '');
        $rutSan = strtoupper(preg_replace('/[^0-9Kk]/', '', $rutRaw));
        if ($rutSan !== '') {
            $dv     = substr($rutSan, -1);
            $cuerpo = substr($rutSan, 0, -1);
            $rutFmt = $cuerpo . '-' . $dv;
        } else {
            $rutFmt = 'ID' . $user->id;
        }
        // --- Nacionalidad + bandera (data URI) ---
        $nationalities = collect(config('nacionalidades', []));
        $val   = (string) ($profile->nacionalidad ?? '');
        $match = $nationalities->first(function ($n) use ($val) {
            return strcasecmp($n['value'] ?? '', $val) === 0
                || strcasecmp($n['iso'] ?? '', $val) === 0
                || strcasecmp($n['abbr'] ?? '', $val) === 0
                || strcasecmp($n['name'] ?? '', $val) === 0;
        });
        $nacName = $match['name'] ?? ($val ?: null);
        $iso2    = strtolower($match['iso'] ?? $match['value'] ?? '');

        $flagDataUrl = null;
        if ($iso2) {
            $candidates = [
                public_path("flags-svg/{$iso2}.svg"),
                public_path("flags/{$iso2}.png"),
            ];
            foreach ($candidates as $p) {
                if (is_file($p)) {
                    $mime = str_ends_with(strtolower($p), '.svg') ? 'image/svg+xml' : 'image/png';
                    $flagDataUrl = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($p));
                    break;
                }
            }
        }

        // --- Foto miniatura absoluta (si existe) ---
        $fotoThumbAbs = null;
        if (!empty($profile->foto_thumb_path) && Storage::disk('public')->exists($profile->foto_thumb_path)) {
            $fotoThumbAbs = Storage::disk('public')->path($profile->foto_thumb_path);
        }

        $brand = PdfBranding::profileBrand();

        $data = [
            'user'         => $user,
            'profile'      => $profile,
            'rutFmt'       => $rutFmt,
            'regionName'   => $regionName,
            'communes'     => $user->communes,
            'brand'        => $brand,
            'fotoThumbAbs' => $fotoThumbAbs,
            'generatedAt'  => now(),
            'nacName'     => $nacName,
            'flagDataUrl' => $flagDataUrl,
        ];

        $pdf = Pdf::loadView('pdf.profile', $data)
            ->setPaper('letter', 'portrait');

        $fileRut  = preg_replace('/[^0-9Kk-]/', '', $rutFmt);
        $filename = "PERFIL_{$fileRut}.pdf";

        // Mostrar en el navegador (inline)
        return $pdf->stream($filename, ['Attachment' => false]);
    }
}
