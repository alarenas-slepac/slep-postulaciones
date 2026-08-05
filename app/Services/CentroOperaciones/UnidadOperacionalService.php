<?php

namespace App\Services\CentroOperaciones;

use App\Models\Establecimiento;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class UnidadOperacionalService
{
    /** @return Collection<string, array<string, mixed>> */
    public function paraEstablecimiento(Establecimiento $establecimiento): Collection
    {
        $nombre = $this->normalizar((string) $establecimiento->nombre_establecimiento);

        return collect(config('centro_operaciones.unidades_operacionales', []))
            ->filter(function (array $unidad) use ($nombre) {
                $fragmento = $this->normalizar((string) ($unidad['establecimiento_nombre_contiene'] ?? ''));

                return $fragmento !== '' && Str::contains($nombre, $fragmento);
            });
    }

    /** @return array<string, mixed>|null */
    public function obtener(Establecimiento $establecimiento, ?string $codigo): ?array
    {
        if ($codigo === null || $codigo === '') {
            return null;
        }

        $unidad = $this->paraEstablecimiento($establecimiento)->get($codigo);

        return $unidad ? ['codigo' => $codigo] + $unidad : null;
    }

    /** @return array<int, array{codigo:?string,label:string,nombre_reporte:string}> */
    public function opciones(Establecimiento $establecimiento): array
    {
        $principal = [[
            'codigo' => null,
            'label' => 'Establecimiento principal',
            'nombre_reporte' => $establecimiento->nombre_establecimiento,
        ]];

        $unidades = $this->paraEstablecimiento($establecimiento)
            ->map(fn (array $unidad, string $codigo) => [
                'codigo' => $codigo,
                'label' => (string) $unidad['label'],
                'nombre_reporte' => (string) $unidad['nombre_reporte'],
            ])
            ->values()
            ->all();

        return [...$principal, ...$unidades];
    }

    public function codigoPermitido(Establecimiento $establecimiento, ?string $codigo): bool
    {
        return $codigo === null || $codigo === '' || $this->obtener($establecimiento, $codigo) !== null;
    }

    public function nombreReporte(Establecimiento $establecimiento, ?string $codigo): string
    {
        return (string) ($this->obtener($establecimiento, $codigo)['nombre_reporte']
            ?? $establecimiento->nombre_establecimiento);
    }

    public function clave(int $establecimientoId, ?string $codigo): string
    {
        return $establecimientoId.'|'.($codigo ?: 'principal');
    }

    private function normalizar(string $valor): string
    {
        return Str::lower(Str::ascii(trim($valor)));
    }
}
