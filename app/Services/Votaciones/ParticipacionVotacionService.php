<?php

namespace App\Services\Votaciones;

use App\Models\JornadaVotacion;
use Illuminate\Support\Facades\Schema;

class ParticipacionVotacionService
{
    /** Sólo agregados: no carga ni entrega identidades del padrón a la vista. */
    public function resumir(?JornadaVotacion $jornada, array $resumen): array
    {
        $padron = $jornada && Schema::hasTable('votacion_padron_consulta')
            ? $jornada->padronConsulta()->whereNotNull('rut')->where('rut', '!=', '')->distinct()->count('rut')
            : 0;

        return $this->calcular($resumen, $padron);
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
