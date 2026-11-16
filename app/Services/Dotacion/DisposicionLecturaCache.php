<?php

namespace App\Services\Dotacion;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** Caché privada de fuentes; nunca guarda saldos distribuidos ni autorizaciones. */
class DisposicionLecturaCache
{
    private ?string $generacion = null;

    private function prefijo(): string
    {
        $conexion = config('database.default');
        return 'dotacion:disposicion:v2:'.hash('sha256', base_path().'|'.$conexion.'|'.config('database.connections.'.$conexion.'.database')).':';
    }

    public function recordar(string $clave, Closure $calculo): mixed
    {
        $segundos = (int) config('dotacion_disposicion.cache_seconds', 120);
        if ($segundos <= 0) {
            return $calculo();
        }
        try {
            $this->generacion ??= Cache::rememberForever($this->prefijo().'generacion', fn () => (string) Str::uuid());
            $generacion = $this->generacion;
            // Claves estables: no dejar una fila huérfana por cada generación
            // en almacenes de base de datos que no purgan automáticamente TTL.
            $cacheKey = $this->prefijo().$clave;
            $guardado = Cache::get($cacheKey);
            if (is_array($guardado) && ($guardado['generacion'] ?? null) === $generacion && array_key_exists('valor', $guardado)) {
                return $guardado['valor'];
            }
        } catch (\Throwable) {
            // Una interrupción del almacén no impide consultar la fuente real.
            return $calculo();
        }
        // Los errores del cálculo no se ocultan ni se vuelve a ejecutar el cálculo.
        $valor = $calculo();
        try {
            Cache::put($cacheKey, ['generacion' => $generacion, 'valor' => $valor], $segundos);
        } catch (\Throwable) {
            // El dato ya se consultó correctamente; la caché es prescindible.
        }
        return $valor;
    }

    public function invalidar(): void
    {
        $this->generacion = null;
        try {
            Cache::forever($this->prefijo().'generacion', (string) Str::uuid());
        } catch (\Throwable) {
            // Nunca convertir un guardado ya confirmado en un error de negocio.
        }
    }
}
