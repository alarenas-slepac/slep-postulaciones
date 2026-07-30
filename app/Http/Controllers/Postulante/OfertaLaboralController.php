<?php

namespace App\Http\Controllers\Postulante;

use App\Http\Controllers\Controller;
use App\Models\BolsaTrabajoOferta;
use App\Models\BolsaTrabajoPostulacion;
use App\Models\DocumentType;
use App\Support\DocumentRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class OfertaLaboralController extends Controller
{
    public function publicIndex(Request $request): View
    {
        $items = BolsaTrabajoOferta::query()
            ->visibleEnPortal(BolsaTrabajoOferta::portalNow())
            ->with(['establecimiento', 'areaDesempeno'])
            ->withCount('postulaciones')
            ->orderByDesc('fecha_inicio_postulaciones')
            ->orderByDesc('hora_inicio_postulaciones')
            ->paginate(12);

        return view('public.ofertas-laborales.index', [
            'items' => $items,
            'isAuthenticatedPortalUser' => $this->isAuthenticatedPortalUser(),
        ]);
    }

    public function index(Request $request): View
    {
        $user = $this->authorizeOfertas();
        $user->loadMissing(['postulantProfile.areaDesempeno', 'documents.type']);

        $eligibility = $this->eligibilityForUser($user);
        $appliedIds = BolsaTrabajoPostulacion::query()
            ->where('user_id', $user->id)
            ->pluck('bolsa_trabajo_oferta_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $items = BolsaTrabajoOferta::query()
            ->visibleEnPortal(BolsaTrabajoOferta::portalNow())
            ->with(['establecimiento', 'areaDesempeno'])
            ->withCount('postulaciones')
            ->orderByDesc('fecha_inicio_postulaciones')
            ->orderByDesc('hora_inicio_postulaciones')
            ->paginate(12);

        return view('postulant.ofertas-laborales.index', [
            'items' => $items,
            'eligibility' => $eligibility,
            'appliedIds' => $appliedIds,
            'user' => $user,
        ]);
    }

    public function downloadBasesPdf(BolsaTrabajoOferta $oferta)
    {
        $this->authorizeOfertas();
        abort_unless($oferta->isVisibleEnPortal(BolsaTrabajoOferta::portalNow()), 404);
        abort_unless($oferta->bases_pdf_path && Storage::disk('local')->exists($oferta->bases_pdf_path), 404);

        return Storage::disk('local')->download(
            $oferta->bases_pdf_path,
            $oferta->bases_pdf_original_name ?: ('bases-oferta-' . $oferta->id . '.pdf'),
            ['Content-Type' => 'application/pdf']
        );
    }

    public function store(Request $request, BolsaTrabajoOferta $oferta): RedirectResponse
    {
        $user = $this->authorizeOfertas();
        $user->loadMissing(['postulantProfile', 'documents.type']);

        if (!$oferta->isVisibleEnPortal(BolsaTrabajoOferta::portalNow())) {
            return back()->with('error', 'La oferta laboral aún no inicia su periodo de postulación.');
        }

        if (!$oferta->isPostulacionAbierta(BolsaTrabajoOferta::portalNow())) {
            return back()->with('error', 'La oferta laboral ya no se encuentra disponible para postular.');
        }

        $eligibility = $this->eligibilityForUser($user);
        if (!$eligibility['eligible']) {
            return back()->with('error', $eligibility['message']);
        }

        $exists = BolsaTrabajoPostulacion::query()
            ->where('bolsa_trabajo_oferta_id', $oferta->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($exists) {
            return back()->with('status', 'Ya registraste tu postulación en esta oferta laboral.');
        }

        BolsaTrabajoPostulacion::create([
            'bolsa_trabajo_oferta_id' => $oferta->id,
            'user_id' => $user->id,
            'postulant_profile_id' => $user->postulantProfile?->id,
            'estado' => 'postulado',
        ]);

        return back()->with('status', 'Postulación registrada correctamente en la oferta laboral.');
    }

    protected function authorizeOfertas()
    {
        $user = Auth::user();
        abort_unless($user, 403);
        $activeRole = method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null;
        abort_unless(in_array($activeRole, ['postulante', 'funcionario'], true), 403);
        abort_unless($user->canModule('postulant.ofertas-laborales', $activeRole), 403);

        return $user;
    }

    protected function isAuthenticatedPortalUser(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        $activeRole = method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null;

        return in_array($activeRole, ['postulante', 'funcionario'], true)
            && $user->canModule('postulant.ofertas-laborales', $activeRole);
    }

    protected function eligibilityForUser($user): array
    {
        $profile = $user->postulantProfile;
        if (!$profile || blank($profile->estamento)) {
            return [
                'eligible' => false,
                'message' => 'No puedes postular todavía. Debes completar tu perfil básico para determinar los documentos requeridos.',
                'missing_docs' => [],
                'rejected_docs' => [],
            ];
        }

        $types = DocumentType::query()->orderBy('sort_order')->orderBy('label')->get();
        $required = DocumentRules::requiredTypesFromCatalog($user, $types);
        $requiredIds = $required->pluck('id')->map(fn ($id) => (int) $id)->all();

        $docs = $user->documents ? $user->documents->whereIn('document_type_id', $requiredIds) : collect();
        $docsWithFile = $docs->filter(fn ($doc) => filled($doc->path));
        $uploadedIds = $docsWithFile->pluck('document_type_id')->map(fn ($id) => (int) $id)->flip();

        $missingDocs = $required
            ->filter(fn ($type) => !isset($uploadedIds[(int) $type->id]))
            ->pluck('label')
            ->values()
            ->all();

        $rejectedDocs = $docsWithFile
            ->filter(fn ($doc) => (string) $doc->status === 'rejected')
            ->map(fn ($doc) => (string) optional($doc->type)->label)
            ->filter()
            ->values()
            ->all();

        if (!empty($missingDocs)) {
            return [
                'eligible' => false,
                'message' => 'No puedes postular todavía. Debes cargar todos tus documentos requeridos.',
                'missing_docs' => $missingDocs,
                'rejected_docs' => $rejectedDocs,
            ];
        }

        if (!empty($rejectedDocs)) {
            return [
                'eligible' => false,
                'message' => 'No puedes postular todavía. Tienes documentos rechazados que deben corregirse y volver a cargarse.',
                'missing_docs' => $missingDocs,
                'rejected_docs' => $rejectedDocs,
            ];
        }

        return [
            'eligible' => true,
            'message' => 'Puedes postular a las ofertas laborales publicadas. Cumples con la carga documental requerida.',
            'missing_docs' => [],
            'rejected_docs' => [],
        ];
    }
}
