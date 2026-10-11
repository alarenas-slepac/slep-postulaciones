<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class WhatsAppClient
{
    public function request(string $method, string $path, array $data = []): array
    {
        if (!config('whatsapp.enabled')) {
            throw new WhatsAppException('disabled');
        }
        $url = rtrim((string) config('whatsapp.url'), '/');
        $parts = parse_url($url);
        $token = (string) config('whatsapp.token');
        if (!$parts || ($parts['scheme'] ?? '') !== 'http' || ($parts['host'] ?? '') !== '127.0.0.1'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || !empty($parts['path']) || !preg_match('/^[A-Za-z0-9_-]{32,256}$/D', $token)) {
            throw new WhatsAppException('configuration');
        }

        try {
            $response = Http::withToken($token)->acceptJson()->asJson()->withoutRedirecting()
                ->connectTimeout(max(1, min(5, (int) config('whatsapp.connect_timeout'))))
                ->timeout(max(2, min(20, (int) config('whatsapp.timeout'))))
                ->send($method, $url.'/v1/'.$path, $method === 'GET' ? [] : ['json' => (object) $data]);
        } catch (ConnectionException) {
            throw new WhatsAppException('service_unavailable', true);
        } catch (\Throwable) {
            throw new WhatsAppException('configuration');
        }

        $result = $response->json();
        if (!$response->successful() || !is_array($result)) {
            $code = is_array($result) ? ($result['error'] ?? '') : '';
            $safeCodes = ['disconnected', 'delivery_unknown', 'idempotency_conflict', 'invalid_recipient', 'connected', 'busy', 'qr_expired', 'unlink_failed'];
            $code = in_array($code, $safeCodes, true) ? $code : match ($response->status()) {
                401, 403 => 'authentication',
                422, 413 => 'invalid_message',
                default => 'service_unavailable',
            };
            throw new WhatsAppException($code, $response->status() >= 500 || $response->status() === 429);
        }

        return $result;
    }

    public function status(): array
    {
        $status = $this->request('GET', 'status');
        $states = ['idle', 'connecting', 'qr', 'connected', 'reconnecting', 'paused', 'logged_out', 'unlinked'];
        $state = in_array($status['state'] ?? '', $states, true) ? $status['state'] : 'unavailable';
        $result = ['state' => $state, 'qr' => null, 'qr_expires_at' => null];
        if ($state === 'qr') {
            try { $qr = $this->request('GET', 'qr'); }
            catch (WhatsAppException $exception) {
                if ($exception->errorCode === 'qr_expired') return $result;
                throw $exception;
            }
            $image = $qr['image'] ?? '';
            if (is_string($image) && strlen($image) < 150000 && preg_match('~^data:image/png;base64,[A-Za-z0-9+/=]+$~D', $image)) {
                $result['qr'] = $image;
                $result['qr_expires_at'] = $qr['expires_at'] ?? null;
            }
        }
        return $result;
    }
}
