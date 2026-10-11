<?php

namespace App\Services\WhatsApp;

use App\Models\FuncionarioAcAutorizado;
use App\Models\User;
use Illuminate\Support\Collection;

class ReemplazoWhatsAppRecipients
{
    public const ROLES = ['coordinador_uatp', 'supervisor_plani', 'coordinador_gdp', 'funcionario_slep'];

    public function phone(?string $value): ?string
    {
        $phone = preg_replace('/[\s().-]+/u', '', trim((string) $value));
        if (str_starts_with($phone ?? '', '00')) {
            $phone = '+'.substr($phone, 2);
        } elseif (preg_match('/^56[0-9]{9}$/D', $phone ?? '')) {
            $phone = '+'.$phone;
        } elseif (preg_match('/^9[0-9]{8}$/D', $phone ?? '')) {
            $phone = '+56'.$phone;
        }

        if (str_starts_with($phone ?? '', '+56') && !preg_match('/^\+56[0-9]{9}$/D', $phone)) {
            return null;
        }
        return preg_match('/^\+[1-9][0-9]{7,14}$/D', $phone ?? '') ? $phone : null;
    }

    public function account(FuncionarioAcAutorizado $record): ?User
    {
        if ($record->registered_user_id) {
            $user = User::with('roles')->find($record->registered_user_id);
            $rut = $this->rut($record->rut_normalizado ?: $record->run.$record->dv);
            if ($user && $rut !== '' && $this->rut($user->rut) !== '' && $rut !== $this->rut($user->rut)) {
                return null;
            }
        } else {
            // Compatibilidad histórica: coincidencia única de RUT, nunca por nombre o email.
            $rut = $this->rut($record->rut_normalizado ?: $record->run.$record->dv);
            if ($rut === '') {
                return null;
            }
            $matches = User::with('roles')
                ->whereRaw("REPLACE(REPLACE(REPLACE(UPPER(rut), '.', ''), '-', ''), ' ', '') = ?", [$rut])
                ->limit(2)->get();
            $user = $matches->count() === 1 ? $matches->first() : null;
        }

        return $user && $user->email_verified_at && $user->hasAnyRole(self::ROLES)
            && $user->canModule('gestion.solicitudes-reemplazo') ? $user : null;
    }

    public function forStage(array $roles, ?int $assignedUserId = null): Collection
    {
        $roles = array_intersect(self::ROLES, $roles);
        $candidates = collect();
        if (!$roles) {
            return $candidates;
        }
        foreach (FuncionarioAcAutorizado::where('notificar_whatsapp_reemplazos', true)->orderBy('id')->get() as $record) {
            if (!$record->estaActivo() || !($phone = $this->phone($record->telefono))) {
                continue;
            }
            $user = $this->account($record);
            if (!$user || !$user->hasAnyRole($roles) || ($assignedUserId !== null && $user->id !== $assignedUserId)) {
                continue;
            }
            $role = collect($roles)->first(fn ($role) => $user->hasRole($role));
            $candidates->push(['user_id' => $user->id, 'authorization_id' => $record->id, 'role' => $role, 'phone' => $phone]);
        }

        // Dos fichas activadas para la misma cuenta son ambiguas: no elegir un teléfono arbitrario.
        $uniqueAccounts = $candidates->groupBy('user_id')->filter(fn ($rows) => $rows->count() === 1)->flatten(1);
        return $uniqueAccounts->unique('phone')->values();
    }

    public function stillEligible(array $policy, string $phone): bool
    {
        if (!in_array($policy['role'] ?? '', self::ROLES, true)) {
            return false;
        }
        return $this->forStage([$policy['role']], (int) ($policy['user_id'] ?? 0))
            ->contains(fn ($recipient) => $recipient['authorization_id'] === (int) ($policy['authorization_id'] ?? 0)
                && $recipient['phone'] === $phone);
    }

    private function rut(?string $value): string
    {
        return strtoupper(preg_replace('/[^0-9kK]/', '', (string) $value) ?? '');
    }
}
