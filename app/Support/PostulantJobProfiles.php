<?php

namespace App\Support;

use App\Models\PostulantJobProfile;
use App\Models\PostulantProfile;
use App\Models\User;
use Illuminate\Support\Collection;

class PostulantJobProfiles
{
    /** @return Collection<string, PostulantProfile|PostulantJobProfile> */
    public static function forUser(User $user): Collection
    {
        $profile = $user->postulantProfile;
        if (! $profile || blank($profile->estamento)) {
            return collect();
        }

        $profile->loadMissing('areaDesempeno');

        return collect(['principal' => $profile])->union(
            self::additional($profile)->keyBy(fn (PostulantJobProfile $job) => 'cargo-'.$job->id)
        );
    }

    public static function additional(PostulantProfile $profile): Collection
    {
        if (! $profile->relationLoaded('jobProfiles')) {
            if (! $profile->getConnection()->getSchemaBuilder()->hasTable('postulant_job_profiles')) {
                $profile->setRelation('jobProfiles', collect());
            } else {
                $profile->load('jobProfiles.areaDesempeno');
            }
        }

        return $profile->jobProfiles->where('activo', true)->whereNotNull('area_desempeno_id');
    }

    public static function forReplacement(PostulantProfile $profile, ?string $estamento, int $areaId, bool $onlyParvulos = false, array $parvulosIds = []): PostulantProfile|PostulantJobProfile|null
    {
        return self::additional($profile)->prepend($profile)->filter(function ($career) use ($estamento, $onlyParvulos, $parvulosIds) {
            $parvulos = in_array((int) $career->area_desempeno_id, $parvulosIds, true);

            return $parvulos || (! $onlyParvulos && $career->estamento === $estamento);
        })->sortByDesc(fn ($career) => $areaId > 0 && (int) $career->area_desempeno_id === $areaId)->first();
    }

    public static function label(PostulantProfile|PostulantJobProfile $profile): string
    {
        $estamento = $profile->estamento === 'docente' ? 'Docente' : 'Asistente de la Educación';

        return implode(' · ', array_filter([
            $estamento, $profile->area_desempeno_nombre, $profile->getAttribute('titulo'),
            $profile->especialidad_tp ?: $profile->mencion,
        ]));
    }
}
