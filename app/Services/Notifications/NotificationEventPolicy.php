<?php

namespace App\Services\Notifications;

use App\Models\NotificationEventSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

class NotificationEventPolicy
{
    public const ORIGINAL = '{{contenido_original}}';
    public const VARIABLES = ['contenido_original', 'asunto_original', 'evento'];

    public function setting(string $key, string $channel): ?NotificationEventSetting
    {
        if (!Schema::hasTable('notification_event_settings')) return null;
        return NotificationEventSetting::where('event_key', $key)->where('channel', $channel)->first();
    }

    public function enabled(string $key, string $channel): bool
    {
        $event = app(NotificationEventCatalog::class)->find($key);
        if (!$event) return true; // Compatibilidad con emisores externos al catálogo.
        if (!in_array($channel, $event['channels'], true)) return false;
        if ($event['protected'] ?? false) return true;
        try { return $this->setting($key, $channel)?->enabled ?? ($channel === 'whatsapp' ? ($event['whatsapp_default'] ?? true) : true); }
        catch (Throwable) {
            Log::warning('Configuración de evento no disponible.', ['code' => 'event_settings_unavailable', 'event' => $key]);
            return false;
        }
    }

    public function recipientAllowed(string $key, int $userId): bool
    {
        $setting = $this->setting($key, 'whatsapp');
        return !$setting || $setting->recipient_mode === 'all' || in_array($userId, $setting->recipient_ids ?? [], true);
    }

    public function validateTemplate(?string $template, string $field, bool $body): void
    {
        if ($template === null || trim($template) === '') return;
        preg_match_all('/\{\{([a-z_]+)\}\}/', $template, $tokens);
        $rest = preg_replace('/\{\{[a-z_]+\}\}/', '', $template);
        if (array_diff($tokens[1], self::VARIABLES) || preg_match('/[{}<>]|@php|@include|@extends|[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $rest)
            || (!$body && preg_match('/[\r\n]/', $template))
            || ($body && substr_count($template, self::ORIGINAL) !== 1)
            || (!$body && str_contains($template, self::ORIGINAL))) {
            throw ValidationException::withMessages([$field => $body
                ? 'Use texto y variables admitidas, incluyendo {{contenido_original}} exactamente una vez.'
                : 'Use una línea de texto con {{asunto_original}} o {{evento}}, sin HTML ni instrucciones.']);
        }
    }

    public function text(string $template, string $original, string $subject, string $event): string
    {
        return strtr($template, [self::ORIGINAL => $original, '{{asunto_original}}' => $subject, '{{evento}}' => $event]);
    }

    public function whatsappText(string $key, string $original, bool $automaticFooter = false): string
    {
        $template = $this->setting($key, 'whatsapp')?->body_template;
        if (!$template) return $original;
        // El contenido generado mantiene sus datos y enlace autorizado. El pie siempre queda al final.
        $footer = "Esta es una notificación automática del SGA.\nPor favor, no respondas a este mensaje.";
        $content = $automaticFooter ? trim(str_replace($footer, '', $original)) : $original;
        $result = $this->text($template, $content, '', app(NotificationEventCatalog::class)->find($key)['name'] ?? $key);
        if ($automaticFooter) $result = rtrim($result)."\n\n".$footer;
        if (mb_strlen($result) > 1000) throw new \App\Services\WhatsApp\WhatsAppException('invalid_message');
        return $result;
    }
}
