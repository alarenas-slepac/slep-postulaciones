<?php

namespace App\Providers;

use App\Models\FuncionarioApiIntegration;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class FuncionarioConsultaServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->group(base_path('routes/funcionario_consulta.php'));
        RateLimiter::for('funcionario-consulta', function (Request $request) {
            $actor = $request->user();
            $limit = $actor instanceof FuncionarioApiIntegration ? $actor->requests_per_minute
                : config('funcionario_consulta.requests_per_minute');
            return Limit::perMinute(max(1, min((int) $limit, config('funcionario_consulta.max_requests_per_minute'))))
                ->by(($actor instanceof FuncionarioApiIntegration ? 'integration:' : 'user:').$actor->id)
                ->response($this->limited(...));
        });
    }

    private function limited(Request $request, array $headers)
    {
        return response()->json(['error' => 'limite_excedido', 'message' => 'Alcanzaste el límite de consultas. Intenta más tarde.'], 429, $headers);
    }
}
