<?php

namespace App\Http\Middleware;

use App\Models\FuncionarioApiIntegration;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class AuthorizeFuncionarioIntegration
{
    public function handle(Request $request, Closure $next)
    {
        $actor = $request->user();
        abort_unless($actor instanceof FuncionarioApiIntegration && $actor->enabled
            && $actor->currentAccessToken() instanceof PersonalAccessToken
            && $actor->currentAccessToken()->expires_at?->isFuture()
            && $actor->tokenCan(FuncionarioApiIntegration::ABILITY), 403);
        abort_if(app()->environment('production') && !$request->isSecure(), 403);
        return $next($request);
    }
}
