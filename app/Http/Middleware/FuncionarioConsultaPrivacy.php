<?php

namespace App\Http\Middleware;

use App\Models\FuncionarioApiIntegration;
use App\Models\User;
use App\Services\Funcionarios\ConsultaRut;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class FuncionarioConsultaPrivacy
{
    public function handle(Request $request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');
        $id = (string) Str::uuid();
        try {
            $ingress = 'funcionario-consulta-ingreso:'.hash('sha256', (string) $request->ip());
            if (!RateLimiter::attempt($ingress, (int) config('funcionario_consulta.unauthenticated_requests_per_minute'), fn () => true, 60)) {
                $response = response()->json(['error' => 'limite_excedido', 'message' => 'Alcanzaste el límite de consultas. Intenta más tarde.'], 429)
                    ->header('Retry-After', RateLimiter::availableIn($ingress));
            } else {
                $response = $next($request);
            }
        } catch (\Throwable $error) {
            $status = match (true) {
                $error instanceof AuthenticationException => 401,
                $error instanceof AuthorizationException => 403,
                $error instanceof HttpExceptionInterface => $error->getStatusCode(),
                default => 503,
            };
            $response = response()->json(['error' => match ($status) {
                401 => 'no_autenticado', 403 => 'sin_permiso', 429 => 'limite_excedido', default => 'servicio_no_disponible',
            }, 'message' => match ($status) {
                401 => 'Se requiere autenticación válida.', 403 => 'No tienes acceso a esta consulta.',
                429 => 'Alcanzaste el límite de consultas. Intenta más tarde.',
                default => 'No fue posible completar la consulta. Intenta más tarde.',
            }], $status);
        }
        // Laravel convierte excepciones de los middleware interiores en respuestas.
        // Normalizar también esas respuestas para que ni APP_DEBUG exponga detalles.
        $status = $response->getStatusCode();
        if (in_array($status, [401, 403], true) || $status >= 500) {
            $status = $status >= 500 ? 503 : $status;
            $response = response()->json(['error' => match ($status) {
                401 => 'no_autenticado', 403 => 'sin_permiso', default => 'servicio_no_disponible',
            }, 'message' => match ($status) {
                401 => 'Se requiere autenticación válida.', 403 => 'No tienes acceso a esta consulta.',
                default => 'No fue posible completar la consulta. Intenta más tarde.',
            }], $status);
        }
        try {
            $actor = $request->user();
            if ($actor instanceof User || $actor instanceof FuncionarioApiIntegration) {
                $rut = ConsultaRut::normalize($request->json('rut'));
                DB::table('funcionario_consulta_audits')->insert([
                    'request_id' => $id, 'user_id' => $actor instanceof User ? $actor->id : null,
                    'integration_id' => $actor instanceof FuncionarioApiIntegration ? $actor->id : null,
                    'rut_fingerprint' => $rut ? hash_hmac('sha256', $rut, (string) config('app.key')) : null,
                    'http_status' => $response->getStatusCode(),
                    'outcome' => $response->getStatusCode() === 200 ? ($response->getData(true)['estado'] ?? 'completado') : 'http_'.$response->getStatusCode(),
                    'created_at' => now(),
                ]);
            }
        } catch (\Throwable) {
            // Fallo cerrado: tampoco divulgar datos si no se pudo registrar la consulta.
            $response = response()->json(['error' => 'servicio_no_disponible', 'message' => 'No fue posible completar la consulta. Intenta más tarde.'], 503);
        }
        return $response->withHeaders(['Cache-Control' => 'no-store, private', 'Pragma' => 'no-cache',
            'Referrer-Policy' => 'no-referrer', 'X-Content-Type-Options' => 'nosniff', 'X-Request-ID' => $id]);
    }
}
