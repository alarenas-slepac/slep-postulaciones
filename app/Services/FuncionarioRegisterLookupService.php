<?php

namespace App\Services;

use App\Services\Padron\PadronVigenciaService;
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

        $padron = app(PadronVigenciaService::class)->porRut($norm['rut']);
        $candidates = $padron['vigentes'];

        if ($candidates->isEmpty()) {
            return [
                'valid' => true,
                'status' => 'not_found',
                'is_funcionario' => false,
                'rut' => $norm['rut'],
                'rut_body' => $norm['rut_body'] ?? null,
                'rut_dv' => $norm['rut_dv'] ?? null,
                'tiene_antecedentes' => $padron['tiene_antecedentes'],
                'message' => $padron['tiene_antecedentes']
                    ? 'El RUT tiene antecedentes en el padrón, pero no un contrato vigente. Puedes continuar como postulante.'
                    : 'RUT no encontrado en la carga disponible de personal.',
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

        // Todas las líneas actuales cuentan: dos RBD vigentes son ambiguos
        // aunque sus últimas cargas parciales correspondan a meses distintos.
        $latestRows = $candidates;

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
                'message' => 'El RUT tiene contratos vigentes en más de un establecimiento. Debe regularizarse el padrón antes del registro.',
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
            'message' => 'RUT encontrado en el padrón vigente. Se usará el establecimiento de su contrato actual para registrar al funcionario.',
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
