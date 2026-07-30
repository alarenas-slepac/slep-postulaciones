<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAdmisionEstablecimientoRequest;
use App\Models\AdmisionEstablecimiento;
use App\Models\Establecimiento;
use App\Services\AdmisionEscolarCompletenessService;
use App\Services\AdmisionEscolarMediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use RuntimeException;

class AdmisionEscolarController extends Controller
{
    public function __construct(
        private readonly AdmisionEscolarCompletenessService $completeness,
        private readonly AdmisionEscolarMediaService $media
    ) {
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $comuna = trim((string) $request->query('comuna', ''));
        $estado = trim((string) $request->query('estado', ''));

        $baseQuery = Establecimiento::query()
            ->with([
                'admisionPerfil.imagenes',
                'admisionPerfil.actualizadoPor:id,nombres,apellido_paterno,apellido_materno,email',
            ])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('nombre_establecimiento', 'like', "%{$q}%")
                        ->orWhere('rbd', 'like', "%{$q}%")
                        ->orWhere('comuna', 'like', "%{$q}%");
                });
            })
            ->when($comuna !== '', fn ($query) => $query->where('comuna', $comuna))
            ->when($estado === 'publicado', fn ($query) => $query->whereHas(
                'admisionPerfil',
                fn ($perfil) => $perfil->where('estado', AdmisionEstablecimiento::ESTADO_PUBLICADO)
            ))
            ->when($estado === 'borrador', fn ($query) => $query->whereHas(
                'admisionPerfil',
                fn ($perfil) => $perfil->where('estado', AdmisionEstablecimiento::ESTADO_BORRADOR)
            ))
            ->when($estado === 'sin_ficha', fn ($query) => $query->whereDoesntHave('admisionPerfil'))
            ->orderBy('comuna')
            ->orderBy('nombre_establecimiento');

        if ($estado === 'incompleto') {
            $filtered = $baseQuery->get()
                ->filter(fn (Establecimiento $item) => ! $this->completeness
                    ->calculate($item, $item->admisionPerfil)['publishable'])
                ->values();
            $items = $this->paginateCollection($filtered, 20, $request);
        } else {
            $items = $baseQuery->paginate(20)->withQueryString();
        }

        $items->getCollection()->each(function (Establecimiento $item) {
            $item->admision_completitud = $this->completeness->calculate($item, $item->admisionPerfil);
        });

        $summaryItems = Establecimiento::query()
            ->with('admisionPerfil.imagenes')
            ->orderBy('id')
            ->get();

        $summary = [
            'total' => $summaryItems->count(),
            'publicados' => $summaryItems->filter(
                fn (Establecimiento $item) => $item->admisionPerfil?->isPublicado()
            )->count(),
            'borradores' => $summaryItems->filter(
                fn (Establecimiento $item) => $item->admisionPerfil
                    && $item->admisionPerfil->estado === AdmisionEstablecimiento::ESTADO_BORRADOR
            )->count(),
            'incompletos' => $summaryItems->filter(
                fn (Establecimiento $item) => ! $this->completeness
                    ->calculate($item, $item->admisionPerfil)['publishable']
            )->count(),
        ];

        $comunas = Establecimiento::query()
            ->whereNotNull('comuna')
            ->where('comuna', '<>', '')
            ->distinct()
            ->orderBy('comuna')
            ->pluck('comuna');

        return view('admin.admision-escolar.index', compact(
            'items',
            'summary',
            'comunas',
            'q',
            'comuna',
            'estado'
        ));
    }

    public function edit(Establecimiento $establecimiento): View
    {
        $establecimiento->load('admisionPerfil.imagenes');
        $perfil = $establecimiento->admisionPerfil
            ?? new AdmisionEstablecimiento([
                'establecimiento_id' => $establecimiento->id,
                'slug' => AdmisionEstablecimiento::slugBase($establecimiento),
                'estado' => AdmisionEstablecimiento::ESTADO_BORRADOR,
            ]);

        $completitud = $this->completeness->calculate($establecimiento, $perfil);

        return view('admin.admision-escolar.edit', compact('establecimiento', 'perfil', 'completitud'));
    }

    public function update(
        UpdateAdmisionEstablecimientoRequest $request,
        Establecimiento $establecimiento
    ): RedirectResponse {
        $perfil = $establecimiento->admisionPerfil()->firstOrNew();
        $data = $request->validated();
        $newPaths = [];
        $deleteAfterSave = [];

        $logoFile = $request->file('logo');
        $directorFile = $request->file('director_foto');
        $deleteLogo = (bool) ($data['eliminar_logo'] ?? false);
        $deleteDirector = (bool) ($data['eliminar_director_foto'] ?? false);
        unset($data['logo'], $data['director_foto'], $data['eliminar_logo'], $data['eliminar_director_foto']);

        try {
            if ($logoFile) {
                $newLogo = $this->media->storeLogo($logoFile, $establecimiento);
                $newPaths[] = $newLogo;
                if ($perfil->logo_path) {
                    $deleteAfterSave[] = $perfil->logo_path;
                }
                $data['logo_path'] = $newLogo;
            } elseif ($deleteLogo && $perfil->logo_path) {
                $deleteAfterSave[] = $perfil->logo_path;
                $data['logo_path'] = null;
            }

            if ($directorFile) {
                $newDirector = $this->media->storeDirectorPhoto($directorFile, $establecimiento);
                $newPaths[] = $newDirector;
                if ($perfil->director_foto_path) {
                    $deleteAfterSave[] = $perfil->director_foto_path;
                }
                $data['director_foto_path'] = $newDirector;
            } elseif ($deleteDirector && $perfil->director_foto_path) {
                $deleteAfterSave[] = $perfil->director_foto_path;
                $data['director_foto_path'] = null;
            }

            $data['orden'] = (int) ($data['orden'] ?? 0);
            $data['actualizado_por'] = $request->user()->id;

            if (! $perfil->exists || blank($perfil->slug)) {
                $data['slug'] = AdmisionEstablecimiento::uniqueSlugFor($establecimiento, $perfil->id);
            }

            if (! $perfil->exists) {
                $data['estado'] = AdmisionEstablecimiento::ESTADO_BORRADOR;
            }

            $perfil->fill($data);
            $perfil->establecimiento()->associate($establecimiento);
            $perfil->save();
        } catch (RuntimeException $exception) {
            foreach ($newPaths as $path) {
                $this->media->delete($path);
            }

            return back()
                ->withInput()
                ->withErrors(['media' => $exception->getMessage()]);
        } catch (\Throwable $exception) {
            foreach ($newPaths as $path) {
                $this->media->delete($path);
            }
            throw $exception;
        }

        foreach (array_unique($deleteAfterSave) as $path) {
            $this->media->delete($path);
        }

        $perfil->load('imagenes');
        $autoUnpublished = false;
        if ($perfil->isPublicado()
            && $this->completeness->publicationMissing($establecimiento, $perfil) !== []) {
            $perfil->forceFill([
                'estado' => AdmisionEstablecimiento::ESTADO_BORRADOR,
                'publicado_at' => null,
                'actualizado_por' => $request->user()->id,
            ])->save();
            $autoUnpublished = true;
        }

        return redirect()
            ->route('admin.admision-escolar.edit', $establecimiento)
            ->with(
                'status',
                $autoUnpublished
                    ? 'La ficha fue guardada y despublicada automáticamente porque dejó de cumplir los requisitos mínimos.'
                    : 'Ficha de Admisión Escolar guardada correctamente.'
            );
    }

    public function preview(Establecimiento $establecimiento): View
    {
        $establecimiento->load('admisionPerfil.imagenes');
        $perfil = $establecimiento->admisionPerfil;
        abort_unless($perfil, 404, 'La ficha de Admisión Escolar aún no ha sido guardada.');

        return view('public.admision-escolar.show', [
            'establecimiento' => $establecimiento,
            'perfil' => $perfil,
            'isPreview' => true,
        ]);
    }

    public function publish(Request $request, Establecimiento $establecimiento): RedirectResponse
    {
        $perfil = $establecimiento->admisionPerfil()->with('imagenes')->first();
        abort_unless($perfil, 404, 'La ficha de Admisión Escolar aún no existe.');

        $missing = $this->completeness->publicationMissing($establecimiento, $perfil);
        if ($missing !== []) {
            return redirect()
                ->route('admin.admision-escolar.edit', $establecimiento)
                ->withErrors([
                    'publicacion' => 'No se puede publicar. Falta: ' . implode(', ', $missing) . '.',
                ]);
        }

        $perfil->forceFill([
            'estado' => AdmisionEstablecimiento::ESTADO_PUBLICADO,
            'publicado_at' => now(),
            'publicado_por' => $request->user()->id,
            'actualizado_por' => $request->user()->id,
        ])->save();

        return redirect()
            ->route('admin.admision-escolar.edit', $establecimiento)
            ->with('status', 'El establecimiento fue publicado en la vitrina de Admisión Escolar.');
    }

    public function unpublish(Request $request, Establecimiento $establecimiento): RedirectResponse
    {
        $perfil = $establecimiento->admisionPerfil()->first();
        abort_unless($perfil, 404);

        $perfil->forceFill([
            'estado' => AdmisionEstablecimiento::ESTADO_BORRADOR,
            'publicado_at' => null,
            'actualizado_por' => $request->user()->id,
        ])->save();

        return redirect()
            ->route('admin.admision-escolar.edit', $establecimiento)
            ->with('status', 'La ficha fue despublicada y permanece guardada como borrador.');
    }

    private function paginateCollection(Collection $items, int $perPage, Request $request): LengthAwarePaginator
    {
        $page = max(1, (int) $request->query('page', 1));
        $slice = $items->forPage($page, $perPage)->values();

        return (new LengthAwarePaginator(
            $slice,
            $items->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        ))->withQueryString();
    }
}
