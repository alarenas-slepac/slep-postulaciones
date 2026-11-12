<?php

namespace App\Services\Auth;

use App\Models\AccountWhatsAppVerification;
use App\Models\User;
use App\Models\WhatsAppDispatch;
use App\Services\Notifications\NotificationEventPolicy;
use App\Services\WhatsApp\WhatsAppDispatcher;
use App\Support\ChileanMobilePhone;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class WhatsAppAccountVerification
{
    public const EVENT = 'auth.whatsapp_verification';
    public const LIFETIME = 300;
    public const COOLDOWN = 60;
    public const MAX_ATTEMPTS = 5;

    public function available(): bool
    {
        $registered = config('notification_events.account_whatsapp', []);
        return isset($registered[self::EVENT]) && (bool) config('whatsapp.enabled') && Schema::hasTable('account_whatsapp_verifications')
            && Schema::hasIndex('account_whatsapp_verifications', 'awv_user_uq', 'unique')
            && Schema::hasIndex('account_whatsapp_verifications', 'awv_phone_uq', 'unique')
            && Schema::hasTable('whatsapp_dispatches') && $this->link() !== null
            && app(NotificationEventPolicy::class)->enabled(self::EVENT, 'whatsapp');
    }

    public function state(User $user): array
    {
        $record = Schema::hasTable('account_whatsapp_verifications')
            ? AccountWhatsAppVerification::where('user_id', $user->id)->first() : null;
        $dispatch = $record?->dispatch_id && Schema::hasTable('whatsapp_dispatches') ? WhatsAppDispatch::find($record->dispatch_id) : null;
        $active = $record && $record->code_hash && $record->expires_at?->isFuture()
            && $record->attempts < self::MAX_ATTEMPTS && hash_equals((string) $record->email_hash, $this->emailHash($user));
        $delivery = $dispatch?->status ?? 'none';
        $deliveryError = match (true) {
            $dispatch?->error_code === 'invalid_recipient' => 'Ese número no pudo recibir el mensaje. Comprueba que tenga WhatsApp y solicita otro código.',
            in_array($delivery, ['failed', 'cancelled'], true) && $active => 'No fue posible entregar este código. Comprueba el número y solicita uno nuevo cuando termine la espera.',
            default => null,
        };
        return [
            'available' => $this->available(), 'verified' => $user->hasVerifiedAccount(),
            'phone' => $record?->pending_phone ?? '', 'challenge_id' => $active ? $record->challenge_id : null,
            'expires_at' => $active ? $record->expires_at->timestamp : null,
            'resend_at' => $record?->resend_at?->timestamp,
            'server_now' => now()->timestamp, 'delivery' => $delivery, 'delivery_error' => $deliveryError,
            'attempts_exhausted' => ($record?->attempts ?? 0) >= self::MAX_ATTEMPTS,
        ];
    }

    public function request(User $user, string $phone): void
    {
        $this->requireAvailable();
        $phone = ChileanMobilePhone::normalize($phone);
        if (! $phone) { $this->invalid('whatsapp_phone', 'Ingresa un celular chileno válido: 9XXXXXXXX, 569XXXXXXXX o +569XXXXXXXX.'); }
        try {
            DB::transaction(function () use ($user, $phone): void {
                $current = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                if ($current->hasVerifiedAccount()) { $this->invalid('whatsapp_phone', 'Esta cuenta ya está validada.'); }
                $record = AccountWhatsAppVerification::where('user_id', $current->id)->lockForUpdate()->first()
                    ?? new AccountWhatsAppVerification(['user_id' => $current->id]);
                if ($record->resend_at?->isFuture()) { $this->invalid('whatsapp_phone', 'Espera a que termine el minuto antes de solicitar otro código.'); }
                if ($this->ownedByAnother($phone, $current->id)) { $this->invalid('whatsapp_phone', 'Este número no está disponible para validar esta cuenta. Utiliza tu número personal o contacta soporte.'); }
                $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $challenge = (string) Str::uuid();
                $record->fill(['pending_phone' => $phone, 'challenge_id' => $challenge, 'email_hash' => $this->emailHash($current),
                    'code_hash' => $this->digest($current->id, $phone, $challenge, $code),
                    'expires_at' => now()->addSeconds(self::LIFETIME), 'resend_at' => now()->addSeconds(self::COOLDOWN),
                    'attempts' => 0, 'dispatch_id' => null])->save();
                $text = "SGA SLEP Andalién Costa\nTu código para validar tu cuenta es: {$code}\nVence en 5 minutos y se utiliza una sola vez. Si solicitaste otro código, utiliza el último. No lo compartas.\n"
                    .$this->link()."\n\nEsta es una notificación automática del SGA.\nPor favor, no respondas a este mensaje.";
                $dispatch = app(WhatsAppDispatcher::class)->enqueue($phone, $text, 'auth.verify:'.$challenge, self::EVENT, $current->id,
                    ['scope' => 'account_verification', 'user_id' => $current->id, 'challenge_id' => $challenge]);
                if (! $dispatch) { $this->invalid('whatsapp_phone', 'No fue posible solicitar el código. Inténtalo nuevamente más tarde.'); }
                $record->update(['dispatch_id' => $dispatch->id]);
            }, 3);
        } catch (ValidationException $e) { throw $e; }
        catch (Throwable) { $this->invalid('whatsapp_phone', 'No fue posible solicitar el código. Inténtalo nuevamente más tarde.'); }
    }

    public function confirm(User $user, string $challenge, string $code): void
    {
        $this->requireAvailable();
        try {
            $error = DB::transaction(function () use ($user, $challenge, $code): ?string {
                $current = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                $record = AccountWhatsAppVerification::where('user_id', $current->id)->lockForUpdate()->first();
                if ($current->hasVerifiedAccount() || ! $record || ! $record->code_hash || ! $record->expires_at?->isFuture()
                    || $record->attempts >= self::MAX_ATTEMPTS || ! hash_equals((string) $record->challenge_id, $challenge)
                    || ! hash_equals((string) $record->email_hash, $this->emailHash($current))) {
                    return 'El código ya no es válido. Solicita uno nuevo si tu cuenta sigue pendiente.';
                }
                if (! preg_match('/^[0-9]{6}$/D', $code)
                    || ! hash_equals($record->code_hash, $this->digest($current->id, $record->pending_phone, $challenge, $code))) {
                    $record->attempts++;
                    if ($record->attempts >= self::MAX_ATTEMPTS) { $record->code_hash = null; }
                    $record->save(); // Se confirma el contador incluso cuando la respuesta será un error.
                    return $record->attempts >= self::MAX_ATTEMPTS
                        ? 'Se agotaron los 5 intentos. Solicita un código nuevo cuando termine la espera.' : 'El código es incorrecto. Revísalo e inténtalo nuevamente.';
                }
                if ($this->ownedByAnother($record->pending_phone, $current->id)) { return 'El número ya no está disponible para validar esta cuenta. Contacta soporte.'; }
                $record->fill(['verified_phone' => $record->pending_phone, 'verified_at' => now(), 'code_hash' => null])->save();
                if (Schema::hasTable('postulant_profiles')) {
                    // No crear un perfil incompleto: producción exige datos adicionales.
                    $profile = $current->postulantProfile()->first();
                    if ($profile) { $profile->forceFill(['telefono1' => $record->verified_phone])->save(); }
                }
                return null;
            }, 3);
        } catch (QueryException $e) {
            $error = ($e->errorInfo[0] ?? '') === '23000' ? 'El número ya no está disponible para validar esta cuenta.' : 'No fue posible validar el código. Inténtalo nuevamente.';
        } catch (Throwable) { $error = 'No fue posible validar el código. Inténtalo nuevamente.'; }
        if ($error !== null) { $this->invalid('whatsapp_code', $error); }
        $user->unsetRelation('whatsappVerification');
    }

    public function deliveryError(array $payload): ?string
    {
        if (! $this->available()) { return 'event_disabled'; }
        $policy = $payload['policy'] ?? [];
        $user = User::find($policy['user_id'] ?? 0);
        if (! $user || $user->hasVerifiedAccount()) { return 'recipient_ineligible'; }
        $record = AccountWhatsAppVerification::where('user_id', $user->id)->first();
        if (! $record || ! $record->code_hash || ! $record->expires_at?->isFuture() || $record->attempts >= self::MAX_ATTEMPTS) { return 'verification_expired'; }
        if ($record->pending_phone !== ($payload['to'] ?? null) || $record->challenge_id !== ($policy['challenge_id'] ?? null)
            || ! hash_equals((string) $record->email_hash, $this->emailHash($user))) { return 'verification_changed'; }
        return $this->ownedByAnother($record->pending_phone, $user->id) ? 'recipient_ineligible' : null;
    }

    private function requireAvailable(): void
    {
        if (! $this->available()) { $this->invalid('whatsapp', 'La validación por WhatsApp no está disponible en este momento. Inténtalo más tarde o contacta soporte.'); }
    }

    private function ownedByAnother(string $phone, int $userId): bool
    {
        return AccountWhatsAppVerification::where('verified_phone', $phone)->where('user_id', '<>', $userId)->exists();
    }

    private function emailHash(User $user): string { return hash('sha256', strtolower(trim($user->email))); }

    private function digest(int $id, string $phone, string $challenge, string $code): string
    {
        return hash_hmac('sha256', $id.'|'.$phone.'|'.$challenge.'|'.$code, (string) config('app.key'));
    }

    private function invalid(string $field, string $message): never { throw ValidationException::withMessages([$field => $message]); }

    private function link(): ?string
    {
        $base = rtrim((string) config('app.url'), '/');
        $parts = parse_url($base);
        if (! $parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || preg_match('/[\s\x00-\x1f\x7f]/', $base)) { return null; }
        return $base.route('verification.notice', [], false).'?channel=whatsapp';
    }
}
