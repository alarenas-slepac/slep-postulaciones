<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Cache;

class TouchLastSeen
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (auth()->check()) {
            $user = auth()->user();

            // Limitamos a 60s para evitar escrituras constantes
            $key = "last_seen_touch:{$user->id}";
            if (!Cache::has($key)) {
                $user->forceFill(['last_seen_at' => now()])->saveQuietly();
                Cache::put($key, true, 60); // 60 segundos
            }
        }

        return $response;
    }
}
