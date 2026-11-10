<?php

namespace App\Services\Votaciones;

use App\Models\JornadaVotacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ParticipacionVotacionService
{
    /** Sólo agregados: no carga ni entrega identidades del padrón a la vista. */
    public function resumir(?JornadaVotacion $jornada, array $resumen): array
    {
        return $this->paraVista($jornada, $resumen)['participacion'];
    }

    public function paraVista(?JornadaVotacion $jornada, array $resumen): array
    {
        return DB::transaction(function () use ($jornada, $resumen): array {
            // Mismo bloqueo que publicación: cifra, mensaje y documento pertenecen a una sola versión.
            $locked = $jornada ? JornadaVotacion::whereKey($jornada->id)->sharedLock()->firstOrFail() : null;
            $definitivos = app(PadronDefinitivoService::class);
            $version = $definitivos->publicado($locked);
            $padron = $version?->personas ?? ($locked && Schema::hasTable('votacion_padron_consulta')
                ? $locked->padronConsulta()->whereNotNull('rut')->where('rut', '!=', '')->distinct()->count('rut') : 0);
            return ['participacion' => $this->calcular($resumen, $padron), 'padronDefinitivo' => $definitivos->contexto($version)];
        });
    }

    public function calcular(array $resumen, int $padron): array
    {
        // El período ya está seleccionado por el controlador. Nunca sumar
        // papeletas distintas ni incluir días ajenos a la publicación vigente.
        $votantes = (int) collect($resumen)->max('emitidos');

        return [
            'votantes' => max(0, $votantes), 'padron' => $padron,
            'porcentaje' => $padron > 0 ? round(max(0, $votantes) / $padron * 100, 1) : null,
        ];
    }
}
