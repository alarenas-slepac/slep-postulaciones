<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdmisionGaleriaRequest;
use App\Http\Requests\Admin\UpdateAdmisionGaleriaImagenRequest;
use App\Models\AdmisionEstablecimiento;
use App\Models\AdmisionEstablecimientoImagen;
use App\Models\Establecimiento;
use App\Services\AdmisionEscolarCompletenessService;
use App\Services\AdmisionEscolarMediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class AdmisionEscolarGaleriaController extends Controller
{
    public function __construct(
        private readonly AdmisionEscolarMediaService $media,
        private readonly AdmisionEscolarCompletenessService $completeness
    ) {
    }

    public function store(
        StoreAdmisionGaleriaRequest $request,
        Establecimiento $establecimiento
    ): RedirectResponse {
        $perfil = $this->profileFor($request, $establecimiento);
        $files = collect($request->file('imagenes', []));
        $maxTotal = max(1, (int) config('admision.max_imagenes_por_establecimiento', 20));
        $existingCount = $perfil->imagenes()->count();

        if (($existingCount + $files->count()) > $maxTotal) {
            return back()->withErrors([
                'imagenes' => "La galería admite un máximo de {$maxTotal} imágenes por establecimiento.",
            ]);
        }

        $storedPaths = [];
        $baseAlt = trim((string) $request->input('texto_alternativo_base', ''));
        $baseAlt = $baseAlt !== '' ? $baseAlt : 'Vista de ' . $establecimiento->nombre_establecimiento;
        $makeFirstCover = $request->boolean('marcar_primera_como_portada')
            || ! $perfil->imagenes()->where('es_portada', true)->exists();
        $nextOrder = (int) $perfil->imagenes()->max('orden') + 1;

        try {
            DB::transaction(function () use (
                $files,
                $establecimiento,
                $perfil,
                $baseAlt,
                $makeFirstCover,
                $nextOrder,
                &$storedPaths
            ) {
                if ($makeFirstCover) {
                    $perfil->imagenes()->update(['es_portada' => false]);
                }

                foreach ($files->values() as $index => $file) {
                    $path = $this->media->storeGalleryImage($file, $establecimiento);
                    $storedPaths[] = $path;

                    $perfil->imagenes()->create([
                        'imagen_path' => $path,
                        'original_name' => mb_substr((string) $file->getClientOriginalName(), 0, 255),
                        'mime_type' => mb_substr((string) $file->getMimeType(), 0, 120),
                        'tamano_bytes' => $file->getSize(),
                        'texto_alternativo' => $baseAlt . ($files->count() > 1 ? ' ' . ($index + 1) : ''),
                        'es_portada' => $makeFirstCover && $index === 0,
                        'orden' => $nextOrder + $index,
                    ]);
                }

                $perfil->forceFill(['actualizado_por' => auth()->id()])->save();
            });
        } catch (RuntimeException $exception) {
            foreach ($storedPaths as $path) {
                $this->media->delete($path);
            }

            return back()
                ->withInput()
                ->withErrors(['imagenes' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                $this->media->delete($path);
            }
            throw $exception;
        }

        return back()->with('status', 'Galería actualizada correctamente.');
    }

    public function update(
        UpdateAdmisionGaleriaImagenRequest $request,
        Establecimiento $establecimiento,
        AdmisionEstablecimientoImagen $imagen
    ): RedirectResponse {
        $perfil = $this->profileFor($request, $establecimiento);
        $this->assertImageBelongsToProfile($imagen, $perfil);

        $imagen->update($request->validated());
        $perfil->forceFill(['actualizado_por' => $request->user()->id])->save();

        return back()->with('status', 'Información de la imagen actualizada.');
    }

    public function cover(
        Request $request,
        Establecimiento $establecimiento,
        AdmisionEstablecimientoImagen $imagen
    ): RedirectResponse {
        $perfil = $this->profileFor($request, $establecimiento);
        $this->assertImageBelongsToProfile($imagen, $perfil);

        DB::transaction(function () use ($perfil, $imagen, $request) {
            $perfil->imagenes()->update(['es_portada' => false]);
            $imagen->forceFill(['es_portada' => true])->save();
            $perfil->forceFill(['actualizado_por' => $request->user()->id])->save();
        });

        return back()->with('status', 'Imagen de portada actualizada.');
    }

    public function destroy(
        Request $request,
        Establecimiento $establecimiento,
        AdmisionEstablecimientoImagen $imagen
    ): RedirectResponse {
        $perfil = $this->profileFor($request, $establecimiento);
        $this->assertImageBelongsToProfile($imagen, $perfil);
        $path = $imagen->imagen_path;
        $wasCover = (bool) $imagen->es_portada;

        DB::transaction(function () use ($imagen, $perfil, $wasCover, $request) {
            $imagen->delete();

            if ($wasCover) {
                $replacement = $perfil->imagenes()->orderBy('orden')->orderBy('id')->first();
                if ($replacement) {
                    $replacement->forceFill(['es_portada' => true])->save();
                }
            }

            $perfil->forceFill(['actualizado_por' => $request->user()->id])->save();
        });

        $this->media->delete($path);

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

        return back()->with(
            'status',
            $autoUnpublished
                ? 'Imagen eliminada. La ficha fue despublicada porque dejó de cumplir los requisitos mínimos.'
                : 'Imagen eliminada de la galería.'
        );
    }

    private function profileFor(Request $request, Establecimiento $establecimiento): AdmisionEstablecimiento
    {
        $perfil = $establecimiento->admisionPerfil()->firstOrCreate(
            [],
            [
                'slug' => AdmisionEstablecimiento::uniqueSlugFor($establecimiento),
                'estado' => AdmisionEstablecimiento::ESTADO_BORRADOR,
                'actualizado_por' => $request->user()->id,
            ]
        );

        return $perfil;
    }

    private function assertImageBelongsToProfile(
        AdmisionEstablecimientoImagen $imagen,
        AdmisionEstablecimiento $perfil
    ): void {
        abort_unless(
            (int) $imagen->admision_establecimiento_id === (int) $perfil->id,
            404
        );
    }
}
