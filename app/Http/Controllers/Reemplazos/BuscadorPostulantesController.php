<?php

namespace App\Http\Controllers\Reemplazos;

use App\Http\Controllers\Controller;
use App\Support\PdfBranding;
use App\Models\AreaDesempeno;
use App\Models\Establecimiento;
use App\Models\Commune;
use App\Models\DocumentType;
use App\Models\PostulantProfile;
use App\Models\PostulantProfileContrato;
use App\Models\SolicitudReemplazo;
use App\Models\User;
use App\Models\UserDocument;
use App\Services\RestrictedRutService;
use App\Support\DocumentRules;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BuscadorPostulantesController extends Controller
{
    public function __construct()
    {
        $this->middleware([
            'auth',
            'ensure.role:admin|funcionario_slep|coordinador_gdp|coordinador_uatp|funcionario_estab',
        ]);
    }

    /**
     * Vista principal del buscador.
     *
     * - Tabla paginada de postulantes (perfil + usuario)
     * - Filtros combinables por columna
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'rut'               => ['nullable', 'string', 'max:30'],
            'nombre'            => ['nullable', 'string', 'max:120'],
            'telefono'          => ['nullable', 'string', 'max:30'],
            'commune_id'        => ['nullable', 'integer', 'exists:communes,id'],
            'area_desempeno_id' => ['nullable', 'integer', 'exists:areas_desempeno,id'],
            'especialidad_tp'    => ['nullable', 'string', 'max:150'],
            'mencion'            => ['nullable', 'string', 'max:255'],
            'exp_min'           => ['nullable', 'integer', 'min:0', 'max:80'],
            'exp_max'           => ['nullable', 'integer', 'min:0', 'max:80'],
            'per_page'          => ['nullable', 'integer', 'in:10,15,25,50'],
        ]);

        $filters = [
            'rut'               => trim((string)($data['rut'] ?? '')),
            'nombre'            => trim((string)($data['nombre'] ?? '')),
            'telefono'          => trim((string)($data['telefono'] ?? '')),
            'commune_id'        => $data['commune_id'] ?? null,
            'area_desempeno_id' => $data['area_desempeno_id'] ?? null,
            'especialidad_tp'    => trim((string)($data['especialidad_tp'] ?? '')),
            'mencion'            => trim((string)($data['mencion'] ?? '')),
            'exp_min'           => $data['exp_min'] ?? null,
            'exp_max'           => $data['exp_max'] ?? null,
            'per_page'          => (int)($data['per_page'] ?? 15),
        ];

        // Catálogos para filtros
        $permitidas = config('chile.comunas_postulacion_permitidas', ['Coronel', 'Lota', 'San Pedro de la Paz', 'Santa Juana', 'Isla Santa María']);
        $communes = Commune::query()
            ->whereIn('name', $permitidas)
            ->orderBy('name')
            ->get(['id', 'name']);

        $areasDocente = AreaDesempeno::query()
            ->activos()
            ->docente()
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'estamento']);

        $areasAsistente = AreaDesempeno::query()
            ->activos()
            ->asistente()
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'estamento']);

        $areaDocenteIds = $areasDocente
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $areaTpIds = $areasDocente
            ->filter(fn ($area) => $this->esAreaDocenteTecnicoProfesional($area->nombre ?? null))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $selectedAreaId = !empty($filters['area_desempeno_id']) ? (int) $filters['area_desempeno_id'] : null;
        $isAreaDocenteSelected = $selectedAreaId && $areaDocenteIds->contains($selectedAreaId);
        $isAreaTpSelected = $selectedAreaId && $areaTpIds->contains($selectedAreaId);

        $especialidadesTp = $areaTpIds->isEmpty()
            ? collect()
            : PostulantProfile::query()
                ->whereIn('area_desempeno_id', $areaTpIds)
                ->whereNotNull('especialidad_tp')
                ->where('especialidad_tp', '<>', '')
                ->pluck('especialidad_tp')
                ->map(fn ($especialidad) => trim((string) $especialidad))
                ->filter()
                ->unique(fn ($especialidad) => mb_strtolower($especialidad))
                ->sort(SORT_NATURAL | SORT_FLAG_CASE)
                ->values();

        $mencionesDocentes = $areaDocenteIds->isEmpty()
            ? collect()
            : PostulantProfile::query()
                ->whereIn('area_desempeno_id', $areaDocenteIds)
                ->whereNotNull('mencion')
                ->where('mencion', '<>', '')
                ->pluck('mencion')
                ->map(fn ($mencion) => trim((string) $mencion))
                ->filter()
                ->unique(fn ($mencion) => mb_strtolower($mencion))
                ->sort(SORT_NATURAL | SORT_FLAG_CASE)
                ->values();

        if (! $isAreaTpSelected) {
            $filters['especialidad_tp'] = '';
        }

        if (! $isAreaDocenteSelected) {
            $filters['mencion'] = '';
        }

        // Query base
        $q = PostulantProfile::query()
            ->select('postulant_profiles.*')
            ->join('users', 'users.id', '=', 'postulant_profiles.user_id')
            ->with([
                'user' => fn($uq) => $uq->with(['communes:id,name', 'trabajoExternoMarcadoPor:id,nombres,apellido_paterno,apellido_materno']),
                'areaDesempeno:id,nombre,estamento',
                'contratosLaboralesActivos.establecimiento:id,rbd,nombre_establecimiento,comuna',
            ])
            // Usuarios elegibles: postulante o funcionario
            ->whereHas('user.roles', function ($rq) {
                $rq->whereIn('name', ['postulante', 'funcionario']);
            });

        // --- Filtros combinables ---

        // RUT
        if ($filters['rut'] !== '') {
            $rutClean = strtoupper(preg_replace('/[^0-9Kk]/', '', $filters['rut']));
            $q->where('users.rut', 'like', '%' . ($rutClean !== '' ? $rutClean : $filters['rut']) . '%');
        }

        // Nombre (tokens AND)
        if ($filters['nombre'] !== '') {
            $tokens = array_values(array_filter(preg_split('/\s+/', $filters['nombre']) ?: []));
            foreach ($tokens as $tok) {
                $q->where(function ($qq) use ($tok) {
                    $qq->where('users.nombres', 'like', "%{$tok}%")
                        ->orWhere('users.apellido_paterno', 'like', "%{$tok}%")
                        ->orWhere('users.apellido_materno', 'like', "%{$tok}%")
                        ->orWhereRaw("CONCAT_WS(' ', users.nombres, users.apellido_paterno, users.apellido_materno) LIKE ?", ["%{$tok}%"]);
                });
            }
        }

        // Teléfono (perfil)
        if ($filters['telefono'] !== '') {
            $tel = $filters['telefono'];
            $q->where(function ($qq) use ($tel) {
                $qq->where('postulant_profiles.telefono1', 'like', "%{$tel}%")
                    ->orWhere('postulant_profiles.telefono2', 'like', "%{$tel}%");
            });
        }

        // Comuna de desempeño (pivot user->communes)
        if (!empty($filters['commune_id'])) {
            $communeId = (int)$filters['commune_id'];
            if ($communeId > 0) {
                $q->whereHas('user.communes', fn($cq) => $cq->where('communes.id', $communeId));
            }
        }

        // Área de desempeño
        if (!empty($filters['area_desempeno_id'])) {
            $areaId = (int)$filters['area_desempeno_id'];
            if ($areaId > 0) {
                $q->where('postulant_profiles.area_desempeno_id', $areaId);
            }
        }

        // Especialidad TP: sólo aplica cuando el área seleccionada es Docente Técnico Profesional
        if ($isAreaTpSelected && $filters['especialidad_tp'] !== '') {
            $q->where('postulant_profiles.especialidad_tp', $filters['especialidad_tp']);
        }

        // Mención: sólo aplica cuando el área seleccionada pertenece al estamento docente
        if ($isAreaDocenteSelected && $filters['mencion'] !== '') {
            $mencion = $filters['mencion'];
            $q->where('postulant_profiles.mencion', 'like', "%{$mencion}%");
        }

        // Años experiencia
        if ($filters['exp_min'] !== null && $filters['exp_min'] !== '') {
            $q->where('postulant_profiles.anios_experiencia', '>=', (int)$filters['exp_min']);
        }
        if ($filters['exp_max'] !== null && $filters['exp_max'] !== '') {
            $q->where('postulant_profiles.anios_experiencia', '<=', (int)$filters['exp_max']);
        }

        // Orden estable
        $q->orderBy('users.apellido_paterno')
            ->orderBy('users.apellido_materno')
            ->orderBy('users.nombres');

        $perPage = in_array($filters['per_page'], [10, 15, 25, 50], true) ? $filters['per_page'] : 15;

        $profiles = $q->paginate($perPage)->withQueryString();

        $currentProfiles = $profiles->getCollection();

        $reemplazosActivosPorPerfil = $this->reemplazosActivosPorPerfil(
            $currentProfiles->pluck('id')->all()
        );

        $documentosObligatoriosPorPerfil = $this->documentosObligatoriosPorPerfil($currentProfiles);

        $currentProfiles->each(function (PostulantProfile $profile) use ($reemplazosActivosPorPerfil, $documentosObligatoriosPorPerfil) {
            $profile->setRelation('reemplazosActivosActuales', $reemplazosActivosPorPerfil->get($profile->id, collect()));
            $profile->setAttribute('documentos_obligatorios_estado', $documentosObligatoriosPorPerfil->get($profile->id, [
                'total' => 0,
                'uploaded' => 0,
                'missing' => 0,
                'missing_labels' => [],
                'is_complete' => null,
                'tooltip' => 'No se detectaron documentos obligatorios para este perfil.',
            ]));
        });

        return view('reemplazos.buscador-postulantes.index', [
            'title'          => 'Buscador de Postulantes y Funcionarios',
            'profiles'       => $profiles,
            'filters'        => $filters,
            'communes'       => $communes,
            'areasDocente'       => $areasDocente,
            'areasAsistente'     => $areasAsistente,
            'especialidadesTp'   => $especialidadesTp,
            'mencionesDocentes'  => $mencionesDocentes,
            'areaDocenteIds'     => $areaDocenteIds,
            'areaTpIds'          => $areaTpIds,
            'isAreaDocenteSelected' => $isAreaDocenteSelected,
            'isAreaTpSelected'   => $isAreaTpSelected,
        ]);
    }

    /**
     * Calcula, para los perfiles visibles en la página actual, si cada usuario subió
     * todos los documentos obligatorios que le corresponden según las reglas vigentes.
     *
     * El criterio de completitud es existencia de archivo cargado por tipo documental
     * obligatorio; no exige aprobación administrativa para no mezclar carga con revisión.
     *
     * @param \Illuminate\Support\Collection<int,\App\Models\PostulantProfile> $profiles
     * @return \Illuminate\Support\Collection<int,array<string,mixed>>
     */
    private function documentosObligatoriosPorPerfil($profiles)
    {
        $profiles = collect($profiles)->filter(fn($profile) => $profile instanceof PostulantProfile);

        if ($profiles->isEmpty()) {
            return collect();
        }

        $documentTypes = DocumentType::query()
            ->orderBy('sort_order')
            ->get();

        if ($documentTypes->isEmpty()) {
            return $profiles->mapWithKeys(fn(PostulantProfile $profile) => [
                $profile->id => [
                    'total' => 0,
                    'uploaded' => 0,
                    'missing' => 0,
                    'missing_labels' => [],
                    'is_complete' => null,
                    'tooltip' => 'No hay tipos de documentos obligatorios configurados.',
                ],
            ]);
        }

        $userIds = $profiles
            ->map(fn(PostulantProfile $profile) => $profile->user?->id)
            ->filter()
            ->unique()
            ->values();

        $documentsByUser = UserDocument::query()
            ->whereIn('user_id', $userIds)
            ->whereNotNull('path')
            ->where('path', '<>', '')
            ->get(['user_id', 'document_type_id', 'path'])
            ->groupBy('user_id');

        return $profiles->mapWithKeys(function (PostulantProfile $profile) use ($documentTypes, $documentsByUser) {
            $user = $profile->user;

            if (!$user) {
                return [$profile->id => [
                    'total' => 0,
                    'uploaded' => 0,
                    'missing' => 0,
                    'missing_labels' => [],
                    'is_complete' => null,
                    'tooltip' => 'No se pudo asociar un usuario a este perfil.',
                ]];
            }

            // DocumentRules evalúa $user->postulantProfile; se fija la relación al perfil
            // ya cargado para evitar consultas adicionales y usar exactamente la fila listada.
            $user->setRelation('postulantProfile', $profile);

            $requiredTypes = DocumentRules::requiredTypesFromCatalog($user, $documentTypes);
            $requiredIds = $requiredTypes->pluck('id')->map(fn($id) => (int) $id)->all();
            $uploadedIds = ($documentsByUser->get($user->id, collect()))
                ->pluck('document_type_id')
                ->map(fn($id) => (int) $id)
                ->unique()
                ->all();

            $missingTypes = $requiredTypes
                ->reject(fn(DocumentType $type) => in_array((int) $type->id, $uploadedIds, true))
                ->values();

            $total = count($requiredIds);
            $uploaded = count(array_intersect($requiredIds, $uploadedIds));
            $missing = $missingTypes->count();
            $missingLabels = $missingTypes->pluck('label')->filter()->values()->all();

            if ($total === 0) {
                $isComplete = null;
                $tooltip = 'No se detectaron documentos obligatorios para este perfil.';
            } elseif ($missing === 0) {
                $isComplete = true;
                $tooltip = 'Todos los documentos obligatorios fueron subidos.';
            } else {
                $isComplete = false;
                $tooltip = 'Faltan documentos obligatorios: ' . implode(', ', $missingLabels);
            }

            return [$profile->id => [
                'total' => $total,
                'uploaded' => $uploaded,
                'missing' => $missing,
                'missing_labels' => $missingLabels,
                'is_complete' => $isComplete,
                'tooltip' => $tooltip,
            ]];
        });
    }

    /**
     * Ficha del postulante.
     */
    public function show(PostulantProfile $postulantProfile)
    {
        $postulantProfile->load([
            'user' => fn($uq) => $uq->with(['communes:id,name']),
            'areaDesempeno:id,nombre,estamento',
            'comuna:id,name,region_code',
            'contratosLaboralesActivos.establecimiento:id,rbd,nombre_establecimiento,comuna',
            'contratosLaboralesActivos.registradoPor:id,nombres,apellido_paterno,apellido_materno',
            'ultimaVinculacionLaboral.establecimiento:id,rbd,nombre_establecimiento,comuna',
            'ultimaVinculacionLaboral.registradoPor:id,nombres,apellido_paterno,apellido_materno',
            'ultimaVinculacionLaboral.desactivadoPor:id,nombres,apellido_paterno,apellido_materno',
            'user.trabajoExternoMarcadoPor:id,nombres,apellido_paterno,apellido_materno',
        ]);

        $user = $postulantProfile->user;

        // Últimas solicitudes donde este postulante fue propuesto (si existen)
        $solicitudes = SolicitudReemplazo::query()
            ->where('postulant_profile_id', $postulantProfile->id)
            ->with(['establecimiento:id,nombre_establecimiento', 'areaDesempeno:id,nombre'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $reemplazosActivos = $this->reemplazosActivosPorPerfil([$postulantProfile->id])
            ->get($postulantProfile->id, collect());

        $restrictionContext = app(RestrictedRutService::class)->restrictionContextForPostulantProfile($postulantProfile);

        return view('reemplazos.buscador-postulantes.show', [
            'title'              => 'Postulante: ' . ($user?->nombre_completo ?? 'Ficha'),
            'profile'            => $postulantProfile,
            'user'               => $user,
            'solicitudes'        => $solicitudes,
            'restrictionContext' => $restrictionContext,
            'establecimientosPorComuna' => $this->establecimientosPorComuna(),
            'canManageContratoLaboral' => $this->canManageContratoLaboral(request()),
            'canManageTrabajoExterno' => $this->canManageTrabajoExterno(request()),
            'ultimaVinculacionLaboral' => $postulantProfile->ultimaVinculacionLaboral,
            'reemplazosActivos' => $reemplazosActivos,
        ]);
    }

    public function storeContratoLaboral(Request $request, PostulantProfile $postulantProfile)
    {
        abort_unless($this->canManageContratoLaboral($request), 403);

        $data = $request->validate([
            'tipo_contrato' => ['required', 'string', Rule::in(['Contrata', 'Plazo Fijo', 'Honorarios', 'Indefinido'])],
            'fecha_termino' => ['nullable', 'date', 'required_unless:tipo_contrato,Indefinido'],
            'vinculaciones' => ['required', 'array', 'min:1', 'max:10'],
            'vinculaciones.*.establecimiento_id' => ['required', 'integer', 'exists:establecimientos,id'],
            'vinculaciones.*.cantidad_horas' => ['required', 'integer', 'min:1', 'max:60'],
        ], [], [
            'vinculaciones' => 'establecimientos asociados',
            'vinculaciones.*.establecimiento_id' => 'establecimiento',
            'vinculaciones.*.cantidad_horas' => 'cantidad de horas',
        ]);

        $vinculaciones = collect($data['vinculaciones'])
            ->filter(fn ($item) => !empty($item['establecimiento_id']) && !empty($item['cantidad_horas']))
            ->map(fn ($item) => [
                'establecimiento_id' => (int) $item['establecimiento_id'],
                'cantidad_horas' => (int) $item['cantidad_horas'],
            ])
            ->values();

        if ($vinculaciones->isEmpty()) {
            return back()->withErrors(['vinculaciones' => 'Debes indicar al menos un establecimiento con horas.'])->withInput();
        }

        if ($vinculaciones->pluck('establecimiento_id')->duplicates()->isNotEmpty()) {
            return back()->withErrors(['vinculaciones' => 'No puedes repetir el mismo establecimiento en una misma vinculación laboral.'])->withInput();
        }

        DB::transaction(function () use ($postulantProfile, $data, $vinculaciones, $request) {
            PostulantProfileContrato::query()
                ->where('postulant_profile_id', $postulantProfile->id)
                ->where('activo', true)
                ->update([
                    'activo' => false,
                    'motivo_desactivacion' => 'Reemplazada por nueva vinculación laboral activa',
                    'desactivado_por' => $request->user()?->id,
                    'desactivado_at' => now(),
                ]);

            foreach ($vinculaciones as $vinculacion) {
                PostulantProfileContrato::create([
                    'postulant_profile_id' => $postulantProfile->id,
                    'tipo_contrato' => $data['tipo_contrato'],
                    'cantidad_horas' => $vinculacion['cantidad_horas'],
                    'fecha_termino' => ($data['tipo_contrato'] === 'Indefinido') ? null : ($data['fecha_termino'] ?? null),
                    'establecimiento_id' => $vinculacion['establecimiento_id'],
                    'registrado_por' => $request->user()?->id,
                    'activo' => true,
                ]);
            }
        });

        return redirect()
            ->route('reemplazos.buscador-postulantes.show', $postulantProfile)
            ->with('success', 'La vinculación laboral activa fue registrada correctamente.');
    }


    public function updateTrabajoExterno(Request $request, PostulantProfile $postulantProfile)
    {
        abort_unless($this->canManageTrabajoExterno($request), 403);

        $data = $request->validate([
            'trabaja_en_otro_lugar' => ['nullable', 'boolean'],
            'trabaja_en_otro_lugar_observacion' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'trabaja_en_otro_lugar' => 'informó que trabaja en otro lugar',
            'trabaja_en_otro_lugar_observacion' => 'observación',
        ]);

        $user = $postulantProfile->user()->firstOrFail();
        $marcado = (bool) ($data['trabaja_en_otro_lugar'] ?? false);

        $user->forceFill([
            'trabaja_en_otro_lugar' => $marcado,
            'trabaja_en_otro_lugar_observacion' => $marcado ? trim((string) ($data['trabaja_en_otro_lugar_observacion'] ?? '')) : null,
            'trabaja_en_otro_lugar_marcado_por' => $marcado ? $request->user()?->id : null,
            'trabaja_en_otro_lugar_marcado_en' => $marcado ? now() : null,
        ])->save();

        return redirect()
            ->route('reemplazos.buscador-postulantes.show', $postulantProfile)
            ->with('success', 'La situación laboral informada fue actualizada correctamente.');
    }

    public function destroyContratoLaboral(Request $request, PostulantProfile $postulantProfile, PostulantProfileContrato $contrato)
    {
        abort_unless($this->canManageContratoLaboral($request), 403);
        abort_unless((int) $contrato->postulant_profile_id === (int) $postulantProfile->id, 404);

        $data = $request->validate([
            'motivo_desactivacion' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $contrato->update([
            'activo' => false,
            'motivo_desactivacion' => $data['motivo_desactivacion'],
            'desactivado_por' => $request->user()?->id,
            'desactivado_at' => now(),
        ]);

        return redirect()
            ->route('reemplazos.buscador-postulantes.show', $postulantProfile)
            ->with('success', 'La vinculación laboral activa fue desactivada correctamente.');
    }
    /**
     * Ver perfil PDF (inline)
     */
    public function perfilView(PostulantProfile $postulantProfile)
    {
        return $this->renderPerfilPdf($postulantProfile, inline: true);
    }

    /**
     * Descargar perfil PDF
     */
    public function perfilPdf(PostulantProfile $postulantProfile)
    {
        return $this->renderPerfilPdf($postulantProfile, inline: false);
    }

    // -----------------
    // Helpers
    // -----------------

    private function reemplazosActivosPorPerfil(array $profileIds)
    {
        $profileIds = collect($profileIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($profileIds->isEmpty()) {
            return collect();
        }

        $hoy = now()->toDateString();

        $solicitudes = SolicitudReemplazo::query()
            ->with(['establecimiento:id,rbd,nombre_establecimiento,comuna'])
            ->where(function ($query) use ($profileIds) {
                $query->whereIn('postulant_profile_id', $profileIds)
                    ->orWhereIn('contrato_trabajo_postulant_profile_id', $profileIds);
            })
            ->where('estado', 'aceptada')
            ->whereDate('fecha_inicio', '<=', $hoy)
            ->whereDate('fecha_termino', '>=', $hoy)
            ->orderBy('fecha_termino')
            ->get();

        $agrupadas = collect();

        foreach ($solicitudes as $solicitud) {
            foreach (['postulant_profile_id', 'contrato_trabajo_postulant_profile_id'] as $campo) {
                $profileId = (int) ($solicitud->{$campo} ?? 0);
                if ($profileId > 0 && $profileIds->contains($profileId)) {
                    $actuales = $agrupadas->get($profileId, collect());

                    if (! $actuales->contains(fn ($item) => (int) $item->id === (int) $solicitud->id)) {
                        $actuales->push($solicitud);
                    }

                    $agrupadas->put($profileId, $actuales);
                }
            }
        }

        return $agrupadas->map(fn ($items) => $items->unique('id')->values());
    }

    private function esAreaDocenteTecnicoProfesional(?string $nombre): bool
    {
        $normalized = mb_strtolower(trim((string) $nombre));
        $normalized = strtr($normalized, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n',
        ]);
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        return $normalized === 'docente tecnico profesional';
    }

    private function canManageContratoLaboral(Request $request): bool
    {
        $user = $request->user();

        return $user && method_exists($user, 'hasRole') && (
            $user->hasRole('funcionario_slep') || $user->hasRole('admin')
        );
    }

    private function canManageTrabajoExterno(Request $request): bool
    {
        $user = $request->user();

        return $user && method_exists($user, 'hasRole') && (
            $user->hasRole('funcionario_slep') || $user->hasRole('coordinador_gdp') || $user->hasRole('admin')
        );
    }

    private function establecimientosPorComuna()
    {
        return Establecimiento::query()
            ->orderBy('comuna')
            ->orderBy('nombre_establecimiento')
            ->get(['id', 'rbd', 'nombre_establecimiento', 'comuna'])
            ->groupBy(fn ($establecimiento) => $establecimiento->comuna ?: 'Sin comuna');
    }
    private function renderPerfilPdf(PostulantProfile $profile, bool $inline)
    {
        $profile->load(['comuna']);
        $user = $profile->user()->firstOrFail();
        $user->load(['communes']);

        // Región legible
        $regiones   = config('chile.regiones', []);
        $regionName = $profile->region_code ? ($regiones[$profile->region_code] ?? $profile->region_code) : '';

        // RUT formateado: sin puntos, con guion
        $rutRaw = (string)($user->rut ?? '');
        $rutSan = strtoupper(preg_replace('/[^0-9Kk]/', '', $rutRaw));
        if ($rutSan !== '') {
            $dv     = substr($rutSan, -1);
            $cuerpo = substr($rutSan, 0, -1);
            $rutFmt = $cuerpo . '-' . $dv;
        } else {
            $rutFmt = 'ID' . $user->id;
        }

        // Nacionalidad + bandera (data URI)
        $nationalities = collect(config('nacionalidades', []));
        $val   = (string)($profile->nacionalidad ?? '');
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

        // Foto miniatura absoluta (si existe)
        $fotoThumbAbs = null;
        if (!empty($profile->foto_thumb_path) && Storage::disk('public')->exists($profile->foto_thumb_path)) {
            $fotoThumbAbs = Storage::disk('public')->path($profile->foto_thumb_path);
        }

        // Paleta/marca para la plantilla PDF
        $brand = PdfBranding::profileBrand();

        $data = [
            'user'         => $user,
            'profile'      => $profile,
            'rutFmt'       => $rutFmt,
            'regionName'   => $regionName,
            'communes'     => $user->communes,
            'brand'        => $brand,
            'fotoThumbAbs' => $fotoThumbAbs,
            'generatedAt'  => Carbon::now(),
            'nacName'      => $nacName,
            'flagDataUrl'  => $flagDataUrl,
        ];

        $pdf = Pdf::loadView('pdf.profile', $data)
            ->setPaper('letter', 'portrait');

        // Nombre de archivo: PERFIL_{RUT}.pdf
        $fileRut  = preg_replace('/[^0-9Kk-]/', '', $rutFmt);
        $filename = "PERFIL_{$fileRut}.pdf";

        return $inline
            ? $pdf->stream($filename, ['Attachment' => false])
            : $pdf->download($filename);
    }
}
