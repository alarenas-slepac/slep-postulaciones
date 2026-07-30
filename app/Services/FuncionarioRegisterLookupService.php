<?php

namespace App\Services;

use App\Models\ReemplazoPersonal;
use App\Support\RutChile;

class FuncionarioRegisterLookupService
{
    public function lookup(?string $rawRut): array
    {
        $norm = RutChile::normalize((string) $rawRut);

        if (!$norm || ($norm['status'] ?? null) === 'invalid_dv') {
            return [
                'valid' => false,
                'status' => 'invalid',
                'is_funcionario' => false,
                'message' => 'Ingresa un RUT válido antes de buscar.',
            ];
        }

        $formattedRut = strtoupper(trim((string) ($norm['rut'] ?? '')));
        $normalizedRut = strtoupper(preg_replace('/[^0-9Kk]/', '', $formattedRut));

        $candidates = ReemplazoPersonal::query()
            ->with(['establecimiento:id,rbd,nombre_establecimiento,comuna'])
            ->where(function ($query) use ($formattedRut, $normalizedRut) {
                $query->whereRaw('UPPER(TRIM(rut)) = ?', [$formattedRut])
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(UPPER(TRIM(rut)), '.', ''), '-', ''), ' ', '') = ?", [$normalizedRut]);
            })
            ->get();

        if ($candidates->isEmpty()) {
            return [
                'valid' => true,
                'status' => 'not_found',
                'is_funcionario' => false,
                'rut' => $norm['rut'],
                'rut_body' => $norm['rut_body'] ?? null,
                'rut_dv' => $norm['rut_dv'] ?? null,
                'message' => 'RUT no encontrado en la carga disponible de personal.',
            ];
        }

        $withoutEstablecimiento = $candidates->filter(fn($row) => !$row->establecimiento_id || !$row->establecimiento);
        if ($withoutEstablecimiento->isNotEmpty()) {
            return [
                'valid' => true,
                'status' => 'error',
                'is_funcionario' => false,
                'rut' => $norm['rut'],
                'message' => 'El RUT existe en reemplazos_personal, pero tiene registros sin establecimiento asociado. Regulariza el padrón antes del registro.',
            ];
        }

        $latestPeriod = (int) $candidates
            ->map(fn($row) => ((int) $row->anio * 100) + (int) $row->mes)
            ->max();

        $latestRows = $candidates->filter(function ($row) use ($latestPeriod) {
            $rowPeriod = ((int) $row->anio * 100) + (int) $row->mes;

            return $latestPeriod > 0 && $rowPeriod === $latestPeriod;
        })->values();

        if ($latestRows->isEmpty()) {
            return [
                'valid' => true,
                'status' => 'not_found',
                'is_funcionario' => false,
                'rut' => $norm['rut'],
                'rut_body' => $norm['rut_body'] ?? null,
                'rut_dv' => $norm['rut_dv'] ?? null,
                'message' => 'RUT no encontrado en la carga disponible de personal. Puedes continuar como postulante.',
            ];
        }

        $establecimientoIds = $latestRows
            ->pluck('establecimiento_id')
            ->filter()
            ->unique()
            ->values();

        if ($establecimientoIds->count() > 1) {
            return [
                'valid' => true,
                'status' => 'error',
                'is_funcionario' => false,
                'rut' => $norm['rut'],
                'message' => 'El RUT aparece en más de un establecimiento dentro de su período más reciente en reemplazos_personal. Debe regularizarse el padrón antes del registro.',
            ];
        }

        $selected = $latestRows
            ->sortByDesc(fn($row) => ((int) $row->anio * 100) + (int) $row->mes)
            ->first();

        if (!$selected || !$selected->establecimiento) {
            return [
                'valid' => true,
                'status' => 'error',
                'is_funcionario' => false,
                'rut' => $norm['rut'],
                'message' => 'No fue posible determinar el establecimiento del funcionario para el registro.',
            ];
        }

        if (!$selected->fecha_nacimiento) {
            return [
                'valid' => true,
                'status' => 'error',
                'is_funcionario' => false,
                'rut' => $norm['rut'],
                'message' => 'El RUT figura en el padrón vigente, pero no tiene fecha de nacimiento registrada. Regulariza el padrón antes del registro.',
            ];
        }

        [$nombres, $apellidoPaterno, $apellidoMaterno] = $this->splitFullName($selected->nombre);
        $establecimiento = $selected->establecimiento;

        return [
            'valid' => true,
            'status' => 'funcionario',
            'is_funcionario' => true,
            'rut' => $norm['rut'],
            'rut_body' => $norm['rut_body'] ?? null,
            'rut_dv' => $norm['rut_dv'] ?? null,
            'nombres' => $nombres,
            'apellido_paterno' => $apellidoPaterno,
            'apellido_materno' => $apellidoMaterno,
            'establecimiento_id' => (int) $establecimiento->id,
            'establecimiento_nombre' => (string) $establecimiento->nombre_establecimiento,
            'establecimiento_label' => trim(($establecimiento->rbd ? ($establecimiento->rbd . ' — ') : '') . $establecimiento->nombre_establecimiento),
            'comuna' => (string) $establecimiento->comuna,
            'fecha_nacimiento' => $selected->fecha_nacimiento->format('Y-m-d'),
            'periodo' => sprintf('%02d/%04d', (int) $selected->mes, (int) $selected->anio),
            'message' => 'RUT encontrado en reemplazos_personal. Se usará el período más reciente del propio RUT para registrar al funcionario.',
        ];
    }

    private function splitFullName(?string $fullName): array
    {
        $name = trim((string) preg_replace('/\s+/', ' ', (string) $fullName));
        if ($name === '') {
            return ['', '', ''];
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        $parts = array_values(array_filter($parts, fn($part) => $part !== ''));
        $count = count($parts);

        if ($count === 1) {
            return [$parts[0], '', ''];
        }

        // La carga de reemplazos_personal viene como:
        // apellido paterno, apellido materno, nombres.
        if ($count === 2) {
            return [$parts[1], $parts[0], ''];
        }

        if ($count === 3) {
            return [$parts[2], $parts[0], $parts[1]];
        }

        $apellidoPaterno = array_shift($parts) ?: '';
        $apellidoMaterno = array_shift($parts) ?: '';
        $nombres = implode(' ', $parts);

        return [$nombres, $apellidoPaterno, $apellidoMaterno];
    }
}
