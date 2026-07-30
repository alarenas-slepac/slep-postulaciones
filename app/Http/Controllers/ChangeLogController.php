<?php

namespace App\Http\Controllers;

use App\Support\ChangeLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ChangeLogController extends Controller
{
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
