<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AreaDesempeno;
use App\Models\Commune;
use App\Models\DocumentType;
use App\Models\PostulantProfile;
use App\Models\SolicitudReemplazo;
use App\Support\ProfileChecklist;
use App\Services\RestrictedRutService;
use Illuminate\Http\Request;

class PostulacionAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'ensure.role:admin']);
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'area_desempeno_id' => ['nullable', 'integer', 'exists:areas_desempeno,id'],
            'mencion' => ['nullable', 'string', 'max:255'],
            'commune_id' => ['nullable', 'integer', 'exists:communes,id'],
            'per_page' => ['nullable', 'integer', 'in:10,15,25,50'],
        ]);

        $filters = [
            'q' => trim((string) ($data['q'] ?? '')),
            'area_desempeno_id' => isset($data['area_desempeno_id']) ? (int) $data['area_desempeno_id'] : null,
            'mencion' => trim((string) ($data['mencion'] ?? '')),
            'commune_id' => isset($data['commune_id']) ? (int) $data['commune_id'] : null,
            'per_page' => (int) ($data['per_page'] ?? 15),
        ];

        $query = PostulantProfile::query()
            ->select('postulant_profiles.*')
            ->join('users', 'users.id', '=', 'postulant_profiles.user_id')
            ->with([
                'user' => fn ($uq) => $uq->with(['communes:id,name']),
                'areaDesempeno:id,nombre,estamento',
            ])
            ->whereHas('user', fn ($uq) => $uq->role('postulante'));

        if ($filters['q'] !== '') {
            $search = $filters['q'];
            $rutClean = strtoupper(preg_replace('/[^0-9Kk]/', '', $search));
            $tokens = array_values(array_filter(preg_split('/\s+/', $search) ?: []));

            $query->where(function ($qq) use ($search, $rutClean, $tokens) {
                if ($rutClean !== '') {
                    $qq->orWhere('users.rut', 'like', '%' . $rutClean . '%');
                }

                $qq->orWhere('users.email', 'like', '%' . $search . '%');

                foreach ($tokens as $token) {
                    $qq->where(function ($tt) use ($token) {
                        $tt->where('users.nombres', 'like', '%' . $token . '%')
                            ->orWhere('users.apellido_paterno', 'like', '%' . $token . '%')
                            ->orWhere('users.apellido_materno', 'like', '%' . $token . '%')
                            ->orWhereRaw("CONCAT_WS(' ', users.nombres, users.apellido_paterno, users.apellido_materno) LIKE ?", ['%' . $token . '%']);
                    });
                }
            });
        }

        if (!empty($filters['area_desempeno_id'])) {
            $query->where('postulant_profiles.area_desempeno_id', $filters['area_desempeno_id']);
        }

        if ($filters['mencion'] !== '') {
            $query->where('postulant_profiles.mencion', 'like', '%' . $filters['mencion'] . '%');
        }

        if (!empty($filters['commune_id'])) {
            $communeId = $filters['commune_id'];
            $query->whereHas('user.communes', fn ($cq) => $cq->where('communes.id', $communeId));
        }

        $profiles = $query
            ->orderBy('users.apellido_paterno')
            ->orderBy('users.apellido_materno')
            ->orderBy('users.nombres')
            ->paginate($filters['per_page'])
            ->withQueryString();

        $areas = AreaDesempeno::query()
            ->activos()
            ->orderBy('estamento')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'estamento']);

        $permitidas = config('chile.comunas_postulacion_permitidas', ['Coronel', 'Lota', 'San Pedro de la Paz', 'Santa Juana', 'Isla Santa María']);

        $communes = Commune::query()
            ->whereIn('name', $permitidas)
            ->orderBy('name')
            ->get(['id', 'name']);

        $menciones = PostulantProfile::query()
            ->whereNotNull('mencion')
            ->where('mencion', '<>', '')
            ->distinct()
            ->orderBy('mencion')
            ->limit(250)
            ->pluck('mencion');

        return view('admin.postulaciones.index', [
            'profiles' => $profiles,
            'filters' => $filters,
            'areas' => $areas,
            'communes' => $communes,
            'menciones' => $menciones,
        ]);
    }

    public function show(PostulantProfile $postulacione, RestrictedRutService $restrictedRutService)
    {
        $profile = $postulacione;
        $profile->load([
            'user' => fn ($uq) => $uq->with([
                'communes:id,name',
                'documents' => fn ($dq) => $dq->with(['type:id,label', 'reviewer:id,nombres,apellido_paterno,apellido_materno,email'])
                    ->orderByDesc('updated_at'),
            ]),
            'areaDesempeno:id,nombre,estamento',
            'comuna:id,name,region_code',
        ]);

        $user = $profile->user;
        abort_unless($user && $user->hasRole('postulante'), 404);

        $check = ProfileChecklist::compute($user);
        $regiones = config('chile.regiones', []);
        $regionName = $profile->region_code ? ($regiones[$profile->region_code] ?? $profile->region_code) : '—';

        $restriction = $restrictedRutService->restrictionContextForUser($user);

        $relatedSolicitudes = SolicitudReemplazo::query()
            ->with([
                'establecimiento:id,nombre_establecimiento,rbd,comuna',
                'areaDesempeno:id,nombre,estamento',
            ])
            ->where('postulant_profile_id', $profile->id)
            ->whereIn('estado', ['aceptada', 'cerrado'])
            ->orderByRaw("CASE WHEN estado = 'cerrado' THEN 0 ELSE 1 END")
            ->orderByDesc('orden_trabajo_creada_at')
            ->orderByDesc('fecha_inicio')
            ->get();

        $requiredTypes = DocumentType::query()
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->filter(fn (DocumentType $type) => $type->isRequiredForUser($user))
            ->values();

        $docs = $user->documents ?? collect();
        $latestDocsByType = $docs
            ->groupBy('document_type_id')
            ->map(fn ($group) => $group->sortByDesc(fn ($doc) => optional($doc->updated_at)?->getTimestamp() ?? 0)->first());

        $requiredDocItems = $requiredTypes->map(function (DocumentType $type) use ($latestDocsByType) {
            return [
                'type' => $type,
                'doc' => $latestDocsByType->get($type->id),
            ];
        });

        $docMetrics = [
            'total_required' => $requiredDocItems->count(),
            'uploaded' => $requiredDocItems->filter(fn ($item) => !empty($item['doc']))->count(),
            'reviewed' => $requiredDocItems->filter(function ($item) {
                $status = (string) optional($item['doc'])->status;
                return in_array($status, ['approved', 'rejected'], true) || !empty(optional($item['doc'])->reviewed_at);
            })->count(),
            'approved' => $requiredDocItems->filter(fn ($item) => (string) optional($item['doc'])->status === 'approved')->count(),
            'rejected' => $requiredDocItems->filter(fn ($item) => (string) optional($item['doc'])->status === 'rejected')->count(),
        ];
        $docMetrics['percent_uploaded'] = $docMetrics['total_required'] > 0
            ? (int) round($docMetrics['uploaded'] * 100 / $docMetrics['total_required'])
            : 0;

        return view('admin.postulaciones.show', [
            'profile' => $profile,
            'user' => $user,
            'check' => $check,
            'regionName' => $regionName,
            'rutFmt' => $this->formatRutChile($user->rut),
            'restriction' => $restriction,
            'relatedSolicitudes' => $relatedSolicitudes,
            'docMetrics' => $docMetrics,
            'requiredDocItems' => $requiredDocItems,
        ]);
    }

    private function formatRutChile(?string $rut): string
    {
        $rut = trim((string) $rut);
        if ($rut === '') {
            return '—';
        }

        $rut = strtoupper(preg_replace('/[^0-9Kk]/', '', $rut));
        if (strlen($rut) < 2) {
            return $rut;
        }

        return substr($rut, 0, -1) . '-' . substr($rut, -1);
    }
}
