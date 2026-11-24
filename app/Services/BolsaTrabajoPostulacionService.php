<?php

namespace App\Services;

use App\Models\BolsaTrabajoOferta;
use App\Models\BolsaTrabajoPostulacion;
use App\Models\DocumentType;
use App\Models\PostulantJobProfile;
use App\Models\PostulantProfile;
use App\Models\User;
use App\Models\UserDocument;
use App\Support\DocumentRules;
use App\Support\PostulantJobProfiles;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BolsaTrabajoPostulacionService
{
    public function compatibleProfiles(User $user, BolsaTrabajoOferta $offer): Collection
    {
        return PostulantJobProfiles::forUser($user)->filter(function ($profile) use ($offer) {
            if ($profile->estamento !== $offer->estamento) {
                return false;
            }
            if (! $offer->area_desempeno_id || (int) $profile->area_desempeno_id === (int) $offer->area_desempeno_id) {
                return true;
            }

            // Los perfiles antiguos pueden tener el área guardada por nombre.
            $legacyArea = $profile instanceof PostulantProfile && ! $profile->area_desempeno_id ? $profile->area_desempeno_nombre : null;

            return filled($legacyArea) && mb_strtolower(trim($legacyArea)) === mb_strtolower(trim($offer->areaDesempeno?->nombre ?? ''));
        });
    }

    public function requirements(
        User $user,
        BolsaTrabajoOferta $offer,
        PostulantProfile|PostulantJobProfile $profile,
        Collection $catalog,
        Collection $documents,
        array $selection = [],
    ): Collection {
        $specific = collect($offer->requisitos_documentales)->keyBy('document_type_id');
        $base = DocumentRules::requiredTypesFromCatalog($user, $catalog, $profile)->pluck('id');

        return $catalog->whereIn('id', $base->merge($specific->keys()))->map(function (DocumentType $type) use ($profile, $specific, $documents, $selection) {
            $rule = $specific->get($type->id, []);
            $files = $documents->where('document_type_id', $type->id)
                ->filter(fn (UserDocument $doc) => filled($doc->path) && Storage::disk($doc->disk ?? 'public')->exists($doc->path));
            $available = $files->filter(fn (UserDocument $doc) => $this->isAcceptable($doc, $rule))->values();
            $requested = $selection[$type->id] ?? null;
            $choose = $type->supportsMultipleCredentials() && ($available->count() > 1 || $profile instanceof PostulantJobProfile);
            $document = $requested !== null ? $available->firstWhere('id', (int) $requested) : ($choose ? null : $available->first());
            $status = match (true) {
                $document !== null => 'available',
                $available->isNotEmpty() => 'choose',
                $files->isNotEmpty() => 'review',
                default => 'missing',
            };

            return compact('type', 'available', 'document', 'status', 'rule');
        })->values();
    }

    protected function isAcceptable(UserDocument $document, array $rule): bool
    {
        if (! in_array($document->status, ['pending', 'approved'], true)
            || (($rule['aprobado'] ?? false) && $document->status !== 'approved')) {
            return false;
        }

        $days = (int) ($rule['vigencia_dias'] ?? 0);
        if ($days === 0) {
            return true;
        }
        $today = BolsaTrabajoOferta::portalNow()->startOfDay();

        return $document->issued_on !== null
            && $document->issued_on->toDateString() <= $today->toDateString()
            && $document->issued_on->toDateString() >= $today->subDays($days)->toDateString();
    }

    public function submit(User $user, BolsaTrabajoOferta $offer, string $profileKey, array $selection): bool
    {
        $connection = $user->getConnection();

        return $connection->transaction(function () use ($user, $offer, $profileKey, $selection, $connection) {
            // Las cargas y las postulaciones bloquean la misma fila del usuario.
            $lockedUser = User::on($connection->getName())->lockForUpdate()->findOrFail($user->id);
            $lockedOffer = BolsaTrabajoOferta::on($connection->getName())->lockForUpdate()->findOrFail($offer->id);
            if ($lockedOffer->postulaciones()->where('user_id', $user->id)->exists()) {
                return false;
            }
            $now = BolsaTrabajoOferta::portalNow();
            if (! $lockedOffer->isVisibleEnPortal($now) || ! $lockedOffer->isPostulacionAbierta($now)) {
                throw ValidationException::withMessages(['oferta' => 'La oferta ya no está abierta para postular.']);
            }
            $profile = $this->compatibleProfiles($lockedUser, $lockedOffer)->get($profileKey);
            if (! $profile) {
                throw ValidationException::withMessages(['perfil_laboral' => 'Elige un cargo de tu perfil que coincida con la oferta.']);
            }

            $catalog = DocumentType::on($connection->getName())->orderBy('sort_order')->orderBy('label')->get();
            $library = $lockedUser->documents()->lockForUpdate()->get();
            $requirements = $this->requirements($lockedUser, $lockedOffer, $profile, $catalog, $library, $selection);
            $pending = $requirements->whereNull('document');
            if ($pending->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'documentos' => 'Completa o selecciona los documentos de la oferta: '.$pending->pluck('type.label')->join(', ').'.',
                ]);
            }

            $postulation = $lockedOffer->postulaciones()->create([
                'user_id' => $lockedUser->id,
                'postulant_profile_id' => $lockedUser->postulantProfile->id,
                'estado' => 'postulado',
                'cargo_presentado' => [
                    'etiqueta' => PostulantJobProfiles::label($profile),
                    'estamento' => $profile->estamento,
                    'area_desempeno_id' => $profile->area_desempeno_id,
                    'area' => $profile->area_desempeno_nombre,
                    'titulo' => $profile->getAttribute('titulo'),
                    'mencion' => $profile->mencion,
                    'especialidad_tp' => $profile->especialidad_tp,
                    'nivel_estudios' => $profile->nivel_estudios,
                ],
            ]);
            $postulation->submittedDocuments()->attach($requirements->pluck('document.id')->all());

            return true;
        }, 3);
    }
}
