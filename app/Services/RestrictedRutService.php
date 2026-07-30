<?php

namespace App\Services;

use App\Models\PostulantProfile;
use App\Models\RestrictedRut;
use App\Models\RestrictedRutManualRecord;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class RestrictedRutService
{
    public function isAvailable(): bool
    {
        return Schema::hasTable('restricted_ruts')
            && Schema::hasTable('restricted_rut_court_records')
            && Schema::hasTable('restricted_rut_manual_records');
    }

    public function normalizeRut(?string $rut): string
    {
        return strtoupper((string) preg_replace('/[^0-9Kk]/', '', (string) $rut));
    }

    public function isRestrictedRut(?string $rut, ?CarbonInterface $when = null): bool
    {
        $normalized = $this->normalizeRut($rut);
        if ($normalized === '' || !$this->isAvailable()) {
            return false;
        }

        return $this->restrictedRutsQuery($when)
            ->where('rut_normalized', $normalized)
            ->exists();
    }

    public function hasCourtRestrictionRut(?string $rut, ?CarbonInterface $when = null): bool
    {
        $normalized = $this->normalizeRut($rut);
        if ($normalized === '' || !$this->isAvailable()) {
            return false;
        }

        return $this->courtRestrictedRutsQuery($when)
            ->where('rut_normalized', $normalized)
            ->exists();
    }

    public function hasManualRestrictionRut(?string $rut, ?CarbonInterface $when = null): bool
    {
        $normalized = $this->normalizeRut($rut);
        if ($normalized === '' || !$this->isAvailable()) {
            return false;
        }

        return $this->manualRestrictedRutsQuery($when)
            ->where('rut_normalized', $normalized)
            ->exists();
    }

    public function isRestrictedUser(?User $user, ?CarbonInterface $when = null): bool
    {
        return $user ? $this->isRestrictedRut($user->rut, $when) : false;
    }

    public function hasCourtRestrictionUser(?User $user, ?CarbonInterface $when = null): bool
    {
        return $user ? $this->hasCourtRestrictionRut($user->rut, $when) : false;
    }

    public function hasManualRestrictionUser(?User $user, ?CarbonInterface $when = null): bool
    {
        return $user ? $this->hasManualRestrictionRut($user->rut, $when) : false;
    }

    public function isRestrictedPostulantProfile(?PostulantProfile $profile, ?CarbonInterface $when = null): bool
    {
        $user = $profile?->relationLoaded('user') ? $profile->user : $profile?->user()->first();
        return $user ? $this->isRestrictedRut($user->rut, $when) : false;
    }

    public function hasCourtRestrictionPostulantProfile(?PostulantProfile $profile, ?CarbonInterface $when = null): bool
    {
        $user = $profile?->relationLoaded('user') ? $profile->user : $profile?->user()->first();
        return $user ? $this->hasCourtRestrictionRut($user->rut, $when) : false;
    }

    public function hasManualRestrictionPostulantProfile(?PostulantProfile $profile, ?CarbonInterface $when = null): bool
    {
        $user = $profile?->relationLoaded('user') ? $profile->user : $profile?->user()->first();
        return $user ? $this->hasManualRestrictionRut($user->rut, $when) : false;
    }

    public function restrictedRutsQuery(?CarbonInterface $when = null): Builder
    {
        $date = ($when ?: now())->toDateString();

        return RestrictedRut::query()
            ->select('restricted_ruts.rut_normalized')
            ->where(function (Builder $query) use ($date) {
                $query->whereHas('courtRecord', function (Builder $court) {
                    $court->where('activa', true);
                })->orWhereHas('manualRecord', function (Builder $manual) use ($date) {
                    $manual->where('activa', true)
                        ->whereDate('fecha_inicio_prohibicion', '<=', $date)
                        ->whereDate('fecha_termino_prohibicion', '>=', $date);
                });
            });
    }

    public function courtRestrictedRutsQuery(?CarbonInterface $when = null): Builder
    {
        return RestrictedRut::query()
            ->select('restricted_ruts.rut_normalized')
            ->whereHas('courtRecord', function (Builder $court) {
                $court->where('activa', true);
            });
    }

    public function manualRestrictedRutsQuery(?CarbonInterface $when = null): Builder
    {
        $date = ($when ?: now())->toDateString();

        return RestrictedRut::query()
            ->select('restricted_ruts.rut_normalized')
            ->whereHas('manualRecord', function (Builder $manual) use ($date) {
                $manual->where('activa', true)
                    ->whereDate('fecha_inicio_prohibicion', '<=', $date)
                    ->whereDate('fecha_termino_prohibicion', '>=', $date);
            });
    }

    public function manualRestrictionRecordForRut(?string $rut, ?CarbonInterface $when = null): ?RestrictedRutManualRecord
    {
        $normalized = $this->normalizeRut($rut);
        if ($normalized === '' || !$this->isAvailable()) {
            return null;
        }

        $date = ($when ?: now())->toDateString();

        $restrictedRut = RestrictedRut::query()
            ->with(['manualRecord'])
            ->where('rut_normalized', $normalized)
            ->whereHas('manualRecord', function (Builder $manual) use ($date) {
                $manual->where('activa', true)
                    ->whereDate('fecha_inicio_prohibicion', '<=', $date)
                    ->whereDate('fecha_termino_prohibicion', '>=', $date);
            })
            ->first();

        return $restrictedRut?->manualRecord;
    }

    public function restrictionContextForRut(?string $rut, ?CarbonInterface $when = null): array
    {
        $normalized = $this->normalizeRut($rut);
        if ($normalized === '' || !$this->isAvailable()) {
            return $this->emptyContext();
        }

        $date = ($when ?: now())->toDateString();

        $restrictedRut = RestrictedRut::query()
            ->with(['courtRecord', 'manualRecord'])
            ->where('rut_normalized', $normalized)
            ->first();

        if (!$restrictedRut) {
            return $this->emptyContext();
        }

        $courtRecord = $restrictedRut->courtRecord;
        $courtActive = (bool) optional($courtRecord)->activa;
        $manualRecord = $restrictedRut->manualRecord;
        $manualActive = false;

        if ($manualRecord && $manualRecord->activa) {
            $start = optional($manualRecord->fecha_inicio_prohibicion)->toDateString();
            $end = optional($manualRecord->fecha_termino_prohibicion)->toDateString();
            $manualActive = $start && $end && $start <= $date && $end >= $date;
        }

        return [
            'court_active' => $courtActive,
            'manual_active' => $manualActive,
            'blocked' => $courtActive || $manualActive,
            'court_name' => $courtActive ? trim((string) ($courtRecord?->nombre ?? '')) : '',
            'court_run' => $courtActive ? trim((string) ($courtRecord?->run_original ?? '')) : '',
            'court_juzgado_origen' => $courtActive ? trim((string) ($courtRecord?->juzgado_origen ?? '')) : '',
            'court_rit' => $courtActive ? trim((string) ($courtRecord?->rit ?? '')) : '',
            'court_fecha_fallo' => $courtActive ? optional($courtRecord?->fecha_fallo)->format('d/m/Y') : null,
            'court_inhabilidad' => $courtActive ? trim((string) ($courtRecord?->inhabilidad_texto ?? '')) : '',
            'manual_comment' => $manualActive ? trim((string) ($manualRecord?->comentario ?? '')) : '',
            'manual_start' => $manualActive ? optional($manualRecord?->fecha_inicio_prohibicion)->format('d/m/Y') : null,
            'manual_end' => $manualActive ? optional($manualRecord?->fecha_termino_prohibicion)->format('d/m/Y') : null,
        ];
    }

    public function restrictionContextForUser(?User $user, ?CarbonInterface $when = null): array
    {
        return $user ? $this->restrictionContextForRut($user->rut, $when) : $this->emptyContext();
    }

    public function restrictionContextForPostulantProfile(?PostulantProfile $profile, ?CarbonInterface $when = null): array
    {
        $user = $profile?->relationLoaded('user') ? $profile->user : $profile?->user()->first();
        return $user ? $this->restrictionContextForRut($user->rut, $when) : $this->emptyContext();
    }

    public function currentStatus(RestrictedRut $restrictedRut, ?CarbonInterface $when = null): array
    {
        $date = ($when ?: now())->toDateString();
        $courtActive = (bool) optional($restrictedRut->courtRecord)->activa;
        $manual = $restrictedRut->manualRecord;
        $manualActive = false;

        if ($manual && $manual->activa) {
            $start = optional($manual->fecha_inicio_prohibicion)->toDateString();
            $end = optional($manual->fecha_termino_prohibicion)->toDateString();
            $manualActive = $start && $end && $start <= $date && $end >= $date;
        }

        return [
            'court_active' => $courtActive,
            'manual_active' => $manualActive,
            'blocked' => $courtActive || $manualActive,
        ];
    }

    private function emptyContext(): array
    {
        return [
            'court_active' => false,
            'manual_active' => false,
            'blocked' => false,
            'court_name' => '',
            'court_run' => '',
            'court_juzgado_origen' => '',
            'court_rit' => '',
            'court_fecha_fallo' => null,
            'court_inhabilidad' => '',
            'manual_comment' => '',
            'manual_start' => null,
            'manual_end' => null,
        ];
    }
}
