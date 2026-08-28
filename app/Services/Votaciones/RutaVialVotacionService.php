<?php

namespace App\Services\Votaciones;

use App\Models\GrupoVotacion;
use App\Models\JornadaVotacion;
use App\Support\Votaciones\CoordenadasEstablecimiento;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class RutaVialVotacionService
{
    public function obtener(JornadaVotacion $jornada): array
    {
        $jornada->loadMissing('grupos.rutas.establecimiento');

        return [
            'proveedor' => 'OSRM / OpenStreetMap',
            'perfil' => $this->perfil(),
            'grupos' => $jornada->grupos
                ->map(fn (GrupoVotacion $grupo) => $this->obtenerGrupo($grupo))
                ->values(),
        ];
    }

    private function obtenerGrupo(GrupoVotacion $grupo): array
    {
        $rutas = $grupo->rutas
            ->filter(fn ($ruta) => $ruta->activa && CoordenadasEstablecimiento::sonValidas(
                $ruta->establecimiento?->latitud,
                $ruta->establecimiento?->longitud,
            ))
            ->values();

        $puntosDirectos = $rutas->map(fn ($ruta) => [
            (float) $ruta->establecimiento->latitud,
            (float) $ruta->establecimiento->longitud,
        ])->all();

        if ($rutas->count() < 2) {
            return $this->respuestaBase($grupo, 'sin_tramos', false, $puntosDirectos);
        }

        if (! config('votaciones.routing.enabled', true)) {
            return $this->respuestaBase($grupo, 'linea_directa', false, $puntosDirectos);
        }

        $firma = sha1(json_encode($rutas->map(fn ($ruta) => [
            $ruta->id,
            (float) $ruta->establecimiento->latitud,
            (float) $ruta->establecimiento->longitud,
        ])->all(), JSON_THROW_ON_ERROR));
        $cacheKey = "votaciones.ruta-vial.{$this->perfil()}.{$firma}";
        $failureKey = "{$cacheKey}.failure";

        if (Cache::has($failureKey)) {
            return Cache::get($failureKey);
        }

        try {
            return Cache::remember(
                $cacheKey,
                max(60, (int) config('votaciones.routing.cache_ttl_seconds', 86400)),
                fn () => $this->consultarProveedor($grupo, $rutas, $puntosDirectos),
            );
        } catch (Throwable $exception) {
            Log::warning('No fue posible calcular una ruta vial de votaciones.', [
                'grupo_votacion_id' => $grupo->id,
                'error' => $exception->getMessage(),
            ]);

            $fallback = $this->respuestaBase($grupo, 'linea_directa', false, $puntosDirectos);
            Cache::put(
                $failureKey,
                $fallback,
                max(10, (int) config('votaciones.routing.failure_cache_ttl_seconds', 60)),
            );

            return $fallback;
        }
    }

    private function consultarProveedor(GrupoVotacion $grupo, $rutas, array $puntosDirectos): array
    {
        $baseUrl = rtrim((string) config('votaciones.routing.base_url'), '/');
        if (! filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('La URL del servicio vial no es válida.');
        }

        $coordenadas = $rutas->map(fn ($ruta) => implode(',', [
            $this->decimal($ruta->establecimiento->longitud),
            $this->decimal($ruta->establecimiento->latitud),
        ]))->implode(';');

        $response = Http::acceptJson()
            ->withUserAgent('SGA-SLEP-Andalien-Costa/1.0')
            ->timeout(max(2, (int) config('votaciones.routing.timeout_seconds', 8)))
            ->get("{$baseUrl}/route/v1/{$this->perfil()}/{$coordenadas}", [
                'alternatives' => 'false',
                'steps' => 'true',
                'geometries' => 'geojson',
                'overview' => 'full',
            ]);

        $this->validarRespuesta($response);
        $route = $response->json('routes.0');
        $trazado = $this->normalizarTrazado(data_get($route, 'geometry.coordinates', []));
        if (count($trazado) < 2) {
            throw new RuntimeException('El servicio vial no devolvió una geometría utilizable.');
        }

        $legs = collect(data_get($route, 'legs', []));
        $tramos = $rutas->slice(0, -1)->values()->map(function ($ruta, int $index) use ($legs, $rutas) {
            $leg = $legs->get($index, []);
            $trazado = $this->normalizarTrazado(
                collect(data_get($leg, 'steps', []))
                    ->flatMap(fn ($step) => data_get($step, 'geometry.coordinates', []))
                    ->all(),
            );
            $siguiente = $rutas->get($index + 1);
            $esVial = count($trazado) >= 2;

            return [
                'desde_ruta_id' => $ruta->id,
                'hasta_ruta_id' => $siguiente->id,
                'distancia_m' => round((float) data_get($leg, 'distance', 0), 1),
                'duracion_s' => round((float) data_get($leg, 'duration', 0), 1),
                'tipo' => $esVial ? 'vial' : 'linea_directa',
                'trazado' => $esVial ? $trazado : [
                    [(float) $ruta->establecimiento->latitud, (float) $ruta->establecimiento->longitud],
                    [(float) $siguiente->establecimiento->latitud, (float) $siguiente->establecimiento->longitud],
                ],
            ];
        })->all();

        return [
            'grupo_id' => $grupo->id,
            'nombre' => $grupo->nombre,
            'tipo' => 'vial',
            'disponible' => true,
            'distancia_m' => round((float) data_get($route, 'distance', collect($tramos)->sum('distancia_m')), 1),
            'duracion_s' => round((float) data_get($route, 'duration', collect($tramos)->sum('duracion_s')), 1),
            'trazado' => $trazado,
            'tramos' => $tramos,
            'puntos_directos' => $puntosDirectos,
        ];
    }

    private function validarRespuesta(Response $response): void
    {
        if (! $response->successful() || $response->json('code') !== 'Ok' || ! is_array($response->json('routes.0'))) {
            throw new RuntimeException('El servicio vial no encontró una ruta para los establecimientos.');
        }
    }

    private function normalizarTrazado(array $coordenadas): array
    {
        return collect($coordenadas)
            ->filter(fn ($punto) => is_array($punto)
                && count($punto) >= 2
                && CoordenadasEstablecimiento::sonValidas($punto[1], $punto[0]))
            ->map(fn ($punto) => [(float) $punto[1], (float) $punto[0]])
            ->values()
            ->all();
    }

    private function respuestaBase(GrupoVotacion $grupo, string $tipo, bool $disponible, array $trazado): array
    {
        return [
            'grupo_id' => $grupo->id,
            'nombre' => $grupo->nombre,
            'tipo' => $tipo,
            'disponible' => $disponible,
            'distancia_m' => null,
            'duracion_s' => null,
            'trazado' => $trazado,
            'tramos' => [],
            'puntos_directos' => $trazado,
        ];
    }

    private function perfil(): string
    {
        $perfil = (string) config('votaciones.routing.profile', 'driving');

        return preg_match('/^[a-z0-9_-]+$/i', $perfil) ? $perfil : 'driving';
    }

    private function decimal(mixed $valor): string
    {
        return rtrim(rtrim(number_format((float) $valor, 7, '.', ''), '0'), '.');
    }
}
