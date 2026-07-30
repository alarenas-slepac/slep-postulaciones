<?php

namespace App\Services;

use App\Models\AdmisionEstablecimiento;
use App\Models\Establecimiento;

class AdmisionEscolarCompletenessService
{
    /**
     * @return array{score:int,label:string,tone:string,missing:array<int,string>,publishable:bool}
     */
    public function calculate(Establecimiento $establecimiento, ?AdmisionEstablecimiento $perfil): array
    {
        $perfil?->loadMissing('imagenes');
        $galleryCount = $perfil?->imagenes?->count() ?? 0;
        $hasCover = $perfil?->imagenes?->contains(fn ($imagen) => (bool) $imagen->es_portada) ?? false;
        $hasSocial = filled($perfil?->facebook_url) || filled($perfil?->instagram_url);
        $baseComplete = filled($establecimiento->nombre_establecimiento)
            && filled($establecimiento->rbd)
            && filled($establecimiento->comuna);

        $checks = [
            ['ok' => $baseComplete, 'weight' => 15, 'missing' => 'Datos base del establecimiento'],
            ['ok' => filled($perfil?->sello_educativo), 'weight' => 15, 'missing' => 'Sello educativo'],
            ['ok' => filled($perfil?->descripcion_corta), 'weight' => 5, 'missing' => 'Descripción corta'],
            ['ok' => filled($perfil?->director_nombre), 'weight' => 10, 'missing' => 'Nombre del director o directora'],
            ['ok' => filled($perfil?->director_foto_path), 'weight' => 10, 'missing' => 'Fotografía del director o directora'],
            ['ok' => filled($perfil?->logo_path), 'weight' => 10, 'missing' => 'Logo del establecimiento'],
            ['ok' => $hasCover, 'weight' => 10, 'missing' => 'Imagen de portada'],
            [
                'ok' => $galleryCount >= max(1, (int) config('admision.min_imagenes_publicacion', 1)),
                'weight' => 15,
                'missing' => 'Galería de imágenes',
            ],
            ['ok' => filled($perfil?->sitio_web_url), 'weight' => 5, 'missing' => 'Sitio web'],
            ['ok' => $hasSocial, 'weight' => 5, 'missing' => 'Facebook o Instagram'],
        ];

        $score = collect($checks)->filter(fn ($check) => $check['ok'])->sum('weight');
        $missing = collect($checks)->reject(fn ($check) => $check['ok'])->pluck('missing')->values()->all();
        $publicationMissing = $this->publicationMissing($establecimiento, $perfil);

        [$label, $tone] = match (true) {
            $score >= 100 => ['Completa', 'success'],
            $score >= 80 => ['Casi completa', 'primary'],
            $score >= 50 => ['En progreso', 'warning'],
            default => ['Incompleta', 'danger'],
        };

        return [
            'score' => (int) $score,
            'label' => $label,
            'tone' => $tone,
            'missing' => $missing,
            'publishable' => $publicationMissing === [],
        ];
    }

    /**
     * Requisitos mínimos obligatorios para publicar. Los enlaces sociales y
     * la descripción corta mejoran la completitud, pero no bloquean el alta.
     *
     * @return array<int, string>
     */
    public function publicationMissing(Establecimiento $establecimiento, ?AdmisionEstablecimiento $perfil): array
    {
        $perfil?->loadMissing('imagenes');
        $images = $perfil?->imagenes ?? collect();
        $minimum = max(1, (int) config('admision.min_imagenes_publicacion', 1));

        $requirements = [
            'Nombre del establecimiento' => filled($establecimiento->nombre_establecimiento),
            'RBD' => filled($establecimiento->rbd),
            'Comuna' => filled($establecimiento->comuna),
            'Sello educativo' => filled($perfil?->sello_educativo),
            'Director o directora' => filled($perfil?->director_nombre),
            'Fotografía del director o directora' => filled($perfil?->director_foto_path),
            'Logo del establecimiento' => filled($perfil?->logo_path),
            'Imagen de portada' => $images->contains(fn ($imagen) => (bool) $imagen->es_portada),
            "Galería con al menos {$minimum} imagen(es)" => $images->count() >= $minimum,
        ];

        return collect($requirements)
            ->reject(fn ($complete) => $complete)
            ->keys()
            ->values()
            ->all();
    }
}
