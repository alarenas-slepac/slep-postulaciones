<?php

namespace App\Services\WhatsApp;

use App\Models\SolicitudReemplazo;
use App\Notifications\Messages\WhatsAppText;
use App\Notifications\ReemplazoWhatsAppNotification;
use App\Support\Votaciones\CoordenadasEstablecimiento;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ReemplazanteWhatsAppNotifier
{
    public const MAX_PDF_BYTES = 5 * 1024 * 1024;
    private const PREFIX = 'private/solicitudes-reemplazo/';

    public function notify(SolicitudReemplazo $solicitud): void
    {
        if (!config('whatsapp.enabled') || !config('whatsapp.reemplazantes.enabled')) {
            return;
        }
        try {
            $solicitud->load(['postulante.user', 'establecimiento.admisionPerfil']);
            $profile = $solicitud->postulante;
            $phone = WhatsAppPhone::normalize($profile?->telefono1);
            if ($solicitud->estado !== 'aceptada' || !$solicitud->fecha_inicio_trabajo || !$solicitud->fecha_termino
                || !$solicitud->orden_trabajo_creada_at || !$phone || !$this->hasAccess($profile?->user)) {
                Log::warning('Aviso al reemplazante omitido.', ['code' => 'reemplazante_ineligible']);
                return;
            }
            $document = $this->document((string) $solicitud->orden_trabajo_pdf_path, (int) $solicitud->id);
            $establishment = $solicitud->establecimiento;
            $address = $this->line($establishment?->admisionPerfil?->direccion_publica ?: 'No informada', 140);
            $name = $this->line($establishment?->nombre_establecimiento ?: 'No informado', 120);
            $maps = null;
            if (CoordenadasEstablecimiento::sonValidas($establishment?->latitud, $establishment?->longitud)
                && !((float) $establishment->latitud === 0.0 && (float) $establishment->longitud === 0.0)) {
                $latitude = (float) $establishment->latitud;
                $longitude = (float) $establishment->longitud;
                $document['location'] = ['latitude' => $latitude, 'longitude' => $longitude, 'name' => $name, 'address' => $address];
                $maps = 'https://www.google.com/maps/search/?'.http_build_query(['api' => 1, 'query' => $latitude.','.$longitude], '', '&', PHP_QUERY_RFC3986);
            }
            $policy = ['scope' => 'reemplazante_ot', 'solicitud_id' => $solicitud->id,
                'profile_id' => $profile->id, 'user_id' => $profile->user_id,
                'created_at' => $solicitud->orden_trabajo_creada_at->toDateTimeString(),
                'start' => $solicitud->fecha_inicio_trabajo->toDateString(), 'end' => $solicitud->fecha_termino->toDateString(),
                'establishment_id' => $solicitud->establecimiento_id];
            // Una aceptación por solicitud. Regenerar, cambiar teléfono o habilitar el flag no crea otro envío.
            $key = 'reemplazante-ot:'.hash_hmac('sha256', (string) $solicitud->id, (string) config('app.key'));
            $text = implode("\n", [
                'SGA SLEP Andalién Costa',
                'Solicitud de reemplazo N° '.$this->line($solicitud->numero_solicitud, 40).' — Aceptada',
                'Tu asignación de reemplazo ha sido aceptada.',
                'Establecimiento: '.$name,
                'RBD: '.$this->line((string) ($establishment?->rbd ?? 'No informado'), 20),
                'Dirección: '.$address,
                'Inicio de trabajo: '.$solicitud->fecha_inicio_trabajo->format('d-m-Y'),
                'Fecha de término: '.$solicitud->fecha_termino->format('d-m-Y'),
                'Orden de trabajo: PDF adjunto.',
                $maps ? 'Ubicación: '.$maps : 'Ubicación: no informada.',
                'Revisa tu reemplazo en el SGA:',
                $this->link($solicitud),
                '',
                'Esta es una notificación automática del SGA.',
                'Por favor, no respondas a este mensaje.',
            ]);
            (new AnonymousNotifiable)->route('whatsapp', $phone)->notify(
                new ReemplazoWhatsAppNotification(new WhatsAppText($text, $key, 'solicitud_reemplazo.aceptada_reemplazante', $policy, $document))
            );
        } catch (Throwable) {
            Log::warning('No fue posible preparar la orden de trabajo WhatsApp.', ['code' => 'reemplazante_notification_unavailable']);
        }
    }

    public function deliveryError(array $payload): ?string
    {
        if (!config('whatsapp.reemplazantes.enabled')) return 'event_disabled';
        $policy = $payload['policy'];
        $s = SolicitudReemplazo::with('postulante.user')->find($policy['solicitud_id'] ?? 0);
        if (!$s || $s->estado !== 'aceptada'
            || $s->orden_trabajo_creada_at?->toDateTimeString() !== ($policy['created_at'] ?? null)
            || $s->fecha_inicio_trabajo?->toDateString() !== ($policy['start'] ?? null)
            || $s->fecha_termino?->toDateString() !== ($policy['end'] ?? null)
            || (int) $s->establecimiento_id !== (int) ($policy['establishment_id'] ?? 0)) return 'stage_changed';
        $profile = $s->postulante;
        if (!$profile || (int) $profile->id !== (int) ($policy['profile_id'] ?? 0)
            || (int) $profile->user_id !== (int) ($policy['user_id'] ?? 0)
            || !$this->hasAccess($profile->user)
            || WhatsAppPhone::normalize($profile->telefono1) !== $payload['to']) return 'recipient_ineligible';
        try {
            $current = $this->document((string) $s->orden_trabajo_pdf_path, (int) $s->id);
            if ($current['path'] !== ($payload['document']['path'] ?? null)
                || $current['sha256'] !== ($payload['document']['sha256'] ?? null)) return 'document_changed';
        } catch (WhatsAppException $exception) {
            return $exception->errorCode;
        }
        return null;
    }

    private function hasAccess(?object $user): bool
    {
        return $user && $user->email_verified_at && $user->hasAnyRole(['postulante', 'funcionario']);
    }

    private function document(string $path, int $id): array
    {
        $relative = str_starts_with($path, self::PREFIX) ? substr($path, strlen(self::PREFIX)) : '';
        if (!preg_match('~^'.$id.'/ORDEN_TRABAJO_[A-Za-z0-9_-]{1,100}\.pdf$~D', $relative)) {
            throw new WhatsAppException('invalid_document');
        }
        $disk = Storage::disk('local');
        $root = realpath($disk->path(self::PREFIX));
        $file = realpath($disk->path($path));
        if (!$root || !$file || !str_starts_with($file, $root.DIRECTORY_SEPARATOR) || !is_file($file)
            || filesize($file) > self::MAX_PDF_BYTES || filesize($file) < 5) {
            throw new WhatsAppException('document_unavailable');
        }
        $handle = fopen($file, 'rb');
        if (!$handle) throw new WhatsAppException('document_unavailable');
        try { $header = fread($handle, 5); } finally { fclose($handle); }
        if ($header !== '%PDF-') throw new WhatsAppException('invalid_document');
        $hash = hash_file('sha256', $file);
        if (!$hash) throw new WhatsAppException('document_unavailable');
        return ['path' => $relative, 'sha256' => $hash];
    }

    private function link(SolicitudReemplazo $solicitud): string
    {
        $base = rtrim((string) config('app.url'), '/');
        $url = parse_url($base);
        if (!$url || ($url['scheme'] ?? '') !== 'https' || empty($url['host'])
            || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])
            || preg_match('/[\s\x00-\x1f\x7f]/', $base)) throw new WhatsAppException('configuration');
        return $base.route('postulant.reemplazos.show', $solicitud, false);
    }

    private function line(?string $value, int $limit): string
    {
        return mb_substr(trim(preg_replace('/[\x00-\x1f\x7f]+/u', ' ', (string) $value) ?? ''), 0, $limit);
    }
}
