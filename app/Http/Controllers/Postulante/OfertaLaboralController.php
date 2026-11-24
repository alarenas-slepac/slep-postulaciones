<?php

namespace App\Http\Controllers\Postulante;

use App\Http\Controllers\Controller;
use App\Models\BolsaTrabajoOferta;
use App\Models\BolsaTrabajoPostulacion;
use App\Models\DocumentType;
use App\Services\BolsaTrabajoPostulacionService;
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

    public function index(BolsaTrabajoPostulacionService $service): View
    {
        $user = $this->authorizeOfertas();
        $now = BolsaTrabajoOferta::portalNow();
        $items = BolsaTrabajoOferta::query()
            ->visibleEnPortal($now)
            ->with(['establecimiento', 'areaDesempeno'])
            ->withCount('postulaciones')
            ->orderByDesc('fecha_inicio_postulaciones')
            ->orderByDesc('hora_inicio_postulaciones')
            ->paginate(12);
        $applied = BolsaTrabajoPostulacion::query()->where('user_id', $user->id)
            ->whereIn('bolsa_trabajo_oferta_id', $items->pluck('id'))->get()->keyBy('bolsa_trabajo_oferta_id');
        $compatible = $items->getCollection()->mapWithKeys(
            fn ($item) => [$item->id => $service->compatibleProfiles($user, $item)->isNotEmpty()]
        );

        return view('postulant.ofertas-laborales.index', compact('items', 'applied', 'compatible', 'now'));
    }

    public function show(Request $request, BolsaTrabajoOferta $oferta, BolsaTrabajoPostulacionService $service): View
    {
        $user = $this->authorizeOfertas();
        $now = BolsaTrabajoOferta::portalNow();
        $application = $oferta->postulaciones()->where('user_id', $user->id)->with('submittedDocuments.type')->first();
        abort_unless($application || $oferta->isVisibleEnPortal($now), 404);
        $oferta->loadMissing(['establecimiento', 'areaDesempeno'])->loadCount('postulaciones');
        $open = $oferta->isPostulacionAbierta($now);
        $profiles = $service->compatibleProfiles($user, $oferta);
        $requested = old('perfil_laboral', $request->query('cargo'));
        $profileKey = is_string($requested) && $profiles->has($requested) ? $requested : $profiles->keys()->first();
        $profile = $profiles->get($profileKey ?? '');
        $requirements = $profile && $open && ! $application ? $service->requirements(
            $user, $oferta, $profile,
            DocumentType::orderBy('sort_order')->orderBy('label')->get(),
            $user->documents()->with('type')->get(),
            (array) old('documentos', [])
        ) : collect();

        return view('postulant.ofertas-laborales.show', compact('oferta', 'application', 'open', 'profiles', 'profileKey', 'requirements'));
    }

    public function history(): View
    {
        $user = $this->authorizeOfertas();
        $applications = BolsaTrabajoPostulacion::query()->where('user_id', $user->id)
            ->with(['oferta.establecimiento', 'oferta.areaDesempeno'])->latest('id')->paginate(12);

        return view('postulant.ofertas-laborales.history', compact('applications'));
    }

    public function downloadBasesPdf(BolsaTrabajoOferta $oferta)
    {
        $user = $this->authorizeOfertas();
        abort_unless($oferta->isVisibleEnPortal(BolsaTrabajoOferta::portalNow())
            || $oferta->postulaciones()->where('user_id', $user->id)->exists(), 404);
        abort_unless($oferta->bases_pdf_path && Storage::disk('local')->exists($oferta->bases_pdf_path), 404);

        return Storage::disk('local')->download(
            $oferta->bases_pdf_path,
            $oferta->bases_pdf_original_name ?: ('bases-oferta-' . $oferta->id . '.pdf'),
            ['Content-Type' => 'application/pdf']
        );
    }

    public function store(Request $request, BolsaTrabajoOferta $oferta, BolsaTrabajoPostulacionService $service): RedirectResponse
    {
        $user = $this->authorizeOfertas();
        $data = $request->validate([
            'perfil_laboral' => ['required', 'string', 'regex:/^(principal|cargo-[1-9][0-9]*)$/', 'max:32'],
            'documentos' => ['nullable', 'array', 'max:150'],
            'documentos.*' => ['nullable', 'integer', 'min:1'],
        ]);

        $created = $service->submit($user, $oferta, $data['perfil_laboral'], $data['documentos'] ?? []);

        return redirect()->route('postulant.ofertas-laborales.show', $oferta)->with('status', $created
            ? 'Postulación registrada correctamente.' : 'Ya registraste tu postulación en esta oferta laboral.');
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

}
