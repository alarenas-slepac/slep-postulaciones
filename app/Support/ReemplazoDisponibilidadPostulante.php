<?php

namespace App\Support;

use App\Models\SolicitudReemplazo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReemplazoDisponibilidadPostulante
{
    public function cruces(?string $inicio, ?string $termino, ?int $solicitudId, array $perfiles): Collection
    {
        if (! $inicio || ! $termino || $termino < $inicio || $perfiles === []) {
            return collect();
        }

        return SolicitudReemplazo::query()
            ->leftJoin('solicitud_reemplazo_jornadas as srj', 'srj.solicitud_reemplazo_id', '=', 'solicitudes_reemplazo.id')
            ->where('solicitudes_reemplazo.estado', 'aceptada')
            ->when($solicitudId !== null, fn ($query) => $query->where('solicitudes_reemplazo.id', '<>', $solicitudId))
            ->whereIn('solicitudes_reemplazo.postulant_profile_id', $perfiles)
            ->whereRaw('DATE(COALESCE(solicitudes_reemplazo.fecha_inicio_trabajo, solicitudes_reemplazo.fecha_inicio)) <= ?', [$termino])
            ->whereDate('solicitudes_reemplazo.fecha_termino', '>=', $inicio)
            ->select('solicitudes_reemplazo.postulant_profile_id')
            ->selectRaw('MAX(solicitudes_reemplazo.fecha_termino) as hasta, COALESCE(SUM(srj.reemplazo_total), 0) as horas')
            ->groupBy('solicitudes_reemplazo.postulant_profile_id')
            ->toBase()->get()->keyBy('postulant_profile_id');
    }

    public function superaLimite(?object $cruce, float $jornada): bool
    {
        return $this->total($cruce, $jornada) > 44.0;
    }

    public function detalle(?object $cruce, ?float $jornada): array
    {
        $aviso = $cruce ? 'En reemplazo activo hasta el '.Carbon::parse($cruce->hasta)->format('d/m/Y').'.' : null;
        $bloqueo = null;
        if ($jornada !== null && $this->superaLimite($cruce, $jornada)) {
            $horas = fn (float $valor) => rtrim(rtrim(number_format($valor, 2, ',', ''), '0'), ',');
            $bloqueo = 'La suma supera el máximo de 44 horas: '.$horas((float) ($cruce->horas ?? 0)).' h vigentes + '
                .$horas($jornada).' h solicitadas = '.$horas($this->total($cruce, $jornada)).' h.';
        }

        return ['aviso' => $aviso, 'bloqueo' => $bloqueo];
    }

    private function total(?object $cruce, float $jornada): float
    {
        return round((float) ($cruce->horas ?? 0) + max(0, $jornada), 2);
    }
}
