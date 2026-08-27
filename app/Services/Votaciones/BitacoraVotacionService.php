<?php

namespace App\Services\Votaciones;

use App\Models\BitacoraVotacion;
use App\Models\GrupoVotacion;
use App\Models\JornadaVotacion;
use App\Models\RutaVotacion;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class BitacoraVotacionService
{
    public function registrar(JornadaVotacion $jornada, string $evento, string $descripcion, ?User $usuario = null, ?GrupoVotacion $grupo = null, ?RutaVotacion $ruta = null, array $metadata = []): BitacoraVotacion
    {
        Cache::forget("votaciones.publica.{$jornada->id}");

        return BitacoraVotacion::create([
            'jornada_votacion_id' => $jornada->id,
            'grupo_votacion_id' => $grupo?->id,
            'ruta_votacion_id' => $ruta?->id,
            'user_id' => $usuario?->id,
            'evento' => $evento,
            'descripcion' => $descripcion,
            'metadata' => $metadata ?: null,
        ]);
    }
}
