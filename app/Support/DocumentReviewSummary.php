<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DocumentReviewSummary
{
    public static function forUser(User $user, Collection $types, Carbon $freshSince): array
    {
        $visibleIds = DocumentRules::visibleTypesFromCatalog($user, $types)->pluck('id');
        // Se conserva el cálculo de avance de carga existente en este resumen.
        $requiredIds = $types->filter(fn ($type) => $type->isRequiredForUser($user))->pluck('id');
        $documents = $user->documents->whereIn('document_type_id', $visibleIds);
        $pending = $documents->where('status', 'pending');
        // updated_at representa la última carga/reenvío; created_at respalda datos históricos.
        $dates = $pending->map(fn ($doc) => $doc->updated_at ?? $doc->created_at)->filter();
        $uploaded = $user->documents->whereIn('document_type_id', $requiredIds)->count();

        return [
            'uploaded' => $uploaded,
            'total' => $requiredIds->count(),
            'percent' => $requiredIds->isNotEmpty() ? (int) round($uploaded * 100 / $requiredIds->count()) : 0,
            'pending_count' => $pending->count(),
            'new_count' => $dates->filter(fn ($date) => $date->gte($freshSince))->count(),
            'oldest_pending_at' => $dates->min(),
            'reviewed' => $documents->isNotEmpty() && $pending->isEmpty(),
        ];
    }

    /** Ordena antes de paginar, conservando el orden de entrada en los empates. */
    public static function orderedIds(Collection $ids, Collection $summaries): Collection
    {
        return $ids->sortBy(function ($id) use ($summaries) {
            $row = $summaries[$id];
            $group = $row['pending_count'] > 0 ? 0 : ($row['reviewed'] ? 1 : 2);

            return [$group, $row['oldest_pending_at']?->getTimestamp() ?? PHP_INT_MAX];
        })->values();
    }
}
