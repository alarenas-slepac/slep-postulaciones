<?php

namespace App\Support;

use App\Models\User;

/** Solo indicadores; no retiene entradas ni HTML. Instancia acotada a la petición. */
class ChangeLogViewData
{
    private \WeakMap $summaries;

    public function __construct()
    {
        $this->summaries = new \WeakMap;
    }

    public function forUser(?User $user): array
    {
        $version = ChangeLog::currentVersion();
        if ($user && isset($this->summaries[$user][$version])) {
            return $this->summaries[$user][$version];
        }
        $current = $previous = false;
        foreach (ChangeLog::visibleEntriesForUser($user) as $entry) {
            if ((string) ($entry['version'] ?? '') === $version) {
                $current = true;
            } else {
                $previous = true;
            }
        }
        $data = [
            'currentAppVersion' => $version,
            'hasVisibleChangeLogEntries' => $current || $previous,
            'hasCurrentChangeLogEntries' => $current,
            'hasPreviousChangeLogEntries' => $previous,
        ];
        if ($user) {
            $this->summaries[$user] = [$version => $data];
        }
        return $data;
    }
}
