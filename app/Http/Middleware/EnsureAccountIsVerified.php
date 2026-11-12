<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;

class EnsureAccountIsVerified extends EnsureEmailIsVerified
{
    public function handle($request, Closure $next, $redirectToRoute = null)
    {
        if ($request->user() instanceof User && $request->user()->hasVerifiedAccount()) {
            return $next($request);
        }
        return parent::handle($request, $next, $redirectToRoute);
    }
}
