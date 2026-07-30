<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Arr;

class ChangeLog
{
    public static function currentVersion(): string
    {
        return (string) (config('changelog.current_version') ?: config('app.version') ?: env('APP_VERSION', '2026.3.2'));
    }

    /** @return array<int, array<string, mixed>> */
    public static function visibleEntriesForUser(?User $user): array
    {
        if (!$user) {
            return [];
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            return collect(config('changelog.entries', []))
                ->sortByDesc(fn ($entry) => (string) data_get($entry, 'published_at', data_get($entry, 'version', '')))
                ->values()
                ->all();
        }

        $userRoles = method_exists($user, 'getRoleNames')
            ? $user->getRoleNames()->map(fn ($role) => (string) $role)->values()->all()
            : [];

        return collect(config('changelog.entries', []))
            ->filter(function ($entry) use ($userRoles) {
                $roles = collect(Arr::wrap(data_get($entry, 'roles', [])))
                    ->filter(fn ($role) => filled($role))
                    ->map(fn ($role) => (string) $role)
                    ->values()
                    ->all();

                if (empty($roles)) {
                    return true;
                }

                return count(array_intersect($roles, $userRoles)) > 0;
            })
            ->sortByDesc(fn ($entry) => (string) data_get($entry, 'published_at', data_get($entry, 'version', '')))
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public static function currentVersionEntriesForUser(?User $user): array
    {
        $current = self::currentVersion();

        return collect(self::visibleEntriesForUser($user))
            ->filter(fn ($entry) => (string) data_get($entry, 'version') === $current)
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public static function previousVersionEntriesForUser(?User $user): array
    {
        $current = self::currentVersion();

        return collect(self::visibleEntriesForUser($user))
            ->filter(fn ($entry) => (string) data_get($entry, 'version') !== $current)
            ->values()
            ->all();
    }

    public static function hasAnyVisibleEntries(?User $user): bool
    {
        return !empty(self::visibleEntriesForUser($user));
    }
}
