<?php

namespace App\Services\Remuneraciones;

use App\Models\ReemplazoPersonal;
use App\Support\RutChile;
use Illuminate\Support\Facades\Schema;

class ReemplazoPersonalRutService
{
    public function normalizar(?string $rut): ?string
    {
        $normalizado = RutChile::normalize($rut);

        if (! $normalizado || $normalizado['status'] !== 'ok') {
            return null;
        }

        return strtoupper($normalizado['rut_body'].'-'.$normalizado['rut_dv']);
    }

    public function buscar(?string $rut): ?array
    {
        $rutNormalizado = $this->normalizar($rut);
        if (! $rutNormalizado || ! Schema::hasTable('reemplazos_personal')) {
            return null;
        }

        $rutPlano = str_replace('-', '', $rutNormalizado);
        $registro = ReemplazoPersonal::query()
            ->where(function ($query) use ($rutNormalizado, $rutPlano) {
                $query->where('rut', $rutNormalizado)
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(UPPER(rut), '.', ''), '-', ''), ' ', '') = ?", [$rutPlano]);
            })
            ->whereNotNull('nombre')
            ->where('nombre', '!=', '')
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->orderByDesc('id')
            ->first();

        if (! $registro) {
            return null;
        }

        $nombre = trim(preg_replace('/\s+/', ' ', (string) $registro->nombre) ?: '');
        if ($nombre === '') {
            return null;
        }

        return [
            'rut' => $rutNormalizado,
            'nombre' => $nombre,
            'periodo' => sprintf('%04d-%02d', $registro->anio, $registro->mes),
        ];
    }
}
