<?php

namespace App\Support;

/** Acumula horas y prorratea cada docente por sus horas en todo el territorio. */
final class DotacionDocenteAcumulado
{
    private array $filas = [];

    private array $horasPorEstablecimiento = [];

    private array $totalPorDocente = [];

    public function agregar(int $id, string $grupo, string $rut, float $horas, bool $esDocente = true): void
    {
        if ($horas <= 0) {
            return;
        }

        $this->filas[$id][$grupo] ??= ['docentes' => 0.0, 'horas' => 0.0];
        $this->filas[$id][$grupo]['horas'] += $horas;
        if ($esDocente) {
            $this->horasPorEstablecimiento[$id][$grupo][$rut] = ($this->horasPorEstablecimiento[$id][$grupo][$rut] ?? 0.0) + $horas;
            $this->totalPorDocente[$rut] = ($this->totalPorDocente[$rut] ?? 0.0) + $horas;
        }
    }

    public function horas(int $id, ?string $grupo = null): float
    {
        if ($grupo !== null) {
            return $this->filas[$id][$grupo]['horas'] ?? 0.0;
        }

        return array_sum(array_column($this->filas[$id] ?? [], 'horas'));
    }

    public function resumir(): array
    {
        $filas = $this->filas;
        foreach ($this->horasPorEstablecimiento as $id => $grupos) {
            foreach ($grupos as $grupo => $horasPorRut) {
                foreach ($horasPorRut as $rut => $horas) {
                    $filas[$id][$grupo]['docentes'] += $horas / $this->totalPorDocente[$rut];
                }
            }
        }

        return $filas;
    }
}
