<?php

namespace App\Services\Notifications;

use App\Models\FuncionarioAcAutorizado;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppPhone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class NotificationWhatsAppRecipients
{
    public function eligible(?array $emails = null): Collection
    {
        $records = Schema::hasTable('funcionarios_ac_autorizados') ? FuncionarioAcAutorizado::all() : collect();
        $profiles = Schema::hasTable('postulant_profiles');
        $users = User::whereNotNull('email_verified_at')->when($emails !== null, fn ($q) => $q->whereIn('email', $emails))
            ->when($profiles, fn ($q) => $q->with('postulantProfile'))->get();
        return $users->map(function (User $user) use ($records, $profiles) {
            $rut = $user->rut_normalized;
            $fichas = $records->filter(fn ($r) => (int) $r->registered_user_id === (int) $user->id
                || ($rut !== '' && $this->rut($r->rut_normalizado ?: $r->run.$r->dv) === $rut));
            if ($fichas->isNotEmpty()) {
                // No sustituir una ficha vencida, duplicada o contradictoria por otro teléfono.
                if ($fichas->count() !== 1) return null;
                $record = $fichas->first();
                $recordRut = $this->rut($record->rut_normalizado ?: $record->run.$record->dv);
                if (!$record->estaActivo() || ($record->registered_user_id && (int) $record->registered_user_id !== (int) $user->id)
                    || ($recordRut !== '' && $rut !== '' && $recordRut !== $rut)) return null;
                $phone = WhatsAppPhone::normalize($record->telefono);
                $source = 'ficha AC';
            } else {
                $phone = $profiles ? WhatsAppPhone::normalize($user->postulantProfile?->telefono1) : null;
                $source = 'perfil';
            }
            return $phone ? ['user_id' => (int) $user->id, 'email' => strtolower($user->email), 'phone' => $phone,
                'label' => $user->display_name.' · '.$source] : null;
        })->filter()->values();
    }

    public function forEmails(array $emails): Collection
    {
        $emails = array_unique(array_map(fn ($email) => strtolower(trim($email)), $emails));
        $eligible = $this->eligible($emails);
        // Un buzón compartido o un teléfono compartido no identifica inequívocamente al receptor.
        return $eligible->groupBy('email')->filter(fn ($rows) => $rows->count() === 1)->flatten(1)
            ->groupBy('phone')->filter(fn ($rows) => $rows->count() === 1)->flatten(1)->values();
    }

    public function stillEligible(array $policy, string $phone): bool
    {
        return $this->forEmails([$policy['recipient_email'] ?? ''])->contains(fn ($r) => $r['user_id'] === (int) ($policy['user_id'] ?? 0) && $r['phone'] === $phone);
    }

    private function rut(?string $value): string { return strtoupper(preg_replace('/[^0-9kK]/', '', (string) $value) ?? ''); }
}
