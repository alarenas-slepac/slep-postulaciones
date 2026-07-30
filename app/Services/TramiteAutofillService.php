<?php

namespace App\Services;

use App\Models\ReemplazoPersonal;
use App\Models\SolicitudReemplazo;
use App\Models\User;
use App\Support\RutChile;

class TramiteAutofillService
{
    public function forUser(User $user): array
    {
        $normalized = RutChile::normalize((string) $user->rut);

        if (!$normalized || ($normalized['status'] ?? null) === 'invalid_dv') {
            return [
                'ok' => false,
                'message' => 'El usuario no tiene un RUT válido para autocompletar el trámite.',
            ];
        }

        $formattedRut = strtoupper(trim((string) ($normalized['rut'] ?? '')));
        $normalizedRut = strtoupper(preg_replace('/[^0-9Kk]/', '', $formattedRut));

        $rows = ReemplazoPersonal::query()
            ->with(['establecimiento:id,rbd,nombre_establecimiento,comuna'])
            ->where(function ($query) use ($formattedRut, $normalizedRut) {
                $query->whereRaw('UPPER(TRIM(rut)) = ?', [$formattedRut])
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(UPPER(TRIM(rut)), '.', ''), '-', ''), ' ', '') = ?", [$normalizedRut]);
            })
            ->get();

        if ($rows->isEmpty()) {
            $fallback = $this->resolveFromSolicitudes($user, $normalized);
            if ($fallback !== null) {
                return $fallback;
            }

            return [
                'ok' => false,
                'rut' => strtolower((string) ($normalized['rut'] ?? '')),
                'nombre_completo' => $this->formatFullNameFromUser($user),
                'email' => strtolower((string) $user->email),
                'message' => 'No se encontraron datos en reemplazos_personal ni en solicitudes de reemplazo aceptadas/cerradas para autocompletar estatuto, escalafón y establecimiento. El trámite no puede enviarse hasta que el RUT exista en alguna de esas fuentes.',
            ];
        }

        $rowsWithoutEst = $rows->filter(fn($row) => !$row->establecimiento_id || !$row->establecimiento);
        if ($rowsWithoutEst->isNotEmpty()) {
            return [
                'ok' => false,
                'message' => 'El RUT existe en reemplazos_personal, pero presenta registros sin establecimiento asociado. Regulariza el padrón antes de crear el trámite.',
            ];
        }

        $latestPeriod = (int) $rows
            ->map(fn($row) => ((int) $row->anio * 100) + (int) $row->mes)
            ->max();

        $latestRows = $rows->filter(function ($row) use ($latestPeriod) {
            return (((int) $row->anio * 100) + (int) $row->mes) === $latestPeriod;
        })->values();

        $establecimientoIds = $latestRows->pluck('establecimiento_id')->filter()->unique()->values();
        if ($establecimientoIds->count() > 1) {
            return [
                'ok' => false,
                'message' => 'El RUT aparece en más de un establecimiento dentro de su período más reciente en reemplazos_personal. Debe regularizarse el padrón antes de crear el trámite.',
            ];
        }

        $selected = $latestRows->first();
        $establecimiento = $selected?->establecimiento;

        if (!$selected || !$establecimiento) {
            return [
                'ok' => false,
                'message' => 'No fue posible determinar el establecimiento asociado al trámite.',
            ];
        }

        return [
            'ok' => true,
            'rut' => strtolower((string) ($normalized['rut'] ?? '')),
            'nombre_completo' => $this->formatFullNameFromUser($user),
            'email' => strtolower((string) $user->email),
            'estatuto' => (string) ($selected->estatuto ?? ''),
            'escalafon' => (string) ($selected->escalafon ?? ''),
            'establecimiento_id' => (int) $establecimiento->id,
            'establecimiento_nombre' => (string) $establecimiento->nombre_establecimiento,
            'establecimiento_label' => trim(($establecimiento->rbd ? ($establecimiento->rbd . ' — ') : '') . $establecimiento->nombre_establecimiento),
            'periodo' => sprintf('%02d/%04d', (int) $selected->mes, (int) $selected->anio),
            'source' => 'reemplazos_personal',
        ];
    }

    private function resolveFromSolicitudes(User $user, array $normalized): ?array
    {
        $profile = $user->postulantProfile;
        if (!$profile) {
            return null;
        }

        $solicitud = SolicitudReemplazo::query()
            ->with([
                'establecimiento:id,rbd,nombre_establecimiento',
                'funcionarioTitular:id,estatuto,escalafon',
            ])
            ->where('postulant_profile_id', $profile->id)
            ->whereIn('estado', ['aceptada', 'cerrado'])
            ->orderByDesc('fecha_inicio_trabajo')
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->first();

        if (!$solicitud || !$solicitud->establecimiento) {
            return null;
        }

        $establecimiento = $solicitud->establecimiento;
        $titular = $solicitud->funcionarioTitular;

        return [
            'ok' => true,
            'rut' => strtolower((string) ($normalized['rut'] ?? '')),
            'nombre_completo' => $this->formatFullNameFromUser($user),
            'email' => strtolower((string) $user->email),
            'estatuto' => (string) ($titular?->estatuto ?? ''),
            'escalafon' => (string) ($titular?->escalafon ?? ''),
            'establecimiento_id' => (int) $establecimiento->id,
            'establecimiento_nombre' => (string) $establecimiento->nombre_establecimiento,
            'establecimiento_label' => trim(($establecimiento->rbd ? ($establecimiento->rbd . ' — ') : '') . $establecimiento->nombre_establecimiento),
            'periodo' => null,
            'source' => 'solicitudes_reemplazo',
            'solicitud_id' => (int) $solicitud->id,
            'solicitud_numero' => (string) ($solicitud->numero_solicitud ?? ''),
        ];
    }

    public function formatFullNameFromUser(User $user): string
    {
        $parts = array_filter([
            $user->nombres,
            $user->apellido_paterno,
            $user->apellido_materno,
        ], fn($value) => trim((string) $value) !== '');

        return $this->formatPersonName(implode(' ', $parts));
    }

    public function formatPersonName(string $value): string
    {
        $value = trim((string) preg_replace('/\s+/', ' ', $value));
        if ($value === '') {
            return '';
        }

        $connectors = ['de', 'del', 'la', 'las', 'los', 'y', 'e', 'da', 'do'];
        $words = preg_split('/\s+/', mb_strtolower($value, 'UTF-8')) ?: [];
        $formatted = [];

        foreach ($words as $index => $word) {
            if ($word === '') {
                continue;
            }

            if ($index > 0 && in_array($word, $connectors, true)) {
                $formatted[] = $word;
                continue;
            }

            $formatted[] = mb_strtoupper(mb_substr($word, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($word, 1, null, 'UTF-8');
        }

        return implode(' ', $formatted);
    }
}
