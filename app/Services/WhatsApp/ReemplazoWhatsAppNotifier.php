<?php

namespace App\Services\WhatsApp;

use App\Models\SolicitudReemplazo;
use App\Notifications\Messages\WhatsAppText;
use App\Notifications\ReemplazoWhatsAppNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ReemplazoWhatsAppNotifier
{
    private const EVENTS = [
        'created' => ['pendiente_uatp', ['coordinador_uatp'], 'Nueva solicitud pendiente de revisión UATP.'],
        'aprobada_uatp_pendiente_plani' => ['pendiente_validacion', ['supervisor_plani'], 'UATP aprobó la solicitud. Pendiente de validación de Planificación.'],
        'validada_plani' => ['pendiente_gdp', ['coordinador_gdp'], 'Planificación validó la solicitud. Pendiente de derivación GDP.'],
        'derivada_slep' => ['derivada_slep', ['funcionario_slep', 'coordinador_gdp'], 'GDP te asignó la solicitud para gestión SLEP.'],
        'reasignada_slep' => ['derivada_slep', ['funcionario_slep', 'coordinador_gdp'], 'GDP te reasignó la solicitud para gestión SLEP.'],
        'reabierta_uatp' => ['pendiente_uatp', ['coordinador_uatp'], 'Solicitud reabierta. Pendiente de revisión UATP.'],
        'reabierta_plani' => ['pendiente_validacion', ['supervisor_plani'], 'Solicitud reabierta. Pendiente de validación de Planificación.'],
        'corregida_uatp' => ['pendiente_uatp', ['coordinador_uatp'], 'El establecimiento reenvió una corrección. Pendiente de revisión UATP.'],
        'corregida_plani' => ['pendiente_validacion', ['supervisor_plani'], 'El establecimiento reenvió una corrección. Pendiente de validación de Planificación.'],
    ];

    public function notify(SolicitudReemplazo $solicitud, string $event, ?string $occurrence = null): void
    {
        if (!config('whatsapp.enabled') || !config('whatsapp.reemplazos.enabled')) {
            return;
        }
        try {
            [$state, $roles, $action] = self::EVENTS[$event] ?? throw new WhatsAppException('invalid_event');
            if ($solicitud->estado !== $state) {
                return;
            }
            $assigned = $state === 'derivada_slep' ? (int) $solicitud->derivada_a_user_id : null;
            $recipients = app(ReemplazoWhatsAppRecipients::class)->forStage($roles, $assigned);
            if ($recipients->isEmpty()) {
                return;
            }
            $text = $this->message($solicitud, $action);
            // Una ocurrencia por acción confirmada, conservada en el registro cifrado de envíos.
            $occurrence ??= $event === 'created' ? 'created' : (string) Str::uuid();
            foreach ($recipients as $recipient) {
                $key = 'reemplazo:'.hash_hmac('sha256', $solicitud->id.'|'.$event.'|'.$occurrence.'|'.$recipient['phone'], (string) config('app.key'));
                $policy = ['scope' => 'reemplazos', 'solicitud_id' => $solicitud->id, 'state' => $state,
                    'assigned' => $assigned !== null, 'user_id' => $recipient['user_id'],
                    'authorization_id' => $recipient['authorization_id'], 'role' => $recipient['role']];
                (new AnonymousNotifiable)->route('whatsapp', $recipient['phone'])->notify(
                    new ReemplazoWhatsAppNotification(new WhatsAppText($text, $key, 'solicitud_reemplazo.'.$event, $policy))
                );
            }
        } catch (Throwable) {
            Log::warning('No fue posible preparar el aviso WhatsApp de reemplazos.', ['code' => 'reemplazo_notification_unavailable']);
        }
    }

    private function message(SolicitudReemplazo $solicitud, string $action): string
    {
        $base = rtrim((string) config('app.url'), '/');
        $url = parse_url($base);
        if (!$url || ($url['scheme'] ?? '') !== 'https' || empty($url['host'])
            || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])
            || preg_match('/[\s\x00-\x1f\x7f]/', $base)) {
            throw new WhatsAppException('configuration');
        }
        $link = $base.route('gestion.solicitudes-reemplazo.show', $solicitud, false);
        $continuity = $solicitud->es_continuidad ? 'Sí (validada)'
            : ($solicitud->continuidad ? 'Sí (declarada; sin validación registrada)'
                : ($solicitud->continuidad === null && $solicitud->es_continuidad === null ? 'No informada' : 'No'));

        return implode("\n", [
            'SGA · Solicitud de reemplazo N° '.$this->line($solicitud->numero_solicitud, 40),
            $action,
            'Establecimiento: '.$this->line($solicitud->establecimiento?->nombre_establecimiento ?: 'No informado', 160),
            'Fecha de inicio: '.($solicitud->fecha_inicio?->format('d-m-Y') ?? 'No informada'),
            'Fecha de término: '.($solicitud->fecha_termino?->format('d-m-Y') ?? 'No informada'),
            'Continuidad: '.$continuity,
            'Ingresa al SGA para revisar y gestionar:',
            $link,
        ]);
    }

    private function line(?string $value, int $limit): string
    {
        return mb_substr(trim(preg_replace('/[\x00-\x1f\x7f]+/u', ' ', (string) $value) ?? ''), 0, $limit);
    }
}
