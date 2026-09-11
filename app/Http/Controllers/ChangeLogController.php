<?php

namespace App\Http\Controllers;

use App\Support\ChangeLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;

class ChangeLogController extends Controller
{
    public function entries(Request $request): Response
    {
        $request->validate(['scope' => ['nullable', 'in:current,history'], 'page' => ['nullable', 'integer', 'min:1']]);
        abort_unless($request->user(), 401);
        $scope = $request->query('scope') ?: 'current';
        $current = ChangeLog::currentVersion();
        $visible = array_values(array_filter(ChangeLog::visibleEntriesForUser($request->user()),
            fn ($entry) => ((string) ($entry['version'] ?? '') === $current) === ($scope === 'current')));
        $total = count($visible);
        $page = min(max(1, (int) ceil($total / 10)), max(1, $request->integer('page', 1)));
        $entries = new LengthAwarePaginator(array_slice($visible, ($page - 1) * 10, 10), $total, 10, $page,
            ['path' => route('changelog.entries'), 'query' => ['scope' => $scope]]);
        unset($visible);
        return response()->view('partials.changelog-entries', compact('entries', 'scope', 'current'))
            ->header('Cache-Control', 'private, no-store')->header('X-ChangeLog-Entries', '1');
    }

    public function acknowledge(Request $request): JsonResponse|RedirectResponse
    {
        $request->session()->forget('show_changelog_modal');
        $request->session()->put('changelog_seen_version', ChangeLog::currentVersion());

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back();
    }
}
