<?php

namespace App\Services\CentroOperaciones;

use App\Models\CentroOperacionesReporte;
use Illuminate\Support\Enumerable;

class EstadoService
{
    /**
     * @param  iterable<int, mixed>  $incidenciasActivas
     */
    public function paraReporte(CentroOperacionesReporte $reporte, iterable $incidenciasActivas = []): string
    {
        $severidades = [
            config("centro_operaciones.funcionamientos.{$reporte->funcionamiento}.severity", 'operativo'),
            config("centro_operaciones.prioridades.{$reporte->prioridad}.severity", 'operativo'),
        ];

        foreach ($reporte->servicios as $servicio) {
            $severidades[] = (string) $servicio->estado;
            // Extintores no operativos deben ser considerados para el estado general
            if ($servicio->servicio === 'extintores' && $servicio->estado === 'critico') {
                $severidades[] = 'critico';
            }
        }

        foreach ($reporte->afectaciones as $afectacion) {
            $severidades[] = config("centro_operaciones.afectaciones.{$afectacion->tipo}.severity", 'alerta');
        }

        foreach ($incidenciasActivas as $incidencia) {
            $severidades[] = (string) data_get($incidencia, 'severidad', 'alerta');
        }

        return $this->mayor($severidades);
    }

    /**
     * @param  iterable<int, string|null>  $estados
     */
    public function mayor(iterable $estados): string
    {
        $mayor = 'operativo';
        foreach ($estados as $estado) {
            $estado = (string) $estado;
            if ($this->orden($estado) > $this->orden($mayor)) {
                $mayor = $estado;
            }
        }

        return $mayor;
    }

    public function orden(string $estado): int
    {
        return (int) config("centro_operaciones.severidad_orden.{$estado}", 0);
    }
}
